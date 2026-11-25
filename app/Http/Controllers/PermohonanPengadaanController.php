<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Controller ini bangun untuk mengelola siklus pengajuan permohonan pengadaan
 *             unit kerja, mencakup daftar riwayat, detail pelacakan, pengajuan baru, revisi surat,
 *             pembatalan, serta sinkronisasi kelulusan verifikasi paralel menuju PPBJ.
 */

namespace App\Http\Controllers;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerifikasiParalel;
use App\Rules\DokumenUploadRule;
use App\Services\PengadaanNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Mengelola siklus pengajuan usulan surat permohonan pengadaan barang dan jasa oleh unit kerja.
 *
 * Tujuan: menyatukan alur CRUD permohonan, revisi pasca penolakan tanpa pemotongan pagu MAK ganda,
 * serta evaluasi kelulusan paralel Bidang II, Perencanaan, Wadir II, dan PPK dalam controller ini.
 *
 * Contoh pemanggilan:
 * `$response = app(PermohonanPengadaanController::class)->index($request);`
 */
class PermohonanPengadaanController extends Controller
{
    /**
     * Menginisialisasi dependensi layanan notifikasi pengadaan ke dalam controller.
     *
     * Tujuan: menyuntikkan layanan notifikasi lewat konstruktor agar setiap transisi
     * status pengajuan dan revisi otomatis memberi kabar ke verifikator terkait.
     *
     * @param  PengadaanNotificationService  $notificationService  Layanan pengirim notifikasi alur PBJ.
     * @return void
     *
     * Contoh pemanggilan:
     * `new PermohonanPengadaanController(app(PengadaanNotificationService::class));`
     */
    public function __construct(
        protected PengadaanNotificationService $notificationService
    ) {}

    /**
     * Menampilkan daftar riwayat usulan permohonan sesuai cakupan hak akses pengguna.
     *
     * Tujuan: menyediakan titik masuk rute resource standar yang mendelegasikan prosesnya
     * ke `permohonanIndex` agar kompatibel dengan penamaan rute lama maupun baru.
     *
     * @param  Request  $request  Permintaan HTTP berisi parameter pencarian atau pilihan berkas aktif.
     * @return View Tampilan halaman daftar permohonan pengadaan.
     *
     * Contoh pemanggilan:
     * `Route::get('/permohonan', [PermohonanPengadaanController::class, 'index']);`
     */
    public function index(Request $request): View
    {
        return $this->permohonanIndex($request);
    }

    /**
     * Memuat koleksi permohonan pengadaan beserta relasi tracking dan isolasi unit kerja.
     *
     * Tujuan: menyaring usulan agar pengusul tanpa izin `permohonan.view_all` hanya dapat
     * melihat berkas miliknya sendiri serta menyembunyikan surat induk yang sudah digantikan revisi.
     *
     * @param  Request  $request  Permintaan HTTP dari pengguna terautentikasi.
     * @return View Halaman indeks permohonan dengan daftar berkas dan rincian tracking.
     *
     * Contoh pemanggilan:
     * `$view = $this->permohonanIndex($request);`
     */
    public function permohonanIndex(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyPermission(['permohonan.view_own', 'permohonan.view_all'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin untuk melihat daftar permohonan pengadaan.');
        }

        $query = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'suratAsalRevisi',
            'alokasiMak.anggaranMak',
            'paketPengadaan.reviuDokumen',
            'paketPengadaan.kontrakSpk',
            'paketPengadaan.penerimaanBarang.fotoDokumentasi',
            'paketPengadaan.pembayaran.transaksiPencairan',
            'paketPengadaan.reminders',
        ])
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi') // sembunyikan arsip induk revisi supaya tidak tampil ganda di daftar aktif.
            ->forUser($user);

