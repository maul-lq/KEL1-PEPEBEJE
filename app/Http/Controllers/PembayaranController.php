<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PembayaranController extends Controller
{
    /**
     * Persetujuan Memo Pembayaran oleh PPK (Tahap 1)
     */
    public function accPpk(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_pembayaran_ppk' => ['nullable', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->acc_pembayaran_ppk = true;
        $pengadaan->tanggal_acc_pembayaran_ppk = now();
        $pengadaan->catatan_pembayaran_ppk = $request->input('catatan_pembayaran_ppk', 'ACC Pembayaran oleh PPK.');
        $pengadaan->status = Pengadaan::STATUS_MEMO_PEMBAYARAN_PPK;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'ACC Memo Pembayaran PPK',
            'PPK menyetujui memo pencairan pembayaran. Diteruskan ke Wadir 2 untuk persetujuan akhir pimpinan.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Memo pembayaran disetujui oleh PPK dan diteruskan ke Wadir 2.');
    }

    /**
     * Persetujuan Memo Pembayaran oleh Wadir 2 (Tahap 2)
     */
    public function accWadir2(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'catatan_pembayaran_wadir2' => ['nullable', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->acc_pembayaran_wadir2 = true;
        $pengadaan->tanggal_acc_pembayaran_wadir2 = now();
        $pengadaan->catatan_pembayaran_wadir2 = $request->input('catatan_pembayaran_wadir2', 'Disetujui pencairan oleh Wadir 2.');
        $pengadaan->status = Pengadaan::STATUS_MEMO_PEMBAYARAN_WADIR2;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Persetujuan Memo Pembayaran Wadir 2',
            'Wadir 2 menyetujui pencairan pembayaran pengadaan. PPBJ siap menerbitkan Surat Perintah Pembayaran (SPP).',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Memo pembayaran disetujui oleh Wadir 2. Silakan PPBJ menerbitkan SPP.');
    }
}
