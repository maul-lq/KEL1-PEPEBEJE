<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\ReviuDokumen;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Menampilkan daftar paket siap SPK (> 50 Juta), antrean review PP/approval PPK,
     * dan direktori dokumen perikatan berbasis permission RBAC.
     */
    public function kontrakIndex(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyPermission(['spk.create', 'reviu.pp', 'reviu.ppk', 'pp.monitoring_aktivitas', 'lihat_pp'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk membuka halaman Kontrak Kerja & Tinjauan Dokumen.');
        }

        $canReviuPp = $user->hasPermission('reviu.pp');
        $canReviuPpk = $user->hasPermission('reviu.ppk');
        $canMonitorPp = $user->canMonitorPp();
        $canCreateSpk = $user->hasPermission('spk.create');
        $canTabReviu = $canReviuPp || $canReviuPpk || $canMonitorPp;
        $hasViewAllPaket = $user->hasPermission('paket.view_all');

        $siapSpkStatuses = [
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            'reviu_dokumen_disetujui',
            'disetujui',
        ];

        $paketSiapSpkQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'vendor', 'kontrakSpk', 'pelaksanaBelanja'])
            ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG)
            ->whereIn('status_paket', $siapSpkStatuses)
            ->doesntHave('kontrakSpk');
        $this->applyKontrakScope($paketSiapSpkQuery, $user);

        $paketSiapSpk = $paketSiapSpkQuery->get()
            ->filter(fn (PaketPengadaan $pkt) => (float) ($pkt->nilai_hps ?: $pkt->alokasi_pagu_paket) > 50000000)
            ->values();

        $paketList = $canCreateSpk ? $paketSiapSpk : collect();
        $vendors = $canCreateSpk ? Vendor::orderBy('nama_vendor')->get() : collect();

        $kontrakBaseQuery = KontrakSPK::with(['paket.vendor', 'paket.ppkUser'])
            ->whereHas('paket', function (Builder $q) use ($user): void {
                $q->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
                $this->applyKontrakScope($q, $user);
            });

        $totalSpkStats = (clone $kontrakBaseQuery)->count();
        $totalNilaiStats = (float) (clone $kontrakBaseQuery)->sum('nilai_kontrak');

        $kontrakQuery = clone $kontrakBaseQuery;

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

        $paketReviu = collect();
        $paketReviuAksi = collect();

        if ($canTabReviu) {
            $reviuQuery = PaketPengadaan::with(['permohonan.unit', 'ppkUser', 'ppUser', 'vendor', 'reviuDokumen', 'kontrakSpk'])
                ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
            $this->applyKontrakScope($reviuQuery, $user);

            // Jika pengguna hanya memiliki izin monitoring PP tanpa reviu/paket.view_all, tampilkan jalur PP saja
            if ($canMonitorPp && ! $canReviuPp && ! $canReviuPpk && ! $hasViewAllPaket) {
                $reviuQuery->where('jalur_routing', PaketPengadaan::ROUTING_PP);
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

            if ($canReviuPp || $canReviuPpk) {
                $paketReviuAksi = $paketReviu->filter(function (PaketPengadaan $pkt) use ($user, $canReviuPp, $canReviuPpk, $hasViewAllPaket): bool {
                    $eligiblePp = $canReviuPp
                        && in_array($pkt->status_paket, ['terdaftar', 'reviu_dokumen'], true)
                        && ($pkt->jalur_routing === PaketPengadaan::ROUTING_PP || (int) $pkt->id_user_pp === (int) $user->id_user);

                    $eligiblePpk = $canReviuPpk
                        && $pkt->status_paket === PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK
                        && ($hasViewAllPaket || (int) $pkt->id_user_ppk === (int) $user->id_user);

                    return $eligiblePp || $eligiblePpk;
                })->values();
            }
        }

        $stats = [
            'total_spk' => $totalSpkStats,
            'antrean_reviu' => ($canReviuPp || $canReviuPpk) ? $paketReviuAksi->count() : $paketReviu->count(),
            'siap_spk' => $paketSiapSpk->count(),
            'total_nilai' => $totalNilaiStats,
        ];

        return view('kontrak.index', compact(
            'paketList',
            'paketSiapSpk',
            'kontrakList',
            'vendors',
            'paketReviu',
            'paketReviuAksi',
            'stats'
        ));
    }

    /**
     * Membatasi query PaketPengadaan pada modul kontrak murni berbasis permission RBAC pengguna.
     */
    protected function applyKontrakScope(Builder $query, User $user): void
    {
        if ($user->hasPermission('paket.view_all')) {
            return;
        }

        $canReviuPpk = $user->hasPermission('reviu.ppk');
        $canReviuPp = $user->hasPermission('reviu.pp');
        $canMonitorPp = $user->canMonitorPp();
        $canCreateSpk = $user->hasPermission('spk.create');

        $query->where(function (Builder $q) use ($user, $canReviuPpk, $canReviuPp, $canMonitorPp, $canCreateSpk): void {
            if ($canReviuPpk) {
                $q->orWhere(function (Builder $sub) use ($user): void {
                    $sub->where('id_user_ppk', $user->id_user)
                        ->where('jalur_routing', '!=', PaketPengadaan::ROUTING_LANGSUNG);
                });
            }

            if ($canReviuPp || $canMonitorPp) {
                $q->orWhere('jalur_routing', PaketPengadaan::ROUTING_PP);
                if ($canReviuPp) {
                    $q->orWhere('id_user_pp', $user->id_user);
                }
            }

            if ($canCreateSpk) {
                $q->orWhere('id_user_ppbj', $user->id_user);
            }

            if (! $canReviuPpk && ! $canReviuPp && ! $canMonitorPp && ! $canCreateSpk) {
                $q->whereRaw('1 = 0');
            }
        });
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
        $pejabatPpList = User::whereHas('roleEntity.permissions', fn ($q) => $q->where('nama_permission', 'reviu.pp'))
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
        if (! $user || ! $user->hasPermission('spk.create')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin menerbitkan dokumen kontrak.');
        }

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'nama_vendor' => ['required', 'string', 'max:255'],
            'file_dokumen_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'tipe_dokumen' => ['nullable', 'string', 'in:spk,surat_pesanan,surat_kontrak,SPK,Kontrak,Surat Pesanan,Surat Kontrak'],
            'nomor_kontrak_spk' => ['required', 'string', 'max:100', 'unique:kontrak_spk,nomor_spk'],
            'nilai_kontrak' => ['required', 'numeric', 'min:1'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan_spk' => ['nullable', 'string'],
            'file_spk' => ['nullable', DokumenUploadRule::pdfOnly()],
            'sifat_kontrak' => ['nullable', 'string'],
            'jadwal_pengiriman' => ['nullable', 'date'],
        ], [
            'nama_vendor.required' => 'Nama Vendor pemenang wajib diisi sebelum mengunggah dokumen kontrak.',
        ]);

        $paket = PaketPengadaan::with('permohonan')->where('nomor_paket', $validated['kode_paket'])->firstOrFail();

        // Larang Kontrak/SPK/Surat Pesanan untuk paket <= Rp 50 Juta (Belanja Langsung)
        $hpsLama = (float) ($paket->nilai_hps ?: $paket->alokasi_pagu_paket);
        if ($paket->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG || $hpsLama <= 50000000) {
            return back()->withErrors([
                'kode_paket' => 'Paket hingga Rp 50.000.000 termasuk Belanja Langsung (cukup menggunakan Kwitansi tanpa kontrak).',
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
                'kode_paket' => 'Dokumen paket ini belum disetujui PPK. Unit Pengadaan baru dapat menerbitkan dokumen kontrak setelah disetujui PPK.',
            ])->withInput();
        }

        // Larang upload lebih dari 1 dokumen perikatan per paket
        if (KontrakSPK::where('nomor_paket', $paket->nomor_paket)->exists()) {
            return back()->withErrors([
                'kode_paket' => 'Paket ini sudah memiliki dokumen kontrak. Setiap paket hanya menggunakan satu jenis dokumen kontrak.',
            ])->withInput();
        }

        $nilaiKontrak = (float) $validated['nilai_kontrak'];
        if ($nilaiKontrak > $hpsLama) {
            return back()->withErrors([
                'nilai_kontrak' => 'Nilai Kontrak Akhir (Rp '.number_format($nilaiKontrak, 0, ',', '.').') tidak boleh melebihi Perkiraan Harga paket (Rp '.number_format($hpsLama, 0, ',', '.').').',
            ])->withInput();
        }

        $legalitasPath = $request->hasFile('file_dokumen_legalitas')
            ? $request->file('file_dokumen_legalitas')->store('vendor_legalitas', 'public')
            : null;

        $tipeInput = Str::lower(str_replace(' ', '_', $validated['tipe_dokumen'] ?? 'spk'));
        $tipeDokumen = match ($tipeInput) {
            'surat_pesanan' => KontrakSPK::DOKUMEN_SURAT_PESANAN,
            'kontrak', 'surat_kontrak' => KontrakSPK::DOKUMEN_SURAT_KONTRAK,
            default => KontrakSPK::DOKUMEN_SPK,
        };

        $sifatInput = Str::lower(str_replace(' ', '_', $validated['sifat_kontrak'] ?? 'lump_sum'));
        $sifatKontrak = match ($sifatInput) {
            'harga_satuan' => KontrakSPK::TIPE_HARGA_SATUAN,
            'sekali_selesai' => KontrakSPK::TIPE_SEKALI_SELESAI,
            'termin_bulanan' => KontrakSPK::TIPE_TERMIN_BULANAN,
            'kontrak_tahunan' => KontrakSPK::TIPE_KONTRAK_TAHUNAN,
            default => KontrakSPK::TIPE_LUMPSUM,
        };

        $fileSpkPath = 'spk/'.str_replace(['/', '\\'], '_', $validated['nomor_kontrak_spk']).'.pdf';
        if ($request->hasFile('file_spk')) {
            $fileSpkPath = $request->file('file_spk')->store('spk', 'public');
        }

        $selisihEfisiensi = max(0, $hpsLama - $nilaiKontrak);
        $namaVendor = trim($validated['nama_vendor']);

        /** @var KontrakSPK $kontrak */
        $kontrak = DB::transaction(function () use (
            $validated,
            $paket,
            $user,
            $namaVendor,
            $legalitasPath,
            $tipeDokumen,
            $sifatKontrak,
            $nilaiKontrak,
            $selisihEfisiensi,
            $fileSpkPath
        ): KontrakSPK {
            $vendor = Vendor::firstOrCreate(
                ['nama_vendor' => $namaVendor],
                ['file_dokumen_legalitas' => $legalitasPath]
            );
            if ($legalitasPath && $vendor->file_dokumen_legalitas !== $legalitasPath) {
                $vendor->file_dokumen_legalitas = $legalitasPath;
                $vendor->save();
            }

            $createdKontrak = KontrakSPK::create([
                'nomor_spk' => $validated['nomor_kontrak_spk'],
                'nomor_paket' => $paket->nomor_paket,
                'id_user' => $user->id_user,
                'tipe_dokumen' => $tipeDokumen,
                'sifat_kontrak' => $sifatKontrak,
                'status_spk' => 'aktif',
                'tanggal_spk' => now(),
                'tanggal_mulai' => $validated['tanggal_mulai'],
                'tanggal_selesai' => $validated['tanggal_selesai'],
                'jadwal_pengiriman' => $validated['jadwal_pengiriman'] ?? $validated['tanggal_selesai'],
                'nilai_kontrak' => $nilaiKontrak,
                'file_spk' => $fileSpkPath,
            ]);

            $paket->id_vendor = $vendor->id;
            $paket->nominal_harga_akhir = $nilaiKontrak;
            $paket->nilai_hasil_negosiasi = $nilaiKontrak;
            $paket->status_paket = 'spk_terbit';

            if ($selisihEfisiensi > 0) {
                $paket->nilai_hps = $nilaiKontrak;
                $paket->alokasi_pagu_paket = $nilaiKontrak;

                if ($paket->permohonan) {
                    $permohonan = $paket->permohonan;
                    $permohonan->estimasi_total_pagu = max(0, (float) $permohonan->estimasi_total_pagu - $selisihEfisiensi);
                    $permohonan->save();

                    if (! empty($permohonan->kode_mak)) {
                        $mak = AnggaranMAK::lockForUpdate()->find($permohonan->kode_mak);
                        if ($mak) {
                            $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + $selisihEfisiensi;
                            $mak->save();
                        }

                        $alokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                            ->where('kode_mak', $permohonan->kode_mak)
                            ->first();
                        if ($alokasi) {
                            $alokasi->nominal_alokasi = max(0, (float) $alokasi->nominal_alokasi - $selisihEfisiensi);
                            $alokasi->save();
                        }
                    }
                }
            }

            $paket->save();

            return $createdKontrak;
        });

        $paket->refresh();

        // Bangkitkan tembusan dan reminder pengiriman barang ke Perlengkapan & Keuangan
        PengadaanRoutingService::generateReminders($paket, $kontrak);

        $infoEfisiensi = $selisihEfisiensi > 0
            ? ' (HPS disesuaikan dari Rp '.number_format($hpsLama, 0, ',', '.').' menjadi Rp '.number_format($nilaiKontrak, 0, ',', '.').'; efisiensi Rp '.number_format($selisihEfisiensi, 0, ',', '.').' dikembalikan ke MAK)'
            : '';

        AuditLog::catat(
            $user->id_user,
            'UPLOAD_DOKUMEN_PERIKATAN_TUNGGAL',
            'kontrak_spk',
            $kontrak->nomor_spk,
            null,
            'aktif',
            "Unggah dokumen perikatan terpilih ({$tipeDokumen}) nomor {$kontrak->nomor_spk} nilai Rp ".number_format($kontrak->nilai_kontrak, 0, ',', '.').$infoEfisiensi
        );

        $this->notificationService->notifyPenerbitanKontrak($kontrak);

        $labelJenisKontrak = match ($tipeDokumen) {
            'surat_pesanan' => 'Surat Pesanan',
            'spk' => 'Kontrak Kerja',
            'kontrak' => 'Surat Kontrak',
            default => strtoupper(str_replace('_', ' ', $tipeDokumen)),
        };

        return redirect()->route('kontrak.index')->with(
            'success',
            "Dokumen kontrak ({$labelJenisKontrak}) nomor {$kontrak->nomor_spk} berhasil diunggah beserta Pengingat Jatuh Tempo dan tembusan ke Perlengkapan & Keuangan.".$infoEfisiensi
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
        if (! $user || ! $user->hasAnyPermission(['reviu.pp', 'reviu.ppk'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk menelaah atau menyetujui dokumen pengadaan.');
        }

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'status_reviu' => ['required', 'in:terverifikasi_pp,disetujui,perlu_perbaikan'],
            'catatan_perbaikan' => ['required_if:status_reviu,perlu_perbaikan', 'nullable', 'string'],
        ], [
            'catatan_perbaikan.required_if' => 'Catatan perbaikan atau alasan penolakan wajib diisi saat meminta revisi dokumen pengadaan.',
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        $hasReviuPp = $user->hasPermission('reviu.pp');
        $hasReviuPpk = $user->hasPermission('reviu.ppk');
        $hasViewAllPaket = $user->hasPermission('paket.view_all');

        // Penegakan wewenang murni berbasis permission
        if ($validated['status_reviu'] === 'terverifikasi_pp' && ! $hasReviuPp) {
            return back()->withErrors([
                'status_reviu' => 'Anda tidak memiliki hak akses verifikasi Pejabat Pengadaan.',
            ])->withInput();
        }

        if ($validated['status_reviu'] === 'disetujui' && ! $hasReviuPpk) {
            return back()->withErrors([
                'status_reviu' => 'Pejabat Pengadaan tidak memiliki wewenang persetujuan akhir. Persetujuan dokumen dilakukan oleh PPK.',
            ])->withInput();
        }

        $isPp = match ($validated['status_reviu']) {
            'terverifikasi_pp' => true,
            'disetujui' => false,
            default => ! $hasReviuPpk
                || ($hasReviuPp && in_array($paket->status_paket, ['terdaftar', 'reviu_dokumen'], true)),
        };

        // Penegakan ruang lingkup berkas PPK & PP berbasis permission
        if (! $isPp && ! $hasViewAllPaket && $paket->id_user_ppk && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak mereviu atau menyetujui paket yang ditugaskan kepada PPK lain.');
        }

        if ($isPp && ! $hasViewAllPaket && $paket->jalur_routing !== PaketPengadaan::ROUTING_PP && (int) $paket->id_user_pp !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak mereviu paket di luar jalur Pejabat Pengadaan.');
        }

        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;

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

        // KASUS 1: Pejabat Pengadaan Verifikasi Berhasil -> Kirim ke PPK
        if ($validated['status_reviu'] === 'terverifikasi_pp') {
            $paket->status_paket = PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;
            $paket->catatan_verifikasi_pp = $validated['catatan_perbaikan'] ?? 'Dokumen pengadaan lolos verifikasi Pejabat Pengadaan; berkas diteruskan ke PPK.';
            $paket->save();

            AuditLog::catat(
                $user->id_user,
                'REVIEW_DOKUMEN_PP_BERHASIL',
                'paket_pengadaan',
                $paket->nomor_paket,
                $statusLama,
                $paket->status_paket,
                "Pejabat Pengadaan menyatakan verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil; dokumen diteruskan ke PPK."
            );

            $this->notificationService->notifyReviewPpKePpk($paket, $validated['catatan_perbaikan'] ?? null);

            return redirect()->route('kontrak.index')->with(
                'success',
                "Verifikasi dokumen pengadaan paket {$paket->nomor_paket} berhasil. Berkas diteruskan ke PPK untuk persetujuan."
            );
        }

        // KASUS 2: PPK Menyetujui Dokumen Pengadaan
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
                "PPK menyetujui dokumen pengadaan paket {$paket->nomor_paket}; berkas diteruskan ke Unit Pengadaan untuk membuat dokumen kontrak."
            );

            $this->notificationService->notifySiapUploadDokumenPpbj($paket, 'ppk');

            return redirect()->route('kontrak.index')->with(
                'success',
                "Dokumen pengadaan paket {$paket->nomor_paket} berhasil disetujui oleh PPK. Berkas diteruskan ke Unit Pengadaan untuk penerbitan kontrak."
            );
        }

        // KASUS 3: Penolakan / Perlu Perbaikan
        $paket->status_paket = 'perlu_perbaikan';
        $paket->catatan_verifikasi_pp = ($isPp ? 'Catatan Pejabat Pengadaan: ' : 'Catatan PPK: ').$validated['catatan_perbaikan'];
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'REVIU_DOKUMEN_PERBAIKAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            $paket->status_paket,
            'Penelaah ('.($isPp ? 'Pejabat Pengadaan' : 'PPK').") mengembalikan dokumen paket {$paket->nomor_paket} ke Unit Pengadaan untuk perbaikan: ".$validated['catatan_perbaikan']
        );

        $this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $validated['catatan_perbaikan'] ?? null);

        return redirect()->route('kontrak.index')->with(
            'success',
            "Dokumen pengadaan paket {$paket->nomor_paket} dikembalikan ke Unit Pengadaan untuk perbaikan."
        );
    }

    /**
     * Menampilkan antrean penerimaan dan verifikasi dokumen pengadaan oleh PPK (BPMN N30-N36).
     */
    public function persetujuanDokumenPpkIndex(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('reviu.ppk')) {
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

        if (! $user->hasPermission('paket.view_all')) {
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
        if (! $user || ! $user->hasPermission('reviu.ppk')) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses persetujuan dokumen pengadaan.');
        }

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan_alasan' => ['required_if:keputusan,ditolak', 'nullable', 'string'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan wajib diisi saat mengembalikan dokumen pengadaan ke Unit Pengadaan.',
        ]);

        $paket = PaketPengadaan::with(['permohonan', 'reviuDokumen'])->where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        if (! $user->hasPermission('paket.view_all') && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak memproses paket yang ditugaskan ke akun PPK lain.');
        }

        $statusLama = $paket->status_paket;
        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;
        $isDisetujui = ($validated['keputusan'] === 'disetujui');
        $catatan = $validated['catatan_alasan'] ?: 'Dokumen pengadaan disetujui oleh PPK; berkas diteruskan ke Unit Pengadaan.';

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
                "PPK ({$user->nama}) menyetujui dokumen paket {$paket->nomor_paket}; diteruskan ke Unit Pengadaan."
            );

            $this->notificationService->notifySiapUploadDokumenPpbj($paket, 'ppk');

            return redirect()->route('ppk.persetujuan-dokumen.index')->with(
                'success',
                "Dokumen pengadaan paket {$paket->nomor_paket} berhasil disetujui oleh PPK. Unit Pengadaan dapat melanjutkan pelaksanaan pengadaan."
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
            "PPK ({$user->nama}) mengembalikan dokumen pengadaan paket {$paket->nomor_paket} ke Unit Pengadaan untuk perbaikan: ".$catatan
        );

        $this->notificationService->notifyReviuDokumen($paket, 'perlu_perbaikan', $catatan);

        return redirect()->route('ppk.persetujuan-dokumen.index')->with(
            'success',
            "Dokumen pengadaan paket {$paket->nomor_paket} dikembalikan ke Unit Pengadaan untuk penyusunan ulang."
        );
    }
}
