<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PenerimaanPerlengkapanController extends Controller
{
    public function showPenerimaan(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'vendor', 'items']);

        return view('roles.perlengkapan.penerimaan', compact('pengadaan'));
    }

    public function storePenerimaan(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'jenis_belanja' => ['required', 'string', 'in:Belanja Barang,Belanja Modal'],
            'status_penerimaan' => ['required', 'string', 'in:Terima,Pending'],
            'nama_penerima' => ['required', 'string', 'max:255'],
            'unit_penerima' => ['required', 'string', 'max:255'],
            'catatan_penerimaan' => ['nullable', 'string'],
            'foto_dokumentasi_barang' => ['nullable', 'image', 'max:10240'],
            'ttd_digital' => ['required', 'string'], // Data URL atau string signature
        ]);

        $photoPath = $pengadaan->foto_dokumentasi_barang;
        if ($request->hasFile('foto_dokumentasi_barang')) {
            $file = $request->file('foto_dokumentasi_barang');
            $fileName = time().'_terima_'.$pengadaan->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('dokumentasi_barang', $fileName, 'public');
            $photoPath = 'dokumentasi_barang/'.$fileName;
        }

        $oldStatus = $pengadaan->status;
        $pengadaan->perlengkapan_user_id = Auth::id();
        $pengadaan->jenis_belanja = $validated['jenis_belanja'];
        $pengadaan->status_penerimaan = $validated['status_penerimaan'];
        $pengadaan->nama_penerima = $validated['nama_penerima'];
        $pengadaan->unit_penerima = $validated['unit_penerima'];
        $pengadaan->catatan_penerimaan = $validated['catatan_penerimaan'] ?? null;
        $pengadaan->foto_dokumentasi_barang = $photoPath;
        $pengadaan->ttd_digital = $validated['ttd_digital'];
        $pengadaan->tanggal_penerimaan = now();

        if ($validated['status_penerimaan'] === 'Terima') {
            $pengadaan->status = Pengadaan::STATUS_BARANG_DITERIMA;
        } else {
            $pengadaan->status = Pengadaan::STATUS_BARANG_PENDING;
        }

        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Pemeriksaan & Penerimaan BMN / Barang',
            'Barang telah diperiksa oleh Bagian Perlengkapan dengan status: '.$validated['status_penerimaan'].' ('.$validated['jenis_belanja'].') oleh '.$validated['nama_penerima'].' ('.$validated['unit_penerima'].'). Dilengkapi TTD digital dan foto dokumentasi.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Data penerimaan barang dan Berita Acara berhasil disimpan.');
    }
}
