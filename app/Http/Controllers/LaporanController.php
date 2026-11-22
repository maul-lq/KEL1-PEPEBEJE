<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis controller LaporanController ini untuk menyajikan halaman Riwayat & Laporan terpadu berbasis komposisi modul RBAC serta menyediakan ekspor PDF (DomPDF) dan CSV RAW secara serentak.
 */

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\LaporanModularService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller Riwayat & Laporan Terpadu (LaporanController)
 *
 * mendelegasikan penyusunan data lintas 10 modul RBAC ke LaporanModularService agar controller
 * tetap ramping dan hanya mengurus negosiasi format respons (Blade HTML, JSON filter reaktif, PDF, dan CSV).
 *
 * Parameter konstruktor: `LaporanModularService $laporanService` — layanan komposisi laporan modular RBAC.
 * Nilai kembali         : Instance controller yang melayani grup rute `/laporan` dan redirect legacy `/monitoring`.
 *
 * Contoh pemanggilan:
 * `Route::get('/laporan', [LaporanController::class, 'laporanIndex'])->name('laporan.index');`
 */
class LaporanController extends Controller
{
    /**
     * menyuntikkan LaporanModularService melalui constructor property promotion agar seluruh endpoint laporan memakai logika RBAC yang sama.
     *
     * @param  LaporanModularService  $laporanService  mengikat service pembangun data riwayat, KPI, PDF, dan CSV.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$controller = app(LaporanController::class);`
     */
    public function __construct(public LaporanModularService $laporanService) {}

    /**
     * menampilkan halaman Riwayat & Laporan terpadu atau mengembalikan payload JSON terbaru ketika filter Alpine.js diubah.
     *
     * @param  Request  $request  membaca tab aktif, filter periode/unit/status/modul, serta flag `laporan_only` untuk request JSON.
     * @return View|JsonResponse mengembalikan view `laporan.index` atau JSON sinkronisasi filter laporan dan riwayat.
     *
     * Contoh pemanggilan:
     * `GET /laporan?tab=laporan&module=semua&tahun=2026`
     */
    public function laporanIndex(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $activeTab = $request->input('tab', 'riwayat');
        $rawFilters = $this->extractFilters($request);

        $modularPayload = $this->laporanService->buildModularPayload($user, $rawFilters);
        $currentView = $modularPayload['current_view'];

        if ($request->wantsJson()) {
            $response = [
                'status' => 'success',
                'active_tab' => $activeTab,
                'modular' => $modularPayload,
                'laporan' => [
                    'cards' => $currentView['cards'],
                    'ringkasan_title' => $currentView['ringkasan_title'],
                    'table_title' => $currentView['table_title'],
                    'columns' => $currentView['columns'],
                    'rows' => $currentView['rows'],
                ],
            ];

            // melewati pembangunan riwayat saat frontend hanya mengganti filter Tab Laporan agar respon JSON tetap cepat.
            if (! $request->boolean('laporan_only')) {
                $response['riwayat'] = $this->laporanService->buildRiwayat($user);
            }

            return response()->json($response);
        }

        $riwayatList = $this->laporanService->buildRiwayat($user);
        $units = Unit::orderBy('kode_unit')->get();

        $tahun = $modularPayload['filters_applied']['tahun'];
        $bulan = $modularPayload['filters_applied']['bulan'];
        $kodeUnit = $modularPayload['filters_applied']['unit'];
        $canDownload = $user->hasPermission('dokumen.unduh_pdf');
        $userUnitCode = (string) ($user->kode_unit ?? '');
        $userUnitName = (string) ($user->unit?->nama_unit ?? $user->kode_unit ?? '');

        return view('laporan.index', compact(
            'riwayatList',
            'modularPayload',
            'units',
            'tahun',
            'bulan',
            'kodeUnit',
            'activeTab',
            'canDownload',
            'userUnitCode',
            'userUnitName'
        ));
    }

    /**
     * menyediakan metode alias `index` yang meneruskan ke `laporanIndex` demi konsistensi konvensi resource controller.
     *
     * @param  Request  $request  meneruskan objek request beserta query filter.
     * @return View|JsonResponse mengembalikan view `laporan.index` atau JSON payload laporan.
     *
     * Contoh pemanggilan:
     * `$controller->index($request);`
     */
    public function index(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
    }

