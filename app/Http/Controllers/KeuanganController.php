<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KeuanganController extends Controller
{
    public function showPencairan(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'vendor', 'items', 'perencanaan', 'ppbj']);

        return view('roles.keuangan.pencairan', compact('pengadaan'));
    }

    public function storePencairan(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_bukti_bayar' => ['required', 'string', 'max:100'],
            'tanggal_bayar' => ['required', 'date'],
            'catatan_keuangan' => ['nullable', 'string'],
            'file_bukti_bayar' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $proofPath = $pengadaan->file_bukti_bayar;
        if ($request->hasFile('file_bukti_bayar')) {
            $file = $request->file('file_bukti_bayar');
            $fileName = time().'_bukti_bayar_'.Str::slug($validated['nomor_bukti_bayar']).'.'.$file->getClientOriginalExtension();
            $file->storeAs('bukti_bayar', $fileName, 'public');
            $proofPath = 'bukti_bayar/'.$fileName;
        }

        $oldStatus = $pengadaan->status;
        $pengadaan->keuangan_user_id = Auth::id();
        $pengadaan->nomor_bukti_bayar = $validated['nomor_bukti_bayar'];
        $pengadaan->tanggal_bayar = $validated['tanggal_bayar'];
        $pengadaan->catatan_keuangan = $validated['catatan_keuangan'] ?? null;
        $pengadaan->file_bukti_bayar = $proofPath;
        $pengadaan->status = Pengadaan::STATUS_SELESAI;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Penyelesaian Pembayaran & Pencairan Dana',
            'Bagian Keuangan mencatat transaksi pembayaran (Ref/Bukti: '.$validated['nomor_bukti_bayar'].') dan mengunggah bukti transfer. Status pengadaan resmi "Selesai".',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Transaksi pembayaran berhasil dicatat. Status pengadaan dinyatakan SELESAI.');
    }
}
