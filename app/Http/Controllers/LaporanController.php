<?php

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
 * Controller Riwayat & Laporan Terpadu (Modul 8)
 *
 * Mengelola Tab Riwayat Aktivitas Audit & Tab Laporan Statistik berbasis komposisi izin RBAC.
 */
class LaporanController extends Controller
{
    public function __construct(public LaporanModularService $laporanService) {}

    /**
     * Menampilkan antarmuka terpadu Riwayat dan Laporan serta melayani pembaruan filter JSON.
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
     * Alias indeks standar resource.
     */
    public function index(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
    }

    /**
     * Mengunduh Laporan PDF terformat rapi berbasis modul RBAC aktif (< 3 Detik - NFR 12).
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
     * Mengunduh Laporan data mentah (RAW CSV) UTF-8 BOM berbasis modul RBAC dan filter aktif.
     */
    public function unduhLaporanCsv(Request $request): Response
    {
        $user = Auth::user();
        $csv = $this->laporanService->buildCsvDownload($user, $this->extractFilters($request));

        return response($csv['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Wrapper kompatibilitas unduh ringkasan PDF terdahulu.
     */
    public function unduhLaporanRingkasanPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * Wrapper kompatibilitas unduh detail PDF terdahulu.
     */
    public function unduhLaporanDetailPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * Wrapper kompatibilitas laporan rekapitulasi terdahulu.
     */
    public function laporanRekapitulasiIndex(Request $request): View|JsonResponse
    {
        return $this->laporanIndex($request);
    }

    /**
     * Wrapper kompatibilitas unduh rekapitulasi terdahulu.
     */
    public function unduhRekapitulasiPdf(Request $request): Response
    {
        return $this->unduhLaporanPdf($request);
    }

    /**
     * Redirect monitoring lama ke laporan.index.
     */
    public function monitoringIndex(): RedirectResponse
    {
        return redirect()->route('laporan.index');
    }

    /**
     * Mengekstrak dan menormalisasi parameter filter request laporan.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        return [
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
