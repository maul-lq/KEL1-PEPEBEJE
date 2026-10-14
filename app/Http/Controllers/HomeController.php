<?php

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\ReviuDokumen;
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
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller Antarmuka Program Riil Berbasis Peran (HomeController)
 *
 * Mengelola seluruh alur navigasi dan aksi pengguna pada antarmuka web semantik Zero-CSS:
 * - Menangani dashboard operasional dinamis untuk 9 peran institusi PNJ.
 * - Modul usulan permohonan pengadaan & revisi surat dinas Srikandi (User / Jurusan).
 * - Modul telaah administrasi berkas Staf Bidang II dan keputusan eksekutif mutlak Wadir II.
 * - Modul verifikasi anggaran MAK & penguncian pagu DIPA (Subbagian Perencanaan).
 * - Modul registrasi paket pengadaan, auto-routing HPS presisi 100%, dan alih tugas PPK (PPBJ).
 * - Modul penerbitan kontrak SPK untuk Pejabat Pengadaan (&le; 200jt) dan PPK (> 200jt terisolasi).
 * - Modul pemeriksaan fisik barang, upload dokumentasi foto, dan BAST (Perlengkapan).
 * - Modul verifikasi tagihan SPP, pencairan dana SP2D, dan upload slip transfer bank (Keuangan).
 * - Modul monitoring agregat 8 siklus pengadaan & jejak audit (Audit Trail).
 * - Helper pengunduhan dokumen resmi format PDF (< 3 detik).
 */
class HomeController extends Controller
{
    /**
     * Menampilkan dashboard utama operasional adaptif berbasis peran pengguna.
     */
    public function index(): View
    {
        $user = Auth::user();
        if ($user) {
            $user->load('unit');
        }

        // Statistik agregat real-time sistem
        $stats = [
            'total_units' => Unit::count(),
            'total_permohonan' => PermohonanPengadaan::count(),
            'total_anggaran' => AnggaranMAK::sum('pagu_anggaran'),
            'total_paket' => PaketPengadaan::count(),
            'total_vendor' => Vendor::count(),
        ];

        // Daftar 5 usulan permohonan terkini (disaring untuk user pemohon)
        $permohonanQuery = PermohonanPengadaan::with(['unit', 'user']);
        if ($user && $user->role === 'user') {
            $permohonanQuery->where('id_user', $user->id_user);
        }
        $recentPermohonan = $permohonanQuery->latest('tanggal_pengajuan')->take(5)->get();

        // Daftar 5 paket pengadaan terkini (menerapkan isolasi data untuk 4 akun PPK)
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser']);
        if ($user) {
            $paketQuery->forUser($user);
        }
        $recentPaket = $paketQuery->latest('tanggal_registrasi_paket')->take(5)->get();

        // Notifikasi personal pengguna
        $notifications = $user ? NotifikasiUser::with('reminder.paket')
            ->where('id_user', $user->id_user)
            ->where('is_dismissed', false)
            ->latest('id')
            ->take(5)
            ->get() : collect();

        $units = Unit::all();

        return view('dashboard.index', compact('user', 'stats', 'recentPermohonan', 'recentPaket', 'notifications', 'units'));
    }

    // =========================================================================
    // MODUL 1: PERMOHONAN PENGADAAN (USER / PEMOHON JURUSAN)
    // =========================================================================

    /**
     * Menampilkan riwayat usulan permohonan milik user login beserta jejak penolakan.
     */
    public function permohonanIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel.user', 'suratAsalRevisi']);

        if ($user && $user->role === 'user') {
            $query->where('id_user', $user->id_user);
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(15);
        $units = Unit::all();

        return view('permohonan.index', compact('permohonanList', 'user', 'units'));
    }

    /**
     * Menampilkan formulir pendaftaran usulan permohonan pengadaan baru berbasis Srikandi.
     */
    public function permohonanCreate(): View
    {
        $units = Unit::all();

        return view('permohonan.create', compact('units'));
    }

