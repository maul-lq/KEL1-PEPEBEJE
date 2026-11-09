<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\ReviuDokumen;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Manajemen Kontrak, Review PP, dan Persetujuan Dokumen PPK (Modul 5)
 *
 * Mengelola tahapan:
 * 1. Monitoring aktivitas Pejabat Pengadaan (PP) (BPMN N21, N27, N31).
 * 2. Penelaahan dan verifikasi berkas pengadaan oleh PP.
 * 3. Penerimaan & Persetujuan Dokumen Pengadaan oleh PPK (BPMN N30-N36).
 * 4. Unggah salah satu dokumen perikatan terpilih (Surat Pesanan / SPK / Surat Kontrak) oleh PPBJ.
 */
class KontrakSPKController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar paket penugasan (> 50 Juta), panel Review PP, Approval PPK,
     * dan unggah dokumen perikatan terpilih oleh PPBJ.
     */
    public function kontrakIndex(Request $request): View
    {
        $user = Auth::user();
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'vendor', 'kontrakSpk', 'pelaksanaBelanja'])
            ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);

        if ($user) {
            $paketQuery->forUser($user);
        }

        $paketList = $paketQuery->get();

        $kontrakQuery = KontrakSPK::with(['paket.vendor', 'paket.ppkUser']);
        if ($user) {
            $kontrakQuery->forUser($user);
        }

        if ($request->filled('q') || $request->filled('search')) {
            $kw = $request->input('q', $request->input('search'));
            $kontrakQuery->where(function ($q) use ($kw) {
                $q->where('nomor_spk', 'like', "%{$kw}%")
                    ->orWhere('nomor_paket', 'like', "%{$kw}%")
                    ->orWhereHas('vendor', function ($vq) use ($kw) {
                        $vq->where('nama_vendor', 'like', "%{$kw}%");
                    });
            });
        }

        if ($request->filled('status_spk')) {
            $kontrakQuery->where('status_spk', $request->input('status_spk'));
        }

        $kontrakList = $kontrakQuery->latest()->paginate(15);
        $vendors = Vendor::all();

        $reviuQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'vendor', 'reviuDokumen', 'kontrakSpk'])
            ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
        if ($user) {
            $reviuQuery->forUser($user);
        }
        $paketReviu = $reviuQuery->whereIn('status_paket', [
            'terdaftar',
            'reviu_dokumen',
            'perlu_perbaikan',
            PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            'reviu_dokumen_disetujui',
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        ])->get();

        return view('kontrak.index', compact('paketList', 'kontrakList', 'vendors', 'paketReviu'));
    }

    public function index(Request $request): View
    {
        return $this->kontrakIndex($request);
    }

    /**
     * Dashboard monitoring aktivitas Pejabat Pengadaan (PP).
     */
    public function monitorPpIndex(Request $request): View
    {
        $user = Auth::user();

        $query = PaketPengadaan::with([
            'permohonan.unit',
            'ppUser',
            'ppkUser',
            'vendor',
            'reviuDokumen.user',
            'kontrakSpk',
        ])->where('jalur_routing', PaketPengadaan::ROUTING_PP);

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('nomor_paket', 'like', "%{$kw}%")
                    ->orWhere('nama_paket', 'like', "%{$kw}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('nama_vendor', 'like', "%{$kw}%"))
                    ->orWhereHas('ppUser', fn ($pq) => $pq->where('nama', 'like', "%{$kw}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status_paket', $request->status);
        }

        $paketPpList = $query->latest('tanggal_registrasi_paket')->paginate(15);
        $totalPaketPp = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->count();
        $paketMenungguReviu = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
            ->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])
            ->count();
        $paketSelesaiReviu = PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)
            ->whereIn('status_paket', [
                PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK,
                PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                'spk_terbit',
                'penerimaan_selesai',
            ])->count();
        $totalPaguPp = (float) PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->sum('nilai_hps');
        $totalNegosiasiPp = (float) PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_PP)->whereNotNull('nilai_hasil_negosiasi')->sum('nilai_hasil_negosiasi');
        $pejabatPpList = User::where('role', 'pp')
            ->orWhereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'reviu.pp'))
            ->get();

        return view('pp.monitoring', compact(
            'paketPpList',
            'totalPaketPp',
            'paketMenungguReviu',
            'paketSelesaiReviu',
            'totalPaguPp',
            'totalNegosiasiPp',
            'pejabatPpList',
            'user'
        ));
    }

    /**
     * Memproses upload salah satu dokumen perikatan terpilih (Surat Pesanan / SPK / Surat Kontrak).
     */
    public function storeKontrak(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'file_dokumen_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'id_vendor' => ['nullable'],
            'tipe_dokumen' => ['nullable', 'string', 'in:spk,surat_pesanan,surat_kontrak,SPK,Kontrak,Surat Pesanan,Surat Kontrak'],
            'nomor_kontrak_spk' => ['required', 'string', 'max:100', 'unique:kontrak_spk,nomor_spk'],
            'nilai_kontrak' => ['required', 'numeric', 'min:1'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan_spk' => ['nullable', 'string'],
            'file_spk' => ['nullable', DokumenUploadRule::pdfOnly()],
            'sifat_kontrak' => ['nullable', 'string'],
            'jadwal_pengiriman' => ['nullable', 'date'],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['kode_paket'])->firstOrFail();

        // Larang Kontrak/SPK/Surat Pesanan untuk paket <= Rp 50 Juta (Belanja Langsung)
        $nominalPaket = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        if ($paket->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG || $nominalPaket <= 50000000) {
            return back()->withErrors([
                'kode_paket' => 'Paket di bawah atau sama dengan Rp 50.000.000 adalah Belanja Langsung (output Kwitansi, tidak ada Kontrak/SPK/Surat Pesanan).',
            ])->withInput();
        }

        // Dokumen pengadaan wajib disetujui PPK (BPMN N35: Iya -> N36) sebelum menerbitkan kontrak/SPK
        if (! in_array($paket->status_paket, [
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            'disetujui',
            'spk_terbit',
        ], true)) {
            return back()->withErrors([
                'kode_paket' => 'Dokumen pengadaan paket ini belum disetujui oleh PPK (BPMN N30-N35). PPBJ baru dapat menerbitkan dokumen perikatan setelah persetujuan PPK.',
            ])->withInput();
        }

        // Larang upload lebih dari 1 dokumen perikatan per paket
        if (KontrakSPK::where('nomor_paket', $paket->nomor_paket)->exists()) {
            return back()->withErrors([
                'kode_paket' => 'Paket ini sudah memiliki 1 dokumen perikatan. Pilih salah satu antara Surat Pesanan, SPK, atau Surat Kontrak (bukan diunggah keduanya).',
            ])->withInput();
        }

        if (! empty($validated['nama_vendor'])) {
            $legalitasPath = $request->hasFile('file_dokumen_legalitas')
                ? $request->file('file_dokumen_legalitas')->store('vendor_legalitas', 'public')
                : null;

            $vendor = Vendor::firstOrCreate(
                ['nama_vendor' => trim($validated['nama_vendor'])],
                [
                    'file_dokumen_legalitas' => $legalitasPath,
                    'is_active' => true,
                ]
            );
            if ($legalitasPath && empty($vendor->file_dokumen_legalitas)) {
                $vendor->file_dokumen_legalitas = $legalitasPath;
                $vendor->save();
            }
            $paket->id_vendor = $vendor->id;
        } elseif (! empty($validated['id_vendor'])) {
            $paket->id_vendor = $validated['id_vendor'];
        }

        if (empty($paket->id_vendor)) {
            return back()->withErrors(['nama_vendor' => 'Penyedia/Vendor pemenang wajib diisi atau dipilih sebelum mengunggah dokumen perikatan.'])->withInput();
        }

        $tipeInput = Str::lower(str_replace(' ', '_', $validated['tipe_dokumen'] ?? 'spk'));
        $tipeDokumen = match ($tipeInput) {
            'surat_pesanan' => KontrakSPK::DOKUMEN_SURAT_PESANAN,
            'kontrak', 'surat_kontrak' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
            default => KontrakSPK::DOKUMEN_SPK,
        };

        $fileSpkPath = 'spk/'.str_replace(['/', '\\'], '_', $validated['nomor_kontrak_spk']).'.pdf';
        if ($request->hasFile('file_spk')) {
            $fileSpkPath = $request->file('file_spk')->store('spk', 'public');
        }

        $kontrak = KontrakSPK::create([
            'nomor_spk' => $validated['nomor_kontrak_spk'],
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'tipe_dokumen' => $tipeDokumen,
            'sifat_kontrak' => KontrakSPK::TIPE_LUMPSUM,
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jadwal_pengiriman' => $validated['jadwal_pengiriman'] ?? $validated['tanggal_selesai'],
            'nilai_kontrak' => $validated['nilai_kontrak'],
            'file_spk' => $fileSpkPath,
        ]);

        $paket->status_paket = 'spk_terbit';
        $paket->save();

        // Bangkitkan tembusan dan reminder pengiriman barang ke Perlengkapan & Keuangan
        PengadaanRoutingService::generateReminders($paket, $kontrak);

        AuditLog::catat(
            $user->id_user,
            'UPLOAD_DOKUMEN_PERIKATAN_TUNGGAL',
            'kontrak_spk',
            $kontrak->nomor_spk,
            null,
            'aktif',
            "Unggah dokumen perikatan terpilih ({$tipeDokumen}) nomor {$kontrak->nomor_spk} nilai Rp ".number_format($kontrak->nilai_kontrak)
        );

        $this->notificationService->notifyPenerbitanKontrak($kontrak);

        return redirect()->route('kontrak.index')->with(
            'success',
            'Dokumen perikatan ('.strtoupper(str_replace('_', ' ', $tipeDokumen)).") nomor {$kontrak->nomor_spk} berhasil diunggah beserta jadwal Reminder dan tembusan ke Perlengkapan & Keuangan."
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeKontrak($request);
    }

    /**
     * Memproses penelaahan dokumen oleh PP atau persetujuan dokumen oleh PPK.
     */
    public function storeReviuWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'status_reviu' => ['required', 'in:terverifikasi_pp,disetujui,perlu_perbaikan'],
            'catatan_perbaikan' => ['required_if:status_reviu,perlu_perbaikan', 'nullable', 'string'],
        ], [
            'catatan_perbaikan.required_if' => 'Catatan perbaikan / alasan penolakan wajib diisi saat meminta perbaikan dokumen pengadaan (BPMN N18).',
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        // Penegakan wewenang: PP tidak bisa klik Approval final
        if (($user->hasRole('pp') || $user->hasPermission('reviu.pp')) && ! $user->hasRole('admin') && ! $user->hasPermission('reviu.ppk') && $validated['status_reviu'] === 'disetujui') {
            return back()->withErrors([
                'status_reviu' => 'Pejabat Pengadaan (PP) tidak memiliki wewenang persetujuan final (Approval). Persetujuan dokumen dilakukan oleh PPK (BPMN N35).',
            ])->withInput();
        }

        // Penegakan isolasi berkas 4 akun PPK
        if ($user->hasPermission('reviu.ppk') && ! $user->hasRole('admin') && $paket->id_user_ppk && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak mereviu atau meng-approve paket yang ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;
        $isPp = ($user->hasRole('pp') || $user->hasPermission('reviu.pp')) && ! ($user->hasPermission('reviu.ppk') && $validated['status_reviu'] === 'disetujui');

        ReviuDokumen::create([
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'putaran_ke' => $nextRound,
            'role_reviewer' => $isPp ? 'pp' : 'ppk',
            'status_reviu' => $validated['status_reviu'],
            'catatan_perbaikan' => $validated['catatan_perbaikan'],
            'tanggal_reviu' => now(),
        ]);

        $statusLama = $paket->status_paket;

        // KASUS 1: PP Verifikasi Berhasil (BPMN N27: Iya -> N31: Kirim ke PPK N30)
        if ($validated['status_reviu'] === 'terverifikasi_pp') {
            $paket->status_paket = PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;
            $paket->catatan_verifikasi_pp = $validated['catatan_perbaikan'] ?? 'Dokumen pengadaan lolos verifikasi teknis & HPS oleh PP (BPMN N27: Iya); berkas diteruskan ke PPK (N31 -> N30).';
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'REVIEW_DOKUMEN_PP_BERHASIL',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "PP menyatakan verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil (BPMN N27: Iya); dokumen diteruskan ke PPK (N31 -> N30)."
            );

            $this->notificationService->notifyReviewPpKePpk($paket, $validated['catatan_perbaikan'] ?? null);

            return redirect()->route('kontrak.index')->with(
                'success',
                "Verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil (BPMN N27 & N31). Berkas diteruskan ke PPK untuk persetujuan (N30)."
            );
        }

        // KASUS 2: PPK Menyetujui Dokumen Pengadaan (BPMN N35: Iya -> N36)
        if ($validated['status_reviu'] === 'disetujui') {
            $paket->status_paket = PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ;
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'APPROVAL_PENGADAAN_PPK',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "PPK menyetujui dokumen pengadaan paket {$paket->nomor_paket} (BPMN N35: Iya); berkas diteruskan ke PPBJ untuk melakukan pengadaan & membuat SPK/Kontrak (N32/N34/N37)."
            );

            $this->notificationService->notifySiapUploadDokumenPpbj($paket, 'ppk');

            return redirect()->route('kontrak.index')->with(
                'success',
                "Dokumen pengadaan paket {$paket->nomor_paket} berhasil disetujui oleh PPK (BPMN N35). Berkas diteruskan ke PPBJ untuk pelaksanaan pengadaan & penerbitan SPK/Kontrak (N32 & N37)."
            );
        }

        // KASUS 3: Penolakan / Perlu Perbaikan (PP N27: Tidak ATAU PPK N35: Tidak -> N18)
        $paket->status_paket = 'perlu_perbaikan';
        $paket->catatan_verifikasi_pp = ($isPp ? 'Catatan PP: ' : 'Catatan PPK: ').$validated['catatan_perbaikan'];
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'REVIU_DOKUMEN_PERBAIKAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            $paket->status_paket,
            'Penelaah ('.($isPp ? 'PP' : 'PPK').") mengembalikan dokumen paket {$paket->nomor_paket} ke PPBJ untuk disusun ulang (BPMN N18): ".$validated['catatan_perbaikan']
        );

        $this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $validated['catatan_perbaikan'] ?? null);

        return redirect()->route('kontrak.index')->with(
            'success',
            "Dokumen pengadaan paket {$paket->nomor_paket} dikembalikan ke PPBJ untuk perbaikan (BPMN N18)."
        );
    }

    /**
     * Menampilkan antrean penerimaan dan verifikasi dokumen pengadaan oleh PPK (BPMN N30-N36).
     */
    public function persetujuanDokumenPpkIndex(Request $request): View
    {
        $user = Auth::user();
        if (! $user->hasPermission('reviu.ppk') && ! $user->hasRole('admin')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses persetujuan dokumen pengadaan.');
        }

        $query = PaketPengadaan::with([
            'permohonan.unit',
            'permohonan.user',
            'ppUser',
            'ppkUser',
            'vendor',
            'reviuDokumen.user',
        ]);

        if (! $user->hasRole('admin')) {
            $query->where('id_user_ppk', $user->id_user);
        }

        $paketAntrean = (clone $query)
            ->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)
            ->latest('tanggal_registrasi_paket')
            ->get();

        $riwayatDisetujui = (clone $query)
            ->whereIn('status_paket', [
                PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
                PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
                'spk_terbit',
                'penerimaan_selesai',
                'selesai',
            ])
            ->latest('updated_at')
            ->take(15)
            ->get();

        $riwayatDitolak = (clone $query)
            ->where('status_paket', 'perlu_perbaikan')
            ->latest('updated_at')
            ->take(15)
            ->get();

        return view('ppk.persetujuan-dokumen', compact('paketAntrean', 'riwayatDisetujui', 'riwayatDitolak', 'user'));
    }

    /**
     * Memproses keputusan persetujuan dokumen pengadaan oleh PPK (BPMN N35 & N36).
     */
    public function keputusanDokumenPpk(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasPermission('reviu.ppk') && ! $user->hasRole('admin')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses persetujuan dokumen pengadaan.');
        }

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan_alasan' => ['required_if:keputusan,ditolak', 'nullable', 'string'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan wajib diisi saat PPK menolak / mengembalikan dokumen pengadaan ke PPBJ.',
        ]);

        $paket = PaketPengadaan::with(['permohonan', 'reviuDokumen'])->where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        if (! $user->hasRole('admin') && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak memproses paket yang ditugaskan ke akun PPK lain.');
        }

        $statusLama = $paket->status_paket;
        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;
        $isDisetujui = ($validated['keputusan'] === 'disetujui');
        $catatan = $validated['catatan_alasan'] ?: 'Dokumen pengadaan disetujui oleh PPK (BPMN N36); berkas diteruskan ke PPBJ.';

        ReviuDokumen::create([
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'putaran_ke' => $nextRound,
            'role_reviewer' => 'ppk',
            'status_reviu' => $isDisetujui ? 'disetujui' : 'perlu_perbaikan',
            'catatan_perbaikan' => $catatan,
            'tanggal_reviu' => now(),
        ]);

        if ($isDisetujui) {
            $paket->status_paket = PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ;
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'APPROVAL_DOKUMEN_PENGADAAN_PPK',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "PPK ({$user->nama}) menyetujui dokumen pengadaan paket {$paket->nomor_paket} (BPMN N35: Iya -> N36); diteruskan ke PPBJ untuk belanja/perikatan."
            );

            $this->notificationService->notifySiapUploadDokumenPpbj($paket, 'ppk');

            return redirect()->route('ppk.persetujuan-dokumen.index')->with(
                'success',
                "Dokumen pengadaan paket {$paket->nomor_paket} BERHASIL DISETUJUI oleh PPK (BPMN N36). PPBJ dapat melanjutkan pelaksanaan pengadaan."
            );
        }

        $paket->status_paket = 'perlu_perbaikan';
        $paket->catatan_verifikasi_pp = "Catatan Penolakan PPK ({$user->nama}): ".$catatan;
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'PENOLAKAN_DOKUMEN_PENGADAAN_PPK',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            $paket->status_paket,
            "PPK ({$user->nama}) mengembalikan dokumen pengadaan paket {$paket->nomor_paket} ke PPBJ untuk disusun ulang (BPMN N35: Tidak -> N18): ".$catatan
        );

        $this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $catatan);

        return redirect()->route('ppk.persetujuan-dokumen.index')->with(
            'success',
            "Dokumen pengadaan paket {$paket->nomor_paket} dikembalikan ke PPBJ untuk penyusunan ulang (BPMN N18)."
        );
    }
}
