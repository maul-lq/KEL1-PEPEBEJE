<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\ReviuDokumen;
use App\Models\RiwayatPenugasanPPK;
use App\Models\User;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Paket Pengadaan Barang/Jasa (PaketPengadaanController)
 *
 * Mengelola siklus paket pengadaan di Bagian PPBJ sesuai Notulensi Pertemuan PPBJ terbaru:
 * - Registrasi paket baru & penentuan Metode Pengadaan (belanja_langsung, non_tender / e_katalog Inaproc, tender / spse).
 * - Auto-routing 3 jalur nominal:
 *   * <= Rp 50.000.000                => Belanja Langsung (dieksekusi PPBJ, output Kwitansi, tanpa Kontrak/SPK -> Perlengkapan)
 *   * > Rp 50.000.000 s.d. 200.000.000 => Melibatkan PP (Review PP + Input Harga & Vendor -> Approval PPK -> PP Belanja -> PPBJ Upload 1 Dokumen)
 *   * > Rp 200.000.000                => Melibatkan PPK (PPBJ Input Data Lelang -> Approval & Belanja PPK -> PPBJ Upload 1 Dokumen, Tanpa Memo PPBJ)
 * - Isolasi berkas 4 akun PPK (NFR RBAC) & mutasi (reassign) PPK.
 */