    /**
     * Memproses pengajuan usulan permohonan baru dan menginisialisasi verifikasi paralel.
     */
    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'perihal' => ['required', 'string', 'max:255'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'tanggal_pengajuan' => ['required', 'date'],
            'file_surat_permohonan' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_kak' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'file_spesifikasi_teknis' => ['nullable', 'file', 'mimes:pdf,doc,docx,xlsx,xls', 'max:10240'],
        ]);

        $filePermohonanPath = $request->hasFile('file_surat_permohonan')
            ? $request->file('file_surat_permohonan')->store('permohonan', 'public')
            : null;

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : null;

        $fileSpekPath = $request->hasFile('file_spesifikasi_teknis')
            ? $request->file('file_spesifikasi_teknis')->store('spek', 'public')
            : null;

        // Buat baris data permohonan pengadaan
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'perihal' => $validated['perihal'],
            'anggaran_diajukan' => $validated['anggaran_diajukan'],
            'kode_unit' => $validated['kode_unit'],
            'id_user' => $user->id_user,
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'],
            'file_surat_permohonan' => $filePermohonanPath,
            'file_kak' => $fileKakPath,
            'file_spesifikasi_teknis' => $fileSpekPath,
            'status' => 'diajukan',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
        ]);

        // Inisialisasi entri verifikasi paralel untuk Staf Bidang 2 / Wadir 2 dan Perencanaan
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'staff_bidang_2',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        // Catat ke jejak audit
        AuditLog::catat(
            $user->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            null,
            'diajukan',
            "Pengajuan usulan permohonan pengadaan nomor {$permohonan->nomor_surat} oleh {$user->nama}"
        );

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$permohonan->nomor_surat} berhasil diajukan dan diteruskan ke antrean telaah Bidang II & Perencanaan."
        );
    }

    /**
     * Memproses pengajuan revisi surat usulan permohonan yang sebelumnya ditolak.
     */
    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat_lama' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'perihal' => ['required', 'string', 'max:255'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'keterangan_revisi' => ['nullable', 'string'],
            'file_kak' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : $induk->file_kak;

        $revisi = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'perihal' => $validated['perihal'],
            'anggaran_diajukan' => $validated['anggaran_diajukan'],
            'kode_unit' => $induk->kode_unit,
            'id_user' => $user->id_user,
            'tanggal_pengajuan' => now(),
            'file_surat_permohonan' => $induk->file_surat_permohonan,
            'file_kak' => $fileKakPath,
            'file_spesifikasi_teknis' => $induk->file_spesifikasi_teknis,
            'status' => 'diajukan',
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => ($induk->revisi_ke ?? 0) + 1,
            'nomor_surat_revisi' => $induk->nomor_surat,
            'tanggal_revisi' => now(),
            'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
        ]);

        // Inisialisasi verifikasi baru untuk berkas revisi
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $revisi->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Surat revisi berhasil diterbitkan dengan nomor {$revisi->nomor_surat} (Revisi ke-{$revisi->revisi_ke})."
        );
    }

    // =========================================================================
    // MODUL 2: VERIFIKASI BIDANG 2 & KEPUTUSAN MUTLAK WADIR 2 (FR 5)
    // =========================================================================

    /**
     * Menampilkan antrean telaah kelengkapan administrasi berkas bagi Staf Bidang II.
     */
    public function verifikasiBidang2(): View
    {
        $permohonanMasuk = PermohonanPengadaan::with(['unit', 'user'])
            ->whereIn('status', ['diajukan', 'reviu_paralel'])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.bidang_2', compact('permohonanMasuk'));
    }

    /**
     * Memproses telaah kelengkapan berkas Staf B2 untuk diteruskan ke Wadir 2.
     */
    public function teruskanStaffB2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'catatan_staff' => ['nullable', 'string'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        // Perbarui entri verifikasi paralel Staf B2
        $verifB2 = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'staff_bidang_2'],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifB2->status_keputusan = 'disetujui';
        $verifB2->catatan_alasan = $validated['catatan_staff'] ?? 'Berkas KAK dan spesifikasi teknis lengkap diverifikasi';
        $verifB2->tanggal_keputusan = now();
        $verifB2->save();

        AuditLog::catat(
            $user->id_user,
            'TELAAH_STAFF_B2',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            'diajukan',
            'reviu_paralel',
            'Staff Bidang 2 memeriksa kelengkapan berkas dan meneruskannya ke Wadir 2: '.($validated['catatan_staff'] ?? 'Lengkap')
        );

        return redirect()->route('verifikasi.bidang-2')->with(
            'success',
            "Telaah berkas untuk surat {$permohonan->nomor_surat} berhasil disimpan dan disampaikan ke antrean Wadir 2."
        );
    }

    /**
     * Menampilkan antrean keputusan eksekutif Wakil Direktur II (ACC / Tolak).
     */
    public function verifikasiWadir2(): View
    {
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user', 'verifikasiParalel'])
            ->whereIn('status', ['diajukan', 'reviu_paralel'])
            ->latest('tanggal_pengajuan')
            ->get();

        return view('verifikasi.wadir_2', compact('permohonanAntrean'));
    }

    /**
     * Memproses keputusan mutlak Wadir 2 (Setuju/Tolak) dengan penegakan alasan wajib jika ditolak.
     */
    public function keputusanWadir2(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'keputusan' => ['required', 'in:disetujui,ditolak'],
            'catatan_alasan' => ['required_if:keputusan,ditolak', 'nullable', 'string'],
        ], [
            'catatan_alasan.required_if' => 'Catatan alasan penolakan/revisi wajib diisi saat Wadir 2 menolak usulan (FR 5.3).',
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);

        // Simpan keputusan pada tabel verifikasi_paralel
        $verifWadir = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'wadir_2'],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifWadir->id_user = $user->id_user;
        $verifWadir->status_keputusan = $validated['keputusan'];
        $verifWadir->catatan_alasan = $validated['catatan_alasan'];
        $verifWadir->tanggal_keputusan = now();
        $verifWadir->save();

        if ($validated['keputusan'] === 'ditolak') {
            $permohonan->status = 'ditolak';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DITOLAK;
            $permohonan->catatan_revisi = $validated['catatan_alasan'];
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'WADIR2_TOLAK_USULAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                'reviu_paralel',
                'ditolak',
                "Wadir 2 menolak usulan permohonan dengan alasan: {$validated['catatan_alasan']}"
            );

            return redirect()->route('verifikasi.wadir-2')->with(
                'success',
                "Keputusan penolakan usulan {$permohonan->nomor_surat} telah disimpan dengan alasan resmi."
            );
        }

        // Jika disetujui (ACC), periksa apakah Perencanaan juga telah menyetujui anggaran
        $perencanaanAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', 'perencanaan')
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($perencanaanAcc) {
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'PERSETUJUAN_PENUH_WADIR2_PERENCANAAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                'reviu_paralel',
                'disetujui',
                'Usulan disetujui penuh oleh Wadir 2 dan Perencanaan, otomatis diteruskan ke PPBJ.'
            );

            return redirect()->route('verifikasi.wadir-2')->with(
                'success',
                'Persetujuan Wadir 2 berhasil dicatat. Seluruh pihak telah menyetujui, usulan beralih ke status DISETUJUI dan siap diproses PPBJ.'
            );
        }

        $permohonan->status = 'reviu_paralel';
        $permohonan->save();

        return redirect()->route('verifikasi.wadir-2')->with(
            'success',
            'Persetujuan (ACC) Wadir 2 telah dicatat. Usulan kini menunggu penyelesaian verifikasi anggaran dari Bagian Perencanaan.'
        );
    }

    // =========================================================================
    // MODUL 3: VERIFIKASI ANGGARAN MAK (SUBBAGIAN PERENCANAAN)
    // =========================================================================

    /**
     * Menampilkan antrean verifikasi anggaran MAK & penetapan pagu DIPA.
     */
    public function verifikasiPerencanaan(): View
    {
        $permohonanAntrean = PermohonanPengadaan::with(['unit', 'user'])
            ->whereIn('status', ['diajukan', 'reviu_paralel'])
            ->latest('tanggal_pengajuan')
            ->get();

        $makList = AnggaranMAK::orderBy('kode_mak')->get();

        return view('verifikasi.perencanaan', compact('permohonanAntrean', 'makList'));
    }

    /**
     * Memproses alokasi MAK DIPA dan persetujuan anggaran permohonan.
     */
    public function alokasiMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'nominal_alokasi' => ['required', 'numeric', 'min:1'],
            'keputusan_anggaran' => ['required', 'in:setuju,tahan'],
            'catatan_anggaran' => ['nullable', 'string'],
        ]);

        $permohonan = PermohonanPengadaan::findOrFail($validated['nomor_surat']);
        $mak = AnggaranMAK::findOrFail($validated['kode_mak']);

        if ($validated['keputusan_anggaran'] === 'tahan') {
            $verifPerencanaan = VerifikasiParalel::firstOrCreate(
                ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'perencanaan'],
                ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
            );
            $verifPerencanaan->status_keputusan = 'pending_anggaran';
            $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Pagu anggaran belum tersedia di DIPA kampus (Hold).';
            $verifPerencanaan->tanggal_keputusan = now();
            $verifPerencanaan->save();

            $statusLama = $permohonan->status_permohonan;
            $permohonan->status = 'pending_anggaran';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_PENDING_ANGGARAN;
            $permohonan->status_anggaran = 'hold';
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'HOLD_ANGGARAN_PERMOHONAN',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                $statusLama,
                PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                'Perencanaan menahan (Hold/Pending) usulan karena anggaran belum tersedia (FR 6.2): '.($validated['catatan_anggaran'] ?? 'Anggaran belum siap')
            );

            return redirect()->route('verifikasi.perencanaan')->with(
                'error',
                "Usulan {$permohonan->nomor_surat} ditandai pending anggaran (Hold) oleh Bagian Perencanaan (FR 6.2)."
            );
        }

        // Catat alokasi MAK
        AlokasiMAK::updateOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'kode_mak' => $mak->kode_mak],
            ['nominal_alokasi' => $validated['nominal_alokasi']]
        );

        // Kurangi sisa pagu MAK
        if ($mak->sisa_pagu_mak >= $validated['nominal_alokasi']) {
            $mak->sisa_pagu_mak -= $validated['nominal_alokasi'];
            $mak->save();
        }

        // Catat persetujuan verifikasi paralel Perencanaan
        $verifPerencanaan = VerifikasiParalel::firstOrCreate(
            ['nomor_surat' => $permohonan->nomor_surat, 'role_verifikator' => 'perencanaan'],
            ['id_user' => $user->id_user, 'status_keputusan' => 'menunggu']
        );
        $verifPerencanaan->status_keputusan = 'disetujui';
        $verifPerencanaan->catatan_alasan = $validated['catatan_anggaran'] ?? 'Pagu anggaran DIPA dialokasikan penuh.';
        $verifPerencanaan->tanggal_keputusan = now();
        $verifPerencanaan->save();

        // Evaluasi apakah Wadir 2 juga sudah menyetujui
        $wadirAcc = VerifikasiParalel::where('nomor_surat', $permohonan->nomor_surat)
            ->where('role_verifikator', 'wadir_2')
            ->where('status_keputusan', 'disetujui')
            ->exists();

        if ($wadirAcc) {
            $permohonan->status = 'disetujui';
            $permohonan->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
            $permohonan->save();

            AuditLog::catat(
                $user->id_user,
                'ALOKASI_MAK_DISETUJUI_PENUH',
                'permohonan_pengadaan',
                $permohonan->nomor_surat,
                'reviu_paralel',
                'disetujui',
                "MAK {$mak->kode_mak} berhasil dialokasikan, usulan disetujui penuh dan diteruskan ke PPBJ."
            );

            return redirect()->route('verifikasi.perencanaan')->with(
                'success',
                "MAK {$mak->kode_mak} berhasil dialokasikan. Usulan kini berstatus DISETUJUI dan siap diproses PPBJ."
            );
        }

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "MAK {$mak->kode_mak} berhasil dialokasikan. Menunggu penyelesaian persetujuan eksekutif Wadir II."
        );
    }

    /**
     * Memproses pemecahan (split) permohonan pengadaan berdasarkan pos anggaran MAK (FR 6.3).
     */
    public function splitMakPerencanaan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $nomorInduk = $request->input('nomor_surat_induk');
        if (! $request->filled('nomor_surat_baru') && $nomorInduk) {
            $countChild = PermohonanPengadaan::where('nomor_surat_induk_split', $nomorInduk)->count();
            $request->merge(['nomor_surat_baru' => $nomorInduk.'.'.($countChild + 1)]);
        }
        if (! $request->filled('estimasi_total_pagu') && $request->filled('nominal_split')) {
            $request->merge(['estimasi_total_pagu' => $request->input('nominal_split')]);
        }
        if (! $request->filled('judul_pengadaan') && $nomorInduk) {
            $indukRef = PermohonanPengadaan::find($nomorInduk);
            $judul = ($indukRef?->judul_pengadaan ?? $indukRef?->perihal ?? 'Pecahan Pengadaan').' (Pecahan MAK '.$request->input('kode_mak').')';
            $request->merge(['judul_pengadaan' => $judul]);
        }

        $validated = $request->validate([
            'nomor_surat_induk' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'judul_pengadaan' => ['required', 'string', 'max:255'],
            'kode_mak' => ['required', 'exists:anggaran_mak,kode_mak'],
            'alasan_pemecahan' => ['required', 'string'],
            'estimasi_total_pagu' => ['required', 'numeric', 'min:1'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_induk']);

        // Kurangi pagu permohonan induk
        $nominalSplit = (float) $validated['estimasi_total_pagu'];
        $induk->estimasi_total_pagu = max(0, (float) ($induk->estimasi_total_pagu ?? 0) - $nominalSplit);
        $induk->save();

        $anak = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat_baru'],
            'judul_pengadaan' => $validated['judul_pengadaan'],
            'tujuan_pengadaan_ringkas' => $induk->tujuan_pengadaan_ringkas,
            'file_permohonan' => $induk->file_permohonan,
            'status_permohonan' => PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'status_anggaran' => 'pending',
            'revisi_ke' => 0,
            'estimasi_total_pagu' => $validated['estimasi_total_pagu'],
            'kode_unit' => $induk->kode_unit,
            'id_user' => $induk->id_user,
            'nomor_surat_induk_split' => $induk->nomor_surat,
            'tanggal_pemecahan' => now(),
            'alasan_pemecahan' => $validated['alasan_pemecahan'],
            'kode_mak' => $validated['kode_mak'],
        ]);

        // Inisialisasi verifikasi paralel untuk surat pecahan baru
        VerifikasiParalel::create([
            'id_user' => $anak->id_user,
            'nomor_surat' => $anak->nomor_surat,
            'role_verifikator' => 'perencanaan',
            'status_keputusan' => 'menunggu',
        ]);

        VerifikasiParalel::create([
            'id_user' => $anak->id_user,
            'nomor_surat' => $anak->nomor_surat,
            'role_verifikator' => 'wadir_2',
            'status_keputusan' => 'menunggu',
        ]);

        AuditLog::catat(
            $user->id_user,
            'SPLIT_PERMOHONAN_MAK',
            'permohonan_pengadaan',
            $anak->nomor_surat,
            $induk->nomor_surat,
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'Pemecahan usulan permohonan oleh Perencanaan berdasarkan MAK '.$validated['kode_mak'].' (FR 6.3 & NFR 9): '.$validated['alasan_pemecahan']
        );

        return redirect()->route('verifikasi.perencanaan')->with(
            'success',
            "Permohonan berhasil dipecah menjadi surat baru {$anak->nomor_surat} dengan alokasi MAK {$validated['kode_mak']} (FR 6.3)."
        );
    }

    // =========================================================================
    // MODUL 4: PAKET PENGADAAN & PENUGASAN PP/PPK (KOORDINATOR PPBJ)
    // =========================================================================

    /**
     * Menampilkan daftar paket pengadaan, jalur routing HPS, dan riwayat mutasi PPK.
     */
    public function paketIndex(Request $request): View
    {
        $user = Auth::user();
        $query = PaketPengadaan::with(['permohonan.unit', 'ppkUser', 'ppUser', 'riwayatPpk.ppkBaru']);

        if ($user) {
            $query->forUser($user);
        }

        $paketList = $query->latest('tanggal_registrasi_paket')->paginate(15);
        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $riwayatPenugasan = RiwayatPenugasanPPK::with(['ppkLama', 'ppkBaru', 'userPenetap'])->latest()->take(20)->get();

        return view('paket.index', compact('paketList', 'ppkUsers', 'riwayatPenugasan'));
    }

    /**
     * Menampilkan form pendaftaran paket pengadaan baru.
     */
    public function paketCreate(): View
    {
        $permohonanDisetujui = PermohonanPengadaan::whereIn('status', ['disetujui'])
            ->orWhere('status_permohonan', PermohonanPengadaan::STATUS_DISETUJUI)
            ->get();

        $ppkUsers = User::where('role', 'ppk')->where('status_aktif', true)->get();
        $ppUsers = User::where('role', 'pp')->where('status_aktif', true)->get();

        return view('paket.create', compact('permohonanDisetujui', 'ppkUsers', 'ppUsers'));
    }

    /**
     * Memproses penyimpanan paket pengadaan baru dengan auto-routing HPS presisi 100%.
     */
    public function storePaket(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required', 'string', 'max:100', 'unique:paket_pengadaan,kode_paket', 'unique:paket_pengadaan,nomor_paket'],
            'nama_paket' => ['required', 'string', 'max:255'],
            'nomor_surat' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'jenis_pengadaan' => ['required', 'string'],
            'metode_pengadaan' => ['required', 'string'],
            'nilai_hps' => ['required', 'numeric', 'gt:0'],
            'id_user_ppk' => ['nullable', 'exists:users,id_user'],
            'id_user_pp' => ['nullable', 'exists:users,id_user'],
        ]);

        $hps = (float) $validated['nilai_hps'];
        $jalur = PaketPengadaan::tentukanRouting($hps);

        // Validasi penugasan PPK jika nilai > 200 juta
        if ($jalur === 'ppk_diatas_200jt' && empty($validated['id_user_ppk'])) {
            return back()->withErrors(['id_user_ppk' => 'Nilai HPS di atas Rp 200 Juta wajib menugaskan salah satu dari 4 akun PPK (NFR 4).'])->withInput();
        }

        $paket = PaketPengadaan::create([
            'kode_paket' => $validated['kode_paket'],
            'nomor_paket' => $validated['kode_paket'],
            'nama_paket' => $validated['nama_paket'],
            'nomor_surat' => $validated['nomor_surat'],
            'jenis_pengadaan' => Str::lower(str_replace(' ', '_', $validated['jenis_pengadaan'])),
            'platform_pengadaan' => 'SPSE/Inaproc',
            'mekanisme_pengadaan' => $validated['metode_pengadaan'],
            'metode_pengadaan' => $validated['metode_pengadaan'],
            'nilai_hps' => $hps,
            'alokasi_pagu_paket' => $hps,
            'jalur_routing' => $jalur,
            'id_user_ppbj' => $user->id_user,
            'id_user_ppk' => $validated['id_user_ppk'] ?? null,
            'id_user_pp' => $validated['id_user_pp'] ?? null,
            'tanggal_registrasi_paket' => now(),
            'status_paket' => 'terdaftar',
        ]);

        AuditLog::catat(
            $user->id_user,
            'REGISTRASI_PAKET',
            'paket_pengadaan',
            $paket->kode_paket,
            null,
            'terdaftar',
            "PPBJ mendaftarkan paket {$paket->kode_paket} HPS Rp ".number_format($hps)." jalur routing {$jalur}"
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Paket pengadaan {$paket->kode_paket} berhasil diterbitkan dengan jalur routing: ".strtoupper(str_replace('_', ' ', $jalur)).'.'
        );
    }

    /**
     * Memproses alih tugas (reassign) paket ke PPK lain (NFR 4.2).
     */
    public function reassignPpkWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'id_user_ppk_baru' => ['required', 'exists:users,id_user'],
            'alasan_pengalihan' => ['required', 'string'],
        ]);

        $paket = PaketPengadaan::where('kode_paket', $validated['kode_paket'])
            ->orWhere('nomor_paket', $validated['kode_paket'])
            ->firstOrFail();

        $ppkBaru = User::findOrFail($validated['id_user_ppk_baru']);
        if ($ppkBaru->role !== 'ppk') {
            return back()->withErrors(['id_user_ppk_baru' => 'User tujuan mutasi harus berstatus akun PPK.']);
        }

        $ppkLama = $paket->id_user_ppk;
        $paket->id_user_ppk = $ppkBaru->id_user;
        $paket->save();

        RiwayatPenugasanPPK::create([
            'kode_paket' => $paket->kode_paket,
            'nomor_paket' => $paket->nomor_paket,
            'id_ppk_lama' => $ppkLama,
            'ppk_lama_user_id' => $ppkLama,
            'id_ppk_baru' => $ppkBaru->id_user,
            'ppk_baru_user_id' => $ppkBaru->id_user,
            'id_user_penetap' => $user->id_user,
            'diubah_oleh_user_id' => $user->id_user,
            'alasan_pengalihan' => $validated['alasan_pengalihan'],
            'alasan_perubahan' => $validated['alasan_pengalihan'],
            'tanggal_penugasan' => now(),
        ]);

        AuditLog::catat(
            $user->id_user,
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket->kode_paket,
            (string) $ppkLama,
            (string) $ppkBaru->id_user,
            "Alih tugas PPK paket {$paket->kode_paket} ke {$ppkBaru->nama}: {$validated['alasan_pengalihan']}"
        );

        return redirect()->route('paket.index')->with(
            'success',
            "Penugasan paket {$paket->kode_paket} berhasil dialihkan ke PPK {$ppkBaru->nama}."
        );
    }

    // =========================================================================
    // MODUL 5: KONTRAK SPK (PEJABAT PENGADAAN & 4 AKUN PPK TERISOLASI)
    // =========================================================================

    /**
     * Menampilkan daftar paket penugasan dan penerbitan Kontrak SPK.
     */
    public function kontrakIndex(): View
    {
        $user = Auth::user();
        $paketQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser']);

        if ($user) {
            $paketQuery->forUser($user);
        }

        $paketList = $paketQuery->get();
        $kontrakList = KontrakSPK::with(['paket', 'vendor'])->latest()->paginate(15);
        $vendors = Vendor::all();

        // Ambil paket yang siap atau dalam proses reviu dokumen oleh PP/PPK (FR 9)
        $reviuQuery = PaketPengadaan::with(['permohonan', 'ppkUser', 'ppUser', 'reviuDokumen']);
        if ($user) {
            $reviuQuery->forUser($user);
        }
        $paketReviu = $reviuQuery->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan', 'reviu_dokumen_disetujui'])->get();

        return view('kontrak.index', compact('paketList', 'kontrakList', 'vendors', 'paketReviu'));
    }

    /**
     * Memproses penerbitan dan penandatanganan Kontrak SPK.
     */
    public function storeKontrak(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kode_paket' => ['required'],
            'id_vendor' => ['required', 'exists:vendors,npwp'],
            'nomor_kontrak_spk' => ['required', 'string', 'max:100', 'unique:kontrak_spk,nomor_spk'],
            'nilai_kontrak' => ['required', 'numeric', 'min:1'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'catatan_spk' => ['nullable', 'string'],
            'file_spk' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        $paket = PaketPengadaan::where('kode_paket', $validated['kode_paket'])
            ->orWhere('nomor_paket', $validated['kode_paket'])
            ->firstOrFail();

        // Hubungkan rekanan ke paket pengadaan
        $paket->npwp_vendor = $validated['id_vendor'];

        // Simpan dokumen kontrak/SPK jika diunggah (FR 11.1 & NFR 11)
        $fileSpkPath = 'spk/spk_'.$validated['nomor_kontrak_spk'].'.pdf';
        if ($request->hasFile('file_spk')) {
            $fileSpkPath = $request->file('file_spk')->store('spk', 'public');
        }

        $kontrak = KontrakSPK::create([
            'nomor_spk' => $validated['nomor_kontrak_spk'],
            'nomor_kontrak_spk' => $validated['nomor_kontrak_spk'],
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'tipe_dokumen' => 'SPK',
            'sifat_kontrak' => 'lump_sum',
            'status_spk' => 'aktif',
            'tanggal_spk' => now(),
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jadwal_pengiriman' => $validated['tanggal_selesai'],
            'nilai_kontrak' => $validated['nilai_kontrak'],
            'file_spk' => $fileSpkPath,
        ]);

        $paket->status_paket = 'spk_terbit';
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            'TERBITKAN_SPK',
            'kontrak_spk',
            $kontrak->nomor_spk,
            null,
            'aktif',
            "Penerbitan Kontrak SPK nomor {$kontrak->nomor_spk} nilai Rp ".number_format($kontrak->nilai_kontrak).' serta penguncian permanen MAK (FR 11 & NFR 6)'
        );

        return redirect()->route('kontrak.index')->with(
            'success',
            "Kontrak SPK nomor {$kontrak->nomor_spk} berhasil diterbitkan (MAK telah dikunci permanen) dan siap serah terima barang."
        );
    }

    /**
     * Memproses reviu dokumen pengadaan oleh PP/PPK dari antarmuka Zero CSS (FR 9).
     */
    public function storeReviuWeb(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'status_reviu' => ['required', 'in:disetujui,perlu_perbaikan'],
            'catatan_perbaikan' => ['required_if:status_reviu,perlu_perbaikan', 'nullable', 'string'],
        ], [
            'catatan_perbaikan.required_if' => 'Catatan perbaikan wajib diisi saat meminta perbaikan dokumen pengadaan (FR 9.3).',
        ]);

        $paket = PaketPengadaan::where('nomor_paket', $validated['nomor_paket'])->firstOrFail();

        // NFR RBAC: Penegakan isolasi berkas 4 akun PPK
        if ($user->role === 'ppk' && (int) $paket->id_user_ppk !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak berhak mereviu paket yang ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        $nextRound = (ReviuDokumen::where('nomor_paket', $paket->nomor_paket)->max('putaran_ke') ?? 0) + 1;

        $reviu = ReviuDokumen::create([
            'nomor_paket' => $paket->nomor_paket,
            'id_user' => $user->id_user,
            'putaran_ke' => $nextRound,
            'role_reviewer' => $user->role === 'pp' ? 'pp' : 'ppk',
            'status_reviu' => $validated['status_reviu'],
            'catatan_perbaikan' => $validated['catatan_perbaikan'],
            'tanggal_reviu' => now(),
        ]);

        if ($validated['status_reviu'] === 'disetujui') {
            $paket->status_paket = 'reviu_dokumen_disetujui';
        } else {
            $paket->status_paket = 'perlu_perbaikan';
        }
        $paket->save();

        AuditLog::catat(
            $user->id_user,
            $validated['status_reviu'] === 'disetujui' ? 'REVIU_DOKUMEN_DISETUJUI' : 'REVIU_DOKUMEN_PERBAIKAN',
            'paket_pengadaan',
            $paket->nomor_paket,
            $paket->status_paket,
            $paket->status_paket,
            'Penelaahan dokumen oleh '.strtoupper($user->role).' (FR 9): '.($validated['catatan_perbaikan'] ?? 'Disetujui untuk SPK')
        );

        $msg = $validated['status_reviu'] === 'disetujui'
            ? "Reviu dokumen paket {$paket->nomor_paket} disetujui. Paket siap diproses penerbitan SPK (FR 9.2)."
            : "Reviu dokumen paket {$paket->nomor_paket} dikembalikan ke PPBJ dengan catatan perbaikan (FR 9.3).";

        return redirect()->route('kontrak.index')->with('success', $msg);
    }

    // =========================================================================
    // MODUL 6: PENERIMAAN BARANG & BAST (UNIT PERLENGKAPAN / BMN)
    // =========================================================================

    /**
     * Menampilkan antrean pemeriksaan fisik barang dan daftar BAST.
     */
    public function penerimaanIndex(): View
    {
        $kontrakList = KontrakSPK::with(['paket', 'vendor'])->whereIn('status_spk', ['aktif', 'terbit'])->get();
        $bastList = PenerimaanBarang::with(['kontrak', 'paket'])->latest()->paginate(15);

        return view('penerimaan.index', compact('kontrakList', 'bastList'));
    }

    /**
     * Memproses pemeriksaan fisik barang, upload foto dokumentasi, dan penerbitan BAST bertoken HMAC.
     */
    public function storePenerimaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_kontrak_spk' => ['required', 'exists:kontrak_spk,nomor_spk'],
            'nomor_bast' => ['required', 'string', 'max:100', 'unique:penerimaan_barang,nomor_bast'],
            'tanggal_terima' => ['required', 'date'],
            'kondisi_barang' => ['required', 'string'],
            'foto_barang' => ['required', 'file', 'max:10240'],
            'catatan_pemeriksaan' => ['nullable', 'string'],
        ]);

        $spk = KontrakSPK::findOrFail($validated['nomor_kontrak_spk']);
        $fotoPath = $request->file('foto_barang')->store('dokumentasi_barang', 'public');

        // Buat token HMAC untuk verifikasi hukum keaslian BAST (NFR 12)
        $tokenHmac = hash_hmac('sha256', $validated['nomor_bast'].'|'.$spk->nomor_spk.'|'.now(), config('app.key'));

        $penerimaan = PenerimaanBarang::create([
            'nomor_bast' => $validated['nomor_bast'],
            'nomor_paket' => $spk->nomor_paket,
            'id_user' => $user->id_user,
            'tanggal_penerimaan' => $validated['tanggal_terima'],
            'status_fisik' => $validated['kondisi_barang'],
            'kondisi_barang' => $validated['kondisi_barang'],
            'ttd_digital_bast' => $tokenHmac,
            'file_bast_signed' => $fotoPath,
            'catatan_pemeriksaan' => $validated['catatan_pemeriksaan'],
            'waktu_selesai_inspeksi' => now(),
        ]);

        // Buat entri tagihan Pembayaran siap cair untuk Bagian Keuangan
        Pembayaran::firstOrCreate(
            ['nomor_spk' => $spk->nomor_spk],
            [
                'id_user' => $user->id_user,
                'status_pembayaran' => 'siap_bayar',
                'nominal_tagihan' => $spk->nilai_kontrak,
                'keterangan' => 'Tagihan serah terima BAST '.$validated['nomor_bast'],
            ]
        );

        AuditLog::catat(
            $user->id_user,
            'TERBITKAN_BAST',
            'penerimaan_barang',
            $penerimaan->nomor_bast,
            null,
            'terbit',
            "Unit Perlengkapan menerbitkan BAST {$penerimaan->nomor_bast} token {$tokenHmac}"
        );

        return redirect()->route('penerimaan.index')->with(
            'success',
            "Berita Acara Serah Terima (BAST) nomor {$penerimaan->nomor_bast} berhasil diterbitkan dan tagihan diteruskan ke Keuangan."
        );
    }

    // =========================================================================
    // MODUL 7: PENCAIRAN DANA & BUKTI TRANSFER (BAGIAN KEUANGAN)
    // =========================================================================

    /**
     * Menampilkan antrean berkas tagihan BAST siap cair dan riwayat pencairan dana.
     */
    public function pembayaranIndex(): View
    {
        $bastSiapBayar = PenerimaanBarang::with(['kontrak.paket'])->latest()->get();
        $transaksiList = TransaksiPencairan::with(['user'])->latest()->paginate(15);

        return view('pembayaran.index', compact('bastSiapBayar', 'transaksiList'));
    }

    /**
     * Memproses eksekusi pencairan dana SP2D dan upload slip bukti transfer bank.
     */
    public function storePembayaran(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'nomor_bast' => ['required', 'exists:penerimaan_barang,nomor_bast'],
            'nomor_transaksi' => ['required', 'string', 'max:100', 'unique:transaksi_pencairan,nomor_transaksi_bank'],
            'tanggal_cair' => ['required', 'date'],
            'nominal_cair' => ['required', 'numeric', 'min:1'],
            'bank_tujuan' => ['required', 'string'],
            'file_bukti_transfer' => ['required', DokumenUploadRule::pdfOnly()],
            'keterangan_cair' => ['nullable', 'string'],
        ]);

        $filePath = $request->file('file_bukti_transfer')->store('bukti_transfer', 'public');
        $bast = PenerimaanBarang::where('nomor_bast', $validated['nomor_bast'])->firstOrFail();

        // Cari atau buat entri pembayaran
        $pembayaran = Pembayaran::firstOrCreate(
            ['nomor_spk' => $bast->nomor_paket],
            ['id_user' => $user->id_user, 'status_pembayaran' => 'lunas', 'nominal_tagihan' => $validated['nominal_cair']]
        );

        $transaksi = TransaksiPencairan::create([
            'nomor_transaksi_bank' => $validated['nomor_transaksi'],
            'nomor_pembayaran' => $pembayaran->id_pembayaran ?? 1,
            'id_user' => $user->id_user,
            'tanggal_transfer' => $validated['tanggal_cair'],
            'nominal_transfer' => $validated['nominal_cair'],
            'file_bukti_transfer' => $filePath,
            'catatan_keuangan' => $validated['keterangan_cair'].' [Bank: '.$validated['bank_tujuan'].']',
        ]);

        // Selesaikan seluruh siklus pengadaan terkait
        $paket = PaketPengadaan::where('nomor_paket', $bast->nomor_paket)->first();
        if ($paket) {
            $paket->status_paket = 'selesai';
            $paket->save();

            if ($paket->permohonan) {
                $paket->permohonan->status = 'selesai';
                $paket->permohonan->status_permohonan = PermohonanPengadaan::STATUS_SELESAI;
                $paket->permohonan->save();
            }
        }

        AuditLog::catat(
            $user->id_user,
            'PENCAIRAN_DANA_SP2D',
            'transaksi_pencairan',
            $transaksi->nomor_transaksi_bank,
            null,
            'lunas',
            "Keuangan mencairkan dana SP2D {$transaksi->nomor_transaksi_bank} sebesar Rp ".number_format($transaksi->nominal_transfer)
        );

        return redirect()->route('pembayaran.index')->with(
            'success',
            "Pencairan dana SP2D nomor {$transaksi->nomor_transaksi_bank} berhasil dieksekusi. Seluruh siklus pengadaan telah selesai 100%!"
        );
    }

    // =========================================================================
    // MODUL 8: MONITORING 8 SIKLUS PENGADAAN & AUDIT TRAIL
    // =========================================================================

    /**
     * Menampilkan agregat monitoring siklus pengadaan dan tabel jejak audit (Audit Trail).
     */
    public function monitoringIndex(Request $request): View
    {
        $query = PermohonanPengadaan::with(['unit', 'paket.kontrak.penerimaan']);

        $searchKey = $request->input('q') ?? $request->input('nomor_surat');
        if (! empty($searchKey)) {
            $query->where(function ($q) use ($searchKey) {
                $q->where('nomor_surat', 'like', '%'.$searchKey.'%')
                    ->orWhere('judul_pengadaan', 'like', '%'.$searchKey.'%');
            });
        }

        if ($request->filled('unit')) {
            $query->where('kode_unit', $request->unit);
        }

        if ($request->filled('status')) {
            $query->where('status_permohonan', $request->status);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_pengajuan', $request->tahun);
        }

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_pengajuan', $request->bulan);
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(20);
        $auditLogs = AuditLog::with('user')->latest()->take(25)->get();
        $units = Unit::all();

        return view('monitoring.index', compact('permohonanList', 'auditLogs', 'units'));
    }

    // =========================================================================
    // HELPER: STREAMING UNDUH PDF RESMI (< 3 DETIK - NFR 12)
    // =========================================================================

    /**
     * Menyajikan streaming dokumen PDF resmi dengan respon cepat < 3 detik.
     */
    public function unduhDokumenPdf(Request $request, string $jenis, string $id): Response
    {
        return match ($jenis) {
            'permohonan' => app(PermohonanPengadaanController::class)->unduhPdf(PermohonanPengadaan::findOrFail($id)),
            'spk' => app(KontrakSPKController::class)->unduhPdf(KontrakSPK::findOrFail($id)),
            'bast' => app(PenerimaanBarangController::class)->unduhPdf(PenerimaanBarang::where('nomor_bast', $id)->firstOrFail()),
            'transfer' => app(TransaksiPencairanController::class)->unduhPdf(TransaksiPencairan::where('nomor_transaksi_bank', $id)->firstOrFail()),
            default => abort(404, 'Dokumen PDF tidak ditemukan.'),
        };
    }
}
