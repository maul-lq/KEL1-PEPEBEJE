<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Models\PengadaanItem;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PengadaanController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $query = Pengadaan::with(['user', 'vendor']);

        // Jika role user biasa, filter milik dia sendiri (kecuali melihat monitoring global)
        if ($user->role === User::ROLE_USER && $request->input('scope') !== 'all') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_pengadaan', 'like', "%{$search}%")
                    ->orWhere('nomor_pengadaan', 'like', "%{$search}%")
                    ->orWhere('nomor_mak', 'like', "%{$search}%")
                    ->orWhere('nomor_spk', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('jalur')) {
            $query->where('jalur_pengadaan', $request->input('jalur'));
        }

        $pengadaans = $query->latest()->paginate(10)->withQueryString();
        $statuses = Pengadaan::STATUS_LABELS;

        return view('pengadaan.index', compact('pengadaans', 'statuses'));
    }

    public function create(): View
    {
        return view('pengadaan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_pengadaan' => ['required', 'string', 'max:255'],
            'jenis_pengadaan' => ['required', 'string'],
            'nomor_surat_user' => ['required', 'string', 'max:100'],
            'tanggal_surat_user' => ['required', 'date'],
            'no_draft_srikandi' => ['nullable', 'string', 'max:100'],
            'latar_belakang' => ['nullable', 'string'],
            'file_surat_permohonan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.spesifikasi' => ['nullable', 'string'],
            'items.*.volume' => ['required', 'numeric', 'min:1'],
            'items.*.satuan' => ['required', 'string', 'max:50'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);

        $filePath = null;
        if ($request->hasFile('file_surat_permohonan')) {
            $file = $request->file('file_surat_permohonan');
            $fileName = time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
            $file->storeAs('surat_permohonan', $fileName, 'public');
            $filePath = 'surat_permohonan/'.$fileName;
        }

        // Hitung total estimasi anggaran dari item-item
        $totalEstimasi = 0;
        foreach ($request->input('items', []) as $item) {
            $totalEstimasi += ((float) $item['volume']) * ((float) $item['harga_satuan']);
        }

        // Generate nomor unik pengadaan format PBJ/YYYY/MM/000
        $year = date('Y');
        $month = date('m');
        $count = Pengadaan::whereYear('created_at', $year)->count() + 1;
        $nomorPengadaan = sprintf('PBJ/%s/%s/%03d', $year, $month, $count);

        $pengadaan = new Pengadaan;
        $pengadaan->nomor_pengadaan = $nomorPengadaan;
        $pengadaan->user_id = Auth::id();
        $pengadaan->nama_pengadaan = $validated['nama_pengadaan'];
        $pengadaan->jenis_pengadaan = $validated['jenis_pengadaan'];
        $pengadaan->latar_belakang = $validated['latar_belakang'] ?? null;
        $pengadaan->estimasi_anggaran = $totalEstimasi;
        $pengadaan->nomor_surat_user = $validated['nomor_surat_user'];
        $pengadaan->tanggal_surat_user = $validated['tanggal_surat_user'];
        $pengadaan->no_draft_srikandi = $validated['no_draft_srikandi'] ?? null;
        $pengadaan->file_surat_permohonan = $filePath;
        $pengadaan->status = Pengadaan::STATUS_DIAJUKAN;

        // Routing jalur otomatis (<50jt, 50-200jt, >200jt)
        PengadaanRoutingService::assignJalur($pengadaan);
        $pengadaan->save();

        // Simpan rincian item
        foreach ($request->input('items', []) as $itemData) {
            $subtotal = ((float) $itemData['volume']) * ((float) $itemData['harga_satuan']);
            PengadaanItem::create([
                'pengadaan_id' => $pengadaan->id,
                'nama_barang' => $itemData['nama_barang'],
                'spesifikasi' => $itemData['spesifikasi'] ?? null,
                'volume' => (int) $itemData['volume'],
                'satuan' => $itemData['satuan'],
                'harga_satuan' => (float) $itemData['harga_satuan'],
                'total_harga' => $subtotal,
            ]);
        }

        // Catat audit log
        AuditLogService::log(
            $pengadaan,
            'Pengajuan Permohonan',
            'User membuat dan mengajukan surat permohonan pengadaan barang dan jasa dengan estimasi total Rp '.number_format($totalEstimasi, 0, ',', '.'),
            null,
            Pengadaan::STATUS_DIAJUKAN
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Permohonan pengadaan berhasil diajukan dan diteruskan ke Wadir 2.');
    }

    public function show(Pengadaan $pengadaan): View
    {
        $pengadaan->load([
            'user',
            'wadir2',
            'perencanaan',
            'ppbj',
            'ppk',
            'perlengkapan',
            'keuangan',
            'vendor',
            'items',
            'logs.user',
        ]);

        return view('pengadaan.show', compact('pengadaan'));
    }

    /**
     * Cetak dokumen pengadaan berformat resmi / PDF (FR 20.0)
     */
    public function cetak(Pengadaan $pengadaan): View
    {
        $pengadaan->load([
            'user',
            'wadir2',
            'perencanaan',
            'ppbj',
            'ppk',
            'perlengkapan',
            'keuangan',
            'vendor',
            'items',
        ]);

        return view('pengadaan.print', compact('pengadaan'));
    }
}