        // mendukung pencarian server-side via parameter q maupun keyword yang case-insensitive di PostgreSQL (ilike) dan SQLite (like).
        $search = trim((string) ($request->filled('q') ? $request->input('q') : $request->input('keyword', '')));
        if ($search !== '') {
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('nomor_surat', $likeOperator, "%{$search}%")
                    ->orWhere('judul_pengadaan', $likeOperator, "%{$search}%")
                    ->orWhere('tujuan_pengadaan_ringkas', $likeOperator, "%{$search}%")
                    ->orWhereHas('unit', fn ($unitQuery) => $unitQuery->where('nama_unit', $likeOperator, "%{$search}%"));
            });
        }

        // menyelaraskan opsi dropdown status dengan nilai status aktual proses verifikasi maupun arsip.
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $statusFilter = strtolower(trim((string) $request->input('status')));
            match ($statusFilter) {
                'reviu_paralel' => $query->whereIn('status_permohonan', [
                    PermohonanPengadaan::STATUS_REVIU_PARALEL,
                    PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2,
                    PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                    PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
                    PermohonanPengadaan::STATUS_PENDING_ANGGARAN,
                    'hold',
                ]),
                'perlu_revisi', 'revisi' => $query->whereIn('status_permohonan', ['perlu_revisi', 'revisi']),
                'disetujui' => $query->whereIn('status_permohonan', [
                    PermohonanPengadaan::STATUS_DISETUJUI,
                    PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                    'paket_terdaftar',
                    'disetujui_wadir2_dan_ppk',
                ]),
                default => $query->where('status_permohonan', $statusFilter),
            };
        }

        // mendukung parameter jenis maupun type pada jenis_pengadaan_split ataupun paket pengadaan terkait.
        $jenisFilter = trim((string) ($request->filled('jenis') ? $request->input('jenis') : $request->input('type', '')));
        if ($jenisFilter !== '' && $jenisFilter !== 'all') {
            $query->where(function ($q) use ($jenisFilter) {
                $q->where('jenis_pengadaan_split', $jenisFilter)
                    ->orWhereHas('paketPengadaan', fn ($pq) => $pq->where('jenis_pengadaan', $jenisFilter));
            });
        }

        // menyaring tanggal pengajuan hanya jika format YYYY-MM-DD valid agar PostgreSQL tidak melempar kesalahan sintaks tipe date.
        if ($request->filled('tanggal')) {
            $tanggalFilter = trim((string) $request->input('tanggal'));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalFilter) === 1) {
                $query->whereDate('tanggal_pengajuan', $tanggalFilter);
            }
        }

        $permohonanList = $query->latest('tanggal_pengajuan')->paginate(15)->withQueryString();
        $units = Unit::all();
        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::eligibleForPpk()->get();

        return view('permohonan.index', compact('permohonanList', 'user', 'units', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan halaman detail dan tracking siklus permohonan pengadaan berdasarkan nomor surat.
     *
     * Tujuan: menyediakan metode `show` standar yang meneruskan pemanggilan ke `permohonanShow`.
     *
     * @param  string  $id  Nomor surat permohonan pengadaan yang ingin ditinjau.
     * @return View Halaman detail permohonan pengadaan.
     *
     * Contoh pemanggilan:
     * `Route::get('/permohonan/{id}', [PermohonanPengadaanController::class, 'show']);`
     */
    public function show(string $id): View
    {
        return $this->permohonanShow($id);
    }

    /**
     * Mengambil data lengkap satu permohonan pengadaan beserta daftar MAK dan akun PPK aktif.
     *
     * Tujuan: memuat seluruh relasi verifikasi hingga pembayaran agar satu halaman detail dapat
     * menampilkan progres 8 siklus PBJ sekaligus aksi sesuai peran pengguna.
     *
     * @param  string  $id  Nomor surat permohonan target.
     * @return View Halaman detail permohonan (`permohonan.show`).
     *
     * Contoh pemanggilan:
     * `$view = $this->permohonanShow('001/PNJ/2026');`
     */
    public function permohonanShow(string $id): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyPermission(['permohonan.view_own', 'permohonan.view_all'])) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin untuk melihat detail permohonan pengadaan.');
        }

        // menerima nomor surat asli maupun hasil encoding %2F agar nomor surat bertanda garis miring tetap dapat dibuka.
        $candidateIds = array_values(array_unique([$id, urldecode($id), rawurldecode($id)]));

        $permohonan = PermohonanPengadaan::with([
            'unit',
            'user',
            'verifikasiParalel.user',
            'paketPengadaan.penerimaanBarang.fotoDokumentasi',
            'paketPengadaan.pembayaran.transaksiPencairan',
            'paket.kontrak.penerimaan',
        ])->whereIn('nomor_surat', $candidateIds)
            ->firstOrFail();

        // mencegah IDOR: pemegang permohonan.view_own tanpa view_all hanya boleh membuka detail surat yang ia buat sendiri.
        if (! $user->hasPermission('permohonan.view_all') && (int) $permohonan->id_user !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda hanya dapat melihat detail permohonan yang Anda ajukan sendiri.');
        }

        $makList = AnggaranMAK::orderBy('kode_mak')->get();
        $ppkUsers = User::eligibleForPpk()->get();

        return view('permohonan.show', compact('permohonan', 'makList', 'ppkUsers'));
    }

    /**
     * Menampilkan formulir pendaftaran usulan permohonan pengadaan baru berbasis Srikandi.
     *
     * Tujuan: menyediakan endpoint `create` standar yang memanggil `permohonanCreate`.
     *
     * @return View Halaman formulir pengajuan permohonan baru.
     *
     * Contoh pemanggilan:
     * `Route::get('/permohonan/create', [PermohonanPengadaanController::class, 'create']);`
     */
    public function create(): View
    {
        return $this->permohonanCreate();
    }

    /**
     * Memvalidasi izin pembuatan permohonan dan menyiapkan daftar unit kerja pada formulir.
     *
     * Tujuan: menolak akses dengan 403 bila pengguna tidak memiliki izin `permohonan.create`
     * agar hanya pengusul berwenang yang dapat membuka formulir pengajuan.
     *
     * @return View Halaman `permohonan.create` dengan variabel `$units`.
     *
     * Contoh pemanggilan:
     * `$view = $this->permohonanCreate();`
     */
    public function permohonanCreate(): View
    {
        if (! Auth::user()?->hasPermission('permohonan.create')) {
            abort(403, 'Akses Ditolak: Peran Anda tidak memiliki izin untuk membuat usulan permohonan.');
        }

        $units = Unit::all();

        return view('permohonan.create', compact('units'));
    }

    /**
     * Memproses pengajuan usulan permohonan baru ke Bidang 2 (Alur Persetujuan Awal Berantai).
     *
     * Tujuan: menyediakan metode `store` resource yang meneruskan request ke `storePermohonan`.
     *
     * @param  Request  $request  Payload formulir surat permohonan dan berkas lampiran.
     * @return RedirectResponse Pengalihan halaman setelah penyimpanan berhasil atau gagal.
     *
     * Contoh pemanggilan:
     * `Route::post('/permohonan', [PermohonanPengadaanController::class, 'store']);`
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->storePermohonan($request);
    }

    /**
     * Memvalidasi dan menyimpan usulan permohonan pengadaan baru beserta berkas KAK dan lampiran.
     *
     * Tujuan: mewajibkan izin `permohonan.create`, memastikan nomor surat unik, menyimpan
     * dokumen KAK PDF dan lampiran pendukung, lalu mengirim notifikasi ke Staf Bidang II.
     *
     * @param  Request  $request  Permintaan POST pengajuan usulan baru.
     * @return RedirectResponse Redirect ke halaman daftar permohonan dengan flash status.
     *
     * Contoh pemanggilan:
     * `$response = $this->storePermohonan($request);`
     */
    public function storePermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('permohonan.create')) {
            abort(403, 'Akses Ditolak: Peran Anda tidak memiliki izin untuk mengajukan permohonan.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:100', 'unique:permohonan_pengadaan,nomor_surat'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'kode_unit' => ['required', 'string', 'exists:units,kode_unit'],
            'tanggal_pengajuan' => ['nullable', 'date'],
            'dokumen_lampiran' => ['nullable', 'array', 'max:5'],
            'dokumen_lampiran.*' => ['nullable', 'file', 'max:15360', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            'file_surat_permohonan' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
            'file_spesifikasi_teknis' => ['nullable', DokumenUploadRule::pdfOnly()],
        ]);

        // 1. Simpan berkas dari input multiple dokumen_lampiran[] (maksimal 5 berkas)
        $dokumenLampiranList = $this->buildDokumenLampiranEntries($request);

        // 2. Simpan berkas dari input spesifik individual (jika ada)
        $filePermohonanPath = $request->hasFile('file_surat_permohonan')
            ? $request->file('file_surat_permohonan')->store('permohonan', 'public')
            : null;

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : null;

        $fileSpekPath = $request->hasFile('file_spesifikasi_teknis')
            ? $request->file('file_spesifikasi_teknis')->store('spek', 'public')
            : null;

        if (empty($dokumenLampiranList)) {
            if ($filePermohonanPath) {
                $dokumenLampiranList[] = [
                    'name' => $request->file('file_surat_permohonan')->getClientOriginalName(),
                    'path' => $filePermohonanPath,
                    'url' => asset('storage/'.$filePermohonanPath),
                    'type' => 'PDF',
                    'size' => 'Surat Pengantar',
                    'label' => 'Surat Pengantar Srikandi',
                ];
            }
            if ($fileKakPath) {
                $dokumenLampiranList[] = [
                    'name' => $request->file('file_kak')->getClientOriginalName(),
                    'path' => $fileKakPath,
                    'url' => asset('storage/'.$fileKakPath),
                    'type' => 'PDF',
                    'size' => 'Kerangka Acuan',
                    'label' => 'Kerangka Acuan',
                ];
            }
            if ($fileSpekPath) {
                $dokumenLampiranList[] = [
                    'name' => $request->file('file_spesifikasi_teknis')->getClientOriginalName(),
                    'path' => $fileSpekPath,
                    'url' => asset('storage/'.$fileSpekPath),
                    'type' => 'PDF',
                    'size' => 'Spesifikasi & Rincian Biaya',
                    'label' => 'Spesifikasi & Rincian Biaya',
                ];
            }
        } else {
            // Petakan 3 file pertama ke kolom individual untuk kompatibilitas ke modul lanjutan
            if (! $filePermohonanPath && isset($dokumenLampiranList[0])) {
                $filePermohonanPath = $dokumenLampiranList[0]['path'];
            }
            if (! $fileKakPath && isset($dokumenLampiranList[1])) {
                $fileKakPath = $dokumenLampiranList[1]['path'];
            }
            if (! $fileSpekPath && isset($dokumenLampiranList[2])) {
                $fileSpekPath = $dokumenLampiranList[2]['path'];
            }
        }

        $kodeUnit = $user->kode_unit ?? $validated['kode_unit'] ?? 'TIK';

        // Buat baris data permohonan pengadaan pada tahap awal Bidang 2
        $permohonan = PermohonanPengadaan::create([
            'nomor_surat' => $validated['nomor_surat'],
            'judul_pengadaan' => $validated['perihal'],
            'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $validated['perihal'],
            'estimasi_total_pagu' => $validated['anggaran_diajukan'],
            'perihal' => $validated['perihal'],
            'anggaran_diajukan' => $validated['anggaran_diajukan'],
            'kode_unit' => $kodeUnit,
            'id_user' => $user->id_user,
            'tanggal_pengajuan' => $validated['tanggal_pengajuan'] ?? now()->toDateString(),
            'file_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_surat_permohonan' => $filePermohonanPath ?? 'permohonan/dummy.pdf',
            'file_kak' => $fileKakPath,
            'file_spesifikasi_teknis' => $fileSpekPath,
            'dokumen_lampiran' => $dokumenLampiranList,
            'status_permohonan' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            'status_anggaran' => 'pending',
            'status' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
        ]);

        // menginisialisasi antrean penentuan pos anggaran dan penetapan PPK di Bagian Perencanaan serta rekam tunggu Wadir 2 tanpa verifikasi Staf Bidang 2.
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN,
            'status_keputusan' => 'menunggu',
        ]);
        VerifikasiParalel::create([
            'id_user' => $user->id_user,
            'nomor_surat' => $permohonan->nomor_surat,
            'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2,
            'status_keputusan' => 'menunggu',
        ]);

        // Catat ke jejak audit
        AuditLog::catat(
            $user->id_user,
            'AJUKAN_PERMOHONAN',
            'permohonan_pengadaan',
            $permohonan->nomor_surat,
            null,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            "Pengajuan usulan permohonan pengadaan nomor {$permohonan->nomor_surat} ke Bagian Perencanaan oleh {$user->nama}"
        );

        // memicu notifikasi siklus: Bagian Perencanaan menerima usulan baru untuk menentukan pos anggaran dan memilih PPK.
        $this->notificationService->notifyPengajuanBaru($permohonan);

        $redirectRoute = $user->hasAnyPermission(['permohonan.view_own', 'permohonan.view_all'])
            ? 'permohonan.index'
            : 'dashboard';

        return redirect()->route($redirectRoute)->with(
            'success',
            "Usulan permohonan nomor {$permohonan->nomor_surat} berhasil diajukan ke Bagian Perencanaan untuk penentuan pos anggaran dan penetapan PPK."
        );
    }

    /**
     * Memproses pengajuan revisi surat usulan permohonan yang sebelumnya ditolak atau perlu perbaikan.
     *
     * Tujuan: menyediakan alias `revisi` yang memanggil `revisiPermohonan` untuk mendukung
     * pembaruan in-place maupun penerbitan nomor surat revisi baru tanpa memotong pagu MAK dua kali.
     *
     * @param  Request  $request  Data perubahan usulan dan dokumen revisi.
     * @return RedirectResponse Redirect ke halaman indeks permohonan setelah revisi disimpan.
     *
     * Contoh pemanggilan:
     * `Route::post('/permohonan/revisi', [PermohonanPengadaanController::class, 'revisi']);`
     */
    public function revisi(Request $request): RedirectResponse
    {
        return $this->revisiPermohonan($request);
    }

    /**
     * Menjalankan logika revisi in-place atau migrasi nomor surat baru sambil mewarisi persetujuan aman.
     *
     * Tujuan: menjaga agar alokasi MAK yang sudah terpotong di Perencanaan serta persetujuan PPK
     * atau Wadir II yang belum menolak tetap terawat tanpa pemotongan saldo pagu MAK ganda.
     *
     * @param  Request  $request  Permintaan HTTP berisi nomor surat lama, perubahan field, dan unggahan baru.
     * @return RedirectResponse Redirect menuju daftar permohonan dengan nomor revisi terbaru.
     *
     * Contoh pemanggilan:
     * `$response = $this->revisiPermohonan($request);`
     */
    public function revisiPermohonan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('permohonan.revisi')) {
            abort(403, 'Akses Ditolak: Peran Anda tidak memiliki izin untuk mengajukan revisi permohonan.');
        }

        $validated = $request->validate([
            'nomor_surat_lama' => ['required', 'exists:permohonan_pengadaan,nomor_surat'],
            'nomor_surat_baru' => ['nullable', 'string', 'max:100'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_pengadaan_ringkas' => ['nullable', 'string', 'max:500'],
            'anggaran_diajukan' => ['required', 'numeric', 'min:1'],
            'keterangan_revisi' => ['nullable', 'string'],
            'file_kak' => ['nullable', DokumenUploadRule::pdfOnly()],
            'dokumen_lampiran' => ['nullable', 'array', 'max:5'],
            'dokumen_lampiran.*' => ['nullable', 'file', 'max:15360', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $induk = PermohonanPengadaan::findOrFail($validated['nomor_surat_lama']);

        // mencegah IDOR pada aksi revisi: hanya pengusul pemilik surat (atau pemegang view_all) yang boleh mengirim revisi.
        if (! $user->hasPermission('permohonan.view_all') && (int) $induk->id_user !== (int) $user->id_user) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin untuk merevisi permohonan milik pengguna lain.');
        }

        // hanya mengizinkan revisi apabila permohonan benar-benar berstatus ditolak atau perlu revisi.
        $statusInduk = strtolower((string) ($induk->status_permohonan ?? $induk->status ?? ''));
        if (! in_array($statusInduk, ['ditolak', 'revisi', 'perlu_revisi'], true)) {
            return back()->with('error', 'Permohonan ini belum berada pada status ditolak atau perlu revisi.')->withInput();
        }

        $oldNomorSurat = $induk->nomor_surat;
        $targetNomorSurat = trim($validated['nomor_surat_baru'] ?? '');
        $isSameSurat = empty($targetNomorSurat) || ($targetNomorSurat === $oldNomorSurat);

        if (! $isSameSurat) {
            $isUsed = PermohonanPengadaan::where('nomor_surat', $targetNomorSurat)->exists();
            if ($isUsed) {
                return back()->withErrors(['nomor_surat_baru' => "Nomor surat {$targetNomorSurat} sudah terdaftar di sistem."])->withInput();
            }
        }

        $uploadedLampiran = $this->buildDokumenLampiranEntries($request);
        $hasNewLampiran = count($uploadedLampiran) > 0;

        $fileKakPath = $request->hasFile('file_kak')
            ? $request->file('file_kak')->store('kak', 'public')
            : ($hasNewLampiran ? null : $induk->file_kak);

        $finalDokumenLampiran = $hasNewLampiran
            ? $uploadedLampiran
            : $induk->dokumen_lampiran;

        $finalFilePermohonan = $hasNewLampiran
            ? $uploadedLampiran[0]['path']
            : ($induk->file_permohonan ?? 'permohonan/dummy.pdf');

        $finalFileSpesifikasiTeknis = $hasNewLampiran
            ? null
            : $induk->file_spesifikasi_teknis;

        $nominalBaru = (float) $validated['anggaran_diajukan'];
        $nominalLama = (float) ($induk->anggaran_diajukan ?? $induk->estimasi_total_pagu ?? 0);
        $selisih = $nominalBaru - $nominalLama;
        $revisiKe = ($induk->revisi_ke ?? 0) + 1;

        $hasMak = ! empty($induk->kode_mak);
        // mengarahkan revisi usulan langsung ke Bagian Perencanaan agar penentuan atau konfirmasi pos anggaran tidak tertahan telaah Staf Bidang 2.
        $targetStatus = PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN;

        // Ambil seluruh riwayat verifikasi paralel sebelum revisi agar id_user verifikator asal dan status ACC aman diwariskan.
        $oldVerifPerencanaan = VerifikasiParalel::where('nomor_surat', $oldNomorSurat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
            ->first();
        $oldVerifWadir = VerifikasiParalel::where('nomor_surat', $oldNomorSurat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
            ->first();
        $oldVerifPpk = VerifikasiParalel::where('nomor_surat', $oldNomorSurat)
            ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
            ->first();

        // Cek kecukupan saldo MAK lebih awal sebelum transaksi DB mutasi dimulai agar error validasi langsung kembali ke form.
        $mak = $induk->kode_mak ? AnggaranMAK::find($induk->kode_mak) : null;
        $alokasiLama = $induk->kode_mak
            ? AlokasiMAK::where('nomor_surat', $oldNomorSurat)->where('kode_mak', $induk->kode_mak)->first()
            : null;

        if ($mak && $alokasiLama && $selisih > 0 && (float) $mak->sisa_pagu_mak < $selisih) {
            return back()->with('error', "Penyesuaian anggaran melebihi sisa pagu pos anggaran {$mak->kode_mak} yang tersedia (tersisa Rp ".number_format($mak->sisa_pagu_mak, 0, ',', '.').').')->withInput();
        }

        $oldLampiranPaths = ($isSameSurat && $hasNewLampiran && is_array($induk->dokumen_lampiran))
            ? collect($induk->dokumen_lampiran)->pluck('path')->filter()->all()
            : [];

        // membungkus penyesuaian saldo MAK, pembaruan/pembuatan surat revisi, pewarisan verifikasi_paralel, dan log audit dalam satu transaksi atomik.
        $activePermohonan = DB::transaction(function () use (
            $isSameSurat,
            $induk,
            $oldNomorSurat,
            $targetNomorSurat,
            $validated,
            $nominalBaru,
            $selisih,
            $fileKakPath,
            $finalFilePermohonan,
            $finalFileSpesifikasiTeknis,
            $finalDokumenLampiran,
            $revisiKe,
            $user,
            $mak,
            $alokasiLama,
            $hasMak,
            $targetStatus,
            $oldVerifPerencanaan,
            $oldVerifWadir,
            $oldVerifPpk
        ) {
            if ($mak && $alokasiLama) {
                if ($selisih > 0) {
                    $mak->sisa_pagu_mak = max(0, (float) $mak->sisa_pagu_mak - $selisih);
                    $mak->save();
                } elseif ($selisih < 0) {
                    $mak->sisa_pagu_mak = (float) $mak->sisa_pagu_mak + abs($selisih);
                    $mak->save();
                }
            }

            if ($isSameSurat) {
                // ==========================================
                // KASUS 1: In-Place Update pada Surat Sama
                // ==========================================
                if ($alokasiLama) {
                    $alokasiLama->update(['nominal_alokasi' => $nominalBaru]);
                }

                $induk->update([
                    'judul_pengadaan' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'estimasi_total_pagu' => $nominalBaru,
                    'file_permohonan' => $finalFilePermohonan,
                    'file_kak' => $fileKakPath,
                    'file_spesifikasi_teknis' => $finalFileSpesifikasiTeknis,
                    'dokumen_lampiran' => $finalDokumenLampiran,
                    'status_permohonan' => $targetStatus,
                    'revisi_ke' => $revisiKe,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                ]);

                $activePermohonan = $induk;
            } else {
                // ==========================================
                // KASUS 2: Surat Baru dengan Nomor Pengajuan Revisi & Pewarisan MAK
                // ==========================================
                $revisi = PermohonanPengadaan::create([
                    'nomor_surat' => $targetNomorSurat,
                    'judul_pengadaan' => $validated['perihal'],
                    'tujuan_pengadaan_ringkas' => $validated['tujuan_pengadaan_ringkas'] ?? $induk->tujuan_pengadaan_ringkas ?? $validated['perihal'],
                    'estimasi_total_pagu' => $nominalBaru,
                    'kode_unit' => $induk->kode_unit,
                    'id_user' => $user->id_user,
                    'tanggal_pengajuan' => now(),
                    'file_permohonan' => $finalFilePermohonan,
                    'file_kak' => $fileKakPath,
                    'file_spesifikasi_teknis' => $finalFileSpesifikasiTeknis,
                    'dokumen_lampiran' => $finalDokumenLampiran,
                    'status_permohonan' => $targetStatus,
                    'revisi_ke' => $revisiKe,
                    'nomor_surat_revisi' => $induk->nomor_surat,
                    'tanggal_revisi' => now(),
                    'alasan_revisi' => $validated['keterangan_revisi'] ?? 'Perbaikan dokumen KAK dan spesifikasi',
                    'kode_mak' => $induk->kode_mak,
                    'status_anggaran' => $induk->status_anggaran ?? 'pending',
                    'id_user_ppk_awal' => $induk->id_user_ppk_awal,
                    'jenis_pengadaan_split' => $induk->jenis_pengadaan_split,
                    'nomor_memo_cek_mak' => $induk->nomor_memo_cek_mak,
                    'catatan_memo_cek_mak' => $induk->catatan_memo_cek_mak,
                    'file_memo_cek_mak' => $induk->file_memo_cek_mak,
                    'nomor_memo_ketersediaan_mak' => $induk->nomor_memo_ketersediaan_mak,
                    'catatan_memo_ketersediaan_mak' => $induk->catatan_memo_ketersediaan_mak,
                    'file_memo_ketersediaan_mak' => $induk->file_memo_ketersediaan_mak,
                ]);

                // Transfer alokasi MAK dari surat lama ke surat baru
                if ($alokasiLama) {
                    AlokasiMAK::create([
                        'nomor_surat' => $revisi->nomor_surat,
                        'kode_mak' => $alokasiLama->kode_mak,
                        'nominal_alokasi' => $nominalBaru,
                        'tanggal_alokasi' => now(),
                    ]);
                    $alokasiLama->delete();
                }

                // Tandai surat lama sebagai telah digantikan oleh revisi baru
                $induk->update([
                    'status_permohonan' => 'digantikan_oleh_revisi',
                    'kode_mak' => null,
                ]);

                $activePermohonan = $revisi;
            }

            // selalu menyertakan id_user pada updateOrCreate karena kolom verifikasi_paralel.id_user bersifat NOT NULL di PostgreSQL.
            VerifikasiParalel::updateOrCreate(
                ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PERENCANAAN],
                [
                    'id_user' => $oldVerifPerencanaan?->id_user ?? $user->id_user,
                    'status_keputusan' => 'menunggu',
                    'catatan_alasan' => null,
                    'tanggal_keputusan' => null,
                ]
            );

            if ($hasMak) {
                // WADIR 2: Jika sudah disetujui, wariskan persetujuan beserta memo fisiknya; selain itu (termasuk status revisi/ditolak) di-reset ke menunggu.
                if ($oldVerifWadir && $oldVerifWadir->status_keputusan === 'disetujui') {
                    VerifikasiParalel::updateOrCreate(
                        ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
                        [
                            'id_user' => $oldVerifWadir->id_user ?? $user->id_user,
                            'status_keputusan' => 'disetujui',
                            'nomor_memo' => $oldVerifWadir->nomor_memo,
                            'file_memo' => $oldVerifWadir->file_memo,
                            'catatan_alasan' => $oldVerifWadir->catatan_alasan,
                            'tanggal_keputusan' => $oldVerifWadir->tanggal_keputusan,
                        ]
                    );
                } else {
                    VerifikasiParalel::updateOrCreate(
                        ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
                        [
                            'id_user' => $oldVerifWadir?->id_user ?? $user->id_user,
                            'status_keputusan' => 'menunggu',
                            'nomor_memo' => null,
                            'file_memo' => null,
                            'catatan_alasan' => null,
                            'tanggal_keputusan' => null,
                        ]
                    );
                }

                // PPK: Jika sudah disetujui, wariskan persetujuan beserta memo fisiknya; jika menolak/minta revisi, di-reset ke menunggu bila akun PPK terisi.
                $ppkActorId = $oldVerifPpk?->id_user ?? $activePermohonan->id_user_ppk_awal;
                if ($oldVerifPpk && $oldVerifPpk->status_keputusan === 'disetujui') {
                    VerifikasiParalel::updateOrCreate(
                        ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                        [
                            'id_user' => $ppkActorId ?? $user->id_user,
                            'status_keputusan' => 'disetujui',
                            'nomor_memo' => $oldVerifPpk->nomor_memo,
                            'file_memo' => $oldVerifPpk->file_memo,
                            'catatan_alasan' => $oldVerifPpk->catatan_alasan,
                            'tanggal_keputusan' => $oldVerifPpk->tanggal_keputusan,
                        ]
                    );
                } elseif (! empty($ppkActorId)) {
                    VerifikasiParalel::updateOrCreate(
                        ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                        [
                            'id_user' => $ppkActorId,
                            'status_keputusan' => 'menunggu',
                            'nomor_memo' => null,
                            'file_memo' => null,
                            'catatan_alasan' => null,
                            'tanggal_keputusan' => null,
                        ]
                    );
                }
            } else {
                // reset antrean Wadir 2 & PPK (bila ada) ke menunggu apabila revisi diajukan sebelum penetapan MAK.
                VerifikasiParalel::updateOrCreate(
                    ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_WADIR_2],
                    [
                        'id_user' => $oldVerifWadir?->id_user ?? $user->id_user,
                        'status_keputusan' => 'menunggu',
                        'nomor_memo' => null,
                        'file_memo' => null,
                        'catatan_alasan' => null,
                        'tanggal_keputusan' => null,
                    ]
                );

                $ppkActorId = $oldVerifPpk?->id_user ?? $activePermohonan->id_user_ppk_awal;
                if (! empty($ppkActorId)) {
                    VerifikasiParalel::updateOrCreate(
                        ['nomor_surat' => $activePermohonan->nomor_surat, 'role_verifikator' => VerifikasiParalel::ROLE_PPK],
                        [
                            'id_user' => $ppkActorId,
                            'status_keputusan' => 'menunggu',
                            'nomor_memo' => null,
                            'file_memo' => null,
                            'catatan_alasan' => null,
                            'tanggal_keputusan' => null,
                        ]
                    );
                }
            }

            $aksiAudit = $hasMak ? 'REVISI_PERMOHONAN_PASCA_MAK' : 'REVISI_PERMOHONAN';
            $catatanAudit = $hasMak
                ? "Revisi usulan permohonan ke-{$revisiKe} ({$activePermohonan->nomor_surat}) pasca alokasi MAK diteruskan ke Bagian Perencanaan."
                : "Revisi usulan permohonan ke-{$revisiKe} ({$activePermohonan->nomor_surat}) oleh {$user->nama}";

            AuditLog::catat(
                $user->id_user,
                $aksiAudit,
                'permohonan_pengadaan',
                $activePermohonan->nomor_surat,
                $oldNomorSurat !== $activePermohonan->nomor_surat ? $oldNomorSurat : null,
                PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
                $catatanAudit
            );

            return $activePermohonan;
        });

        foreach ($oldLampiranPaths as $oldPath) {
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        if ($hasMak) {
            $this->notificationService->notifyRevisiKePerencanaan($activePermohonan);

            return redirect()->route('permohonan.index')->with(
                'success',
                "Usulan permohonan nomor {$activePermohonan->nomor_surat} berhasil diperbarui (Revisi ke-{$activePermohonan->revisi_ke}) dan diajukan kembali ke Bagian Perencanaan."
            );
        }

        // memicu notifikasi siklus revisi berkas usulan langsung ke Bagian Perencanaan.
        $this->notificationService->notifyPengajuanRevisi($activePermohonan);

        return redirect()->route('permohonan.index')->with(
            'success',
            "Usulan permohonan nomor {$activePermohonan->nomor_surat} berhasil direvisi (ke-{$activePermohonan->revisi_ke}) dan diajukan kembali ke Bagian Perencanaan."
        );
    }

    /**
     * Membatalkan dan menghapus usulan permohonan pengadaan melalui endpoint resource standar.
     *
     * Tujuan: mendelegasikan aksi hapus ke `destroyPermohonan` agar penanganan nomor surat
     * yang mengandung karakter slash ter-encode tetap konsisten.
     *
     * @param  string  $id  Nomor surat permohonan (bisa dalam bentuk raw maupun URL-encoded).
     * @return RedirectResponse Redirect ke daftar permohonan setelah penghapusan.
     *
     * Contoh pemanggilan:
     * `Route::delete('/permohonan/{id}', [PermohonanPengadaanController::class, 'destroy']);`
     */
    public function destroy(string $id): RedirectResponse
    {
        return $this->destroyPermohonan($id);
    }

    /**
     * Memvalidasi kepemilikan atau izin hapus lalu menjalankan cascade delete permohonan dalam transaksi DB.
     *
     * Tujuan: memastikan pengusul hanya bisa membatalkan usulannya sendiri saat statusnya masih
     * draft, diajukan, atau ditolak agar berkas yang sudah masuk paket tidak hilang sepihak.
     *
     * @param  string  $id  Nomor surat permohonan target penghapusan.
     * @return RedirectResponse Redirect ke halaman indeks permohonan dengan status penghapusan.
     *
     * Contoh pemanggilan:
     * `$response = $this->destroyPermohonan($nomorSurat);`
     */
    public function destroyPermohonan(string $id): RedirectResponse
    {
        $user = Auth::user();
        // cek variasi urldecode karena nomor surat dinas sering memuat tanda garis miring (/).
        $permohonan = PermohonanPengadaan::where('nomor_surat', $id)
            ->orWhere('nomor_surat', urldecode($id))
            ->orWhere('nomor_surat', rawurldecode($id))
            ->firstOrFail();

        $canDeleteAny = $user && $user->hasPermission('permohonan.delete');

        if (! $canDeleteAny) {
            if (! $user || ! $user->hasPermission('permohonan.create') || (int) $permohonan->id_user !== (int) $user->id_user) {
                abort(403, 'Akses Ditolak: Anda hanya dapat membatalkan permohonan milik sendiri.');
            }

            // kunci pembatalan mandiri bila berkas sudah diverifikasi atau diproses paket agar integritas audit terjaga.
            if (! in_array($permohonan->status_permohonan, ['draft', 'diajukan', 'ditolak'], true)) {
                return redirect()->route('permohonan.index')->with('error', 'Permohonan yang telah diverifikasi atau diproses paket tidak dapat dibatalkan.');
            }
        }

        $nomor = $permohonan->nomor_surat;

        DB::transaction(function () use ($permohonan) {
            $permohonan->cascadeDelete();
        });

        AuditLog::catat(
            $user->id_user,
            'HAPUS_PERMOHONAN',
            'permohonan_pengadaan',
            $nomor,
            null,
            'dihapus',
            "Pembatalan dan penghapusan usulan permohonan nomor {$nomor} oleh {$user->nama}"
        );

        return redirect()->route('permohonan.index')->with('success', "Usulan permohonan nomor {$nomor} berhasil dibatalkan dan dihapus.");
    }

    /**
     * Melakukan sinkronisasi otomatis status usulan yang telah lolos seluruh syarat verifikasi paralel.
     *
     * Tujuan: mengevaluasi pola AND-join (MAK Perencanaan tersedia + ACC Wadir 2 + ACC PPK)
     * agar usulan yang sudah memenuhi tiga syarat tersebut langsung naik ke status `disetujui`.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `app(PermohonanPengadaanController::class)->syncAllPermohonanDisetujuiParalel();`
     */
    public function syncAllPermohonanDisetujuiParalel(): void
    {
        $kandidat = PermohonanPengadaan::with(['verifikasiParalel'])
            ->whereNotIn('status_permohonan', [
                PermohonanPengadaan::STATUS_DISETUJUI,
                PermohonanPengadaan::STATUS_DIPROSES_PPBJ,
                PermohonanPengadaan::STATUS_DITOLAK,
                PermohonanPengadaan::STATUS_SELESAI,
                'digantikan_oleh_revisi',
            ])
            ->whereNotNull('kode_mak')
            ->get();

        foreach ($kandidat as $p) {
            $perencanaanAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PERENCANAAN)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty() || ! empty($p->kode_mak);

            $wadirAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_WADIR_2)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            // evaluasi kelulusan paralel (AND-join): Perencanaan (MAK) + Wadir 2 (ACC) + PPK (ACC) wajib terpenuhi bersama.
            $ppkAcc = $p->verifikasiParalel
                ->where('role_verifikator', VerifikasiParalel::ROLE_PPK)
                ->where('status_keputusan', 'disetujui')
                ->isNotEmpty();

            if ($perencanaanAcc && $wadirAcc && $ppkAcc) {
                $statusLama = $p->status_permohonan;
                $p->status = 'disetujui';
                $p->status_permohonan = PermohonanPengadaan::STATUS_DISETUJUI;
                $p->save();

                AuditLog::catat(
                    Auth::id() ?? $p->id_user,
                    'PERSETUJUAN_PENUH_AWAL_KE_PPBJ',
                    'permohonan_pengadaan',
                    $p->nomor_surat,
                    $statusLama,
                    'disetujui',
                    'Sinkronisasi otomatis: Usulan disetujui penuh oleh Perencanaan, Wadir 2, dan PPK terpilih; diteruskan ke PPBJ.'
                );
            }
        }
    }

    /**
     * Menyimpan berkas multi-upload `dokumen_lampiran[]` dan membangun array metadata lampiran.
     *
     * Tujuan: memisahkan penyimpanan fisik multi-file lampiran ke folder publik agar data JSON
     * lampiran permohonan menyimpan nama asli, path penyimpanan, tipe MIME, dan ukuran seragam.
     *
     * @param  Request  $request  Permintaan HTTP yang membawa array file `dokumen_lampiran`.
     * @return array<int, array{name: string, path: string, url: string, type: string, size: string, label: string}> Daftar metadata lampiran tersimpan.
     *
     * Contoh pemanggilan:
     * `$lampiran = $this->buildDokumenLampiranEntries($request);`
     */
    private function buildDokumenLampiranEntries(Request $request): array
    {
        $entries = [];

        if (! $request->hasFile('dokumen_lampiran')) {
            return $entries;
        }

        foreach ($request->file('dokumen_lampiran') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $ext = strtoupper($file->getClientOriginalExtension());
            $sizeBytes = $file->getSize();
            $sizeFormatted = ($sizeBytes > 1048576)
                ? number_format($sizeBytes / 1048576, 1).' MB'
                : number_format($sizeBytes / 1024, 0).' KB';
            $path = $file->store('lampiran_permohonan', 'public');

            $entries[] = [
                'name' => $originalName,
                'path' => $path,
                'url' => asset('storage/'.$path),
                'type' => $ext,
                'size' => $sizeFormatted,
                'label' => $originalName,
            ];
        }

        return $entries;
    }
}
