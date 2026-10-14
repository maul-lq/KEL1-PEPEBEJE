<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorFileLegalitas;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller Manajemen Rekanan / Vendor (Vendor Controller)
 *
 * Bertanggung jawab mengelola basis data penyedia barang dan jasa (vendor/rekanan) dalam sistem SIM-PBJ.
 * Mengakomodasi pencatatan identitas resmi perusahaan (NPWP, NIB), data PIC kontak, informasi rekening
 * perbankan untuk pencairan keuangan, serta pengunggahan dan pengarsipan berkas legalitas fisik/digital
 * rekanan sesuai aturan validasi dokumen (PDF atau citra gambar maksimal 10MB).
 */
class VendorController extends Controller
{
    /**
     * Menampilkan daftar penyedia/vendor dengan paginasi dan filter.
     *
     * Mendukung pemfilteran status keaktifan (is_active) serta pencarian
     * teks bebas pada nama perusahaan, NPWP, atau NIB penyedia.
     *
     * @param  Request  $request  Objek HTTP request berisi filter status dan kata kunci pencarian
     * @return JsonResponse Koleksi data vendor terpaginasi beserta dokumen legalitas terkait
     */
    public function index(Request $request): JsonResponse
    {
        // Eager load relasi berkas legalitas untuk meminimalisasi beban query (N+1 prevention)
        $query = Vendor::with('filesLegalitas');

        // Filter berdasarkan status keaktifan vendor (aktif / non-aktif)
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Pencarian parsial berdasarkan nama perusahaan, nomor NPWP, atau nomor NIB
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%");
            });
        }

        // Terapkan paginasi 15 data per halaman
        $vendors = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $vendors,
        ]);
    }

    /**
     * Mendaftarkan vendor/rekanan baru beserta berkas legalitas pertamanya.
     *
     * Memvalidasi keunikan NPWP dan NIB, kontak perwakilan (PIC), data rekening bank,
     * serta menyimpan berkas fisik legalitas ke storage publik (atau jalur URI yang diberikan).
     *
     * @param  Request  $request  Objek HTTP request berisi data form vendor dan lampiran berkas
     * @return JsonResponse Data lengkap vendor baru beserta status HTTP 201 Created
     */
    public function store(Request $request): JsonResponse
    {
        // Validasi keaslian, format, dan batasan ekstensi berkas legalitas
        $validated = $request->validate([
            'npwp' => 'required|string|max:30|unique:vendors,npwp',
            'nib' => 'required|string|max:30|unique:vendors,nib',
            'nama_perusahaan' => 'required|string|max:200',
            'alamat' => 'required|string',
            'is_active' => 'boolean',
            'nama_pic' => 'required|string|max:100',
            'telepon_pic' => 'required|string|max:20',
            'email_pic' => 'required|email|max:100',
            'nama_bank' => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:150',
            'file_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nama_dokumen' => 'nullable|string|max:150',
        ]);

        // Simpan data pokok vendor ke tabel vendors
        $vendor = Vendor::create($validated);

        // Penanganan penyimpanan berkas legalitas fisik jika diunggah dalam bentuk file multipart
        if ($request->hasFile('file_legalitas')) {
            $path = $request->file('file_legalitas')->store('legalitas', 'public');
            VendorFileLegalitas::create([
                'npwp' => $vendor->npwp,
                'nama_dokumen' => $request->nama_dokumen ?? 'Dokumen Legalitas Utama',
                'file_legalitas' => $path,
            ]);
        } elseif ($request->filled('file_legalitas')) {
            // Penanganan jika berkas dikirimkan sebagai path/string referensi dokumen
            VendorFileLegalitas::create([
                'npwp' => $vendor->npwp,
                'nama_dokumen' => $request->nama_dokumen ?? 'Dokumen Legalitas Utama',
                'file_legalitas' => $request->file_legalitas,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil ditambahkan',
            'data' => $vendor->load('filesLegalitas'),
        ], 201);
    }

    /**
     * Menampilkan profil terperinci suatu vendor beserta berkas legalitas dan paket pengadaan yang pernah dikerjakan.
     *
     * @param  Vendor  $vendor  Model binding penyedia barang/jasa
     * @return JsonResponse Data profil vendor, daftar berkas legalitas, dan histori keterlibatan paket
     */
    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $vendor->load(['filesLegalitas', 'paketPengadaan']),
        ]);
    }

    /**
     * Memperbarui informasi data profil, PIC, atau rekening perbankan vendor.
     *
     * @param  Request  $request  Objek HTTP request berisi data pembaruan
     * @param  Vendor  $vendor  Model binding penyedia yang akan diperbarui
     * @return JsonResponse Data vendor yang telah diperbarui
     */
    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        // Validasi data masukan dengan pengecualian nomor NIB milik vendor itu sendiri
        $validated = $request->validate([
            'nib' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('vendors', 'nib')->ignore($vendor->npwp, 'npwp')],
            'nama_perusahaan' => 'sometimes|required|string|max:200',
            'alamat' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
            'nama_pic' => 'sometimes|required|string|max:100',
            'telepon_pic' => 'sometimes|required|string|max:20',
            'email_pic' => 'sometimes|required|email|max:100',
            'nama_bank' => 'sometimes|required|string|max:100',
            'nomor_rekening' => 'sometimes|required|string|max:50',
            'atas_nama' => 'sometimes|required|string|max:150',
        ]);

        // Simpan perubahan profil vendor
        $vendor->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil diperbarui',
            'data' => $vendor->load('filesLegalitas'),
        ]);
    }

    /**
     * Menghapus data vendor dari sistem.
     *
     * @param  Vendor  $vendor  Model binding vendor yang akan dihapus
     * @return JsonResponse Pesan konfirmasi penghapusan
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        $vendor->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor berhasil dihapus',
        ]);
    }
}
