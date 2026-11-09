<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Manajemen Paket Pengadaan Barang/Jasa (Modul 4)
 *
 * Mengelola siklus paket pengadaan di Bagian PPBJ:
 * - Registrasi paket baru & penentuan Metode Pengadaan (Belanja Langsung, Non-Tender / E-Katalog, Tender SPSE).
 * - Auto-routing 3 jalur nominal:
 *   * <= Rp 50 Juta   => Belanja Langsung (PPBJ eksekusi Kwitansi -> BAST Perlengkapan)
 *   * 50 - 200 Juta   => Verifikasi PP (Review & Negosiasi) -> ACC Dokumen PPK -> Belanja PP -> Kontrak SPK
 *   * > 200 Juta      => Tender SPSE -> ACC Dokumen PPK -> Eksekusi PPK -> Kontrak Surat Perjanjian
 * - Mutasi (reassign) akun PPK & pemecahan jenis pengadaan (Split Jenis).
 */
class PaketPengadaanController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar paket pengadaan, filter, dan data permohonan yang siap dipaketkan.
     */
    public function paketIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['permohonan.unit', 'ppkUser', 'ppUser', 'vendor', 'pelaksanaBelanja']);

        if ($user) {
            $query->forUser($user);
        }

        if ($request->filled('q') || $request->filled('search')) {
            $kw = $request->input('q', $request->input('search'));
            $query->where(function ($q) use ($kw) {
                $q->where('nomor_paket', 'like', "%{$kw}%")
                    ->orWhere('nama_paket', 'like', "%{$kw}%")
                    ->orWhere('nomor_surat', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_paket', $request->input('status'));
        }

        if ($request->filled('jalur_routing')) {
            $query->where('jalur_routing', $request->input('jalur_routing'));
        }

        if ($request->filled('jenis_pengadaan')) {
            $query->where('jenis_pengadaan', $request->input('jenis_pengadaan'));
        }

        $this->syncAllPermohonanDisetujuiParalel();

        $paketList = $query->latest('tanggal_registrasi_paket')->paginate(15);
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $riwayatPenugasan = AuditLog::with('user')
            ->where('jenis_aksi', 'REASSIGN_PPK')
            ->latest('waktu_log')
            ->take(20)
            ->get();
        $permohonanDisetujui = PermohonanPengadaan::with([
            'unit',
            'ppkAwal',
            'anggaranMak',
            'alokasiMak.anggaranMak',
            'verifikasiParalel.user',
        ])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])
            ->whereDoesntHave('paketPengadaan')
            ->latest('tanggal_pengajuan')
            ->get();
        $vendors = Vendor::all();

        // Paket yang dikembalikan untuk perbaikan (BPMN N18: Titik kembali jika ditolak PP N27 atau PPK N35)
        $paketPerluPerbaikan = PaketPengadaan::with([
            'permohonan.unit',
            'ppkUser',
            'ppUser',
            'vendor',
            'reviuDokumen.user',
        ])
            ->where('status_paket', 'perlu_perbaikan')
            ->latest()
            ->get();

        return view('paket.index', compact('paketList', 'ppkUsers', 'riwayatPenugasan', 'permohonanDisetujui', 'vendors', 'paketPerluPerbaikan'));
    }

    public function index(Request $request): View
    {
        return $this->paketIndex($request);
    }

    /**
     * Memproses pemecahan (split) berkas permohonan berdasarkan jenis pengadaan oleh PPBJ (FR 7.2).
     */
    public function splitJenisPpbj(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $nomorInduk = $request->input('nomor_surat_induk');
        if (! $request->filled('nomor_surat_baru') && $nomorInduk) {
            $countChild = PermohonanPengadaan::where('nomor_surat_induk_split', $nomorInduk)->count();
            $request->merge(['nomor_surat_baru' => $nomorInduk.'.PBJ.'.($countChild + 1)]);
        }

        $validated = $request->validate([
            'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'jenis_pengadaan_split' => ['required', 'string', 'in:barang,jasa,konstruksi,jasa_lainnya,jasa_konsultansi,pekerjaan_konstruksi'],
            'estimasi_total_pagu' => ['required', 'numeric', 'min:1'],
            'alasan_pemecahan' => ['required', 'string'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);

        $nominalSplit = (float) $validated['estimasi_total_pagu'];
        $induk->estimasi_total_pagu = max(0, (float) ($induk->estimasi_total_pagu ?? 0) - $nominalSplit);
        $induk->save();

        $anak = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'perihal' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
            'file_permohonan' => $induk->file_permohonan,
            'status_permohonan' => PermohonanPengadaan::STATUS_DISETUJUI,
            'status_anggaran' => $induk->status_anggaran ?? 'tersedia',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
            'anggaran_diajukan' => $validated['estimasi_total_pagu'],
            'kode_unit' => $induk->kode_unit,
            'id_user' => $induk->id_user,
            'id_user_ppk_awal' => $induk->id_user_ppk_awal,
            'nomor_memo_cek_mak' => $induk->nomor_memo_cek_mak,
            'nomor_memo_ketersediaan_mak' => $induk->nomor_memo_ketersediaan_mak,
            'nomor_surat_induk_split' => $induk->nomor_surat,
            'tanggal_pemecahan' => now(),
            'alasan_pemecahan' => $validated['alasan_pemecahan'],
            'kode_mak' => $induk->kode_mak,
            'jenis_pengadaan_split' => $validated['jenis_pengadaan_split'],
        ]);

        AuditLog::catat(
            $user->id_user,
            'SPLIT_BERKAS_JENIS_PPBJ',
            'permohonan_pengadaan',
            $anak->nomor_surat,
            $induk->nomor_surat,
            PermohonanPengadaan::STATUS_DISETUJUI,
            'Pemecahan berkas oleh PPBJ berdasarkan jenis pengadaan '.$validated['jenis_pengadaan_split'].': '.$validated['alasan_pemecahan']
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Berkas berhasil dipecah menjadi surat baru {$anak->nomor_surat} untuk jenis pengadaan ".strtoupper($validated['jenis_pengadaan_split']).'.'
        );
    }

    /**
     * Menampilkan form pendaftaran paket pengadaan baru.
     */
    public function paketCreate(): View
    {
        $this->syncAllPermohonanDisetujuiParalel();

        $permohonanDisetujui = PermohonanPengadaan::with([
            'unit',
            'ppkAwal',
            'anggaranMak',
            'alokasiMak.anggaranMak',
            'verifikasiParalel.user',
        ])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            ])
            ->get();

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->take(4)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();
        $vendors = Vendor::orderBy('nama_vendor')->get();

        return view('paket.create', compact('permohonanDisetujui', 'ppkUsers', 'ppUsers', 'vendors'));
    }

    public function create(): View
    {
        return $this->paketCreate();
    }

    /**
     * Memproses penyimpanan paket pengadaan baru dan auto-routing nominal.
     */
    public function storePaket(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required', 'string', 'max:100', 'unique:paket_pengadaan,nomor_paket'],
            'nama_paket' => ['required', 'string', 'max:255'],
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'jenis_pengadaan' => ['required', 'string'],
            'metode_pengadaan' => ['required', 'string'],
            'nilai_hps' => ['required', 'numeric', 'gt:0'],
            'nominal_harga_perkiraan' => ['nullable', 'numeric', 'min:0'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'min:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'id_vendor' => ['nullable'],
            'nomor_memo_ppbj' => ['nullable', 'string', 'max:100'],
            'id_user_ppk' => ['nullable', 'exists:users,id_user'],
            'id_user_pp' => ['nullable', 'exists:users,id_user'],
        ]);

        $permohonan = PermohonanPengadaan::with('anggaranMak')->findOrFail($validated['nomor_surat']);
        $hps = (float) $validated['nilai_hps'];
        $paguAwal = (float) ($permohonan->estimasi_total_pagu ?? 0);
        $selisih = $hps - $paguAwal;
        $mak = $permohonan->kode_mak ? AnggaranMAK::find($permohonan->kode_mak) : null;

        // Sinkronisasi sisa pagu MAK terhadap selisih HPS
        if ($mak) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->withErrors([
                        'nilai_hps' => 'Penetapan HPS (Rp '.number_format($hps, 0, ',', '.').') melebihi pagu awal permohonan (Rp '.number_format($paguAwal, 0, ',', '.').') dan sisa pagu MAK '.$mak->kode_mak.' tidak mencukupi (tersisa Rp '.number_format($mak->sisa_pagu_mak, 0, ',', '.').').',
                    ])->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } elseif ($selisih < 0) {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }

            $alokasi = AlokasiMAK::where('nomor_surat', $permohonan->nomor_surat)
                ->where('kode_mak', $mak->kode_mak)
                ->first();
            if ($alokasi) {
                $alokasi->nominal_alokasi = $hps;
                $alokasi->save();
            }
        }

        $permohonan->estimasi_total_pagu = $hps;
        $permohonan->save();

        $jalur = PaketPengadaan::tentukanRouting($hps);
        $inheritedPpk = $validated['id_user_ppk'] ?? $permohonan->id_user_ppk_awal;

        if ($jalur === PaketPengadaan::ROUTING_PPK && empty($inheritedPpk)) {
            return back()->withErrors(['id_user_ppk' => 'Nilai HPS > Rp 200 Juta wajib menugaskan salah satu dari 4 akun PPK aktif.'])->withInput();
        }

        $nomorMemoPpbj = ($jalur === PaketPengadaan::ROUTING_PPK)
            ? null
            : ($validated['nomor_memo_ppbj'] ?? ('MEMO-PPBJ/'.date('Y').'/'.rand(100, 999)));

        $idUserPp = null;
        $idUserPpk = null;

        if ($jalur === PaketPengadaan::ROUTING_LANGSUNG) {
            $metodePengadaan = PaketPengadaan::METODE_BELANJA_LANGSUNG;
            $idUserPpk = $inheritedPpk ?? User::where('role', 'ppk')->where('status_aktif', true)->first()?->id_user;
        } elseif ($jalur === PaketPengadaan::ROUTING_PP) {
            $idUserPp = $validated['id_user_pp'] ?? User::where('role', 'pp')->where('status_aktif', true)->first()?->id_user;
            $idUserPpk = $inheritedPpk ?? User::where('role', 'ppk')->where('status_aktif', true)->first()?->id_user;
            $metodePengadaan = $validated['metode_pengadaan'];
        } else {
            $idUserPpk = $inheritedPpk;
            $metodePengadaan = $validated['metode_pengadaan'];
        }

        $platform = match ($metodePengadaan) {
            PaketPengadaan::METODE_E_KATALOG, PaketPengadaan::METODE_NON_TENDER, 'E-Purchasing', 'Surat Pesanan' => 'Inaproc / E-Katalog',
            PaketPengadaan::METODE_SPSE, PaketPengadaan::METODE_TENDER, 'Tender' => 'SPSE',
            PaketPengadaan::METODE_BELANJA_LANGSUNG, 'Pengadaan Langsung' => 'Belanja Langsung',
            default => 'SPSE/Inaproc',
        };

        $hargaPerkiraan = isset($validated['nominal_harga_perkiraan']) ? (float) $validated['nominal_harga_perkiraan'] : $hps;
        $hargaAkhir = isset($validated['nominal_harga_akhir']) ? (float) $validated['nominal_harga_akhir'] : null;

        $idVendor = $validated['id_vendor'] ?? null;
        if (! empty($validated['nama_vendor'])) {
            $v = Vendor::firstOrCreate(['nama_vendor' => trim($validated['nama_vendor'])]);
            $idVendor = $v->id;
        }

        // Jalur 50-200jt masuk ke verifikasi teknis PP terlebih dahulu; jalur lainnya langsung ke antrean Approval PPK (BPMN N30)
        $statusAwal = ($jalur === PaketPengadaan::ROUTING_PP)
            ? 'terdaftar'
            : PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;

        $paket = PaketPengadaan::create([
            'kode_paket' => $validated['kode_paket'],
            'nomor_paket' => $validated['kode_paket'],
            'nama_paket' => $validated['nama_paket'],
            'nomor_surat' => $validated['nomor_surat'],
            'jenis_pengadaan' => Str::lower(str_replace(' ', '_', $validated['jenis_pengadaan'])),
            'platform_pengadaan' => $platform,
            'mekanisme_pengadaan' => $metodePengadaan,
            'metode_pengadaan' => $metodePengadaan,
            'nilai_hps' => $hps,
            'alokasi_pagu_paket' => $hps,
            'nominal_harga_perkiraan' => $hargaPerkiraan,
            'nominal_harga_akhir' => $hargaAkhir,
            'nilai_hasil_negosiasi' => $hargaAkhir,
            'id_vendor' => $idVendor,
            'jalur_routing' => $jalur,
            'nomor_memo_ppbj' => $nomorMemoPpbj,
            'id_user_ppbj' => $user->id_user,
            'id_user_ppk' => $idUserPpk,
            'id_user_pp' => $idUserPp,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => $statusAwal,
        ]);

        AuditLog::catat(
            $user->id_user,
            'REGISTRASI_PAKET',
            'paket_pengadaan',
            $paket->kode_paket,
            null,
            'terdaftar',
            "PPBJ mendaftarkan paket {$paket->kode_paket} metode {$metodePengadaan} HPS Rp ".number_format($hps)." jalur routing {$jalur}"
        );

        $this->notificationService->notifyPendaftaranPaket($paket);

        return redirect()->route('paket.index')->with(
            'success',
            "Paket pengadaan {$paket->kode_paket} berhasil diterbitkan dengan metode {$metodePengadaan} (jalur: ".strtoupper(str_replace('_', ' ', $jalur)).').'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storePaket($request);
    }

    /**
     * Mengeksekusi Belanja Langsung untuk paket <= Rp 50 Juta oleh PPBJ (Kwitansi tanpa Kontrak/SPK).
     */
    public function storeBelanjaLangsungWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'nomor_kwitansi_belanja' => ['required', 'string', 'max:100'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'gt:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'file_dokumen_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'id_vendor' => ['nullable'],
            'jadwal_pengiriman' => ['nullable', 'date'],
            'file_kwitansi_belanja' => ['nullable', DokumenUploadRule::pdfOrImage()],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        if ($paket->jalur_routing !== PaketPengadaan::ROUTING_LANGSUNG) {
            return back()->withErrors([
                'nomor_paket' => 'Eksekusi Belanja Langsung (Kwitansi tanpa Kontrak/SPK) hanya berlaku untuk paket <= Rp 50.000.000.',
            ])->withInput();
        }

        // Dokumen pengadaan wajib disetujui PPK (BPMN N30-N35) sebelum belanja langsung
        if (! in_array($paket->status_paket, [
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
            'disetujui',
            PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
        ], true)) {
            return back()->withErrors([
                'nomor_paket' => 'Dokumen pengadaan paket ini belum disetujui oleh PPK (BPMN N30-N35). PPBJ baru dapat mengunggah kwitansi belanja setelah persetujuan PPK.',
            ])->withInput();
        }

        $kwitansiPath = $request->hasFile('file_kwitansi_belanja')
            ? $request->file('file_kwitansi_belanja')->store('kwitansi_belanja', 'public')
            : ('kwitansi_belanja/kwitansi_'.str_replace(['/', '\\'], '_', $paket->nomor_paket).'.pdf');

        $hargaAkhir = isset($validated['nominal_harga_akhir'])
            ? (float) $validated['nominal_harga_akhir']
            : (float) ($paket->nominal_harga_akhir ?: $paket->nilai_hps);

        if (! empty($validated['nama_vendor'])) {
            $legalitasPath = $request->hasFile('file_dokumen_legalitas')
                ? $request->file('file_dokumen_legalitas')->store('vendor_legalitas', 'public')
                : null;

            $vendor = Vendor::firstOrCreate(
                ['nama_vendor' => trim($validated['nama_vendor'])],
                [
                    'file_dokumen_legalitas' => $legalitasPath,
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

        $statusLama = $paket->status_paket;
        $paket->nomor_kwitansi_belanja = $validated['nomor_kwitansi_belanja'];
        $paket->file_kwitansi_belanja = $kwitansiPath;
        $paket->nominal_harga_akhir = $hargaAkhir;
        $paket->nilai_hasil_negosiasi = $hargaAkhir;
        $paket->dieksekusi_belanja_oleh = $user->id_user;
        $paket->tanggal_eksekusi_belanja = now();
        $paket->status_paket = PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI;
        $paket->save();

        // Bangkitkan pengingat (reminder) ke Perlengkapan & Keuangan
        PengadaanRoutingService::generateBelanjaLangsungReminder($paket, $validated['jadwal_pengiriman'] ?? null);

        AuditLog::catat(
            $user->id_user,
            'EKSEKUSI_BELANJA_LANGSUNG_KWITANSI',
            'paket_pengadaan',
            $paket->nomor_paket,
            $statusLama,
            PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
            "PPBJ mengeksekusi Belanja Langsung paket {$paket->nomor_paket} dengan Kwitansi {$paket->nomor_kwitansi_belanja} (tanpa Kontrak/SPK)."
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Belanja Langsung paket {$paket->nomor_paket} berhasil dieksekusi dengan Kwitansi {$paket->nomor_kwitansi_belanja} (tanpa Kontrak/SPK). Siap pemeriksaan fisik & BAST Perlengkapan."
        );
    }

    public function belanjaLangsung(Request $request): RedirectResponse
    {
        return $this->storeBelanjaLangsungWeb($request);
    }

    /**
     * Memproses alih tugas (reassign) paket ke PPK lain oleh PPBJ.
     */
    public function reassignPpkWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'id_user_ppk_baru' => ['required', 'exists:users,id_user'],
            'alasan_pengalihan' => ['required', 'string'],
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['kode_paket'])
            ->firstOrFail();

        $ppkBaru = User::findOrFail($validated['id_user_ppk_baru']);
        if ($ppkBaru->role !== 'ppk') {
            return back()->withErrors(['id_user_ppk_baru' => 'User tujuan mutasi harus berstatus akun PPK.']);
        }

        $ppkLama = $paket->id_user_ppk;
        $paket->id_user_ppk = $ppkBaru->id_user;
        $paket->alasan_pengalihan_ppk = $validated['alasan_pengalihan'];
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket->kode_paket,
            (string) $ppkLama,
            (string) $ppkBaru->id_user,
            "Alih tugas PPK paket {$paket->kode_paket} ke {$ppkBaru->nama}: {$validated['alasan_pengalihan']}"
        );

        $this->notificationService->notifyReassignPpk($paket, $ppkBaru, $validated['alasan_pengalihan']);

        return redirect()->route('paket.index')->with(
            'success',
            "Penugasan paket {$paket->kode_paket} berhasil dialihkan ke PPK {$ppkBaru->nama}."
        );
    }

    public function reassignPpk(Request $request): RedirectResponse
    {
        return $this->reassignPpkWeb($request);
    }

    /**
     * Menampilkan form revisi dokumen pengadaan oleh PPBJ (BPMN N18).
     */
    public function paketEdit(string $id): View
    {
        $paket = PaketPengadaan::with([
            'permohonan.unit',
            'permohonan.anggaranMak',
            'permohonan.verifikasiParalel.user',
            'reviuDokumen.user',
            'ppkUser',
            'ppUser',
            'vendor',
        ])->where('nomor_paket', $id)->firstOrFail();

        $user = Auth::user();
        if ($user && ! $user->hasRole('admin') && ! $user->hasRole('ppbj') && ! $user->hasAnyPermission(['paket.create', 'paket.view_all'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk mengubah dokumen pengadaan.');
        }

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->take(4)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();
        $vendors = Vendor::orderBy('nama_vendor')->get();

        return view('paket.edit', compact('paket', 'ppkUsers', 'ppUsers', 'vendors'));
    }

    public function edit(string $id): View
    {
        return $this->paketEdit($id);
    }

    /**
     * Memproses pembaruan dokumen pengadaan oleh PPBJ setelah perbaikan (BPMN N18 -> N22 -> N25 -> N28).
     */
    public function paketUpdate(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();
        $paket = PaketPengadaan::with('permohonan.anggaranMak')->where('nomor_paket', $id)->firstOrFail();

        $validated = $request->validate([
            'nama_paket' => ['required', 'string', 'max:255'],
            'jenis_pengadaan' => ['required', 'string'],
            'metode_pengadaan' => ['required', 'string'],
            'nilai_hps' => ['required', 'numeric', 'gt:0'],
            'nominal_harga_perkiraan' => ['nullable', 'numeric', 'min:0'],
            'nominal_harga_akhir' => ['nullable', 'numeric', 'min:0'],
            'nama_vendor' => ['nullable', 'string', 'max:255'],
            'id_vendor' => ['nullable'],
            'id_user_ppk' => ['nullable', 'exists:users,id_user'],
            'id_user_pp' => ['nullable', 'exists:users,id_user'],
            'catatan_perbaikan_ppbj' => ['nullable', 'string'],
        ]);

        $hps = (float) $validated['nilai_hps'];
        $hpsLama = (float) $paket->nilai_hps;
        $selisih = $hps - $hpsLama;
        $mak = $paket->permohonan?->kode_mak ? AnggaranMAK::find($paket->permohonan->kode_mak) : null;

        if ($mak && $selisih != 0) {
            if ($selisih > 0) {
                if ((float) $mak->sisa_pagu_mak < $selisih) {
                    return back()->withErrors([
                        'nilai_hps' => 'Penyesuaian HPS melebihi sisa pagu MAK '.$mak->kode_mak.' yang tersedia (tersisa Rp '.number_format($mak->sisa_pagu_mak, 0, ',', '.').').',
                    ])->withInput();
                }
                $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                $mak->save();
            } else {
                $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                $mak->save();
            }

            if ($paket->nomor_surat) {
                $alokasi = AlokasiMAK::where('nomor_surat', $paket->nomor_surat)
                    ->where('kode_mak', $mak->kode_mak)
                    ->first();
                if ($alokasi) {
                    $alokasi->nominal_alokasi = $hps;
                    $alokasi->save();
                }
            }
        }

        $jalur = PaketPengadaan::tentukanRouting($hps);

        $idVendor = $validated['id_vendor'] ?? $paket->id_vendor;
        if (! empty($validated['nama_vendor'])) {
            $v = Vendor::firstOrCreate(['nama_vendor' => trim($validated['nama_vendor'])]);
            $idVendor = $v->id;
        }

        $idUserPpk = $validated['id_user_ppk'] ?? $paket->id_user_ppk;
        $idUserPp = ($jalur === PaketPengadaan::ROUTING_PP)
            ? ($validated['id_user_pp'] ?? $paket->id_user_pp ?? User::where('role', 'pp')->where('status_aktif', true)->first()?->id_user)
            : null;

        $platform = match ($validated['metode_pengadaan']) {
            PaketPengadaan::METODE_E_KATALOG, PaketPengadaan::METODE_NON_TENDER, 'E-Purchasing', 'Surat Pesanan' => 'Inaproc / E-Katalog',
            PaketPengadaan::METODE_SPSE, PaketPengadaan::METODE_TENDER, 'Tender' => 'SPSE',
            PaketPengadaan::METODE_BELANJA_LANGSUNG, 'Pengadaan Langsung' => 'Belanja Langsung',
            default => 'SPSE/Inaproc',
        };

        // Reset status paket sesuai rute BPMN N28
        // Jalur 50-200jt kembali ke verifikasi PP (terdaftar); Jalur Belanja Langsung (<=50jt) dan Tender (>200jt) langsung kembali ke persetujuan PPK (menunggu_approval_ppk)
        $statusBaru = ($jalur === PaketPengadaan::ROUTING_PP)
            ? 'terdaftar'
            : PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;

        $paket->update([
            'nama_paket' => $validated['nama_paket'],
            'jenis_pengadaan' => Str::lower(str_replace(' ', '_', $validated['jenis_pengadaan'])),
            'platform_pengadaan' => $platform,
            'mekanisme_pengadaan' => $validated['metode_pengadaan'],
            'metode_pengadaan' => $validated['metode_pengadaan'],
            'nilai_hps' => $hps,
            'alokasi_pagu_paket' => $hps,
            'nominal_harga_perkiraan' => $validated['nominal_harga_perkiraan'] ?? $hps,
            'nominal_harga_akhir' => $validated['nominal_harga_akhir'] ?? null,
            'id_vendor' => $idVendor,
            'jalur_routing' => $jalur,
            'id_user_ppk' => $idUserPpk,
            'id_user_pp' => $idUserPp,
            'status_paket' => $statusBaru,
        ]);

        AuditLog::catat(
            $user->id_user,
            'PERBAIKAN_DOKUMEN_PENGADAAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            'perlu_perbaikan',
            $statusBaru,
            'PPBJ menyusun ulang dokumen pengadaan (BPMN N18/N22/N25) dengan HPS Rp '.number_format($hps).' menuju jalur '.strtoupper(str_replace('_', ' ', $jalur)).': '.($validated['catatan_perbaikan_ppbj'] ?? 'Dokumen telah diperbaiki')
        );

        $this->notificationService->notifyPendaftaranPaket($paket);

        return redirect()->route('paket.index')->with(
            'success',
            "Dokumen paket {$paket->nomor_paket} berhasil diperbaiki dan diteruskan kembali ke jalur ".strtoupper(str_replace('_', ' ', $jalur)).' (BPMN N28).'
        );
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        return $this->paketUpdate($request, $id);
    }

    /**
     * Menampilkan detail siklus paket pengadaan.
     */
    public function paketShow(Request $request, string $id): View
    {
        $paket = PaketPengadaan::with([
            'permohonan.unit',
            'permohonan.ppkAwal',
            'permohonan.verifikasiParalel.user',
            'ppbjUser',
            'ppkUser',
            'ppUser',
            'vendor',
            'riwayatPpk.ppkLama',
            'riwayatPpk.ppkBaru',
            'riwayatPpk.user',
            'kontrakSpk',
            'penerimaanBarang',
            'pembayaran.memoBayar.user',
            'reviuDokumen.user',
        ])->where('nomor_paket', $id)->firstOrFail();

        $user = Auth::user();
        if ($user && $user->cannot('view', $paket)) {
            abort(403, 'Akses Ditolak: Berkas paket pengadaan ini ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('paket.show', compact('paket', 'ppkUsers'));
    }

    public function show(Request $request, string $id): View
    {
        return $this->paketShow($request, $id);
    }

    /**
     * Menampilkan form pemecahan jenis pengadaan (standalone page).
     */
    public function splitJenisCreate(): View
    {
        $permohonanDisetujui = PermohonanPengadaan::where('status_permohonan', PermohonanPengadaan::STATUS_DISETUJUI)->get();

        return view('paket.split_jenis', compact('permohonanDisetujui'));
    }

    /**
     * Menampilkan form alih tugas PPK (standalone page).
     */
    public function reassignPpkCreate(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['ppkUser']);
        if ($user) {
            $query->forUser($user);
        }
        $paketList = $query->get();
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();

        return view('paket.reassign', compact('paketList', 'ppkUsers'));
    }

    /**
     * Memeriksa dan menyelaraskan status permohonan yang telah disetujui paralel lengkap.
     */
    public function syncAllPermohonanDisetujuiParalel(): void
    {
        $kandidat = PermohonanPengadaan::with(['verifikasiParalel'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                PermohonanPengadaan::STATUS_DITOLAK,
                'digantikan_oleh_revisi',
                PermohonanPengadaan::STATUS_SELESAI,
            ])
            ->whereNotNull('kode_mak')
            ->where('kode_mak', '!=', '')
            ->get();

        foreach ($kandidat as $p) {
            $verif = $p->verifikasiParalel;
            $wadirAcc = $verif->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            $ppkAcc = empty($p->id_user_ppk_awal) || $verif->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            if ($wadirAcc && $ppkAcc) {
                $statusLama = $p->status_permohonan;
                $p->status = PermohonanPengadaan::STATUS_DISETUJUI;
                $p->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                $p->save();

                AuditLog::catat(
                    Auth::id() ?? 1,
                    'AUTO_SYNC_PERSETUJUAN_PARALEL',
                    'permohonan_pengadaan',
                    $p->nomor_surat,
                    $statusLama,
                    PermohonanPengadaan::STATUS_DISETUJUI,
                    'Status permohonan disinkronkan otomatis menjadi Disetujui karena MAK, Wadir 2, dan PPK telah lengkap disetujui.'
                );
            }
        }
    }
}
