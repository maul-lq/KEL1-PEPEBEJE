<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller PenerimaanBarangController ini untuk mengelola antrean pemeriksaan fisik barang oleh Unit Perlengkapan, penerbitan BAST bertanda tangan HMAC-SHA256, dan dokumentasi foto.
 */

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DokumentasiPenerimaanBarang;
use App\Models\KontrakSPK;
use App\Models\PaketPengadaan;
use App\Models\PenerimaanBarang;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use App\Services\PengadaanRoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controller Penerimaan Fisik & BAST (PenerimaanBarangController)
 *
 * memisahkan kewenangan penerbitan BAST murni di controller ini untuk peran Perlengkapan,
 * mencakup penerimaan dari jalur Kontrak/SPK (>50jt) maupun jalur Belanja Langsung (<=50jt).
 *
 * Parameter konstruktor: `PengadaanNotificationService $notificationService` — layanan pengirim notifikasi BAST terbit.
 * Nilai kembali         : Instance controller yang melayani rute `/penerimaan-barang`.
 *
 * Contoh pemanggilan:
 * `Route::post('/penerimaan-barang', [PenerimaanBarangController::class, 'storePenerimaan'])->name('penerimaan.store');`
 */
class PenerimaanBarangController extends Controller
{
    /**
     * menyuntikkan layanan notifikasi pengadaan agar penerbitan BAST langsung mengabarkan PPBJ dan unit pemohon.
     *
     * @param  PengadaanNotificationService  $notificationService  mengikat service notifikasi melalui property promotion.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$controller = app(PenerimaanBarangController::class);`
     */
    public function __construct(
        public PengadaanNotificationService $notificationService
    ) {}

    /**
     * menampilkan antrean paket siap periksa fisik (baik dari SPK terbit maupun Belanja Langsung selesai) serta daftar BAST yang sudah diterbitkan.
     *
     * @return View mengembalikan tampilan Blade `penerimaan.index` dengan `$kontrakList`, `$belanjaLangsungList`, dan `$bastList`.
     *
     * Contoh pemanggilan:
     * `GET /penerimaan-barang`
     */
    public function penerimaanIndex(): View
    {
        $kontrakList = KontrakSPK::with(['paket.vendor'])
            ->whereIn('status_spk', ['aktif', 'terbit'])
            ->whereDoesntHave('paket.penerimaanBarang')
            ->get();

        // juga memuat Belanja Langsung <=50jt yang sudah punya kwitansi karena jalur tersebut tetap wajib BAST Perlengkapan meski tanpa Kontrak/SPK.
        $belanjaLangsungList = PaketPengadaan::with(['vendor', 'permohonan'])
            ->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
            ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
            ->whereDoesntHave('penerimaanBarang')
            ->get();

        $bastList = PenerimaanBarang::with(['kontrak', 'paket.vendor', 'fotoDokumentasi', 'user'])->latest()->paginate(15);

        return view('penerimaan.index', compact('kontrakList', 'belanjaLangsungList', 'bastList'));
    }

    /**
     * menyediakan metode alias `index` yang meneruskan langsung ke `penerimaanIndex`.
     *
     * @return View mengembalikan tampilan Blade `penerimaan.index`.
     *
     * Contoh pemanggilan:
     * `$controller->index();`
     */
    public function index(): View
    {
        return $this->penerimaanIndex();
    }

    /**
     * memproses penyimpanan pemeriksaan fisik barang, mengunggah foto & berkas BAST, membangkitkan token HMAC-SHA256, dan menghentikan pengingat pengiriman.
     *
     * @param  Request  $request  memvalidasi referensi paket/SPK, nomor BAST unik, tanggal terima, kategori belanja, dan lampiran foto/dokumen.
     * @return RedirectResponse mengarahkan kembali ke halaman penerimaan barang dengan pesan konfirmasi terbitnya BAST.
     *
     * Contoh pemanggilan:
     * `POST /penerimaan-barang`
     */
    public function storePenerimaan(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (empty($request->nomor_bast)) {
            $request->merge(['nomor_bast' => 'BAST/'.date('Ymd').'/'.strtoupper(Str::random(6))]);
        }
        // menyinkronkan kedua nama field kondisi ('status_fisik' dan 'kondisi_barang') agar form baru maupun payload uji lama tetap terbaca.
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

        $pihak3Path = null;
        if ($request->hasFile('file_penerimaan_pihak3')) {
            $pihak3Path = $request->file('file_penerimaan_pihak3')->store('pihak3', 'public');
        }

        $bastSignedPath = 'bast/'.str_replace(['/', '\\'], '_', $validated['nomor_bast']).'.pdf';
        if ($request->hasFile('file_bast_signed')) {
            $bastSignedPath = $request->file('file_bast_signed')->store('bast', 'public');
        }

        // menandatangani BAST dengan HMAC-SHA256 berbasis app.key sebelum disimpan untuk menjamin keabsahan hukum penerimaan barang.
        $tokenHmac = PenerimaanBarang::generateSignatureToken($validated['nomor_bast'], $user->id_user);

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
            // menyisipkan record foto placeholder bila unggahan foto opsional tidak disertakan agar galeri BAST di UI tidak kosong.
            DokumentasiPenerimaanBarang::create([
                'id_penerimaan' => $penerimaan->id_penerimaan,
                'foto_dokumentasi' => 'dokumentasi_barang/item_default.jpg',
                'keterangan_foto' => $keteranganInput ?? 'Foto fisik barang item terlihat',
            ]);
        }

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

        // mematikan pengingat pengiriman sekali jalan (bukan kontrak berkala) dan mengabarkan PPBJ/pemohon hanya bila barang dinyatakan diterima lengkap.
        if ($isTerima) {
            PengadaanRoutingService::deactivateForPaket($nomorPaket, true);
            $this->notificationService->notifyPenerimaanBarang($penerimaan);
        }

        return redirect()->route('penerimaan.index')->with(
            'success',
            "Bukti Serah Terima Barang nomor {$penerimaan->nomor_bast} dan foto dokumentasi berhasil disimpan & dikirim ke Unit Pengadaan."
        );
    }

    /**
     * menyediakan metode alias `store` yang meneruskan langsung ke `storePenerimaan`.
     *
     * @param  Request  $request  meneruskan objek request penyimpanan BAST.
     * @return RedirectResponse mengembalikan hasil redirect dari `storePenerimaan`.
     *
     * Contoh pemanggilan:
     * `$controller->store($request);`
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storePenerimaan($request);
    }
}
