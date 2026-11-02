<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Vendor;
use App\Models\VendorFileLegalitas;
use App\Rules\DokumenUploadRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controller Manajemen Direktori Rekanan & Vendor (VendorWebController)
 *
 * Bertanggung jawab melayani fitur operasional master data rekanan/penyedia barang dan jasa khusus role PPBJ:
 * - Menampilkan direktori rekanan terdaftar dengan pencarian multi-kolom, filter keaktifan, dan indikator keterlibatan paket.
 * - Pendaftaran vendor/rekanan baru beserta berkas legalitas awal (NIB/NPWP).
 * - Pembaruan profil perusahaan, kontak PIC, dan rekening bank penyalur pencairan SP2D.
 * - Pengunggahan dan pengelolaan dokumen legalitas tambahan per vendor.
 * - Aktivasi dan penonaktifan status kemitraan rekanan.
 * - Penghapusan data rekanan dengan proteksi pencegahan hapus rekanan yang sudah terikat paket pengadaan.
 */
class VendorWebController extends Controller
{
    /**
     * Menampilkan daftar seluruh rekanan penyedia beserta KPI metrik dan filter.
     */
    public function index(Request $request): View
    {
        $query = Vendor::with(['filesLegalitas', 'paketPengadaan']);

        // Filter status keaktifan rekanan
        if ($request->filled('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Pencarian multi-kolom (nama, npwp, nib, nama PIC, rekening, atau bank)
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('nama_perusahaan', 'like', "%{$q}%")
                    ->orWhere('npwp', 'like', "%{$q}%")
                    ->orWhere('nib', 'like', "%{$q}%")
                    ->orWhere('nama_pic', 'like', "%{$q}%")
                    ->orWhere('email_pic', 'like', "%{$q}%")
                    ->orWhere('nama_bank', 'like', "%{$q}%")
                    ->orWhere('nomor_rekening', 'like', "%{$q}%");
            });
        }

        $vendors = $query->latest('created_at')->paginate(10)->withQueryString();

        $stats = [
            'total_vendor' => Vendor::count(),
            'vendor_aktif' => Vendor::where('is_active', true)->count(),
            'vendor_nonaktif' => Vendor::where('is_active', false)->count(),
            'vendor_dalam_paket' => Vendor::whereHas('paketPengadaan')->count(),
        ];

