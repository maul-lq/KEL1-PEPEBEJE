<?php

namespace App\Http\Controllers;

use App\Models\Pengadaan;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PpbjController extends Controller
{
    /**
     * 1. Registrasi Permohonan Pengadaan oleh PPBJ (FR 7.0 & 8.0)
     */
    public function showRegister(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['user', 'items', 'wadir2', 'perencanaan']);

        return view('roles.ppbj.register', compact('pengadaan'));
    }

    public function storeRegister(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_memo_ppbj' => ['required', 'string', 'max:100'],
            'metode_pengadaan' => ['required', 'string'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->ppbj_user_id = Auth::id();
        $pengadaan->nomor_memo_ppbj = $validated['nomor_memo_ppbj'];
        $pengadaan->metode_pengadaan = $validated['metode_pengadaan'];
        $pengadaan->tanggal_registrasi_ppbj = now();
        $pengadaan->status = Pengadaan::STATUS_TEREGISTRASI_PPBJ;

        // Pastikan jalur pengadaan ter-update berdasarkan nominal
        PengadaanRoutingService::assignJalur($pengadaan);
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Registrasi Pengadaan PPBJ',
            'PPBJ mencatat memo Wadir 2 dengan nomor register: '.$validated['nomor_memo_ppbj'].' (Metode: '.$validated['metode_pengadaan'].'). Berkas diarahkan ke '.$pengadaan->jalur_label.' untuk reviu.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Permohonan berhasil diregistrasi dan diteruskan ke '.$pengadaan->jalur_label.' untuk direviu.');
    }

    /**
     * 2. Penerbitan SPK / Surat Pesanan / Kontrak & Pengaturan Alarm (FR 11.0 & 12.0)
     */
    public function showCreateSpk(Pengadaan $pengadaan): View
    {
        $vendors = Vendor::where('is_active', true)->get();

        return view('roles.ppbj.create-spk', compact('pengadaan', 'vendors'));
    }

    public function storeSpk(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'nomor_spk' => ['required', 'string', 'max:100'],
            'tanggal_spk' => ['required', 'date'],
            'nilai_kontrak' => ['required', 'numeric', 'min:0'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai_jadwal' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'file_spk' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'klasifikasi_alarm' => ['nullable', 'string', 'max:50'],
            'id_paket_lkpp' => ['nullable', 'string', 'max:255'],
        ]);

        $filePath = $pengadaan->file_spk;
        if ($request->hasFile('file_spk')) {
            $file = $request->file('file_spk');
            $fileName = time().'_spk_'.Str::slug($validated['nomor_spk']).'.'.$file->getClientOriginalExtension();
            $file->storeAs('spk', $fileName, 'public');
            $filePath = 'spk/'.$fileName;
        }

        $oldStatus = $pengadaan->status;
        $pengadaan->vendor_id = $validated['vendor_id'];
        $pengadaan->nomor_spk = $validated['nomor_spk'];
        $pengadaan->tanggal_spk = $validated['tanggal_spk'];
        $pengadaan->nilai_kontrak = $validated['nilai_kontrak'];
        $pengadaan->tanggal_mulai = $validated['tanggal_mulai'];
        $pengadaan->tanggal_selesai_jadwal = $validated['tanggal_selesai_jadwal'];
        $pengadaan->file_spk = $filePath;
        $pengadaan->klasifikasi_alarm = $validated['klasifikasi_alarm'] ?? 'H-2 Jatuh Tempo';
        $pengadaan->id_paket_lkpp = $validated['id_paket_lkpp'] ?? $pengadaan->id_paket_lkpp;
        $pengadaan->status = Pengadaan::STATUS_SPK_DITERBITKAN;
        $pengadaan->save();

        // Buat alarm reminder dan tembusan otomatis
        PengadaanRoutingService::generateReminders($pengadaan);

        AuditLogService::log(
            $pengadaan,
            'Penerbitan SPK / Surat Kontrak',
            'PPBJ menerbitkan SPK No: '.$validated['nomor_spk'].' kepada vendor '.($pengadaan->vendor?->nama_perusahaan ?? '').' dengan nilai kontrak Rp '.number_format($validated['nilai_kontrak'], 0, ',', '.').'. Tembusan dan jadwal alarm pengingat telah diaktifkan.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Dokumen SPK / Kontrak berhasil diterbitkan. Alarm jadwal dan tembusan otomatis telah diatur.');
    }

    /**
     * 3. Pengajuan Dokumen Pembayaran setelah barang diterima (FR 15.0)
     */
    public function showAjukanPembayaran(Pengadaan $pengadaan): View
    {
        return view('roles.ppbj.ajukan-pembayaran', compact('pengadaan'));
    }

    public function storeAjukanPembayaran(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $validated = $request->validate([
            'catatan_pengajuan_pembayaran' => ['nullable', 'string'],
            'file_dokumen_pembayaran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $filePath = $pengadaan->file_dokumen_pembayaran;
        if ($request->hasFile('file_dokumen_pembayaran')) {
            $file = $request->file('file_dokumen_pembayaran');
            $fileName = time().'_pembayaran_'.$pengadaan->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('dokumen_pembayaran', $fileName, 'public');
            $filePath = 'dokumen_pembayaran/'.$fileName;
        }

        $oldStatus = $pengadaan->status;
        $pengadaan->file_dokumen_pembayaran = $filePath;
        $pengadaan->catatan_pengajuan_pembayaran = $validated['catatan_pengajuan_pembayaran'] ?? null;
        $pengadaan->tanggal_pengajuan_pembayaran = now();
        $pengadaan->status = Pengadaan::STATUS_PENGAJUAN_PEMBAYARAN;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Pengajuan Berkas Pembayaran',
            'PPBJ mengunggah kelengkapan berkas tagihan/pembayaran dan mengajukan memo persetujuan bertingkat ke PPK.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Berkas pembayaran berhasil diajukan untuk persetujuan PPK dan Wadir 2.');
    }

    /**
     * 4. Penerbitan Surat Perintah Pembayaran (SPP) ke Bagian Keuangan (FR 17.0)
     */
    public function terbitkanSpp(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'nomor_spp' => ['required', 'string', 'max:100'],
            'tanggal_spp' => ['required', 'date'],
        ]);

        $oldStatus = $pengadaan->status;
        $pengadaan->nomor_spp = $request->input('nomor_spp');
        $pengadaan->tanggal_spp = $request->input('tanggal_spp');
        $pengadaan->status = Pengadaan::STATUS_SP_PEMBAYARAN_TERBIT;
        $pengadaan->save();

        AuditLogService::log(
            $pengadaan,
            'Penerbitan Surat Perintah Pembayaran (SPP)',
            'PPBJ menerbitkan SPP No: '.$request->input('nomor_spp').' dan menginstruksikan pencairan dana ke Bagian Keuangan.',
            $oldStatus,
            $pengadaan->status
        );

        return redirect()->route('pengadaan.show', $pengadaan)
            ->with('success', 'Perintah pembayaran (SPP) berhasil diterbitkan ke Bagian Keuangan.');
    }
}
