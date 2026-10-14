<?php

namespace App\Http\Controllers;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\RiwayatPenugasanPPK;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Beranda & Simulasi Alur Pengadaan (HomeController)
 *
 * Mengelola seluruh antarmuka simulasi backend (khusus pengujian oleh Overseer Backend):
 * - Dashboard agregat real-time dengan pemisahan akses peran (RBAC & isolasi 4 akun PPK).
 * - Simulasi pengajuan usulan permohonan baru & pengajuan usulan revisi unary (FR 3).
 * - Simulasi alur pemeriksaan berkas oleh Staf Bidang 2 dan keputusan mutlak Wadir 2 (FR 5).
 * - Simulasi pendaftaran paket dengan auto-routing HPS dan alih penugasan PPK (NFR 4).
 * - Pemantauan siklus 8 tahapan pengadaan (NFR 7).
 * - Helper unduh berkas resmi format PDF (NFR 12).
 */
class HomeController extends Controller
{
    /**
     * Menampilkan halaman beranda (dashboard) simulasi pengujian backend.
     * Mengambil statistik ringkasan, permohonan terbaru, paket pengadaan terisolasi, dan notifikasi.
     */
    public function index(): View
    {
        // 1. Ambil data pengguna yang sedang login beserta unit kerjanya
        $user = Auth::user();
        if ($user) {
            $user->load('unit');
        }

        // 2. Hitung statistik agregat real-time dari database untuk kartu informasi dashboard
        $stats = [
            'total_units' => Unit::count(),                              // Jumlah unit kerja & jurusan
            'total_permohonan' => PermohonanPengadaan::count(),          // Total usulan permohonan masuk
            'total_anggaran' => AnggaranMAK::sum('pagu_anggaran'),       // Akumulasi pagu anggaran DIPA
            'total_paket' => PaketPengadaan::count(),                    // Total paket pengadaan aktif
            'total_vendor' => Vendor::count(),                          // Jumlah rekanan terdaftar
        ];

        // 3. Ambil 5 permohonan pengadaan terbaru (disaring sesuai kepemilikan jika peran adalah 'user')
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user']);
        if ($user && $user->role === 'user') {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        // 4. Ambil 5 paket pengadaan terbaru dengan penegakan isolasi wewenang (4 akun PPK / PP / Pemohon)
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser']);
        if ($user) {
            $paketQuery->forUser($user);
        }
        $recentPaket = $paketQuery->latest('tanggal_registrasi_paket')->take(5)->get();

        // 5. Ambil 5 notifikasi pengguna terbaru yang belum ditutup (dismissed)
        $notifications = $user ? NotifikasiUser::with('reminder.paket')
            ->where('id_user', $user->id_user)
            ->where('is_dismissed', false)
            ->latest('id')
            ->take(5)
            ->get() : collect();

        // 6. Ambil seluruh master unit kerja untuk keperluan navigasi
        $units = Unit::all();

        return view('tes_home', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units'));
    }

    /**
     * Menampilkan halaman pengujian interaktif endpoint RESTful API backend.
     */
    public function testApi(): View
    {
        return view('tes_api');
    }

    /**
     * Menampilkan antarmuka simulasi pengajuan usulan permohonan pengadaan & revisi Srikandi (FR 3).
     */
    public function tesPermohonan(Request $request): View
    {
        $user = Auth::user();
        // Query permohonan lengkap dengan relasi unit, user pemohon, verifikasi paralel, dan riwayat revisi
        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel', 'suratAsalRevisi']);

        // Terapkan pembatasan data sesuai peran pengguna yang login
        if ($user) {
            $query->forUser($user);
        }

        // Tampilkan 10 permohonan per halaman
        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(10);

        // Ambil daftar permohonan yang berstatus ditolak untuk opsi dropdown pada form revisi usulan
        $permohonanDitolak = PermohonanPengadaan::where('status_permohonan', PermohonanPengadaan::STATUS_DITOLAK)
            ->when($user && $user->role === 'user', fn ($q) => $q->where('id_user', $user->id_user))
            ->get();

        $units = Unit::all();

        return view('tes_permohonan', compact('user', 'permohonanList', 'units', 'permohonanDitolak'));
    }

    /**
     * Memproses penyimpanan usulan permohonan baru dari form simulasi web (FR 3.1 - FR 3.3).
     */
    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi input: format surat unik, judul, KAK ringkas, dan berkas wajib format PDF (maks 10MB)
        $validated = $request->validate([
            'nomor_surat' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
        ]);

        // 2. Simpan fisik file PDF permohonan ke disk storage publik
        $filePath = $request->file('file_permohonan')->store('permohonan', 'public');

        // 3. Buat baris data permohonan baru di tabel permohonan_pengadaan
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'],
            'file_permohonan' => $filePath,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL, // Masuk ke status reviu paralel
            'status_anggaran' => 'pending',
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
            'tanggal_pengajuan' => now(),
            'kode_unit' => $user->kode_unit ?? Unit::first()->kode_unit,
            'id_user' => $user->id_user,
        ]);