        return view('vendors.index', compact('vendors', 'stats'));
    }

    /**
     * Menyimpan data pendaftaran rekanan/vendor baru oleh PPBJ.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'npwp' => 'required|string|max:30|unique:vendors,npwp',
            'nib' => 'required|string|max:30|unique:vendors,nib',
            'nama_perusahaan' => 'required|string|max:200',
            'alamat' => 'required|string',
            'is_active' => 'nullable|boolean',
            'nama_pic' => 'required|string|max:100',
            'telepon_pic' => 'required|string|max:20',
            'email_pic' => 'required|email|max:100',
            'nama_bank' => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:150',
            'file_legalitas' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nama_dokumen' => 'nullable|string|max:150',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->is_active : true;

        $vendor = Vendor::create($validated);

        if ($request->hasFile('file_legalitas')) {
            $path = $request->file('file_legalitas')->store('legalitas', 'public');
            VendorFileLegalitas::create([
                'npwp' => $vendor->npwp,
                'nama_dokumen' => $request->nama_dokumen ?: 'Dokumen Legalitas Utama (NIB/NPWP)',
                'file_legalitas' => $path,
                'uploaded_at' => now(),
            ]);
        }

        AuditLog::catat(
            Auth::id(),
            'CREATE',
            'vendors',
            $vendor->npwp,
            null,
            json_encode($vendor->toArray()),
            'Pendaftaran rekanan vendor baru oleh PPBJ'
        );

        return redirect()->route('vendors.index')->with('success', "Rekanan {$vendor->nama_perusahaan} berhasil didaftarkan ke direktori vendor.");
    }

    /**
     * Memperbarui profil, data kontak, atau rekening perbankan rekanan vendor.
     */
    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'nib' => ['required', 'string', 'max:30', Rule::unique('vendors', 'nib')->ignore($vendor->npwp, 'npwp')],
            'nama_perusahaan' => 'required|string|max:200',
            'alamat' => 'required|string',
            'is_active' => 'nullable|boolean',
            'nama_pic' => 'required|string|max:100',
            'telepon_pic' => 'required|string|max:20',
            'email_pic' => 'required|email|max:100',
            'nama_bank' => 'required|string|max:100',
            'nomor_rekening' => 'required|string|max:50',
            'atas_nama' => 'required|string|max:150',
            'file_legalitas_baru' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'nama_dokumen_baru' => 'nullable|string|max:150',
        ]);

        $oldData = $vendor->toArray();
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->is_active : $vendor->is_active;

        $vendor->update($validated);

        if ($request->hasFile('file_legalitas_baru')) {
            $path = $request->file('file_legalitas_baru')->store('legalitas', 'public');
            VendorFileLegalitas::create([
                'npwp' => $vendor->npwp,
                'nama_dokumen' => $request->nama_dokumen_baru ?: 'Dokumen Legalitas Tambahan',
                'file_legalitas' => $path,
                'uploaded_at' => now(),
            ]);
        }

        AuditLog::catat(
            Auth::id(),
            'UPDATE',
            'vendors',
            $vendor->npwp,
            json_encode($oldData),
            json_encode($vendor->toArray()),
            'Pembaruan profil/legalitas rekanan vendor oleh PPBJ'
        );

        return redirect()->route('vendors.index')->with('success', "Data rekanan {$vendor->nama_perusahaan} berhasil diperbarui.");
    }

    /**
     * Mengubah status keaktifan rekanan (Aktif <-> Nonaktif).
     */
    public function toggleStatus(Vendor $vendor): RedirectResponse
    {
        $oldStatus = $vendor->is_active;
        $vendor->is_active = ! $oldStatus;
        $vendor->save();

        AuditLog::catat(
            Auth::id(),
            'UPDATE',
            'vendors',
            $vendor->npwp,
            json_encode(['is_active' => $oldStatus]),
            json_encode(['is_active' => $vendor->is_active]),
            $vendor->is_active ? 'Aktivasi status kemitraan rekanan vendor oleh PPBJ' : 'Penonaktifan rekanan vendor oleh PPBJ'
        );

        $statusText = $vendor->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return redirect()->route('vendors.index')->with('success', "Status rekanan {$vendor->nama_perusahaan} berhasil {$statusText}.");
    }

    /**
     * Menghapus data rekanan dari direktori (hanya jika belum memiliki paket pengadaan).
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        if ($vendor->paketPengadaan()->exists()) {
            return redirect()->route('vendors.index')->with('error', "Rekanan {$vendor->nama_perusahaan} tidak dapat dihapus karena tercatat dalam paket pengadaan institusi. Anda dapat menonaktifkan status keaktifannya.");
        }

        $oldData = $vendor->toArray();

        foreach ($vendor->filesLegalitas as $file) {
            if (Storage::disk('public')->exists($file->file_legalitas)) {
                Storage::disk('public')->delete($file->file_legalitas);
            }
            $file->delete();
        }

        $vendor->delete();

        AuditLog::catat(
            Auth::id(),
            'DELETE',
            'vendors',
            $vendor->npwp,
            json_encode($oldData),
            null,
            'Penghapusan data rekanan vendor dari direktori oleh PPBJ'
        );

        return redirect()->route('vendors.index')->with('success', "Rekanan {$vendor->nama_perusahaan} berhasil dihapus dari direktori.");
    }

    /**
     * Mengunggah dokumen legalitas tambahan untuk rekanan terpilih.
     */
    public function uploadLegalitas(Request $request, Vendor $vendor): RedirectResponse
    {
        $request->validate([
            'nama_dokumen' => 'required|string|max:150',
            'file_legalitas' => ['required', DokumenUploadRule::pdfOrImage()],
        ]);

        $path = $request->file('file_legalitas')->store('legalitas', 'public');
        $file = VendorFileLegalitas::create([
            'npwp' => $vendor->npwp,
            'nama_dokumen' => $request->nama_dokumen,
            'file_legalitas' => $path,
            'uploaded_at' => now(),
        ]);

        AuditLog::catat(
            Auth::id(),
            'UPLOAD',
            'vendor_file_legalitas',
            (string) $file->id,
            null,
            json_encode($file->toArray()),
            "Pengunggahan dokumen legalitas {$file->nama_dokumen} untuk vendor {$vendor->nama_perusahaan}"
        );

        return redirect()->route('vendors.index')->with('success', "Dokumen legalitas '{$file->nama_dokumen}' berhasil diunggah.");
    }

    /**
     * Menghapus satu berkas legalitas rekanan.
     */
    public function destroyLegalitas(VendorFileLegalitas $file): RedirectResponse
    {
        $docName = $file->nama_dokumen;

        if (Storage::disk('public')->exists($file->file_legalitas)) {
            Storage::disk('public')->delete($file->file_legalitas);
        }

        $file->delete();

        AuditLog::catat(
            Auth::id(),
            'DELETE',
            'vendor_file_legalitas',
            (string) $file->id,
            json_encode($file->toArray()),
            null,
            "Penghapusan dokumen legalitas '{$docName}' oleh PPBJ"
        );

        return redirect()->route('vendors.index')->with('success', "Dokumen legalitas '{$docName}' berhasil dihapus.");
    }
}
