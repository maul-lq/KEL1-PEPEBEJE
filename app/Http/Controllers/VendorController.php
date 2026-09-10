<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $query = Vendor::withCount('pengadaans');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%")
                    ->orWhere('nama_kontak', 'like', "%{$search}%");
            });
        }

        $vendors = $query->latest()->paginate(10)->withQueryString();

        return view('vendor.index', compact('vendors'));
    }

    public function create(): View
    {
        return view('vendor.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_perusahaan' => ['required', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'nib' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'nama_kontak' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'nama_bank' => ['nullable', 'string', 'max:50'],
            'nomor_rekening' => ['nullable', 'string', 'max:50'],
            'nama_rekening' => ['nullable', 'string', 'max:100'],
            'file_legalitas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $filePath = null;
        if ($request->hasFile('file_legalitas')) {
            $file = $request->file('file_legalitas');
            $fileName = time().'_legalitas_'.Str::slug($validated['nama_perusahaan']).'.'.$file->getClientOriginalExtension();
            $file->storeAs('legalitas_vendor', $fileName, 'public');
            $filePath = 'legalitas_vendor/'.$fileName;
        }

        $validated['file_legalitas'] = $filePath;
        $validated['is_active'] = true;

        Vendor::create($validated);

        return redirect()->route('vendor.index')
            ->with('success', 'Data penyedia/vendor beserta kelengkapan berkas berhasil disimpan.');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load(['pengadaans' => function ($q) {
            $q->latest();
        }]);

        return view('vendor.show', compact('vendor'));
    }
}
