<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerencanaanController extends Controller
{
    public function showInputMak(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'items', 'wadir2']);

        return view('roles.perencanaan.input-mak', compact('pengadaan'));
    }

    public function storeMak(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_mak' => ['required', 'string', 'max:100'],
            'pagu_anggaran' => ['required', 'numeric', 'min:0'],
            'sumber_dana' => ['required', 'string', 'max:50'],
            'tanggal_mak' => ['required', 'date'],
            'catatan_perencanaan' => ['nullable', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->perencanaan_user_id = Auth::id();
        $pengadaan->nomor_mak = $validated['nomor_mak'];
        $pengadaan->pagu_anggaran = $validated['pagu_anggaran'];
        $pengadaan->sumber_dana = $validated['sumber_dana'];
        $pengadaan->tanggal_mak = $validated['tanggal_mak'];
        $pengadaan->catatan_perencanaan = $validated['catatan_perencanaan'] ?? null;
        $pengadaan->status = Pengadaan::STATUS_MAK_DITETAPKAN;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Penetapan MAK & Pagu Anggaran',
            'Bagian Perencanaan menetapkan MAK: '.$validated['nomor_mak'].' dengan pagu Rp '.number_format($validated['pagu_anggaran'], 0, ',', '.').' ('.$validated['sumber_dana'].'). Berkas diteruskan ke PPBJ untuk registrasi.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Nomor MAK dan Pagu Anggaran berhasil ditetapkan.');
    }
}
