<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Penerimaan Barang & BAST (Modul 6)
 *
 * Mengelola tahapan penerimaan fisik oleh Unit Perlengkapan:
 * - Pemeriksaan fisik kiriman vendor (kondisi: terima, pending, tidak_sesuai).
 * - Pembangkitan token tanda tangan digital HMAC-SHA256 pada dokumen BAST.
 * - Penyimpanan lampiran foto dokumentasi fisik kiriman.
 * - Pemutakhiran status paket menjadi 'penerimaan_selesai' dan notifikasi ke PPBJ.
 */
class PenerimaanBarangController extends Controller
{
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan antrean pemeriksaan fisik barang dan daftar BAST.
     */
    public function penerimaanIndex(): View
    {
        $kontrakList = KontrakSPK::with(['paket.vendor'])
            ->whereIn('status_spk', ['aktif', 'terbit'])
            ->whereDoesntHave('paket.penerimaanBarang')
            ->get();

        $belanjaLangsungList = PaketPengadaan::with(['vendor', 'permohonan'])
            ->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
            ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
            ->whereDoesntHave('penerimaanBarang')
            ->get();

        $bastList = PenerimaanBarang::with(['kontrak', 'paket.vendor', 'fotoDokumentasi', 'user'])->latest()->paginate(15);

        return view('penerimaan.index', compact('kontrakList', 'belanjaLangsungList', 'bastList'));
    }

    public function index(): View
    {
        return $this->penerimaanIndex();
    }

    /**
     * Memproses pemeriksaan fisik barang, upload foto, dan penerbitan BAST.
     */
    public function storePenerimaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (empty($request->nomor_bast)) {
            $request->merge(['nomor_bast' => 'BAST/'.date('Ymd').'/'.strtoupper(Str::random(6))]);
        }
        if ($request->filled('status_fisik') && ! $request->filled('kondisi_barang')) {
            $request->merge(['kondisi_barang' => $request->input('status_fisik')]);
        }
        if ($request->filled('kondisi_barang') && ! $request->filled('status_fisik')) {
            $request->merge(['status_fisik' => $request->input('kondisi_barang')]);
        }

        $validated = $request->validate([
            'nomor_kontrak_spk' => ['required_without:nomor_paket', 'nullable', 'exists:kontrak_spk,nomor_spk'],
            'nomor_paket' => ['required_without:nomor_kontrak_spk', 'nullable', 'exists:paket_pengadaan,nomor_paket'],
            'nomor_bast' => ['required', 'string', 'max:100', 'unique:penerimaan_barang,nomor_bast'],
            'tanggal_terima' => ['required', 'date'],
            'kondisi_barang' => ['nullable', 'string'],
            'status_fisik' => ['nullable', 'string'],
            'kategori_belanja' => ['nullable', 'string', 'in:belanja_barang,belanja_modal'],
            'foto_barang' => ['nullable'],
            'foto_barang.*' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'keterangan_foto' => ['nullable', 'string', 'max:255'],
            'file_bast_signed' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'file_penerimaan_pihak3' => ['nullable', DokumenUploadRule::pdfOrImage()],
            'catatan_pemeriksaan' => ['nullable', 'string'],
        ]);

        $kondisi = $validated['status_fisik'] ?? $validated['kondisi_barang'] ?? 'terima';

        $nomorPaket = $validated['nomor_paket'] ?? null;
        if (empty($nomorPaket)) {
            $spk = KontrakSPK::where('nomor_spk', $validated['nomor_kontrak_spk'])->firstOrFail();
            $nomorPaket = $spk->nomor_paket;
        }

        // 1. Simpan dokumen pihak ketiga jika ada
        $pihak3Path = null;
        if ($request->hasFile('file_penerimaan_pihak3')) {
            $pihak3Path = $request->file('file_penerimaan_pihak3')->store('pihak3', 'public');
        }