        // 4. Inisialisasi antrean verifikasi paralel untuk Bagian Perencanaan
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        // 5. Inisialisasi antrean verifikasi paralel untuk Pimpinan (Wadir 2)
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        return redirect()->route('permohonan.web')->with('success', 'Permohonan pengadaan nomor '.$permohonan->nomor_surat.' berhasil diajukan dan diteruskan ke Verifikasi Paralel (FR 3.1 - FR 3.3)!');
    }

    /**
     * Memproses pengajuan revisi permohonan yang ditolak dari form simulasi web (FR 3.4).
     * Menerbitkan nomor surat baru, menambah counter revisi_ke, dan menghubungkan relasi unary.
     */
    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi input revisi: nomor surat lama harus terdaftar, nomor surat baru wajib unik
        $validated = $request->validate([
            'nomor_surat_lama' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'nomor_surat_baru' => 'required|string|max:100|unique:permohonan_pengadaan,nomor_surat',
            'judul_pengadaan' => 'required|string|max:255',
            'tujuan_pengadaan_ringkas' => 'required|string',
            'file_permohonan' => ['required', DokumenUploadRule::pdfOnly()],
            'estimasi_total_pagu' => 'nullable|numeric|min:0',
            'alasan_revisi' => 'required|string',
        ]);

        // 2. Ambil data permohonan induk yang ditolak
        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);
        // Simpan file lampiran usulan revisi baru
        $filePath = $request->file('file_permohonan')->store('permohonan', 'public');

        // 3. Terbitkan baris permohonan baru dengan counter revisi_ke yang bertambah (+1) dan tautan unary ke surat lama
        $revisi = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'],
            'file_permohonan' => $filePath,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => $induk->revisi_ke + 1,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'] ?? $induk->estimasi_total_pagu,
            'tanggal_pengajuan' => now(),
            'kode_unit' => $induk->kode_unit,
            'id_user' => $user->id_user,
            'nomor_surat_revisi' => $induk->nomor_surat, // Relasi unary ke surat sebelumnya
            'tanggal_revisi' => now(),
            'alasan_revisi' => $validated['alasan_revisi'],
        ]);

        // 4. Buat siklus verifikasi paralel baru untuk permohonan revisi tersebut
        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $revisi->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        return redirect()->route('permohonan.web')->with('success', 'Revisi permohonan berhasil diterbitkan dengan nomor baru: '.$revisi->nomor_surat.' (FR 3.4)');
    }

    /**
     * Menampilkan antarmuka simulasi pemantauan status 8 tahapan siklus pengadaan (NFR 7).
     */
    public function tesMonitoring(Request $request): View
    {
        $user = Auth::user();
        $selectedNomor = $request->query('nomor_surat');
        $trackingData = null;

        // Jika user memilih nomor surat tertentu dari daftar, ambil 8-stage tracking-nya
        if ($selectedNomor) {
            $permohonan = PermohonanPengadaan::find($selectedNomor);
            if ($permohonan) {
                $controller = app(PermohonanPengadaanController::class);
                $trackingData = $controller->tracking($permohonan)->getData(true);
            }
        }

        // Ambil 20 usulan permohonan terbaru untuk daftar pilihan tracking
        $allPermohonan = PermohonanPengadaan::with('unit')->latest('tanggal_pengajuan')->take(20)->get();

        return view('tes_monitoring', compact('user', 'selectedNomor', 'trackingData', 'allPermohonan'));
    }

    /**
     * Menampilkan antarmuka pendaftaran paket pengadaan & simulasi alih tugas PPK (NFR 4 & FR 7).
     */
    public function tesPaket(Request $request): View
    {
        $user = Auth::user();
        // Query paket dengan eager loading seluruh aktor pengadaan terkait
        $query = PaketPengadaan::with(['permohonan.unit', 'ppbjUser', 'ppkUser', 'ppUser', 'riwayatPpk.ppkBaru']);

        // Terapkan isolasi akses (PPK hanya melihat paket miliknya)
        if ($user) {
            $query->forUser($user);
        }

        $paketList = $query->latest('tanggal_registrasi_paket')->paginate(10);
        // Ambil usulan yang sudah disetujui / siap dipaketkan oleh PPBJ
        $permohonanList = PermohonanPengadaan::whereIn('status_permohonan', [
            PermohonanPengadaan::STATUS_DISETUJUI,
            PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
        ])->get();

        // Ambil daftar 4 akun PPK aktif dan Pejabat Pengadaan (PP) aktif
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();

        return view('tes_paket', compact('user', 'paketList', 'permohonanList', 'ppkUsers', 'ppUsers'));
    }

    /**
     * Menyimpan data paket pengadaan baru dengan auto-routing ambang batas HPS (NFR 4 & FR 8).
     */
    public function storePaket(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi input pembuatan paket pengadaan
        $validated = $request->validate([
            'nomor_paket' => 'required|string|max:100|unique:paket_pengadaan,nomor_paket',
            'nama_paket' => 'required|string|max:255',
            'jenis_pengadaan' => 'required|string|in:barang,jasa_lainnya,konstruksi,jasa_konsultansi',
            'platform_pengadaan' => 'required|string|max:50',
            'mekanisme_pengadaan' => 'required|string|max:50',
            'nilai_hps' => 'required|numeric|gt:0', // HPS wajib angka positif
            'alokasi_pagu_paket' => 'required|numeric|gt:0',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'id_user_ppk' => 'nullable|exists:users,id_user',
            'id_user_pp' => 'nullable|exists:users,id_user',
        ]);

        // 2. Tetapkan identitas staf PPBJ yang mendaftarkan paket
        $validated['id_user_ppbj'] = $user?->id_user ?? User::where('role', 'ppbj')->first()->id_user;
        // 3. Tentukan jalur pengadaan secara 100% deterministik berdasarkan nilai HPS (NFR 4)
        $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        $validated['tanggal_registrasi_paket'] = now();
        $validated['status_paket'] = 'draft';

        // Catat tanggal penugasan jika pejabat telah dipilih
        if (! empty($validated['id_user_ppk'])) {
            $validated['tanggal_penugasan_ppk'] = now();
            $validated['status_penugasan_ppk'] = 'ditugaskan';
        }

        if (! empty($validated['id_user_pp'])) {
            $validated['tanggal_penugasan_pp'] = now();
        }

        // 4. Simpan paket pengadaan ke database
        $paket = PaketPengadaan::create($validated);

        return redirect()->route('paket.web')->with('success', 'Paket pengadaan '.$paket->nomor_paket.' berhasil didaftarkan dengan jalur routing otomatis: '.$paket->jalur_routing.' (NFR 4)');
    }

    /**
     * Memproses alih tugas (reassign) paket ke PPK lain dari simulasi web (NFR 4 & FR 7.3).
     */
    public function reassignPpkWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi input pergantian penugasan PPK
        $validated = $request->validate([
            'nomor_paket' => 'required|exists:paket_pengadaan,nomor_paket',
            'ppk_baru_user_id' => 'required|exists:users,id_user',
            'alasan_perubahan' => 'required|string',
        ]);

        // 2. Pastikan akun tujuan pengalihan memang memiliki peran 'ppk'
        $ppkBaru = User::find($validated['ppk_baru_user_id']);
        if (! $ppkBaru || $ppkBaru->role !== 'ppk') {
            return redirect()->back()->withErrors(['ppk_baru_user_id' => 'Akun tujuan pengalihan harus merupakan salah satu dari 4 akun PPK aktif (NFR 4).']);
        }

        // 3. Mutasi penugasan pada tabel paket_pengadaan
        $paket = PaketPengadaan::findOrFail($validated['nomor_paket']);
        $ppkLama = $paket->id_user_ppk;

        $paket->id_user_ppk = $validated['ppk_baru_user_id'];
        $paket->tanggal_penugasan_ppk = now();
        $paket->alasan_pengalihan_ppk = $validated['alasan_perubahan'];
        $paket->save();

        // 4. Catat riwayat pergantian PPK ke tabel audit riwayat_penugasan_ppk
        RiwayatPenugasanPPK::create([
            'nomor_paket' => $paket->nomor_paket,
            'ppk_lama_user_id' => $ppkLama,
            'ppk_baru_user_id' => $validated['ppk_baru_user_id'],
            'diubah_oleh_user_id' => $user?->id_user ?? User::first()->id_user,
            'alasan_perubahan' => $validated['alasan_perubahan'],
            'tanggal_penugasan' => now(),
        ]);

        // 5. Rekam jejak audit ke tabel audit_log
        AuditLog::catat(
            $user?->id_user ?? User::first()->id_user,
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket->nomor_paket,
            $ppkLama ? (string) $ppkLama : 'belum_ada',
            (string) $validated['ppk_baru_user_id'],
            'Pengalihan tugas PPK paket pengadaan (NFR 4 & FR 7.3): '.$validated['alasan_perubahan']
        );

        return redirect()->route('paket.web')->with('success', 'Penugasan PPK untuk paket '.$paket->nomor_paket.' berhasil dialihkan ke '.$ppkBaru->nama.' (NFR 4)');
    }

    /**
     * Menampilkan antarmuka simulasi pemeriksaan Staf Bidang 2 & keputusan Wadir 2 (FR 5).
     */
    public function tesVerifikasiB2(Request $request): View
    {
        $user = Auth::user();

        // Mengambil daftar permohonan yang berada dalam fase verifikasi / review
        $permohonanList = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel.user'])
            ->whereIn('status_permohonan', [
                PermohonanPengadaan::STATUS_REVIU_PARALEL,
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
            ])
            ->latest('tanggal_pengajuan')
            ->paginate(10);

        return view('tes_verifikasi_b2', compact('user', 'permohonanList'));
    }

    /**
     * Memproses telaah berkas oleh Staf Bidang 2 untuk diteruskan secara resmi ke Wadir 2 (FR 5.1).
     */
    public function teruskanStaffB2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi nomor surat usulan yang akan diteruskan
        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'catatan_pemeriksaan' => 'nullable|string',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        // 2. Rekam jejak telaah Staf Bidang 2 ke audit log institusi
        AuditLog::catat(
            $user?->id_user ?? User::first()->id_user,
            'STAFF_B2_TERUSKAN_KE_WADIR',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            $permohonan->status_permohonan,
            $permohonan->status_permohonan,
            'Staff Bidang 2 memeriksa berkas dan menyampaikan usulan permohonan ke Wadir 2 (FR 5.1): '.($validated['catatan_pemeriksaan'] ?? 'Berkas lengkap')
        );

        return redirect()->route('verifikasi-b2.web')->with('success', 'Permohonan '.$permohonan->nomor_surat.' berhasil disampaikan ke Wadir 2 untuk persetujuan eksekutif (FR 5.1)!');
    }

    /**
     * Memproses keputusan mutlak Wadir 2 (Setuju / Tolak / Revisi) dengan dual-approval cascade (FR 5.2 - FR 5.4).
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // 1. Validasi: jika keputusan ditolak atau revisi, catatan alasan wajib disertakan
        $validated = $request->validate([
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'status_keputusan' => 'required|in:disetujui,ditolak,revisi',
            'catatan_alasan' => 'required_if:status_keputusan,ditolak,revisi|nullable|string',
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan wajib diisi saat Wadir 2 menolak atau meminta revisi usulan (FR 5.3).',
        ]);

        // 2. Ambil atau buat entri verifikasi paralel khusus Wadir 2
        $verifWadir = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $validated['nomor_surat'], 'role_verifikator' => 'wadir_2'],
            ['id_user' => $user?->id_user ?? User::where('role', 'wadir_2')->first()->id_user, 'status_keputusan' => 'menunggu']
        );

        // Perbarui putusan verifikasi Wadir 2
        $verifWadir->id_user = $user?->id_user ?? $verifWadir->id_user;
        $verifWadir->status_keputusan = $validated['status_keputusan'];
        $verifWadir->catatan_alasan = $validated['catatan_alasan'];
        $verifWadir->tanggal_keputusan = now();
        $verifWadir->save();

        // 3. Evaluasi kelulusan verifikasi paralel (Dual approval dengan Bagian Perencanaan)
        $permohonan = PermohonanPengadaan::find($validated['nomor_surat']);
        if ($permohonan) {
            // Jika Wadir 2 menolak, status usulan langsung gugur (ditolak)
            if ($validated['status_keputusan'] === 'ditolak') {
                $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
                $permohonan->save();
            }
            // Jika Wadir 2 menyetujui, periksa apakah Bagian Perencanaan juga sudah menyetujui anggaran
            elseif ($validated['status_keputusan'] === 'disetujui') {
                $perencanaanOk = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
                    ->where('role_verifikator', 'perencanaan')
                    ->where('status_keputusan', 'disetujui')
                    ->exists();

                // Jika kedua pihak telah menyetujui (Dual ACC), otomatis teruskan status ke 'disetujui' untuk PPBJ
                if ($perencanaanOk) {
                    $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                    $permohonan->status_anggaran = 'disetujui';
                    $permohonan->save();

                    // Catat peristiwa persetujuan penuh ke audit log
                    AuditLog::catat(
                        $user?->id_user ?? User::first()->id_user,
                        'PERMOHONAN_DISETUJUI_PENUH_KE_PPBJ',
                        'permohonan_pengadaan',
                        $permohonan->nomor_surat,
                        PermohonanPengadaan::STATUS_REVIU_PARALEL,
                        PermohonanPengadaan::STATUS_DISETUJUI,
                        'Permohonan disetujui penuh oleh Wadir 2 dan Perencanaan, otomatis diteruskan ke PPBJ (FR 5.4)'
                    );
                }
            }
        }

        return redirect()->route('verifikasi-b2.web')->with('success', 'Keputusan Wadir 2 untuk usulan '.$validated['nomor_surat'].' berhasil disimpan dengan status: '.strtoupper($validated['status_keputusan']).' (FR 5.2 - FR 5.4)');
    }

    /**
     * Helper pengunduhan dokumen resmi PDF melalui antarmuka web simulasi (NFR 12).
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        // Mencocokkan jenis berkas ke controller pengunduhan masing-masing
        return match ($jenis) {
            'permohonan' => app(PermohonanPengadaanController::class)->unduhPdf(PermohonanPengadaan::findOrFail($id)),
            'spk' => app(KontrakSPKController::class)->unduhPdf(KontrakSPK::findOrFail($id)),
            'bast' => app(PenerimaanBarangController::class)->unduhPdf(PenerimaanBarang::findOrFail($id)),
            'transfer' => app(TransaksiPencairanController::class)->unduhPdf(TransaksiPencairan::findOrFail($id)),
            default => abort(404, 'Jenis dokumen PDF tidak dikenali.'),
        };
    }
}