class PaketPengadaanController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar paket pengadaan dengan penyaringan otomatis sesuai wewenang peran (NFR RBAC).
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaketPengadaan::with(['permohonan.unit', 'ppbjUser', 'ppkUser', 'ppUser', 'vendor']);

        // 1. Terapkan isolasi berkas: PPK hanya melihat paket miliknya, Pemohon melihat usulannya
        if ($request->user()) {
            $query->forUser($request->user());
        }

        // 2. Filter berdasarkan status paket ('draft', 'proses_pemilihan', 'kontrak_terbit', 'selesai', dll.)
        if ($request->filled('status')) {
            $query->where('status_paket', $request->status);
        }

        // 3. Filter berdasarkan jalur routing pengadaan
        if ($request->filled('jalur_routing')) {
            $query->where('jalur_routing', $request->jalur_routing);
        }

        // 4. Filter berdasarkan jenis pengadaan ('barang', 'konstruksi', 'jasa_konsultansi', 'jasa_lainnya')
        if ($request->filled('jenis_pengadaan')) {
            $query->where('jenis_pengadaan', $request->jenis_pengadaan);
        }

        // 5. Filter spesifik berdasarkan ID akun PPK yang ditugaskan
        if ($request->filled('id_user_ppk')) {
            $query->where('id_user_ppk', $request->id_user_ppk);
        }

        // 6. Pencarian kata kunci pada nomor paket atau nama paket
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_paket', 'like', "%{$search}%")
                    ->orWhere('nama_paket', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('tanggal_registrasi_paket')->paginate(15),
        ]);
    }

    /**
     * Mendaftarkan paket pengadaan baru dengan auto-routing deterministik HPS (FR 7.1 & NFR 4).
     */
    public function store(Request $request): JsonResponse
    {
        // Fallback default input metode & platform jika tidak dikirimkan secara lengkap
        if (! $request->filled('mekanisme_pengadaan')) {
            $request->merge(['mekanisme_pengadaan' => $request->input('metode_pengadaan', 'tender')]);
        }
        if (! $request->filled('platform_pengadaan')) {
            $metodeInput = strtolower((string) $request->input('mekanisme_pengadaan'));
            $platformDefault = match (true) {
                str_contains($metodeInput, 'belanja_langsung') => 'Belanja Langsung',
                str_contains($metodeInput, 'katalog') || str_contains($metodeInput, 'non_tender') => 'Inaproc / E-Katalog',
                default => 'SPSE',
            };
            $request->merge(['platform_pengadaan' => $platformDefault]);
        }
        if (! $request->filled('nilai_hps') && $request->filled('nominal_harga_perkiraan')) {
            $request->merge(['nilai_hps' => $request->input('nominal_harga_perkiraan')]);
        }
        if (! $request->filled('alokasi_pagu_paket')) {
            $request->merge(['alokasi_pagu_paket' => $request->input('nilai_hps', 10000000)]);
        }
        if (! $request->filled('id_user_ppbj')) {
            $ppbjId = $request->input('ppbj_user_id') ?? $request->user()?->id_user ?? User::where('role', 'ppbj')->first()?->id_user ?? User::first()?->id_user;
            $request->merge(['id_user_ppbj' => $ppbjId]);
        }
        if ($request->input('jenis_pengadaan') === 'pekerjaan_konstruksi') {
            $request->merge(['jenis_pengadaan' => 'konstruksi']);
        }

        // 1. Validasi input registrasi paket pengadaan (HPS wajib angka positif > 0)
        $validated = $request->validate([
            'nomor_paket' => 'required|string|max:100|unique:paket_pengadaan,nomor_paket',
            'nama_paket' => 'required|string|max:255',
            'jenis_pengadaan' => 'required|string|in:barang,jasa_lainnya,konstruksi,pekerjaan_konstruksi,jasa_konsultansi',
            'platform_pengadaan' => 'required|string|max:50',
            'mekanisme_pengadaan' => 'required|string|max:50',
            'nilai_hps' => 'required|numeric|gt:0',
            'nominal_harga_perkiraan' => 'nullable|numeric|gt:0',
            'nominal_harga_akhir' => 'nullable|numeric|gt:0',
            'alokasi_pagu_paket' => 'required|numeric|gt:0',
            'file_dokumen_pengadaan' => 'nullable|string|max:255',
            'id_paket_lkpp' => 'nullable|string|max:100',
            'nomor_surat' => 'required|exists:permohonan_pengadaan,nomor_surat',
            'nomor_memo_ppbj' => 'nullable|string|max:100',
            'id_user_ppbj' => 'required|exists:users,id_user',
            'id_user_ppk' => 'nullable|exists:users,id_user',
            'id_user_pp' => 'nullable|exists:users,id_user',
            'npwp_vendor' => 'nullable|exists:vendors,npwp',
        ]);

        // Kalkulasi jalur routing otomatis deterministik 100% dari nilai HPS
        $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        $validated['nominal_harga_perkiraan'] = $validated['nominal_harga_perkiraan'] ?? $validated['nilai_hps'];
        if (! empty($validated['nominal_harga_akhir'])) {
            $validated['nilai_hasil_negosiasi'] = $validated['nominal_harga_akhir'];
        }
        if (! empty($validated['npwp_vendor'])) {
            $validated['tanggal_penunjukan_vendor'] = now();
        }

        // Warisi otomatis id_user_ppk dari permohonan.id_user_ppk_awal jika paket > 50jt dan PPK tidak di-override
        $permohonanAsal = PermohonanPengadaan::find($validated['nomor_surat']);
        if (empty($validated['id_user_ppk']) && $validated['jalur_routing'] !== PaketPengadaan::ROUTING_LANGSUNG && $permohonanAsal?->id_user_ppk_awal) {
            $validated['id_user_ppk'] = $permohonanAsal->id_user_ppk_awal;
        }

        // Untuk jalur 50jt - 200jt, tugaskan otomatis 1 akun PP aktif jika belum diisi
        if (empty($validated['id_user_pp']) && $validated['jalur_routing'] === PaketPengadaan::ROUTING_PP) {
            $validated['id_user_pp'] = User::where('role', 'pp')->where('status_aktif', true)->first()?->id_user;
        }

        // 2. NFR 4: Validasi bahwa pejabat yang ditugaskan sebagai PPK benar-benar memiliki peran 'ppk'
        if (! empty($validated['id_user_ppk'])) {
            $ppkUser = User::find($validated['id_user_ppk']);
            if (! $ppkUser || $ppkUser->role !== 'ppk') {
                return response()->json([
                    'message' => 'Data yang diberikan tidak valid.',
                    'errors' => [
                        'id_user_ppk' => ['User penugasan PPK harus memiliki role PPK aktif (NFR 4).'],
                    ],
                ], 422);
            }
        }

        // 3. NFR 4: Validasi bahwa pejabat yang ditugaskan sebagai PP benar-benar memiliki peran 'pp'
        if (! empty($validated['id_user_pp'])) {
            $ppUser = User::find($validated['id_user_pp']);
            if (! $ppUser || $ppUser->role !== 'pp') {
                return response()->json([
                    'message' => 'Data yang diberikan tidak valid.',
                    'errors' => [
                        'id_user_pp' => ['User penugasan PP harus memiliki role PP aktif (NFR 4).'],
                    ],
                ], 422);
            }
        }

        $validated['tanggal_registrasi_paket'] = now();
        $validated['status_paket'] = 'draft';

        // Aturan bisnis Notulensi PPBJ: Paket > 200 juta (jalur PPK) tidak menggunakan memo pengadaan PPBJ
        if ($validated['jalur_routing'] === PaketPengadaan::ROUTING_PPK) {
            $validated['nomor_memo_ppbj'] = null;
        }

        // Catat tanggal penugasan PPK jika telah dipilih
        if (! empty($validated['id_user_ppk'])) {
            $validated['tanggal_penugasan_ppk'] = now();
            $validated['status_penugasan_ppk'] = 'ditugaskan';
        }

        // Catat tanggal penugasan PP jika telah dipilih
        if (! empty($validated['id_user_pp'])) {
            $validated['tanggal_penugasan_pp'] = now();
        }

        // 5. Simpan data paket pengadaan ke database
        $paket = PaketPengadaan::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil diregistrasi dengan jalur routing: '.$paket->jalur_routing,
            'data' => $paket->load(['permohonan', 'ppbjUser', 'ppkUser', 'ppUser']),
        ], 201);
    }

    /**
     * Menampilkan detail lengkap satu paket pengadaan dengan otorisasi isolasi berkas PPK.
     */
    public function show(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        // Menegakkan isolasi berkas: PPK dilarang melihat paket milik PPK lain (HTTP 403)
        $user = $request->user();
        if ($user && $user->cannot('view', $paket_pengadaan)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Berkas paket pengadaan ini ditugaskan kepada PPK lain (Isolasi Berkas PPK).',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $paket_pengadaan->load([
                'permohonan.unit',
                'ppbjUser',
                'ppkUser',
                'ppUser',
                'vendor.filesLegalitas',
                'reviuDokumen.user',
                'riwayatPpk',
                'kontrakSpk',
                'penerimaanBarang.fotoDokumentasi',
                'pembayaran.memoBayar',
                'reminders',
            ]),
        ]);
    }

    /**
     * Memperbarui informasi paket pengadaan (termasuk negosiasi harga dan vendor pemenang).
     */
    public function update(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        // Otorisasi hak ubah paket
        $user = $request->user();
        if ($user && $user->cannot('update', $paket_pengadaan)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Anda tidak memiliki wewenang untuk memperbarui paket ini.',
            ], 403);
        }

        $validated = $request->validate([
            'nama_paket' => 'sometimes|required|string|max:255',
            'jenis_pengadaan' => 'sometimes|required|string',
            'platform_pengadaan' => 'sometimes|required|string',
            'mekanisme_pengadaan' => 'sometimes|required|string',
            'nilai_hps' => 'sometimes|required|numeric|min:0',
            'nominal_harga_perkiraan' => 'nullable|numeric|min:0',
            'nominal_harga_akhir' => 'nullable|numeric|min:0',
            'alokasi_pagu_paket' => 'sometimes|required|numeric|min:0',
            'file_dokumen_pengadaan' => 'nullable|string|max:255',
            'status_paket' => 'sometimes|required|string',
            'id_paket_lkpp' => 'nullable|string|max:100',
            'nilai_hasil_negosiasi' => 'nullable|numeric|min:0',
            'catatan_verifikasi_pp' => 'nullable|string',
            'npwp_vendor' => 'nullable|exists:vendors,npwp',
        ]);

        // Jika nilai HPS diperbarui, hitung ulang jalur routing otomatisnya
        if (isset($validated['nilai_hps'])) {
            $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        }

        $paket_pengadaan->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil diperbarui',
            'data' => $paket_pengadaan,
        ]);
    }

    /**
     * Eksekusi Belanja Langsung oleh PPBJ untuk paket <= Rp 50.000.000:
     * Output berupa Kwitansi (tanpa Kontrak/SPK/Surat Pesanan) dan opsional jadwal Reminder pengiriman -> lanjut ke Perlengkapan.
     */
    public function belanjaLangsung(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        if ($paket_pengadaan->jalur_routing !== PaketPengadaan::ROUTING_LANGSUNG) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belanja Langsung beroutput Kwitansi hanya berlaku untuk paket bernilai <= Rp 50.000.000.',
                'errors' => [
                    'nomor_paket' => ['Paket bernilai > Rp 50.000.000 wajib melalui alur PP/PPK dan dokumen perikatan.'],
                ],
            ], 422);
        }

        $fileRule = $request->hasFile('file_kwitansi_belanja')
            ? ['nullable', DokumenUploadRule::pdfOrImage()]
            : 'nullable|string|max:255';

        $validated = $request->validate([
            'nomor_kwitansi_belanja' => 'required|string|max:100',
            'file_kwitansi_belanja' => $fileRule,
            'nominal_harga_akhir' => 'nullable|numeric|gt:0|lte:50000000',
            'npwp_vendor' => 'nullable|exists:vendors,npwp',
            'jadwal_pengiriman' => 'nullable|date',
        ]);

        $kwitansiPath = $validated['file_kwitansi_belanja'] ?? 'kwitansi/kwitansi_belanja_dummy.pdf';
        if ($request->hasFile('file_kwitansi_belanja')) {
            $kwitansiPath = $request->file('file_kwitansi_belanja')->store('kwitansi', 'public');
        }

        $operatorId = $request->user()?->id_user ?? $paket_pengadaan->id_user_ppbj;
        $hargaAkhir = (float) ($validated['nominal_harga_akhir'] ?? $paket_pengadaan->nilai_hps);

        $paket_pengadaan->nomor_kwitansi_belanja = $validated['nomor_kwitansi_belanja'];
        $paket_pengadaan->file_kwitansi_belanja = $kwitansiPath;
        $paket_pengadaan->nominal_harga_akhir = $hargaAkhir;
        $paket_pengadaan->nilai_hasil_negosiasi = $hargaAkhir;
        if (! empty($validated['npwp_vendor'])) {
            $paket_pengadaan->npwp_vendor = $validated['npwp_vendor'];
            $paket_pengadaan->tanggal_penunjukan_vendor = now();
        }
        $paket_pengadaan->dieksekusi_belanja_oleh = $operatorId;
        $paket_pengadaan->tanggal_eksekusi_belanja = now();
        $paket_pengadaan->status_paket = PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI;
        $paket_pengadaan->save();

        PengadaanRoutingService::generateBelanjaLangsungReminder($paket_pengadaan, $validated['jadwal_pengiriman'] ?? null);

        AuditLog::catat(
            $operatorId ?? 1,
            'EKSEKUSI_BELANJA_LANGSUNG_KWITANSI',
            'paket_pengadaan',
            $paket_pengadaan->nomor_paket,
            'draft',
            PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI,
            "PPBJ mengeksekusi Belanja Langsung (<= 50jt) dengan output Kwitansi {$paket_pengadaan->nomor_kwitansi_belanja} tanpa Kontrak/SPK"
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Belanja Langsung berhasil dieksekusi dengan output Kwitansi (tanpa Kontrak/SPK) dan diteruskan ke Perlengkapan',
            'data' => $paket_pengadaan->load(['vendor', 'pelaksanaBelanja', 'reminders']),
        ]);
    }

    /**
     * Review kelengkapan dokumen oleh Pejabat Pengadaan (PP) untuk paket > Rp 50 Juta s.d. Rp 200 Juta:
     * PP hanya mereview/verifikasi kelengkapan & menginput Nominal Harga Perkiraan, Nominal Harga Akhir, serta Vendor/Pemenang.
     * PP TIDAK BISA melakukan Approval final (Approval tetap oleh PPK).
     */
    public function reviewPp(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        if ($paket_pengadaan->jalur_routing !== PaketPengadaan::ROUTING_PP) {
            return response()->json([
                'status' => 'error',
                'message' => 'Review Pejabat Pengadaan (PP) hanya berlaku untuk paket > Rp 50.000.000 s.d. Rp 200.000.000.',
                'errors' => [
                    'nomor_paket' => ['Paket ini tidak berada pada jalur Pejabat Pengadaan (50jt - 200jt).'],
                ],
            ], 422);
        }

        if ($request->input('hasil_verifikasi') === 'disetujui' || $request->input('status_reviu') === 'disetujui') {
            return response()->json([
                'status' => 'error',
                'message' => 'Pejabat Pengadaan (PP) tidak memiliki wewenang klik Approval final. Persetujuan tetap dilakukan oleh PPK.',
                'errors' => [
                    'hasil_verifikasi' => ['PP tidak dapat memberikan Approval final; gunakan hasil_verifikasi = lengkap atau perlu_perbaikan.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'hasil_verifikasi' => 'required|string|in:lengkap,perlu_perbaikan',
            'nominal_harga_perkiraan' => 'required_if:hasil_verifikasi,lengkap|nullable|numeric|gt:0',
            'nominal_harga_akhir' => 'required_if:hasil_verifikasi,lengkap|nullable|numeric|gt:0',
            'npwp_vendor' => 'required_if:hasil_verifikasi,lengkap|nullable|exists:vendors,npwp',
            'catatan_verifikasi_pp' => 'required_if:hasil_verifikasi,perlu_perbaikan|nullable|string',
        ], [
            'nominal_harga_perkiraan.required_if' => 'Nominal Harga Perkiraan wajib diisi oleh PP setelah review kelengkapan.',
            'nominal_harga_akhir.required_if' => 'Nominal Harga Akhir wajib diisi oleh PP setelah review kelengkapan.',
            'npwp_vendor.required_if' => 'Vendor / Pemenang wajib dipilih oleh PP setelah review kelengkapan.',
            'catatan_verifikasi_pp.required_if' => 'Catatan perbaikan wajib diisi apabila berkas dinyatakan perlu perbaikan.',
        ]);

        $operatorId = $request->user()?->id_user ?? $paket_pengadaan->id_user_pp ?? 1;
        $nextRound = (ReviuDokumen::where('nomor_paket', $paket_pengadaan->nomor_paket)->max('putaran_ke') ?? 0) + 1;
        $isLengkap = $validated['hasil_verifikasi'] === 'lengkap';

        ReviuDokumen::create([
            'nomor_paket' => $paket_pengadaan->nomor_paket,
            'id_user' => $operatorId,
            'putaran_ke' => $nextRound,
            'role_reviewer' => 'pp',
            'status_reviu' => $isLengkap ? 'terverifikasi_pp' : 'perlu_perbaikan',
            'catatan_perbaikan' => $validated['catatan_verifikasi_pp'] ?? 'Kelengkapan dokumen telah diverifikasi PP, menunggu Approval PPK.',
            'tanggal_reviu' => now(),
        ]);

        if ($isLengkap) {
            $paket_pengadaan->nominal_harga_perkiraan = $validated['nominal_harga_perkiraan'];
            $paket_pengadaan->nominal_harga_akhir = $validated['nominal_harga_akhir'];
            $paket_pengadaan->nilai_hasil_negosiasi = $validated['nominal_harga_akhir'];
            $paket_pengadaan->npwp_vendor = $validated['npwp_vendor'];
            $paket_pengadaan->tanggal_penunjukan_vendor = now();
            $paket_pengadaan->status_paket = PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK;
        } else {
            $paket_pengadaan->status_paket = 'perlu_perbaikan';
        }
        $paket_pengadaan->catatan_verifikasi_pp = $validated['catatan_verifikasi_pp'] ?? null;
        $paket_pengadaan->save();

        AuditLog::catat(
            $operatorId,
            $isLengkap ? 'REVIEW_KELENGKAPAN_PP_SELESAI' : 'REVIEW_KELENGKAPAN_PP_PERBAIKAN',
            'paket_pengadaan',
            $paket_pengadaan->nomor_paket,
            'terdaftar',
            $paket_pengadaan->status_paket,
            'Review kelengkapan PP pada paket 50-200jt: '.($validated['catatan_verifikasi_pp'] ?? 'Harga perkiraan, harga akhir, dan vendor telah diinput; diteruskan ke PPK untuk Approval.')
        );

        if ($isLengkap) {
            $this->notificationService->notifyReviewPpKePpk($paket_pengadaan, $validated['catatan_verifikasi_pp'] ?? null);
        } else {
            $this->notificationService->notifyReviuDokumen($paket_pengadaan, 'perlu_perbaikan', $validated['catatan_verifikasi_pp'] ?? null);
        }

        return response()->json([
            'status' => 'success',
            'message' => $isLengkap
                ? 'Review kelengkapan PP serta input Harga Perkiraan, Harga Akhir, dan Vendor berhasil disimpan. Menunggu Approval PPK.'
                : 'Paket dikembalikan ke PPBJ dengan catatan perbaikan dari PP.',
            'data' => $paket_pengadaan->load(['vendor', 'ppkUser', 'ppUser', 'reviuDokumen']),
        ]);
    }

    /**
     * Eksekusi Kewenangan Belanja sesuai Notulensi Pertemuan PPBJ:
     * - Paket > 50jt s.d. 200jt: Kewenangan belanja berada pada PP (setelah PPK memberikan Approval).
     * - Paket > 200jt: Kewenangan belanja berada pada PPK penanggung jawab paket.
     */
    public function eksekusiBelanja(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        $user = $request->user();

        if ($paket_pengadaan->jalur_routing === PaketPengadaan::ROUTING_LANGSUNG) {
            return response()->json([
                'status' => 'error',
                'message' => 'Paket <= Rp 50 Juta menggunakan endpoint belanja-langsung oleh PPBJ.',
            ], 422);
        }

        if ($paket_pengadaan->jalur_routing === PaketPengadaan::ROUTING_PP) {
            if ($user && ! in_array($user->role, ['pp', 'admin'], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Kewenangan eksekusi belanja untuk paket > Rp 50 Juta s.d. Rp 200 Juta berada pada Pejabat Pengadaan (PP).',
                ], 403);
            }

            $sudahApprovePpk = in_array($paket_pengadaan->status_paket, [
                PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                'reviu_dokumen_disetujui',
            ], true) || ReviuDokumen::where('nomor_paket', $paket_pengadaan->nomor_paket)
                ->where('role_reviewer', 'ppk')
                ->where('status_reviu', 'disetujui')
                ->exists();

            if (! $sudahApprovePpk) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pejabat Pengadaan (PP) baru dapat mengeksekusi belanja setelah PPK memberikan Approval.',
                    'errors' => [
                        'status_paket' => ['Approval PPK belum tercatat untuk paket ini.'],
                    ],
                ], 422);
            }
        } elseif ($paket_pengadaan->jalur_routing === PaketPengadaan::ROUTING_PPK) {
            if ($user && ! in_array($user->role, ['ppk', 'admin'], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Kewenangan eksekusi belanja untuk paket > Rp 200 Juta berada pada PPK.',
                ], 403);
            }
            if ($user && $user->role === 'ppk' && (int) $paket_pengadaan->id_user_ppk !== (int) $user->id_user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses Ditolak: Paket ini ditugaskan kepada akun PPK lain (Isolasi Berkas PPK).',
                ], 403);
            }
        }

        $operatorId = $user?->id_user ?? ($paket_pengadaan->jalur_routing === PaketPengadaan::ROUTING_PP ? $paket_pengadaan->id_user_pp : $paket_pengadaan->id_user_ppk) ?? 1;
        $rolePelaksana = $paket_pengadaan->jalur_routing === PaketPengadaan::ROUTING_PP ? 'pp' : 'ppk';

        $paket_pengadaan->dieksekusi_belanja_oleh = $operatorId;
        $paket_pengadaan->tanggal_eksekusi_belanja = now();
        $paket_pengadaan->status_paket = PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ;
        $paket_pengadaan->save();

        AuditLog::catat(
            $operatorId,
            'EKSEKUSI_KEWENANGAN_BELANJA',
            'paket_pengadaan',
            $paket_pengadaan->nomor_paket,
            'approved_ppk',
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
            'Eksekusi kewenangan belanja oleh '.strtoupper($rolePelaksana).' selesai; diteruskan ke PPBJ untuk upload 1 dokumen perikatan terpilih.'
        );

        $this->notificationService->notifySiapUploadDokumenPpbj($paket_pengadaan, $rolePelaksana);

        return response()->json([
            'status' => 'success',
            'message' => 'Eksekusi kewenangan belanja berhasil dicatat. PPBJ kini dapat mengunggah salah satu dokumen perikatan (Surat Pesanan / SPK / Surat Kontrak).',
            'data' => $paket_pengadaan->load(['pelaksanaBelanja', 'vendor', 'ppkUser', 'ppUser']),
        ]);
    }

    /**
     * Mengalihkan penugasan paket ke akun PPK lain (Reassign PPK) (FR 7.3 & NFR 4).
     * Hanya dapat dilakukan oleh Bagian PPBJ atau Perencanaan.
     */
    public function reassignPpk(Request $request, PaketPengadaan $paket_pengadaan): JsonResponse
    {
        // 1. Otorisasi peran: hanya PPBJ dan Perencanaan yang boleh memutasi tugas PPK
        $user = $request->user();
        if ($user && $user->cannot('reassignPpk', $paket_pengadaan)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak: Hanya PPBJ dan Perencanaan yang dapat mengubah penugasan PPK (FR 7.3).',
            ], 403);
        }

        // 2. Validasi ID akun PPK baru dan alasan pengalihan tugas
        if (! $request->filled('ppk_baru_user_id') && $request->filled('id_user_ppk_baru')) {
            $request->merge(['ppk_baru_user_id' => $request->input('id_user_ppk_baru')]);
        }
        if (! $request->filled('diubah_oleh_user_id') && $user) {
            $request->merge(['diubah_oleh_user_id' => $user->id_user]);
        }

        $validated = $request->validate([
            'ppk_baru_user_id' => 'required|exists:users,id_user',
            'diubah_oleh_user_id' => 'required|exists:users,id_user',
            'alasan_perubahan' => 'required|string',
        ]);

        // Pastikan akun target memang berstatus peran PPK aktif
        $ppkBaru = User::find($validated['ppk_baru_user_id']);
        if (! $ppkBaru || $ppkBaru->role !== 'ppk') {
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => [
                    'ppk_baru_user_id' => ['Akun tujuan pengalihan harus merupakan salah satu dari 4 akun PPK aktif (NFR 4).'],
                ],
            ], 422);
        }

        // 3. Mutasikan penugasan pada record paket pengadaan
        $ppkLama = $paket_pengadaan->id_user_ppk;
        $paket_pengadaan->id_user_ppk = $validated['ppk_baru_user_id'];
        $paket_pengadaan->tanggal_penugasan_ppk = now();
        $paket_pengadaan->alasan_pengalihan_ppk = $validated['alasan_perubahan'];
        $paket_pengadaan->save();

        // 4. Catat riwayat pergantian penugasan ke tabel riwayat_penugasan_ppk
        RiwayatPenugasanPPK::create([
            'nomor_paket' => $paket_pengadaan->nomor_paket,
            'ppk_lama_user_id' => $ppkLama,
            'ppk_baru_user_id' => $validated['ppk_baru_user_id'],
            'diubah_oleh_user_id' => $validated['diubah_oleh_user_id'],
            'alasan_perubahan' => $validated['alasan_perubahan'],
            'tanggal_penugasan' => now(),
        ]);

        // 5. Rekam ke audit log institusi
        AuditLog::catat(
            $validated['diubah_oleh_user_id'],
            'REASSIGN_PPK',
            'paket_pengadaan',
            $paket_pengadaan->nomor_paket,
            $ppkLama ? (string) $ppkLama : 'belum_ada',
            (string) $validated['ppk_baru_user_id'],
            'Pengalihan penugasan PPK paket pengadaan (NFR 4 & FR 7.3): '.$validated['alasan_perubahan']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan PPK berhasil dialihkan dan dicatat pada riwayat',
            'data' => $paket_pengadaan->load(['ppkUser', 'riwayatPpk']),
        ]);
    }

    /**
     * Menghapus paket pengadaan dari database.
     */
    public function destroy(PaketPengadaan $paket_pengadaan): JsonResponse
    {
        $paket_pengadaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket pengadaan berhasil dihapus',
        ]);
    }
}