        // 2. Simpan naskah fisik BAST jika diunggah atau generate path dokumen resmi
        $bastSignedPath = 'bast/'.str_replace(['/', '\\'], '_', $validated['nomor_bast']).'.pdf';
        if ($request->hasFile('file_bast_signed')) {
            $bastSignedPath = $request->file('file_bast_signed')->store('bast', 'public');
        }

        // 3. Buat token HMAC SHA-256 untuk verifikasi keabsahan dokumen BAST
        $tokenHmac = PenerimaanBarang::generateSignatureToken($validated['nomor_bast'], $user->id_user);

        // 4. Simpan data BAST (BPMN N48 & N49)
        $penerimaan = PenerimaanBarang::create([
            'nomor_bast' => $validated['nomor_bast'],
            'nomor_paket' => $nomorPaket,
            'id_user' => $user->id_user,
            'tanggal_penerimaan' => $validated['tanggal_terima'],
            'status_fisik' => $kondisi,
            'kategori_belanja' => $validated['kategori_belanja'] ?? PenerimaanBarang::KATEGORI_BELANJA_BARANG,
            'kondisi_barang' => $kondisi,
            'ttd_digital_bast' => $tokenHmac,
            'file_bast_signed' => $bastSignedPath,
            'file_penerimaan_pihak3' => $pihak3Path,
            'catatan_pemeriksaan' => $validated['catatan_pemeriksaan'],
            'waktu_selesai_inspeksi' => now(),
        ]);

        // 5. Simpan foto dokumentasi fisik barang (BPMN N46 & N49)
        $keteranganInput = $validated['keterangan_foto'] ?? null;
        if ($request->hasFile('foto_barang')) {
            $files = is_array($request->file('foto_barang')) ? $request->file('foto_barang') : [$request->file('foto_barang')];
            $totalFiles = count($files);
            foreach ($files as $idx => $f) {
                $path = $f->store('dokumentasi_barang', 'public');
                DokumentasiPenerimaanBarang::create([
                    'id_penerimaan' => $penerimaan->id_penerimaan,
                    'foto_dokumentasi' => $path,
                    'keterangan_foto' => $keteranganInput
                        ? ($keteranganInput.($totalFiles > 1 ? ' (#'.($idx + 1).')' : ''))
                        : ('Foto fisik barang #'.($idx + 1)),
                ]);
            }
        } else {
            DokumentasiPenerimaanBarang::create([
                'id_penerimaan' => $penerimaan->id_penerimaan,
                'foto_dokumentasi' => 'dokumentasi_barang/item_default.jpg',
                'keterangan_foto' => $keteranganInput ?? 'Foto fisik barang item terlihat',
            ]);
        }

        // 6. Perbarui status paket agar diterima di N51 PPBJ & siap dibuat dokumen pembayaran di N52
        $paket = PaketPengadaan::find($nomorPaket);
        $isTerima = in_array($kondisi, ['terima', 'diterima_lengkap', 'sesuai'], true);
        if ($paket) {
            $paket->status_paket = $isTerima ? 'penerimaan_selesai' : 'penerimaan_pending';
            $paket->save();
        }

        AuditLog::catat(
            $user->id_user,
            'TERBITKAN_BAST_KIRIM_PPBJ',
            'penerimaan_barang',
            $penerimaan->nomor_bast,
            null,
            $penerimaan->status_fisik,
            "Unit Perlengkapan menerbitkan BAST {$penerimaan->nomor_bast} paket {$nomorPaket} dan mengirimkan dokumentasi ke PPBJ."
        );

        if ($isTerima) {
            $this->notificationService->notifyPenerimaanBarang($penerimaan);
        }

        return redirect()->route('penerimaan.index')->with(
            'success',
            "Berita Acara Serah Terima (BAST) nomor {$penerimaan->nomor_bast} dan foto dokumentasi berhasil diterbitkan & dikirim ke PPBJ."
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storePenerimaan($request);
    }
}