    /**
     * merender dan mengunduhkan berkas PDF landscape berformat rapi menggunakan DomPDF sesuai modul RBAC dan filter aktif pengguna.
     *
     * @param  Request  $request  mengekstrak parameter filter yang sedang aktif pada bar filter laporan.
     * @return Response mengembalikan unduhan berkas PDF bernama `Laporan_PBJ_PNJ_*.pdf`.
     *
     * Contoh pemanggilan:
     * `GET /laporan/unduh-pdf?module=semua&tahun=2026`
     */
    public function unduhLaporanPdf(Request $request): Response
    {
        $user = Auth::user();
        $exportData = $this->laporanService->resolveExportData($user, $this->extractFilters($request));

        $suffix = $exportData['selected_module'] === 'semua'
            ? 'Gabungan_RBAC'
            : $exportData['selected_module'];
        $tahunTag = $exportData['filters_applied']['tahun'] !== ''
            ? $exportData['filters_applied']['tahun']
            : 'SemuaTahun';
        $filename = "Laporan_PBJ_PNJ_{$suffix}_{$tahunTag}_".now()->format('Ymd_His').'.pdf';

        // memilih orientasi A4 landscape supaya tabel rincian lintas kolom dan badge status tidak terpotong di DomPDF.
        $pdf = Pdf::loadView('laporan.pdf', [
            'user' => $exportData['user'],
            'roleLabel' => $exportData['role_label'],
            'activeModuleLabels' => $exportData['active_module_labels'],
            'selectedModule' => $exportData['selected_module'],
            'sections' => $exportData['sections'],
            'filtersApplied' => $exportData['filters_applied'],
            'filterSummaryText' => $exportData['filter_summary_text'],
            'generatedAt' => now()->translatedFormat('d M Y, H:i:s').' WIB',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * menghasilkan unduhan data mentah CSV (UTF-8 BOM, angka numerik murni) yang dipicu bersamaan dengan unduhan PDF.
     *
     * @param  Request  $request  mengekstrak parameter filter aktif agar isi CSV selalu sinkron dengan PDF dan tabel UI.
     * @return Response mengembalikan file `text/csv; charset=UTF-8` dengan header `no-store`.
     *
     * Contoh pemanggilan:
     * `GET /laporan/unduh-csv?module=anggaran_mak&tahun=2026`
     */
    public function unduhLaporanCsv(Request $request): Response
    {
        $user = Auth::user();
        $csv = $this->laporanService->buildCsvDownload($user, $this->extractFilters($request));

        // mematikan cache pada respons unduhan agar hasil ekspor selalu mencerminkan data transaksi detik terakhir.
        return response($csv['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * mempertahankan wrapper rute unduh ringkasan PDF agar endpoint dan pengujian terdahulu tetap berfungsi melalui jalur modular terbaru.
     *
     * @param  Request  $request  meneruskan query filter ke `unduhLaporanPdf`.
     * @return Response mengembalikan unduhan PDF laporan.
     *
     * Contoh pemanggilan:
     * `GET /laporan/unduh-laporan-pdf`
     */
    public function unduhLaporanRingkasanPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * mempertahankan wrapper rute unduh detail PDF agar hanya ada satu sumber generasi PDF yang terawat.
     *
     * @param  Request  $request  meneruskan query filter ke `unduhLaporanPdf`.
     * @return Response mengembalikan unduhan PDF laporan.
     *
     * Contoh pemanggilan:
     * `GET /laporan/unduh-detail-pdf`
     */
    public function unduhLaporanDetailPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * mempertahankan alias rekapitulasi anggaran terdahulu dan meneruskannya ke `laporanIndex`.
     *
     * @param  Request  $request  meneruskan request filter ke indeks laporan terpadu.
     * @return View|JsonResponse mengembalikan view atau JSON laporan.
     *
     * Contoh pemanggilan:
     * `GET /laporan/rekapitulasi-anggaran`
     */
    public function laporanRekapitulasiIndex(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
    }

    /**
     * mempertahankan alias unduh rekapitulasi PDF terdahulu dan meneruskannya ke `unduhLaporanPdf`.
     *
     * @param  Request  $request  meneruskan request filter ke generator PDF terpadu.
     * @return Response mengembalikan unduhan PDF laporan.
     *
     * Contoh pemanggilan:
     * `GET /laporan/rekapitulasi-anggaran/pdf`
     */
    public function unduhRekapitulasiPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * mengalihkan akses modul monitoring lama secara permanen ke halaman Riwayat & Laporan terpadu.
     *
     * @return RedirectResponse mengembalikan redirect menuju rute `laporan.index`.
     *
     * Contoh pemanggilan:
     * `GET /monitoring`
     */
    public function monitoringIndex(): RedirectResponse
    {
        return redirect()->route('laporan.index');
    }

    /**
     * mengekstrak dan menormalkan seluruh query parameter filter laporan dengan tahun berjalan sebagai default ketika `tahun` tidak dikirim.
     *
     * @param  Request  $request  membaca input `tahun`, `bulan`, `unit`, `status`, `role_filter`, `q`, dan `module`.
     * @return array<string, mixed> mengembalikan array filter ternormalisasi bertipe string.
     *
     * Contoh pemanggilan:
     * `$filters = $this->extractFilters($request);`
     */
    private function extractFilters(Request $request): array
    {
        return [
            // membedakan ketiadaan parameter 'tahun' (default tahun berjalan) dari string kosong ('Semua Tahun') agar pengguna bisa memilih opsi Semua Tahun.
            'tahun' => $request->has('tahun') ? (string) $request->input('tahun') : date('Y'),
            'bulan' => (string) $request->input('bulan', ''),
            'unit' => (string) $request->input('unit', ''),
            'status' => (string) $request->input('status', ''),
            'role_filter' => (string) $request->input('role_filter', ''),
            'q' => (string) $request->input('q', ''),
            'module' => (string) $request->input('module', ''),
        ];
    }
}
