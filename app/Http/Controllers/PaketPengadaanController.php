<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaketPengadaan;
use App\Models\RiwayatPenugasanPPK;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller Manajemen Paket Pengadaan Barang/Jasa (PaketPengadaanController)
 *
 * Mengelola siklus paket pengadaan di Bagian PPBJ:
 * - Registrasi paket baru dari usulan permohonan yang telah disetujui (FR 7.1).
 * - Auto-routing 100% deterministik berdasarkan ambang batas HPS positif (NFR 4 & FR 8.0):
 *   * HPS <= 50 juta           => Pengadaan Langsung (PPBJ langsung menerbitkan SPK -> Perlengkapan)
 *   * 50jt < HPS < 200jt       => Pejabat Pengadaan (PP) -> PPK -> PPBJ (SPK) -> Perlengkapan
 *   * HPS >= 200 juta          => Pejabat Pembuat Komitmen (4 akun PPK mandiri) -> PPBJ (SPK) -> Perlengkapan (Tanpa Memo Pengadaan)
 * - Isolasi berkas 4 akun PPK (NFR RBAC).
 * - Alih tugas (reassign) paket ke PPK lain disertai pencatatan riwayat & jejak audit (FR 7.3 & NFR 4).
 */
class PaketPengadaanController extends Controller
{
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
        // Fallback default input jika tidak dikirimkan secara lengkap
        if (! $request->filled('platform_pengadaan')) {
            $request->merge(['platform_pengadaan' => 'SPSE']);
        }
        if (! $request->filled('mekanisme_pengadaan')) {
            $request->merge(['mekanisme_pengadaan' => $request->input('metode_pengadaan', 'Tender')]);
        }
        if (! $request->filled('alokasi_pagu_paket')) {
            $request->merge(['alokasi_pagu_paket' => $request->input('nilai_hps', 10000000)]);
        }
        if (! $request->filled('id_user_ppbj')) {
            $ppbjId = $request->input('ppbj_user_id') ?? User::where('role', 'ppbj')->first()?->id_user ?? User::first()?->id_user;
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
            'nilai_hps' => 'required|numeric|gt:0', // Nilai HPS tidak boleh 0 atau negatif
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

        // 4. NFR 4: Kalkulasi jalur routing otomatis deterministik 100% dari nilai HPS
        $validated['jalur_routing'] = PaketPengadaan::tentukanRouting((float) $validated['nilai_hps']);
        $validated['tanggal_registrasi_paket'] = now();
        $validated['status_paket'] = 'draft';

        // Aturan bisnis: Paket >= 200 juta (jalur PPK) tidak menggunakan memo pengadaan PPBJ
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
