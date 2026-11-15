<?php

namespace App\Services;

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\KontrakSPK;
use App\Models\MemoBayar;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\PermohonanPengadaan;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Layanan Komposisi Laporan & Riwayat Modular Berbasis RBAC Permissions.
 *
 * Membangun daftar seksi laporan secara kumulatif berdasarkan izin aktif pada peran pengguna
 * tanpa mengandalkan pengecekan string role hardcoded, sekaligus menyediakan ekspor CSV RAW
 * dan data terstruktur untuk generator PDF (DomPDF).
 */
class LaporanModularService
{
    /**
     * Urutan kanonik 10 modul laporan independen berbasis kelompok izin RBAC.
     *
     * @var array<int, string>
     */
    public const MODULE_ORDER = [
        'administrasi_rbac',
        'rekapitulasi_dipa',
        'permohonan_sendiri',
        'verifikasi_usulan',
        'anggaran_mak',
        'pengadaan_ppbj',
        'penugasan_pp',
        'komitmen_ppk',
        'penerimaan_bmn',
        'pembayaran_sp2d',
    ];

    /**
     * Menentukan daftar key modul laporan yang aktif sesuai izin RBAC pengguna saat ini.
     * Mendukung role tunggal maupun role gabungan (misal modul X + modul Y).
     *
     * @return array<int, string>
     */
    public function resolveActiveModuleKeys(User $user): array
    {
        $user->loadMissing(['unit', 'roleEntity.permissions']);

        $active = [];

        if ($user->hasAnyPermission(['admin.approval_registrasi', 'admin.user_manage', 'admin.role_manage'])) {
            $active[] = 'administrasi_rbac';
        }

        if ($user->hasPermission('laporan.rekapitulasi')) {
            $active[] = 'rekapitulasi_dipa';
        }

        if ($user->hasAnyPermission(['permohonan.create', 'permohonan.view_own', 'permohonan.revisi']) && ! $user->hasPermission('permohonan.view_all')) {
            $active[] = 'permohonan_sendiri';
        }

        if ($user->hasAnyPermission(['verifikasi.bidang_2_view', 'verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide', 'permohonan.view_all'])) {
            $active[] = 'verifikasi_usulan';
        }

        if ($user->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'anggaran.mak_lock', 'permohonan.split_mak'])) {
            $active[] = 'anggaran_mak';
        }

        if ($user->hasAnyPermission(['paket.create', 'paket.view_all', 'paket.reassign_ppk', 'spk.create', 'pp.monitoring_aktivitas', 'lihat_pp'])) {
            $active[] = 'pengadaan_ppbj';
        }

        if ($user->hasPermission('reviu.pp')) {
            $active[] = 'penugasan_pp';
        }

        if ($user->hasPermission('reviu.ppk')) {
            $active[] = 'komitmen_ppk';
        }

        if ($user->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign'])) {
            $active[] = 'penerimaan_bmn';
        }

        if ($user->hasAnyPermission(['pembayaran.pengajuan', 'pembayaran.memo_ppk', 'pembayaran.memo_wadir2', 'pembayaran.spp_terbitkan', 'pembayaran.pencairan_sp2d'])) {
            $active[] = 'pembayaran_sp2d';
        }

        return $active;
    }

    /**
     * Memeriksa apakah user hanya berhak melihat data pembayarannya sendiri (isolasi PPK murni).
     */
    public function isStrictPpkPembayaranScope(User $user): bool
    {
        return $user->hasPermission('pembayaran.memo_ppk')
            && ! $user->hasAnyPermission([
                'pembayaran.pencairan_sp2d',
                'pembayaran.memo_wadir2',
                'pembayaran.pengajuan',
                'pembayaran.spp_terbitkan',
                'paket.view_all',
            ]);
    }

    /**
     * Membangun daftar Riwayat Aktivitas (Tab 1) menggunakan gabungan query (union scope)
     * seluruh izin RBAC aktif pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildRiwayat(User $user): array
    {
        $user->loadMissing(['unit', 'roleEntity.permissions']);

        $auditQuery = AuditLog::with('user')->latest('waktu_log');
        $hasGlobalAudit = $user->hasPermission('audit_log.view');

        if (! $hasGlobalAudit) {
            $ownPermohonanNomor = PermohonanPengadaan::where('id_user', $user->id_user)->pluck('nomor_surat');
            $ownPaketNomor = PaketPengadaan::whereIn('nomor_surat', $ownPermohonanNomor)->pluck('nomor_paket');
            $ppPaketNomor = $user->hasPermission('reviu.pp')
                ? PaketPengadaan::where(fn (Builder $q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))->pluck('nomor_paket')
                : collect();
            $ppkPaketNomor = $user->hasPermission('reviu.ppk')
                ? PaketPengadaan::where('id_user_ppk', $user->id_user)->pluck('nomor_paket')
                : collect();

            $auditQuery->where(function (Builder $q) use ($user, $ownPermohonanNomor, $ownPaketNomor, $ppPaketNomor, $ppkPaketNomor) {
                $q->where('id_user', $user->id_user);

                if ($user->hasAnyPermission(['permohonan.view_all', 'verifikasi.bidang_2_view', 'verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide'])) {
                    $q->orWhereIn('nama_entitas', ['permohonan_pengadaan', 'verifikasi_paralel'])
                        ->orWhere('jenis_aksi', 'like', '%TELAAH%')
                        ->orWhere('jenis_aksi', 'like', '%WADIR%')
                        ->orWhere('jenis_aksi', 'like', '%BIDANG_2%')
                        ->orWhere('jenis_aksi', 'like', '%AJUKAN%');
                } elseif ($user->hasAnyPermission(['permohonan.create', 'permohonan.view_own', 'permohonan.revisi'])) {
                    if ($ownPermohonanNomor->isNotEmpty()) {
                        $q->orWhere(fn (Builder $sub) => $sub->where('nama_entitas', 'permohonan_pengadaan')->whereIn('id_entitas', $ownPermohonanNomor));
                    }
                    if ($ownPaketNomor->isNotEmpty()) {
                        $q->orWhere(fn (Builder $sub) => $sub->where('nama_entitas', 'paket_pengadaan')->whereIn('id_entitas', $ownPaketNomor));
                    }
                }

                if ($user->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'anggaran.mak_lock', 'permohonan.split_mak'])) {
                    $q->orWhereIn('nama_entitas', ['anggaran_mak', 'alokasi_mak'])
                        ->orWhere('jenis_aksi', 'like', '%MAK%')
                        ->orWhere('jenis_aksi', 'like', '%ANGGARAN%');
                }

                if ($user->hasAnyPermission(['paket.create', 'paket.view_all', 'spk.create', 'pp.monitoring_aktivitas', 'lihat_pp'])) {
                    $q->orWhereIn('nama_entitas', ['paket_pengadaan', 'vendor', 'kontrak_spk', 'reminder_pengadaan'])
                        ->orWhere('jenis_aksi', 'like', '%PPBJ%')
                        ->orWhere('jenis_aksi', 'like', '%PAKET%')
                        ->orWhere('jenis_aksi', 'like', '%ROUTING%');
                }

                if ($user->hasPermission('reviu.pp')) {
                    $q->orWhere('jenis_aksi', 'like', '%_PP%')
                        ->orWhere('jenis_aksi', 'like', '%NEGOSIASI%');
                    if ($ppPaketNomor->isNotEmpty()) {
                        $q->orWhere(fn (Builder $sub) => $sub->where('nama_entitas', 'paket_pengadaan')->whereIn('id_entitas', $ppPaketNomor));
                    }
                }

                if ($user->hasPermission('reviu.ppk')) {
                    $q->orWhere('jenis_aksi', 'like', '%PPK%')
                        ->orWhere('jenis_aksi', 'like', '%APPROVAL_LELANG%');
                    if ($ppkPaketNomor->isNotEmpty()) {
                        $q->orWhere(fn (Builder $sub) => $sub->where('nama_entitas', 'paket_pengadaan')->whereIn('id_entitas', $ppkPaketNomor));
                    }
                }

                if ($user->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign'])) {
                    $q->orWhereIn('nama_entitas', ['penerimaan_barang', 'kontrak_spk'])
                        ->orWhere('jenis_aksi', 'like', '%BAST%')
                        ->orWhere('jenis_aksi', 'like', '%PENERIMAAN%');
                }

                if ($user->hasAnyPermission(['pembayaran.pengajuan', 'pembayaran.memo_ppk', 'pembayaran.memo_wadir2', 'pembayaran.spp_terbitkan', 'pembayaran.pencairan_sp2d'])) {
                    $q->orWhereIn('nama_entitas', ['pembayaran', 'memo_bayar', 'transaksi_pencairan'])
                        ->orWhere('jenis_aksi', 'like', '%BAYAR%')
                        ->orWhere('jenis_aksi', 'like', '%CAIR%')
                        ->orWhere('jenis_aksi', 'like', '%SP2D%')
                        ->orWhere('jenis_aksi', 'like', '%MEMO_BAYAR%')
                        ->orWhere('jenis_aksi', 'like', '%SPP%');
                }

                if ($user->hasAnyPermission(['admin.approval_registrasi', 'admin.user_manage', 'admin.role_manage'])) {
                    $q->orWhereIn('nama_entitas', ['users', 'roles', 'permissions'])
                        ->orWhere('jenis_aksi', 'like', '%USER%')
                        ->orWhere('jenis_aksi', 'like', '%ROLE%')
                        ->orWhere('jenis_aksi', 'like', '%REGISTRASI%');
                }
            });
        }

        $auditLogsRaw = $auditQuery->take(80)->get();
        $permohonanLookup = PermohonanPengadaan::with('unit')->get()->keyBy('nomor_surat');
        $paketLookup = PaketPengadaan::with('permohonan.unit')->get()->keyBy('nomor_paket');

        $riwayatList = [];
        foreach ($auditLogsRaw as $log) {
            $riwayatList[] = $this->formatAuditLogItem($log, $permohonanLookup, $paketLookup);
        }

        $riwayatList = $this->appendFallbackRiwayat($user, $riwayatList);

        usort($riwayatList, fn (array $a, array $b): int => strcmp((string) ($b['waktu_raw'] ?? ''), (string) ($a['waktu_raw'] ?? '')));

        return array_slice($riwayatList, 0, 80);
    }

    /**
     * Membangun payload lengkap Tab Laporan (komposisi seluruh modul aktif, filter adaptif, dan view gabungan).
     *
     * @param  array<string, mixed>  $rawFilters
     * @return array<string, mixed>
     */
    public function buildModularPayload(User $user, array $rawFilters): array
    {
        $activeKeys = $this->resolveActiveModuleKeys($user);
        $hasMultiple = count($activeKeys) >= 2;
        $requestedModule = (string) ($rawFilters['module'] ?? '');

        $selectedModule = $hasMultiple ? 'semua' : ($activeKeys[0] ?? 'kosong');
        if ($requestedModule === 'semua' && $hasMultiple) {
            $selectedModule = 'semua';
        } elseif ($requestedModule !== '' && in_array($requestedModule, $activeKeys, true)) {
            $selectedModule = $requestedModule;
        }

        $tahun = isset($rawFilters['tahun']) && $rawFilters['tahun'] !== '' && $rawFilters['tahun'] !== 'all'
            ? (int) $rawFilters['tahun']
            : null;
        $bulan = isset($rawFilters['bulan']) && $rawFilters['bulan'] !== ''
            ? (int) $rawFilters['bulan']
            : null;
        if ($bulan !== null && ($bulan < 1 || $bulan > 12)) {
            $bulan = null;
        }

        $kodeUnit = trim((string) ($rawFilters['unit'] ?? ''));
        $statusFilter = trim((string) ($rawFilters['status'] ?? ''));
        $roleFilter = trim((string) ($rawFilters['role_filter'] ?? ''));
        $search = trim((string) ($rawFilters['q'] ?? ''));

        $filters = [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'unit' => $kodeUnit,
            'status' => $statusFilter,
            'role_filter' => $roleFilter,
            'q' => $search,
        ];

        $modules = [];
        foreach ($activeKeys as $key) {
            $modules[$key] = $this->buildSingleModule($key, $user, $filters);
        }

        $semuaView = $hasMultiple ? $this->buildSemuaModulSummary($modules, $user) : null;
        $tabs = $this->buildTabDescriptors($modules, $hasMultiple, $semuaView);
        $rolesList = in_array('administrasi_rbac', $activeKeys, true)
            ? Role::orderBy('label')->get(['nama_role', 'label'])->map(fn (Role $r): array => [
                'value' => $r->nama_role,
                'label' => $r->label ?: strtoupper($r->nama_role),
            ])->values()->all()
            : [];

        $currentView = $selectedModule === 'semua' && $semuaView !== null
            ? $semuaView
            : ($modules[$selectedModule] ?? $this->buildEmptyModulePayload());

        return [
            'active_module_keys' => $activeKeys,
            'has_multiple_modules' => $hasMultiple,
            'selected_module' => $selectedModule,
            'tabs' => $tabs,
            'modules' => $modules,
            'semua_view' => $semuaView,
            'current_view' => $currentView,
            'roles_list' => $rolesList,
            'filters_applied' => [
                'tahun' => $tahun !== null ? (string) $tahun : '',
                'bulan' => $bulan !== null ? (string) $bulan : '',
                'unit' => $kodeUnit,
                'status' => $statusFilter,
                'role_filter' => $roleFilter,
                'q' => $search,
                'module' => $selectedModule,
            ],
        ];
    }

    /**
     * Mengambil seksi-seksi modul yang siap diekspor ke PDF atau CSV dengan validasi RBAC ketat.
     *
     * @param  array<string, mixed>  $rawFilters
     * @return array<string, mixed>
     */
    public function resolveExportData(User $user, array $rawFilters): array
    {
        $requestedModule = trim((string) ($rawFilters['module'] ?? ''));
        $activeKeys = $this->resolveActiveModuleKeys($user);

        if ($requestedModule !== '' && $requestedModule !== 'semua' && ! in_array($requestedModule, $activeKeys, true)) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki hak akses untuk mengunduh laporan modul tersebut.');
        }

        $payload = $this->buildModularPayload($user, $rawFilters);
        $selectedModule = $payload['selected_module'];

        $sections = [];
        if ($selectedModule === 'semua') {
            foreach ($payload['modules'] as $mod) {
                $sections[] = $mod;
            }
        } elseif (isset($payload['modules'][$selectedModule])) {
            $sections[] = $payload['modules'][$selectedModule];
        }

        return [
            'user' => $user,
            'role_label' => $user->roleEntity?->label ?? 'Pengguna',
            'active_module_labels' => array_map(fn (array $m): string => $m['tab_label'], $payload['modules']),
            'selected_module' => $selectedModule,
            'sections' => $sections,
            'filters_applied' => $payload['filters_applied'],
            'filter_summary_text' => $this->formatFilterSummaryText($payload['filters_applied']),
        ];
    }

    /**
     * Menghasilkan konten file CSV RAW (UTF-8 BOM, delimiter koma RFC-4180, angka murni numerik).
     *
     * @param  array<string, mixed>  $rawFilters
     * @return array{filename: string, content: string}
     */
    public function buildCsvDownload(User $user, array $rawFilters): array
    {
        $export = $this->resolveExportData($user, $rawFilters);
        $sections = $export['sections'];
        $filters = $export['filters_applied'];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['POLITEKNIK NEGERI JAKARTA - SIM-PBJ']);
        fputcsv($handle, ['DATA LAPORAN (CSV)']);
        fputcsv($handle, ['Pengguna', $user->nama, 'Peran', $export['role_label']]);
        fputcsv($handle, ['Cakupan Modul', $export['selected_module'] === 'semua' ? implode(' + ', $export['active_module_labels']) : ($sections[0]['tab_label'] ?? '-')]);
        fputcsv($handle, ['Filter Aktif', $export['filter_summary_text'], 'Waktu Unduh', now()->format('d/m/Y H:i:s')]);
        fputcsv($handle, []);

        if (count($sections) === 0) {
            fputcsv($handle, ['Keterangan']);
            fputcsv($handle, ['Belum ada modul laporan yang aktif pada hak akses akun ini.']);
        } elseif (count($sections) === 1) {
            $sec = $sections[0];
            fputcsv($handle, $sec['csv_columns']);
            foreach ($sec['csv_rows'] as $row) {
                fputcsv($handle, $row);
            }
        } else {
            foreach ($sections as $idx => $sec) {
                fputcsv($handle, ['=== SEKSI '.($idx + 1).': '.strtoupper($sec['tab_label']).' ===']);
                fputcsv($handle, ['Ringkasan KPI', ...array_map(fn (array $c): string => $c['label'].': '.$c['val'], $sec['cards'])]);
                fputcsv($handle, $sec['csv_columns']);
                if (count($sec['csv_rows']) === 0) {
                    fputcsv($handle, ['(Tidak ada data pada periode/filter terpilih)']);
                } else {
                    foreach ($sec['csv_rows'] as $row) {
                        fputcsv($handle, $row);
                    }
                }
                fputcsv($handle, []);
            }
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        $suffix = $export['selected_module'] === 'semua' ? 'Gabungan' : $export['selected_module'];
        $tahunTag = $filters['tahun'] !== '' ? $filters['tahun'] : 'SemuaTahun';
        $filename = 'Laporan_CSV_PNJ_'.$suffix.'_'.$tahunTag.'_'.now()->format('Ymd_His').'.csv';

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * Membangun descriptor tab ringkas untuk switcher header Ringkasan & Tabel di frontend.
     *
     * @param  array<string, array<string, mixed>>  $modules
     * @param  array<string, mixed>|null  $semuaView
     * @return array<int, array<string, mixed>>
     */
    private function buildTabDescriptors(array $modules, bool $hasMultiple, ?array $semuaView): array
    {
        $tabs = [];
        if ($hasMultiple && $semuaView !== null) {
            $tabs[] = [
                'key' => 'semua',
                'label' => 'Semua Modul ('.count($modules).')',
                'count' => count($semuaView['rows']),
                'filters' => $semuaView['filters'],
            ];
        }

        foreach ($modules as $mod) {
            $tabs[] = [
                'key' => $mod['key'],
                'label' => $mod['tab_label'],
                'count' => count($mod['rows']),
                'filters' => $mod['filters'],
            ];
        }

        return $tabs;
    }

    /**
     * Membangun tampilan ringkasan terpadu saat tab 'Semua Modul' dipilih (untuk Role Gabungan X + Y).
     *
     * @param  array<string, array<string, mixed>>  $modules
     * @return array<string, mixed>
     */
    private function buildSemuaModulSummary(array $modules, User $user): array
    {
        $combinedCards = [];
        $combinedRows = [];
        $usesTahun = false;
        $usesBulan = false;
        $unitMode = 'none';

        foreach ($modules as $mod) {
            $primaryCard = $mod['cards'][0] ?? null;
            if ($primaryCard !== null) {
                $combinedCards[] = [
                    'label' => $mod['tab_label'].' — '.$primaryCard['label'],
                    'val' => $primaryCard['val'],
                    'icon' => $primaryCard['icon'] ?? 'chart-pie',
                    'sub' => ($mod['cards'][1]['label'] ?? 'Total Data').': '.($mod['cards'][1]['val'] ?? count($mod['rows'])),
                ];
            }

            if ($mod['filters']['tahun']) {
                $usesTahun = true;
            }
            if ($mod['filters']['bulan']) {
                $usesBulan = true;
            }
            if ($mod['filters']['unit'] === 'all') {
                $unitMode = 'all';
            } elseif ($mod['filters']['unit'] === 'locked_own' && $unitMode === 'none') {
                $unitMode = 'locked_own';
            }

            foreach (array_slice($mod['rows'], 0, 15) as $r) {
                $combinedRows[] = [
                    'no' => count($combinedRows) + 1,
                    'col1' => $mod['tab_label'],
                    'col2' => ($r['col1'] ?? '-').' — '.($r['col2'] ?? '-'),
                    'col3' => $r['col3'] ?? '-',
                    'col4' => $r['col4'] ?? '-',
                    'col5' => $r['col5'] ?? '-',
                    'link' => $r['link'] ?? null,
                    'nomor_surat' => $r['nomor_surat'] ?? '',
                    'judul' => $r['judul'] ?? '',
                    'kode_unit' => $r['kode_unit'] ?? '',
                    'modul' => $mod['tab_label'],
                ];
            }
        }

        return [
            'key' => 'semua',
            'tab_label' => 'Semua Modul',
            'description' => 'Gabungan seluruh modul laporan sesuai hak akses akun Anda.',
            'ringkasan_title' => 'Ringkasan Terpadu ('.count($modules).' Modul Aktif)',
            'table_title' => 'Ikhtisar Data Lintas Modul Aktif ('.$user->nama.')',
            'filters' => [
                'tahun' => $usesTahun,
                'bulan' => $usesBulan,
                'unit' => $unitMode,
                'status_label' => null,
                'status_options' => [],
                'has_role_filter' => in_array('administrasi_rbac', array_keys($modules), true),
            ],
            'cards' => $combinedCards,
            'columns' => ['No.', 'Modul', 'Referensi & Uraian Utama', 'Keterangan / Tanggal', 'Nilai / Atribut', 'Status / Ringkas'],
            'rows' => $combinedRows,
            'csv_columns' => [],
            'csv_rows' => [],
        ];
    }

    /**
     * Dispatcher pembangunan data untuk satu key modul laporan.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildSingleModule(string $key, User $user, array $filters): array
    {
        return match ($key) {
            'rekapitulasi_dipa' => $this->buildModulRekapitulasiDipa($filters),
            'permohonan_sendiri' => $this->buildModulPermohonanSendiri($user, $filters),
            'verifikasi_usulan' => $this->buildModulVerifikasiUsulan($filters),
            'anggaran_mak' => $this->buildModulAnggaranMak($filters),
            'pengadaan_ppbj' => $this->buildModulPengadaanPpbj($filters),
            'penugasan_pp' => $this->buildModulPenugasanPp($user, $filters),
            'komitmen_ppk' => $this->buildModulKomitmenPpk($user, $filters),
            'penerimaan_bmn' => $this->buildModulPenerimaanBmn($filters),
            'pembayaran_sp2d' => $this->buildModulPembayaranSp2d($user, $filters),
            'administrasi_rbac' => $this->buildModulAdministrasiRbac($filters),
            default => $this->buildEmptyModulePayload(),
        };
    }

    /**
     * Modul 1: Rekapitulasi DIPA & Distribusi Unit (`laporan.rekapitulasi`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulRekapitulasiDipa(array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $search = $filters['q'];

        $totalPaguDipa = (float) AnggaranMAK::query()
            ->when($tahun, fn (Builder $q) => $q->where('tahun_anggaran', $tahun))
            ->sum('pagu_anggaran');

        $permohonans = PermohonanPengadaan::query()
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_pengajuan', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_pengajuan', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->where('kode_unit', $kodeUnit))
            ->get();

        $totalUsulan = (float) $permohonans->sum('estimasi_total_pagu');

        $kontrakQuery = KontrakSPK::query()
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_spk', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_spk', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('paket.permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)));
        $totalKontrak = (float) $kontrakQuery->sum('nilai_kontrak');

        $cairQuery = TransaksiPencairan::query()
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_transfer', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_transfer', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('pembayaran.paket.permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)));
        $totalRealisasi = (float) $cairQuery->sum('nominal_transfer');

        $sisaAnggaran = max(0, $totalPaguDipa - $totalRealisasi);
        $persentaseSerapan = $totalPaguDipa > 0 ? round(($totalRealisasi / $totalPaguDipa) * 100, 1) : 0.0;

        $units = Unit::orderBy('kode_unit')->get();
        $groupedByUnit = $permohonans->groupBy('kode_unit');

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($units as $u) {
            if ($kodeUnit !== '' && $u->kode_unit !== $kodeUnit) {
                continue;
            }
            $unitItems = $groupedByUnit->get($u->kode_unit, collect());
            $countP = $unitItems->count();
            $nilaiP = (float) $unitItems->sum('estimasi_total_pagu');
            $sharePct = $totalUsulan > 0 ? round(($nilaiP / $totalUsulan) * 100, 1) : 0.0;

            $row = [
                'no' => $no,
                'col1' => $u->kode_unit,
                'col2' => $u->nama_unit,
                'col3' => $countP.' Berkas',
                'col4' => 'Rp '.number_format($nilaiP, 0, ',', '.'),
                'col5' => $sharePct.'%',
                'link' => route('permohonan.index', ['unit' => $u->kode_unit]),
                'nomor_surat' => $u->kode_unit,
                'judul' => $u->nama_unit,
                'kode_unit' => $u->kode_unit,
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $u->kode_unit,
                $u->nama_unit,
                $countP,
                $this->rawNumeric($nilaiP),
                $sharePct,
            ];
            $no++;
        }

        return [
            'key' => 'rekapitulasi_dipa',
            'tab_label' => 'Rekapitulasi Anggaran',
            'description' => 'Rekapitulasi serapan anggaran institusi dan distribusi usulan per unit.',
            'ringkasan_title' => 'Ringkasan Realisasi Anggaran Institusi',
            'table_title' => 'Distribusi Anggaran per Jurusan dan Unit Kerja',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => null,
                'status_options' => [],
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Pagu Anggaran', 'val' => 'Rp '.number_format($totalPaguDipa, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => $tahun ? 'Anggaran tahun '.$tahun : 'Semua tahun'],
                ['label' => 'Total Pencairan Dana', 'val' => 'Rp '.number_format($totalRealisasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Dana sudah cair'],
                ['label' => 'Total Estimasi Usulan', 'val' => 'Rp '.number_format($totalUsulan, 0, ',', '.'), 'icon' => 'document-text', 'sub' => $kodeUnit !== '' ? 'Unit '.$kodeUnit : 'Semua usulan'],
                ['label' => 'Sisa Anggaran Belum Terserap', 'val' => 'Rp '.number_format($sisaAnggaran, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Sisa alokasi anggaran'],
                ['label' => 'Total Nilai Kontrak', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Kontrak aktif'],
                ['label' => 'Persentase Serapan Anggaran', 'val' => $persentaseSerapan.'%', 'icon' => 'chart-pie', 'sub' => 'Realisasi terhadap pagu'],
            ],
            'columns' => ['No.', 'Kode Unit', 'Nama Jurusan / Unit Kerja', 'Jumlah Usulan', 'Total Nilai Usulan', 'Pangsa Usulan (%)'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Kode Unit', 'Nama Jurusan / Unit Kerja', 'Jumlah Usulan (Berkas)', 'Total Nilai Usulan (IDR)', 'Pangsa Usulan (%)'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 2: Permohonan Unit Sendiri (`permohonan.create` / `permohonan.view_own` tanpa `view_all`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulPermohonanSendiri(User $user, array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $status = $filters['status'];
        $search = $filters['q'];

        $unitNama = $user->unit?->nama_unit ?? $user->kode_unit ?? 'Unit Kerja';
        $items = PermohonanPengadaan::with(['paketPengadaan.kontrakSpk', 'paketPengadaan.pembayaran.transaksiPencairan'])
            ->where('id_user', $user->id_user)
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_pengajuan', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_pengajuan', $bulan))
            ->when($status !== '', fn (Builder $q) => $this->applyPermohonanStatusFilter($q, $status))
            ->latest('tanggal_pengajuan')
            ->get();

        $totalUsulan = (float) $items->sum('estimasi_total_pagu');
        $disetujuiCount = $items->whereIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai'])->count();
        $prosesCount = $items->whereNotIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai', 'ditolak', 'batal'])->count();

        $permohonanNomor = $items->pluck('nomor_surat');
        $paketNomor = PaketPengadaan::whereIn('nomor_surat', $permohonanNomor)->pluck('nomor_paket');
        $totalKontrak = (float) KontrakSPK::whereIn('nomor_paket', $paketNomor)->sum('nilai_kontrak');
        $pembayaranNomor = Pembayaran::whereIn('nomor_paket', $paketNomor)->pluck('nomor_pembayaran');
        $totalRealisasi = (float) TransaksiPencairan::whereIn('nomor_pembayaran', $pembayaranNomor)->sum('nominal_transfer');
        $serapanPct = $totalUsulan > 0 ? round(($totalRealisasi / $totalUsulan) * 100, 1) : 0.0;

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($items as $item) {
            $kontrakVal = (float) $item->paketPengadaan->sum(fn (PaketPengadaan $pkt) => (float) ($pkt->kontrakSpk?->nilai_kontrak ?? 0));
            $cairVal = (float) $item->paketPengadaan->sum(
                fn (PaketPengadaan $pkt) => (float) $pkt->pembayaran->sum(
                    fn (Pembayaran $byr) => (float) ($byr->transaksiPencairan?->nominal_transfer ?? 0)
                )
            );

            $row = [
                'no' => $no,
                'col1' => $item->nomor_surat,
                'col2' => $item->judul_pengadaan,
                'col3' => $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->translatedFormat('d M Y') : '-',
                'col4' => 'Rp '.number_format((float) $item->estimasi_total_pagu, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $item->status_permohonan)),
                'link' => route('permohonan.index', ['selected' => $item->nomor_surat]),
                'nomor_surat' => $item->nomor_surat,
                'judul' => $item->judul_pengadaan,
                'kode_unit' => $item->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $item->nomor_surat,
                $item->kode_unit ?? $user->kode_unit ?? '-',
                $item->judul_pengadaan,
                $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->format('Y-m-d') : '',
                $this->rawNumeric($item->estimasi_total_pagu),
                $this->rawNumeric($kontrakVal),
                $this->rawNumeric($cairVal),
                ucwords(str_replace('_', ' ', (string) $item->status_permohonan)),
            ];
            $no++;
        }

        return [
            'key' => 'permohonan_sendiri',
            'tab_label' => 'Usulan Unit Saya',
            'description' => 'Rekapitulasi permohonan pengadaan milik pengusul pada unit '.$unitNama.'.',
            'ringkasan_title' => 'Ringkasan Pengadaan Unit Kerja ('.$user->nama.')',
            'table_title' => 'Daftar Rekapitulasi Usulan Pengadaan Unit ('.$unitNama.')',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'locked_own',
                'status_label' => 'Status Usulan',
                'status_options' => $this->permohonanStatusOptions(),
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Estimasi Usulan Unit', 'val' => 'Rp '.number_format($totalUsulan, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Total usulan unit Anda'],
                ['label' => 'Total Pencairan Dana', 'val' => 'Rp '.number_format($totalRealisasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Dana belanja sudah cair'],
                ['label' => 'Total Nilai Kontrak', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Kontrak aktif'],
                ['label' => 'Usulan Telah Disetujui', 'val' => $disetujuiCount.' Berkas', 'icon' => 'check-badge', 'sub' => 'Disetujui Wadir 2 & siap diproses'],
                ['label' => 'Usulan Sedang Diproses', 'val' => $prosesCount.' Berkas', 'icon' => 'clock', 'sub' => 'Tahap verifikasi & pengadaan'],
                ['label' => 'Persentase Serapan Unit', 'val' => $serapanPct.'%', 'icon' => 'chart-pie', 'sub' => 'Rasio realisasi belanja unit'],
            ],
            'columns' => ['No.', 'Nomor Surat', 'Nama Pengadaan', 'Tanggal Pengajuan', 'Estimasi Pagu', 'Status Pengadaan'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Surat', 'Kode Unit', 'Nama Pengadaan', 'Tanggal Pengajuan', 'Estimasi Pagu (IDR)', 'Nilai Kontrak (IDR)', 'Pencairan Dana (IDR)', 'Status Pengadaan'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 3: Verifikasi & Telaah Usulan (`verifikasi.bidang_2_view`, `verifikasi.wadir_2_*`, `permohonan.view_all`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulVerifikasiUsulan(array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];

        $items = PermohonanPengadaan::with('unit')
            ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_pengajuan', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_pengajuan', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->where('kode_unit', $kodeUnit))
            ->when($status !== '', fn (Builder $q) => $this->applyPermohonanStatusFilter($q, $status))
            ->latest('tanggal_pengajuan')
            ->get();

        $totalMasuk = $items->count();
        $menungguTelaah = $items->whereIn('status_permohonan', ['diajukan', PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2])->count();
        $reviuParalel = $items->whereIn('status_permohonan', [
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
            PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK,
            'pending_anggaran',
        ])->count();
        $disetujui = $items->whereIn('status_permohonan', ['disetujui', 'diproses_ppbj', 'selesai'])->count();
        $revisiTolak = $items->whereIn('status_permohonan', ['ditolak', 'revisi', 'perlu_revisi'])->count();
        $totalPaguMasuk = (float) $items->sum('estimasi_total_pagu');

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($items as $item) {
            $row = [
                'no' => $no,
                'col1' => $item->nomor_surat,
                'col2' => $item->unit?->nama_unit ?? $item->kode_unit ?? '-',
                'col3' => $item->judul_pengadaan,
                'col4' => 'Rp '.number_format((float) $item->estimasi_total_pagu, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $item->status_permohonan)),
                'link' => route('permohonan.index', ['selected' => $item->nomor_surat]),
                'nomor_surat' => $item->nomor_surat,
                'judul' => $item->judul_pengadaan,
                'kode_unit' => $item->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $item->nomor_surat,
                $item->kode_unit ?? '-',
                $item->unit?->nama_unit ?? $item->kode_unit ?? '-',
                $item->judul_pengadaan,
                $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->format('Y-m-d') : '',
                $this->rawNumeric($item->estimasi_total_pagu),
                $item->kode_mak ?? '',
                ucwords(str_replace('_', ' ', (string) $item->status_permohonan)),
            ];
            $no++;
        }

        return [
            'key' => 'verifikasi_usulan',
            'tab_label' => 'Verifikasi & Usulan',
            'description' => 'Rekapitulasi berkas usulan permohonan masuk, telaah administrasi, dan persetujuan pimpinan.',
            'ringkasan_title' => 'Ringkasan Penerimaan & Verifikasi Usulan Pengadaan',
            'table_title' => 'Rekapitulasi Usulan Pengadaan Lintas Unit Kerja',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Status Berkas',
                'status_options' => $this->permohonanStatusOptions(),
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Berkas Usulan Masuk', 'val' => $totalMasuk.' Berkas', 'icon' => 'document-text', 'sub' => 'Dari jurusan & unit kerja'],
                ['label' => 'Menunggu Telaah Administrasi', 'val' => $menungguTelaah.' Berkas', 'icon' => 'clock', 'sub' => 'Tahap pemeriksaan berkas awal'],
                ['label' => 'Reviu Anggaran & Pimpinan', 'val' => $reviuParalel.' Berkas', 'icon' => 'arrow-path', 'sub' => 'Cek anggaran & persetujuan Wadir 2'],
                ['label' => 'Berkas Disetujui / Proses', 'val' => $disetujui.' Berkas', 'icon' => 'check-badge', 'sub' => 'Lolos verifikasi & siap diproses'],
                ['label' => 'Berkas Ditolak / Revisi', 'val' => $revisiTolak.' Berkas', 'icon' => 'x-circle', 'sub' => 'Perlu perbaikan oleh unit'],
                ['label' => 'Total Pagu Usulan Masuk', 'val' => 'Rp '.number_format($totalPaguMasuk, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Akumulasi estimasi usulan'],
            ],
            'columns' => ['No.', 'Nomor Surat', 'Jurusan / Unit Pengusul', 'Nama Pengadaan', 'Estimasi Pagu', 'Status Berkas'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Surat', 'Kode Unit', 'Unit Pengusul', 'Nama Pengadaan', 'Tanggal Pengajuan', 'Estimasi Pagu (IDR)', 'Kode Pos Anggaran', 'Status Berkas'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 4: Anggaran & MAK DIPA (`anggaran.mak_*`, `permohonan.split_mak`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulAnggaranMak(array $filters): array
    {
        $tahun = $filters['tahun'];
        $status = $filters['status'];
        $search = $filters['q'];

        $makList = AnggaranMAK::query()
            ->when($tahun, fn (Builder $q) => $q->where('tahun_anggaran', $tahun))
            ->when($status === 'terkunci', fn (Builder $q) => $q->where('is_locked', true))
            ->when($status === 'aktif', fn (Builder $q) => $q->where('is_locked', false))
            ->latest('updated_at')
            ->get();

        $totalPaguDipa = (float) $makList->sum('pagu_anggaran');
        $totalSisaMak = (float) $makList->sum('sisa_pagu_mak');
        $paguTeralokasi = max(0, $totalPaguDipa - $totalSisaMak);
        $lockedCount = $makList->where('is_locked', true)->count();
        $activeCount = $makList->where('is_locked', false)->count();
        $menungguMak = PermohonanPengadaan::whereIn('status_permohonan', [
            'diajukan',
            PermohonanPengadaan::STATUS_REVIU_PARALEL,
            'pending_anggaran',
            PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN,
        ])->where(function (Builder $q) {
            $q->whereNull('kode_mak')->orWhere('kode_mak', '');
        })->count();

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($makList as $item) {
            $teralokasi = max(0, (float) $item->pagu_anggaran - (float) $item->sisa_pagu_mak);
            $uraian = $item->uraian_mak ?: ($item->nama_mak ?? 'Mata Anggaran Kegiatan');
            $kunciLabel = $item->is_locked ? 'Terkunci' : 'Terbuka';

            $row = [
                'no' => $no,
                'col1' => $item->kode_mak,
                'col2' => $uraian.' ('.$kunciLabel.')',
                'col3' => (string) $item->tahun_anggaran,
                'col4' => 'Rp '.number_format((float) $item->pagu_anggaran, 0, ',', '.'),
                'col5' => 'Rp '.number_format((float) $item->sisa_pagu_mak, 0, ',', '.'),
                'link' => route('verifikasi.perencanaan'),
                'nomor_surat' => $item->kode_mak,
                'judul' => $uraian,
                'kode_unit' => '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $item->kode_mak,
                $uraian,
                $item->sumber_dana ?? '-',
                (int) $item->tahun_anggaran,
                $this->rawNumeric($item->pagu_anggaran),
                $this->rawNumeric($teralokasi),
                $this->rawNumeric($item->sisa_pagu_mak),
                $kunciLabel,
            ];
            $no++;
        }

        return [
            'key' => 'anggaran_mak',
            'tab_label' => 'Pos Anggaran',
            'description' => 'Rekapitulasi pagu, sisa saldo, serta status penguncian pos anggaran.',
            'ringkasan_title' => 'Ringkasan Alokasi Pos Anggaran (Perencanaan)',
            'table_title' => 'Daftar Alokasi Pos Anggaran',
            'filters' => [
                'tahun' => true,
                'bulan' => false,
                'unit' => 'none',
                'status_label' => 'Status Kunci Anggaran',
                'status_options' => [
                    ['value' => 'aktif', 'label' => 'Terbuka / Aktif'],
                    ['value' => 'terkunci', 'label' => 'Terkunci'],
                ],
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Pagu Anggaran', 'val' => 'Rp '.number_format($totalPaguDipa, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => $tahun ? 'Anggaran tahun '.$tahun : 'Semua tahun'],
                ['label' => 'Anggaran Teralokasi', 'val' => 'Rp '.number_format($paguTeralokasi, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Alokasi pengadaan'],
                ['label' => 'Sisa Saldo Anggaran', 'val' => 'Rp '.number_format($totalSisaMak, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Saldo belum terserap'],
                ['label' => 'Pos Anggaran Terkunci', 'val' => $lockedCount.' Rekening', 'icon' => 'lock-closed', 'sub' => 'Dikunci Perencanaan'],
                ['label' => 'Pos Anggaran Aktif', 'val' => $activeCount.' Rekening', 'icon' => 'lock-open', 'sub' => 'Tersedia untuk usulan'],
                ['label' => 'Usulan Menunggu Cek Anggaran', 'val' => $menungguMak.' Berkas', 'icon' => 'clock', 'sub' => 'Antrean verifikasi anggaran'],
            ],
            'columns' => ['No.', 'Kode Pos Anggaran', 'Uraian Anggaran', 'Tahun', 'Pagu Anggaran', 'Sisa Saldo'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Kode Pos Anggaran', 'Uraian Anggaran', 'Sumber Dana', 'Tahun Anggaran', 'Pagu Anggaran (IDR)', 'Anggaran Teralokasi (IDR)', 'Sisa Saldo (IDR)', 'Status Kunci'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 5: Paket & Kontrak Pengadaan PPBJ (`paket.create`, `paket.view_all`, `spk.create`, `lihat_pp`, dll).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulPengadaanPpbj(array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];

        $pakets = PaketPengadaan::with(['vendor', 'kontrakSpk', 'permohonan.unit'])
            ->when($tahun, fn (Builder $q) => $q->whereYear('created_at', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('created_at', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)))
            ->when($status !== '', fn (Builder $q) => $q->where('status_paket', $status))
            ->latest('created_at')
            ->get();

        $totalPaket = $pakets->count();
        $langsungCount = $pakets->where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)->count();
        $ppCount = $pakets->where('jalur_routing', PaketPengadaan::ROUTING_PP)->count();
        $ppkCount = $pakets->where('jalur_routing', PaketPengadaan::ROUTING_PPK)->count();
        $totalKontrak = (float) KontrakSPK::whereIn('nomor_paket', $pakets->pluck('nomor_paket'))->sum('nilai_kontrak');
        $siapPaketCount = PermohonanPengadaan::where('status_permohonan', 'disetujui')->whereDoesntHave('paketPengadaan')->count();

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($pakets as $p) {
            $jalurLabel = match ($p->jalur_routing) {
                PaketPengadaan::ROUTING_LANGSUNG => 'Belanja Langsung (<=50jt)',
                PaketPengadaan::ROUTING_PP => 'Pejabat Pengadaan (50-200jt)',
                PaketPengadaan::ROUTING_PPK => 'PPK (>200jt)',
                default => 'Umum',
            };

            $row = [
                'no' => $no,
                'col1' => $p->nomor_paket,
                'col2' => $p->nama_paket,
                'col3' => $jalurLabel,
                'col4' => 'Rp '.number_format((float) $p->nilai_hps, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $p->status_paket)),
                'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                'nomor_surat' => $p->nomor_paket,
                'judul' => $p->nama_paket,
                'kode_unit' => $p->permohonan?->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $p->nomor_paket,
                $p->nomor_surat ?? '-',
                $p->permohonan?->kode_unit ?? '-',
                $p->nama_paket,
                $jalurLabel,
                ucwords(str_replace('_', ' ', (string) ($p->metode_pengadaan ?? '-'))),
                $p->vendor?->nama_vendor ?? 'Belum ditetapkan',
                $this->rawNumeric($p->nilai_hps),
                $this->rawNumeric($p->kontrakSpk?->nilai_kontrak ?? 0),
                ucwords(str_replace('_', ' ', (string) $p->status_paket)),
            ];
            $no++;
        }

        return [
            'key' => 'pengadaan_ppbj',
            'tab_label' => 'Paket & Kontrak',
            'description' => 'Rekapitulasi paket pengadaan per jalur nilai (<=50Jt, 50-200Jt, >200Jt) beserta dokumen kontrak.',
            'ringkasan_title' => 'Ringkasan Paket Pengadaan (Unit Pengadaan)',
            'table_title' => 'Daftar Paket Pengadaan (Unit Pengadaan)',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Status Paket',
                'status_options' => $this->paketStatusOptions(),
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Paket Terdaftar', 'val' => $totalPaket.' Paket', 'icon' => 'briefcase', 'sub' => 'Pengadaan sesuai filter'],
                ['label' => 'Belanja Langsung (<=50Jt)', 'val' => $langsungCount.' Paket', 'icon' => 'bolt', 'sub' => 'Kwitansi tanpa kontrak'],
                ['label' => 'Pejabat Pengadaan (50-200Jt)', 'val' => $ppCount.' Paket', 'icon' => 'user-check', 'sub' => 'Penugasan Pejabat Pengadaan'],
                ['label' => 'Jalur Pokja / PPK (>200Jt)', 'val' => $ppkCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Tender & kontrak PPK'],
                ['label' => 'Total Nilai Kontrak', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Kontrak aktif'],
                ['label' => 'Usulan Siap Dibuat Paket', 'val' => $siapPaketCount.' Berkas', 'icon' => 'clock', 'sub' => 'Telah disetujui Wadir 2'],
            ],
            'columns' => ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Jalur Pengadaan', 'Perkiraan Harga', 'Status Paket'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Paket', 'Nomor Surat Usulan', 'Kode Unit', 'Nama Paket Pengadaan', 'Jalur Pengadaan', 'Metode Pengadaan', 'Vendor / Rekanan', 'Perkiraan Harga (IDR)', 'Nilai Kontrak (IDR)', 'Status Paket'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 6: Penugasan Pejabat Pengadaan (`reviu.pp`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulPenugasanPp(User $user, array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];

        $pakets = PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk'])
            ->where(function (Builder $q) use ($user) {
                $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])
                    ->orWhere('id_user_pp', $user->id_user);
            })
            ->when($tahun, fn (Builder $q) => $q->whereYear('created_at', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('created_at', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)))
            ->when($status !== '', fn (Builder $q) => $q->where('status_paket', $status))
            ->latest('created_at')
            ->get();

        $totalPaket = $pakets->count();
        $reviuCount = $pakets->whereIn('status_paket', ['terdaftar', 'reviu_dokumen', 'perlu_perbaikan'])->count();
        $menungguPpkCount = $pakets->where('status_paket', PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK)->count();
        $siapBelanjaCount = $pakets->whereIn('status_paket', [
            PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
            PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
            'reviu_dokumen_disetujui',
            'spk_terbit',
            'selesai',
        ])->count();
        $totalHps = (float) $pakets->sum('nilai_hps');
        $totalKontrak = (float) KontrakSPK::whereIn('nomor_paket', $pakets->pluck('nomor_paket'))->sum('nilai_kontrak');

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($pakets as $p) {
            $hargaAkhir = (float) ($p->kontrakSpk?->nilai_kontrak ?? $p->nominal_harga_akhir ?? $p->nilai_hasil_negosiasi ?? 0);
            $row = [
                'no' => $no,
                'col1' => $p->nomor_paket,
                'col2' => $p->nama_paket,
                'col3' => ucwords(str_replace('_', ' ', (string) ($p->metode_pengadaan ?? 'Pengadaan Langsung'))),
                'col4' => 'Rp '.number_format((float) $p->nilai_hps, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $p->status_paket)),
                'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                'nomor_surat' => $p->nomor_paket,
                'judul' => $p->nama_paket,
                'kode_unit' => $p->permohonan?->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $p->nomor_paket,
                $p->permohonan?->kode_unit ?? '-',
                $p->nama_paket,
                ucwords(str_replace('_', ' ', (string) ($p->metode_pengadaan ?? 'Pengadaan Langsung'))),
                $p->vendor?->nama_vendor ?? 'Belum ditetapkan',
                $this->rawNumeric($p->nilai_hps),
                $this->rawNumeric($hargaAkhir),
                ucwords(str_replace('_', ' ', (string) $p->status_paket)),
            ];
            $no++;
        }

        return [
            'key' => 'penugasan_pp',
            'tab_label' => 'Penugasan Pejabat Pengadaan',
            'description' => 'Rekapitulasi paket pengadaan Rp 50-200 Juta dalam penanganan Pejabat Pengadaan.',
            'ringkasan_title' => 'Ringkasan Pejabat Pengadaan ('.$user->nama.')',
            'table_title' => 'Daftar Paket Pengadaan Langsung',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Status Paket',
                'status_options' => $this->paketStatusOptions(),
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Paket Penugasan', 'val' => $totalPaket.' Paket', 'icon' => 'briefcase', 'sub' => 'Paket nilai 50jt - 200jt'],
                ['label' => 'Dalam Reviu & Negosiasi', 'val' => $reviuCount.' Paket', 'icon' => 'clock', 'sub' => 'Tahap periksa dokumen & harga'],
                ['label' => 'Menunggu Persetujuan PPK', 'val' => $menungguPpkCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Menunggu persetujuan PPK'],
                ['label' => 'Siap Belanja / Kontrak Terbit', 'val' => $siapBelanjaCount.' Paket', 'icon' => 'check-badge', 'sub' => 'Sudah disetujui'],
                ['label' => 'Total Perkiraan Harga', 'val' => 'Rp '.number_format($totalHps, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Total perkiraan harga'],
                ['label' => 'Total Nilai Kontrak', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Nilai kontrak final'],
            ],
            'columns' => ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Perkiraan Harga', 'Status Paket'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Paket', 'Kode Unit', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Vendor / Rekanan', 'Perkiraan Harga (IDR)', 'Harga Negosiasi / Kontrak (IDR)', 'Status Paket'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 7: Komitmen & Paket PPK (`reviu.ppk`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulKomitmenPpk(User $user, array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];

        $pakets = PaketPengadaan::with(['permohonan.unit', 'vendor', 'kontrakSpk', 'pembayaran.transaksiPencairan'])
            ->where('id_user_ppk', $user->id_user)
            ->when($tahun, fn (Builder $q) => $q->whereYear('created_at', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('created_at', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)))
            ->when($status !== '', fn (Builder $q) => $q->where('status_paket', $status))
            ->latest('created_at')
            ->get();

        $paketNomorList = $pakets->pluck('nomor_paket');
        $antreanAwal = PermohonanPengadaan::where('id_user_ppk_awal', $user->id_user)
            ->whereIn('status_permohonan', [PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, PermohonanPengadaan::STATUS_REVIU_PARALEL])
            ->count();
        $totalKontrak = (float) KontrakSPK::whereIn('nomor_paket', $paketNomorList)->sum('nilai_kontrak');
        $tenderCount = $pakets->where('jalur_routing', PaketPengadaan::ROUTING_PPK)->count();
        $memoPending = MemoBayar::where('role_approver', 'ppk')
            ->whereIn('status_memo', ['diajukan', 'menunggu'])
            ->whereHas('pembayaran.paket', fn (Builder $q) => $q->where('id_user_ppk', $user->id_user))
            ->count();
        $pembayaranNomor = Pembayaran::whereIn('nomor_paket', $paketNomorList)->pluck('nomor_pembayaran');
        $totalCair = (float) TransaksiPencairan::whereIn('nomor_pembayaran', $pembayaranNomor)->sum('nominal_transfer');

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($pakets as $p) {
            $kontrakVal = (float) ($p->kontrakSpk?->nilai_kontrak ?? 0);
            $valDisplay = $kontrakVal > 0 ? $kontrakVal : (float) $p->nilai_hps;
            $cairVal = (float) $p->pembayaran->sum(
                fn (Pembayaran $byr) => (float) ($byr->transaksiPencairan?->nominal_transfer ?? 0)
            );

            $row = [
                'no' => $no,
                'col1' => $p->nomor_paket,
                'col2' => $p->nama_paket,
                'col3' => ucwords(str_replace('_', ' ', (string) ($p->metode_pengadaan ?? 'Tender / Non-Tender'))),
                'col4' => 'Rp '.number_format($valDisplay, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $p->status_paket)),
                'link' => route('paket.index', ['selected' => $p->nomor_paket]),
                'nomor_surat' => $p->nomor_paket,
                'judul' => $p->nama_paket,
                'kode_unit' => $p->permohonan?->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $p->nomor_paket,
                $p->permohonan?->kode_unit ?? '-',
                $p->nama_paket,
                ucwords(str_replace('_', ' ', (string) ($p->metode_pengadaan ?? '-'))),
                $p->vendor?->nama_vendor ?? 'Belum ditetapkan',
                $this->rawNumeric($p->nilai_hps),
                $this->rawNumeric($kontrakVal),
                $this->rawNumeric($cairVal),
                ucwords(str_replace('_', ' ', (string) $p->status_paket)),
            ];
            $no++;
        }

        return [
            'key' => 'komitmen_ppk',
            'tab_label' => 'Komitmen & Paket PPK',
            'description' => 'Rekapitulasi paket pengadaan di bawah tanggung jawab PPK '.$user->nama.'.',
            'ringkasan_title' => 'Ringkasan Komitmen & Paket Pengadaan (PPK: '.$user->nama.')',
            'table_title' => 'Daftar Paket Komitmen PPK ('.$user->nama.')',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Status Paket',
                'status_options' => $this->paketStatusOptions(),
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Paket Komitmen PPK', 'val' => $pakets->count().' Paket', 'icon' => 'briefcase', 'sub' => 'Paket penugasan Anda'],
                ['label' => 'Antrean Persetujuan Awal', 'val' => $antreanAwal.' Berkas', 'icon' => 'clock', 'sub' => 'Usulan masuk tahap PPK'],
                ['label' => 'Total Nilai Kontrak', 'val' => 'Rp '.number_format($totalKontrak, 0, ',', '.'), 'icon' => 'document-check', 'sub' => 'Kontrak aktif'],
                ['label' => 'Paket Tender / Seleksi (>200Jt)', 'val' => $tenderCount.' Paket', 'icon' => 'shield-check', 'sub' => 'Paket skala besar'],
                ['label' => 'Memo Bayar Menunggu Persetujuan', 'val' => $memoPending.' Berkas', 'icon' => 'document-text', 'sub' => 'Persetujuan pembayaran'],
                ['label' => 'Total Pencairan Dana', 'val' => 'Rp '.number_format($totalCair, 0, ',', '.'), 'icon' => 'check-circle', 'sub' => 'Dana sudah cair'],
            ],
            'columns' => ['No.', 'Nomor Paket', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Nilai Kontrak / Perkiraan Harga', 'Status Paket'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Paket', 'Kode Unit', 'Nama Paket Pengadaan', 'Metode Pengadaan', 'Vendor / Rekanan', 'Perkiraan Harga (IDR)', 'Nilai Kontrak (IDR)', 'Pencairan Dana (IDR)', 'Status Paket'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 8: Penerimaan Barang & BAST BMN (`penerimaan.view`, `penerimaan.bast_create`, `penerimaan.bast_sign`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulPenerimaanBmn(array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];

        $items = PenerimaanBarang::with(['paket.vendor', 'paket.permohonan.unit', 'paket.kontrakSpk', 'kontrak'])
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_penerimaan', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_penerimaan', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('paket.permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)))
            ->when($status !== '', function (Builder $q) use ($status) {
                if (in_array($status, [PenerimaanBarang::KATEGORI_BELANJA_BARANG, PenerimaanBarang::KATEGORI_BELANJA_MODAL], true)) {
                    $q->where('kategori_belanja', $status);
                } else {
                    $q->where('status_fisik', $status);
                }
            })
            ->latest('tanggal_penerimaan')
            ->get();

        $totalSpkMasuk = KontrakSPK::count() + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
            ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
            ->count();
        $belumDiperiksa = KontrakSPK::whereDoesntHave('paket.penerimaanBarang')->count()
            + PaketPengadaan::where('jalur_routing', PaketPengadaan::ROUTING_LANGSUNG)
                ->where('status_paket', PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI)
                ->whereDoesntHave('penerimaanBarang')
                ->count();
        $barangCount = $items->where('kategori_belanja', PenerimaanBarang::KATEGORI_BELANJA_BARANG)->count();
        $modalCount = $items->where('kategori_belanja', PenerimaanBarang::KATEGORI_BELANJA_MODAL)->count();

        $totalNilaiBast = 0.0;
        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($items as $item) {
            $nilaiFisik = (float) ($item->kontrak?->nilai_kontrak
                ?? $item->paket?->kontrakSpk?->nilai_kontrak
                ?? $item->paket?->nominal_harga_akhir
                ?? $item->paket?->nilai_hps
                ?? 0);
            $totalNilaiBast += $nilaiFisik;

            $bastNo = $item->nomor_bast ?: ('BAST-'.str_pad((string) $item->id_penerimaan, 4, '0', STR_PAD_LEFT));
            $statusFisikLabel = match ($item->status_fisik) {
                PenerimaanBarang::STATUS_TERIMA, PenerimaanBarang::STATUS_DITERIMA_LENGKAP => 'Diterima Lengkap',
                PenerimaanBarang::STATUS_PENDING => 'Dalam Pemeriksaan',
                PenerimaanBarang::STATUS_TIDAK_SESUAI => 'Tidak Sesuai',
                default => ucwords(str_replace('_', ' ', (string) ($item->status_fisik ?? 'Diterima'))),
            };

            $row = [
                'no' => $no,
                'col1' => $bastNo,
                'col2' => $item->paket?->nama_paket ?? 'Penerimaan Hasil Pengadaan',
                'col3' => $item->tanggal_penerimaan ? Carbon::parse($item->tanggal_penerimaan)->translatedFormat('d M Y') : '-',
                'col4' => ucwords(str_replace('_', ' ', (string) ($item->kategori_belanja ?? 'Belanja Barang'))),
                'col5' => $statusFisikLabel,
                'link' => route('penerimaan.index'),
                'nomor_surat' => $bastNo,
                'judul' => $item->paket?->nama_paket ?? 'Barang Pengadaan',
                'kode_unit' => $item->paket?->permohonan?->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $bastNo,
                $item->nomor_paket,
                $item->paket?->permohonan?->kode_unit ?? '-',
                $item->paket?->nama_paket ?? 'Penerimaan Hasil Pengadaan',
                $item->paket?->vendor?->nama_vendor ?? 'Belanja Langsung / Rekanan',
                $item->tanggal_penerimaan ? Carbon::parse($item->tanggal_penerimaan)->format('Y-m-d') : '',
                ucwords(str_replace('_', ' ', (string) ($item->kategori_belanja ?? 'Belanja Barang'))),
                $this->rawNumeric($nilaiFisik),
                $statusFisikLabel,
            ];
            $no++;
        }

        return [
            'key' => 'penerimaan_bmn',
            'tab_label' => 'Penerimaan & Serah Terima',
            'description' => 'Rekapitulasi serah terima barang dan kategori belanja.',
            'ringkasan_title' => 'Ringkasan Penerimaan Barang & Aset',
            'table_title' => 'Daftar Penerimaan Barang & Serah Terima',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Kategori / Status Fisik',
                'status_options' => [
                    ['value' => PenerimaanBarang::KATEGORI_BELANJA_BARANG, 'label' => 'Kategori: Belanja Barang'],
                    ['value' => PenerimaanBarang::KATEGORI_BELANJA_MODAL, 'label' => 'Kategori: Belanja Modal'],
                    ['value' => PenerimaanBarang::STATUS_TERIMA, 'label' => 'Status: Diterima Lengkap'],
                    ['value' => PenerimaanBarang::STATUS_PENDING, 'label' => 'Status: Dalam Pemeriksaan'],
                    ['value' => PenerimaanBarang::STATUS_TIDAK_SESUAI, 'label' => 'Status: Tidak Sesuai'],
                ],
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Total Kontrak / Belanja Masuk', 'val' => $totalSpkMasuk.' Berkas', 'icon' => 'document-check', 'sub' => 'Kontrak & belanja langsung'],
                ['label' => 'Menunggu Pemeriksaan Fisik', 'val' => $belumDiperiksa.' Paket', 'icon' => 'clock', 'sub' => 'Belum dilakukan serah terima'],
                ['label' => 'Total Bukti Serah Terima', 'val' => $items->count().' Dokumen', 'icon' => 'check-badge', 'sub' => 'Serah terima tercatat'],
                ['label' => 'Aset Belanja Barang', 'val' => $barangCount.' Berkas', 'icon' => 'cube', 'sub' => 'Barang habis pakai'],
                ['label' => 'Aset Belanja Modal', 'val' => $modalCount.' Berkas', 'icon' => 'building-library', 'sub' => 'Aset tetap & inventaris'],
                ['label' => 'Total Nilai Fisik Diterima', 'val' => 'Rp '.number_format($totalNilaiBast, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Akumulasi barang sesuai filter'],
            ],
            'columns' => ['No.', 'Nomor Serah Terima', 'Nama Paket / Barang', 'Tanggal Penerimaan', 'Kategori Belanja', 'Status Verifikasi'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Serah Terima', 'Nomor Paket', 'Kode Unit', 'Nama Paket / Barang', 'Vendor / Rekanan', 'Tanggal Penerimaan', 'Kategori Belanja', 'Nilai Fisik (IDR)', 'Status Fisik'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 9: Pembayaran & Pencairan SP2D (`pembayaran.*`).
     * Menerapkan isolasi paket PPK bila pengguna hanya memegang `pembayaran.memo_ppk`.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulPembayaranSp2d(User $user, array $filters): array
    {
        $tahun = $filters['tahun'];
        $bulan = $filters['bulan'];
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $search = $filters['q'];
        $strictPpk = $this->isStrictPpkPembayaranScope($user);

        $items = Pembayaran::with(['paket.vendor', 'transaksiPencairan', 'paket.permohonan.unit'])
            ->when($strictPpk, fn (Builder $q) => $q->whereHas('paket', fn (Builder $pq) => $pq->where('id_user_ppk', $user->id_user)))
            ->when($tahun, fn (Builder $q) => $q->whereYear('tanggal_pengajuan', $tahun))
            ->when($bulan, fn (Builder $q) => $q->whereMonth('tanggal_pengajuan', $bulan))
            ->when($kodeUnit !== '', fn (Builder $q) => $q->whereHas('paket.permohonan', fn (Builder $pq) => $pq->where('kode_unit', $kodeUnit)))
            ->when($status !== '', fn (Builder $q) => $q->where('status_pembayaran', $status))
            ->latest('tanggal_pengajuan')
            ->get();

        $sppSiap = $items->where('status_pembayaran', Pembayaran::STATUS_PENGAJUAN)->count();
        $sp2dSiap = $items->where('status_pembayaran', Pembayaran::STATUS_PERINTAH_BAYAR)->count();
        $totalCairTx = $items->filter(fn (Pembayaran $p): bool => $p->transaksiPencairan !== null)->count();
        $totalNominalCair = (float) $items->sum(fn (Pembayaran $p): float => (float) ($p->transaksiPencairan?->nominal_transfer ?? 0));
        $totalTagihan = (float) $items->sum(fn (Pembayaran $p): float => (float) ($p->nominal_pengajuan ?? 0));
        $sisaTagihan = max(0, $totalTagihan - $totalNominalCair);

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($items as $item) {
            $nominalTagihan = (float) ($item->nominal_pengajuan ?? 0);
            $nominalCair = (float) ($item->transaksiPencairan?->nominal_transfer ?? 0);
            $namaPaketAtauVendor = $item->paket?->nama_paket ?? ('Tagihan '.($item->paket?->vendor?->nama_vendor ?? 'Vendor'));

            $row = [
                'no' => $no,
                'col1' => $item->nomor_pembayaran,
                'col2' => $namaPaketAtauVendor,
                'col3' => $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->translatedFormat('d M Y') : '-',
                'col4' => 'Rp '.number_format($nominalTagihan, 0, ',', '.'),
                'col5' => ucwords(str_replace('_', ' ', (string) $item->status_pembayaran)),
                'link' => route('pembayaran.index', ['nomor_paket' => $item->nomor_paket]),
                'nomor_surat' => $item->nomor_pembayaran,
                'judul' => $namaPaketAtauVendor,
                'kode_unit' => $item->paket?->permohonan?->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $item->nomor_pembayaran,
                $item->nomor_paket ?? '-',
                $item->paket?->permohonan?->kode_unit ?? '-',
                $item->paket?->nama_paket ?? '-',
                $item->paket?->vendor?->nama_vendor ?? '-',
                $item->nomor_kwitansi ?? $item->paket?->nomor_kwitansi_belanja ?? '',
                $item->nomor_invoice ?? '',
                $item->tanggal_pengajuan ? Carbon::parse($item->tanggal_pengajuan)->format('Y-m-d') : '',
                $this->rawNumeric($nominalTagihan),
                $this->rawNumeric($nominalCair),
                ucwords(str_replace('_', ' ', (string) $item->status_pembayaran)),
            ];
            $no++;
        }

        return [
            'key' => 'pembayaran_sp2d',
            'tab_label' => 'Pembayaran & Pencairan Dana',
            'description' => $strictPpk
                ? 'Rekapitulasi pengajuan pembayaran & memo bayar khusus paket komitmen PPK Anda.'
                : 'Rekapitulasi tagihan penyedia, perintah bayar, serta pencairan dana.',
            'ringkasan_title' => 'Ringkasan Pembayaran & Pencairan Dana',
            'table_title' => 'Daftar Verifikasi Pembayaran & Pencairan Dana',
            'filters' => [
                'tahun' => true,
                'bulan' => true,
                'unit' => 'all',
                'status_label' => 'Status Pembayaran',
                'status_options' => [
                    ['value' => Pembayaran::STATUS_PENGAJUAN, 'label' => 'Pengajuan / Memo Bayar'],
                    ['value' => Pembayaran::STATUS_PERINTAH_BAYAR, 'label' => 'Perintah Bayar Terbit'],
                    ['value' => Pembayaran::STATUS_LUNAS, 'label' => 'Lunas (Dana Cair)'],
                    ['value' => Pembayaran::STATUS_DITOLAK, 'label' => 'Ditolak / Revisi'],
                ],
                'has_role_filter' => false,
            ],
            'cards' => [
                ['label' => 'Pengajuan & Memo Bayar', 'val' => $sppSiap.' Berkas', 'icon' => 'clock', 'sub' => 'Dalam reviu memo PPK / Wadir 2'],
                ['label' => 'Siap Dicairkan', 'val' => $sp2dSiap.' Berkas', 'icon' => 'document-check', 'sub' => 'Perintah bayar terbit'],
                ['label' => 'Transaksi Lunas / Cair', 'val' => $totalCairTx.' Transaksi', 'icon' => 'check-badge', 'sub' => 'Dana berhasil ditransfer'],
                ['label' => 'Total Pencairan Dana', 'val' => 'Rp '.number_format($totalNominalCair, 0, ',', '.'), 'icon' => 'banknotes', 'sub' => 'Dana sudah cair ke penyedia'],
                ['label' => 'Total Nilai Tagihan Masuk', 'val' => 'Rp '.number_format($totalTagihan, 0, ',', '.'), 'icon' => 'document-text', 'sub' => 'Akumulasi nominal pengajuan'],
                ['label' => 'Sisa Tagihan Dalam Proses', 'val' => 'Rp '.number_format($sisaTagihan, 0, ',', '.'), 'icon' => 'calculator', 'sub' => 'Antrean verifikasi & pencairan'],
            ],
            'columns' => ['No.', 'Nomor Pembayaran', 'Nama Paket / Rekanan', 'Tanggal Pengajuan', 'Nominal Pembayaran', 'Status Pembayaran'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nomor Pembayaran', 'Nomor Paket', 'Kode Unit', 'Nama Paket', 'Vendor / Rekanan', 'Nomor Kwitansi', 'Nomor Invoice', 'Tanggal Pengajuan', 'Nominal Tagihan (IDR)', 'Pencairan Dana (IDR)', 'Status Pembayaran'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Modul 10: Administrasi Pengguna & RBAC (`admin.approval_registrasi`, `admin.user_manage`, `admin.role_manage`).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildModulAdministrasiRbac(array $filters): array
    {
        $kodeUnit = $filters['unit'];
        $status = $filters['status'];
        $roleFilter = $filters['role_filter'];
        $search = $filters['q'];

        $allUsers = User::with(['unit', 'roleEntity'])
            ->when($kodeUnit !== '', fn (Builder $q) => $q->where('kode_unit', $kodeUnit))
            ->when($roleFilter !== '', function (Builder $q) use ($roleFilter) {
                $q->whereHas('roleEntity', fn (Builder $rq) => $rq->where('nama_role', $roleFilter));
            })
            ->when($status !== '', function (Builder $q) use ($status) {
                if ($status === 'aktif') {
                    $q->where('status_aktif', true);
                } elseif ($status === User::STATUS_REGISTRASI_MENUNGGU) {
                    $q->where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU);
                } elseif ($status === 'nonaktif') {
                    $q->where('status_aktif', false);
                }
            })
            ->latest()
            ->get();

        $totalUsers = $allUsers->count();
        $activeUsers = $allUsers->where('status_aktif', true)->count();
        $pendingUsers = $allUsers->where('status_registrasi', User::STATUS_REGISTRASI_MENUNGGU)->count();
        $rejectedUsers = $allUsers->where('status_registrasi', User::STATUS_REGISTRASI_DITOLAK)->count();
        $totalRoles = Role::count();
        $pctAktif = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0.0;

        $rows = [];
        $csvRows = [];
        $no = 1;
        foreach ($allUsers as $u) {
            $roleLabel = $u->roleEntity?->label ?? 'Pengguna';
            $statusLabel = $u->status_aktif
                ? 'Aktif'
                : ($u->status_registrasi === User::STATUS_REGISTRASI_MENUNGGU ? 'Menunggu Persetujuan' : 'Nonaktif');

            $row = [
                'no' => $no,
                'col1' => $u->nama,
                'col2' => $u->nip ?? $u->username ?? '-',
                'col3' => $u->unit?->nama_unit ?? $u->kode_unit ?? 'Pusat',
                'col4' => $roleLabel,
                'col5' => $statusLabel,
                'link' => route('admin.persetujuan-registrasi.index'),
                'nomor_surat' => $u->nip ?? $u->username ?? '',
                'judul' => $u->nama,
                'kode_unit' => $u->kode_unit ?? '',
            ];

            if (! $this->matchesSearch($row, $search)) {
                continue;
            }

            $rows[] = $row;
            $csvRows[] = [
                $no,
                $u->nama,
                $u->nip ?? $u->username ?? '-',
                $u->email,
                $u->unit?->nama_unit ?? $u->kode_unit ?? 'Pusat',
                $u->roleEntity?->nama_role ?? '-',
                $roleLabel,
                ucwords(str_replace('_', ' ', (string) $u->status_registrasi)),
                $u->status_aktif ? 'Aktif' : 'Nonaktif',
            ];
            $no++;
        }

        return [
            'key' => 'administrasi_rbac',
            'tab_label' => 'Pengguna & Hak Akses',
            'description' => 'Rekapitulasi akun pengguna, status pendaftaran, dan pembagian peran.',
            'ringkasan_title' => 'Ringkasan Manajemen Pengguna & Sistem (Administrasi)',
            'table_title' => 'Distribusi dan Status Pengguna Institusi PNJ',
            'filters' => [
                'tahun' => false,
                'bulan' => false,
                'unit' => 'all',
                'status_label' => 'Status Akun',
                'status_options' => [
                    ['value' => 'aktif', 'label' => 'Akun Aktif'],
                    ['value' => User::STATUS_REGISTRASI_MENUNGGU, 'label' => 'Menunggu Persetujuan Admin'],
                    ['value' => 'nonaktif', 'label' => 'Nonaktif / Ditolak'],
                ],
                'has_role_filter' => true,
            ],
            'cards' => [
                ['label' => 'Total Pengguna Terdaftar', 'val' => $totalUsers.' Akun', 'icon' => 'users', 'sub' => 'Pengguna terdaftar sesuai filter'],
                ['label' => 'Pengguna Aktif Terverifikasi', 'val' => $activeUsers.' Akun', 'icon' => 'check-circle', 'sub' => 'Dapat mengakses sistem'],
                ['label' => 'Registrasi Menunggu Persetujuan', 'val' => $pendingUsers.' Pendaftar', 'icon' => 'clock', 'sub' => 'Memerlukan persetujuan admin'],
                ['label' => 'Registrasi Ditolak', 'val' => $rejectedUsers.' Akun', 'icon' => 'x-circle', 'sub' => 'Permohonan akun ditolak'],
                ['label' => 'Total Peran Terdaftar', 'val' => $totalRoles.' Peran', 'icon' => 'shield-check', 'sub' => 'Daftar peran & hak akses'],
                ['label' => 'Persentase Akun Aktif', 'val' => $pctAktif.'%', 'icon' => 'chart-pie', 'sub' => 'Rasio akun terverifikasi'],
            ],
            'columns' => ['No.', 'Nama Pengguna', 'NIP / Username', 'Unit Kerja / Jurusan', 'Peran Akses', 'Status Akun'],
            'rows' => $rows,
            'csv_columns' => ['No', 'Nama Pengguna', 'NIP / Username', 'Email', 'Unit Kerja', 'Kode Role', 'Label Role', 'Status Registrasi', 'Status Aktif'],
            'csv_rows' => $csvRows,
        ];
    }

    /**
     * Payload kosong yang aman apabila pengguna belum memiliki satu pun permission laporan aktif.
     *
     * @return array<string, mixed>
     */
    private function buildEmptyModulePayload(): array
    {
        return [
            'key' => 'kosong',
            'tab_label' => 'Belum Ada Modul Laporan',
            'description' => 'Akun Anda belum memiliki hak akses modul laporan aktif.',
            'ringkasan_title' => 'Ringkasan Laporan (Belum Ada Modul Aktif)',
            'table_title' => 'Data Laporan Tidak Tersedia untuk Izin Saat Ini',
            'filters' => [
                'tahun' => false,
                'bulan' => false,
                'unit' => 'none',
                'status_label' => null,
                'status_options' => [],
                'has_role_filter' => false,
            ],
            'cards' => [],
            'columns' => ['No.', 'Referensi', 'Uraian', 'Tanggal', 'Nilai', 'Status'],
            'rows' => [],
            'csv_columns' => ['Keterangan'],
            'csv_rows' => [['Tidak ada izin modul laporan aktif pada role ini.']],
        ];
    }

    private function applyPermohonanStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'revisi') {
            $query->whereIn('status_permohonan', ['revisi', 'perlu_revisi']);

            return;
        }
        $query->where('status_permohonan', $status);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function permohonanStatusOptions(): array
    {
        return [
            ['value' => PermohonanPengadaan::STATUS_DIAJUKAN, 'label' => 'Diajukan'],
            ['value' => PermohonanPengadaan::STATUS_MENUNGGU_BIDANG_2, 'label' => 'Menunggu Telaah Bidang II'],
            ['value' => PermohonanPengadaan::STATUS_CEK_MAK_PERENCANAAN, 'label' => 'Cek Pos Anggaran Perencanaan'],
            ['value' => PermohonanPengadaan::STATUS_MENUNGGU_ACC_WADIR_PPK, 'label' => 'Menunggu Persetujuan Wadir 2 / PPK'],
            ['value' => PermohonanPengadaan::STATUS_DISETUJUI, 'label' => 'Disetujui'],
            ['value' => PermohonanPengadaan::STATUS_DIPROSES_PPBJ, 'label' => 'Diproses Unit Pengadaan'],
            ['value' => 'revisi', 'label' => 'Perlu Revisi'],
            ['value' => PermohonanPengadaan::STATUS_DITOLAK, 'label' => 'Ditolak'],
            ['value' => PermohonanPengadaan::STATUS_SELESAI, 'label' => 'Selesai'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function paketStatusOptions(): array
    {
        return [
            ['value' => PaketPengadaan::STATUS_TERDAFTAR, 'label' => 'Terdaftar'],
            ['value' => PaketPengadaan::STATUS_BELANJA_LANGSUNG_SELESAI, 'label' => 'Belanja Langsung Selesai'],
            ['value' => PaketPengadaan::STATUS_MENUNGGU_APPROVAL_PPK, 'label' => 'Menunggu Persetujuan PPK'],
            ['value' => PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP, 'label' => 'Disetujui PPK (Siap Belanja)'],
            ['value' => PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ, 'label' => 'Siap Unggah Dokumen Kontrak'],
            ['value' => PaketPengadaan::STATUS_SPK_TERBIT, 'label' => 'Kontrak Terbit'],
            ['value' => PaketPengadaan::STATUS_SELESAI, 'label' => 'Selesai'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesSearch(array $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }
        $needle = mb_strtolower($search);
        foreach (['col1', 'col2', 'col3', 'col4', 'col5', 'nomor_surat', 'judul', 'kode_unit'] as $field) {
            if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function rawNumeric(mixed $value): int|float
    {
        $floatVal = (float) ($value ?? 0);

        return floor($floatVal) === $floatVal ? (int) $floatVal : round($floatVal, 2);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function formatFilterSummaryText(array $filters): string
    {
        $parts = [];
        $parts[] = 'Tahun: '.($filters['tahun'] !== '' ? $filters['tahun'] : 'Semua Tahun');

        if ($filters['bulan'] !== '') {
            $bulanMap = [
                '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
                '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus',
                '9' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
            ];
            $parts[] = 'Bulan: '.($bulanMap[$filters['bulan']] ?? $filters['bulan']);
        }
        if ($filters['unit'] !== '') {
            $parts[] = 'Unit: '.$filters['unit'];
        }
        if ($filters['status'] !== '') {
            $parts[] = 'Status: '.$filters['status'];
        }
        if ($filters['role_filter'] !== '') {
            $parts[] = 'Role: '.$filters['role_filter'];
        }
        if ($filters['q'] !== '') {
            $parts[] = 'Cari: "'.$filters['q'].'"';
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  Collection<string, PermohonanPengadaan>  $permohonanLookup
     * @param  Collection<string, PaketPengadaan>  $paketLookup
     * @return array<string, mixed>
     */
    private function formatAuditLogItem(AuditLog $log, Collection $permohonanLookup, Collection $paketLookup): array
    {
        $nomorSurat = $log->id_entitas;
        $namaPengadaan = null;
        $kodeUnitLog = '';
        $link = route('permohonan.index');

        if ($log->nama_entitas === 'permohonan_pengadaan') {
            $permohonan = $permohonanLookup[$nomorSurat] ?? null;
            $namaPengadaan = $permohonan?->judul_pengadaan;
            $kodeUnitLog = $permohonan?->kode_unit ?? '';
            $link = route('permohonan.index', ['selected' => $nomorSurat]);
        } elseif ($log->nama_entitas === 'paket_pengadaan') {
            $paket = $paketLookup[$log->id_entitas] ?? null;
            $nomorSurat = $paket?->nomor_surat ?? $log->id_entitas;
            $namaPengadaan = $paket?->nama_paket ?? $paket?->permohonan?->judul_pengadaan;
            $kodeUnitLog = $paket?->permohonan?->kode_unit ?? '';
            $link = route('paket.index', ['selected' => $log->id_entitas]);
        } elseif ($log->nama_entitas === 'penerimaan_barang') {
            $namaPengadaan = $log->alasan ?: 'Pemeriksaan Fisik & Serah Terima';
            $link = route('penerimaan.index');
        } elseif (in_array($log->nama_entitas, ['pembayaran', 'memo_bayar', 'transaksi_pencairan'], true)) {
            $namaPengadaan = $log->alasan ?: 'Verifikasi Tagihan & Pencairan Dana';
            $link = route('pembayaran.index');
        } elseif (in_array($log->nama_entitas, ['anggaran_mak', 'alokasi_mak'], true)) {
            $namaPengadaan = $log->alasan ?: 'Alokasi Pos Anggaran';
            $link = route('verifikasi.perencanaan');
        } elseif (in_array($log->nama_entitas, ['users', 'roles', 'permissions'], true)) {
            $namaPengadaan = $log->alasan ?: 'Manajemen Akun / Hak Akses';
            $link = route('admin.persetujuan-registrasi.index');
        }

        if (! $namaPengadaan) {
            $namaPengadaan = $log->alasan ?: ($log->jenis_aksi.' pada '.$log->nama_entitas);
        }

        $permohonan = $nomorSurat ? ($permohonanLookup[$nomorSurat] ?? null) : null;
        $revisiStr = ($permohonan && ! empty($permohonan->id_surat_asal_revisi)) ? 'Revisi ke-1' : 'Revisi ke-0';

        $statusText = 'Dalam Proses';
        $statusColor = 'blue';
        if (stripos($log->jenis_aksi, 'ACC') !== false || stripos($log->alasan ?? '', 'disetujui') !== false) {
            $statusText = 'Disetujui';
            $statusColor = 'emerald';
        } elseif (stripos($log->jenis_aksi, 'TOLAK') !== false || stripos($log->alasan ?? '', 'ditolak') !== false) {
            $statusText = 'Ditolak';
            $statusColor = 'rose';
        } elseif ($permohonan && in_array($permohonan->status_permohonan, ['disetujui', 'selesai'], true)) {
            $statusText = 'Disetujui';
            $statusColor = 'emerald';
        }

        $waktuCarbon = $log->waktu_log ?? now();

        return [
            'id' => 'log_'.$log->id_log,
            'waktu' => $waktuCarbon->translatedFormat('d M Y, H:i'),
            'waktu_raw' => $waktuCarbon->toIso8601String(),
            'tahun' => (int) $waktuCarbon->format('Y'),
            'bulan' => (int) $waktuCarbon->format('m'),
            'nama_pengadaan' => $namaPengadaan,
            'nomor_surat' => $nomorSurat ?? '',
            'kode_unit' => $kodeUnitLog ?: ($permohonan?->kode_unit ?? ''),
            'revisi' => $revisiStr,
            'aktivitas' => $log->alasan ?: ($log->jenis_aksi.' pada '.$log->nama_entitas),
            'jenis_aktivitas' => $this->resolveJenisAktivitas($log->jenis_aksi),
            'status' => $statusText,
            'status_color' => $statusColor,
            'link' => $link,
        ];
    }

    /**
     * Melengkapi entitas fallback secara kumulatif apabila jejak AuditLog belum terisi untuk izin aktif.
     *
     * @param  array<int, array<string, mixed>>  $riwayatList
     * @return array<int, array<string, mixed>>
     */
    private function appendFallbackRiwayat(User $user, array $riwayatList): array
    {
        $seen = collect($riwayatList)->pluck('nomor_surat')->filter()->flip()->all();

        if ($user->hasAnyPermission(['permohonan.create', 'permohonan.view_own']) && ! $user->hasPermission('permohonan.view_all')) {
            $items = PermohonanPengadaan::where('id_user', $user->id_user)
                ->where('status_permohonan', '!=', 'digantikan_oleh_revisi')
                ->latest('tanggal_pengajuan')->take(10)->get();
            foreach ($items as $p) {
                if (! isset($seen[$p->nomor_surat])) {
                    $seen[$p->nomor_surat] = true;
                    $pWaktu = $p->tanggal_pengajuan ? Carbon::parse($p->tanggal_pengajuan) : now();
                    $st = ($p->status_permohonan === 'ditolak') ? 'Ditolak' : (in_array($p->status_permohonan, ['disetujui', 'selesai'], true) ? 'Disetujui' : 'Dalam Proses');
                    $col = ($st === 'Disetujui') ? 'emerald' : (($st === 'Ditolak') ? 'rose' : 'blue');
                    $riwayatList[] = [
                        'id' => 'permohonan_'.md5($p->nomor_surat),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $p->judul_pengadaan,
                        'nomor_surat' => $p->nomor_surat,
                        'kode_unit' => $p->kode_unit,
                        'revisi' => ! empty($p->id_surat_asal_revisi) ? 'Revisi ke-1' : 'Revisi ke-0',
                        'aktivitas' => 'Pengajuan usulan unit (Status: '.ucwords(str_replace('_', ' ', (string) $p->status_permohonan)).')',
                        'jenis_aktivitas' => 'Pengajuan',
                        'status' => $st,
                        'status_color' => $col,
                        'link' => route('permohonan.index', ['selected' => $p->nomor_surat]),
                    ];
                }
            }
        }

        if ($user->hasPermission('reviu.pp')) {
            $items = PaketPengadaan::with('permohonan')
                ->where(fn (Builder $q) => $q->whereIn('jalur_routing', [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt'])->orWhere('id_user_pp', $user->id_user))
                ->latest('created_at')->take(10)->get();
            foreach ($items as $pkt) {
                if (! isset($seen[$pkt->nomor_paket])) {
                    $seen[$pkt->nomor_paket] = true;
                    $pWaktu = $pkt->created_at ?? now();
                    $riwayatList[] = [
                        'id' => 'paket_pp_'.md5($pkt->nomor_paket),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pkt->nama_paket,
                        'nomor_surat' => $pkt->nomor_paket,
                        'kode_unit' => $pkt->permohonan?->kode_unit ?? '',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Penanganan paket oleh Pejabat Pengadaan ('.ucwords(str_replace('_', ' ', (string) $pkt->status_paket)).')',
                        'jenis_aktivitas' => 'Pengadaan Langsung (PP)',
                        'status' => in_array($pkt->status_paket, ['spk_terbit', 'selesai'], true) ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => in_array($pkt->status_paket, ['spk_terbit', 'selesai'], true) ? 'emerald' : 'blue',
                        'link' => route('paket.index', ['selected' => $pkt->nomor_paket]),
                    ];
                }
            }
        }

        if ($user->hasPermission('reviu.ppk')) {
            $items = PaketPengadaan::with('permohonan')
                ->where('id_user_ppk', $user->id_user)
                ->latest('created_at')->take(10)->get();
            foreach ($items as $pkt) {
                if (! isset($seen[$pkt->nomor_paket])) {
                    $seen[$pkt->nomor_paket] = true;
                    $pWaktu = $pkt->created_at ?? now();
                    $riwayatList[] = [
                        'id' => 'paket_ppk_'.md5($pkt->nomor_paket),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pkt->nama_paket,
                        'nomor_surat' => $pkt->nomor_paket,
                        'kode_unit' => $pkt->permohonan?->kode_unit ?? '',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Komitmen pengadaan PPK ('.ucwords(str_replace('_', ' ', (string) $pkt->status_paket)).')',
                        'jenis_aktivitas' => 'Kontrak',
                        'status' => in_array($pkt->status_paket, ['spk_terbit', 'selesai'], true) ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => in_array($pkt->status_paket, ['spk_terbit', 'selesai'], true) ? 'emerald' : 'blue',
                        'link' => route('paket.index', ['selected' => $pkt->nomor_paket]),
                    ];
                }
            }
        }

        if ($user->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign'])) {
            $items = PenerimaanBarang::with(['paket.permohonan'])
                ->latest('tanggal_penerimaan')->take(10)->get();
            foreach ($items as $pb) {
                $suratKey = $pb->nomor_bast ?: 'BAST-'.$pb->id_penerimaan;
                if (! isset($seen[$suratKey])) {
                    $seen[$suratKey] = true;
                    $pWaktu = $pb->tanggal_penerimaan ? Carbon::parse($pb->tanggal_penerimaan) : now();
                    $riwayatList[] = [
                        'id' => 'penerimaan_'.md5($suratKey),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $pb->paket?->nama_paket ?? 'Penerimaan Hasil Pengadaan',
                        'nomor_surat' => $suratKey,
                        'kode_unit' => $pb->paket?->permohonan?->kode_unit ?? 'LKP',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Penerimaan & serah terima barang',
                        'jenis_aktivitas' => 'Penerimaan',
                        'status' => 'Disetujui',
                        'status_color' => 'emerald',
                        'link' => route('penerimaan.index'),
                    ];
                }
            }
        }

        if ($user->hasAnyPermission(['pembayaran.pencairan_sp2d', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan'])) {
            $items = Pembayaran::with(['paket.vendor', 'paket.permohonan'])
                ->latest('tanggal_pengajuan')->take(10)->get();
            foreach ($items as $byr) {
                if (! isset($seen[$byr->nomor_pembayaran])) {
                    $seen[$byr->nomor_pembayaran] = true;
                    $pWaktu = $byr->tanggal_pengajuan ? Carbon::parse($byr->tanggal_pengajuan) : now();
                    $isDone = in_array($byr->status_pembayaran, ['lunas', 'selesai', 'pencairan_selesai'], true);
                    $riwayatList[] = [
                        'id' => 'bayar_'.md5($byr->nomor_pembayaran),
                        'waktu' => $pWaktu->translatedFormat('d M Y, H:i'),
                        'waktu_raw' => $pWaktu->toIso8601String(),
                        'tahun' => (int) $pWaktu->format('Y'),
                        'bulan' => (int) $pWaktu->format('m'),
                        'nama_pengadaan' => $byr->paket?->nama_paket ?? ('Tagihan '.($byr->paket?->vendor?->nama_vendor ?? 'Vendor')),
                        'nomor_surat' => $byr->nomor_pembayaran,
                        'kode_unit' => $byr->paket?->permohonan?->kode_unit ?? 'KEU',
                        'revisi' => 'Revisi ke-0',
                        'aktivitas' => 'Verifikasi tagihan & pencairan dana',
                        'jenis_aktivitas' => 'Pembayaran',
                        'status' => $isDone ? 'Disetujui' : 'Dalam Proses',
                        'status_color' => $isDone ? 'emerald' : 'blue',
                        'link' => route('pembayaran.index'),
                    ];
                }
            }
        }

        return $riwayatList;
    }

    private function resolveJenisAktivitas(string $jenisAksi): string
    {
        $upper = strtoupper($jenisAksi);
        if (str_contains($upper, 'AJUKAN') || str_contains($upper, 'PERMOHONAN')) {
            return 'Pengajuan';
        }
        if (str_contains($upper, '_PP') || str_contains($upper, 'REVIEW_KELENGKAPAN_PP') || str_contains($upper, 'NEGOSIASI')) {
            return 'Pengadaan Langsung (PP)';
        }
        if (str_contains($upper, 'ACC') || str_contains($upper, 'SETUJU') || str_contains($upper, 'VERIFIKASI') || str_contains($upper, 'TELAAH')) {
            return 'Persetujuan';
        }
        if (str_contains($upper, 'TOLAK')) {
            return 'Penolakan';
        }
        if (str_contains($upper, 'REVISI')) {
            return 'Revisi';
        }
        if (str_contains($upper, 'MAK') || str_contains($upper, 'ANGGARAN')) {
            return 'Anggaran';
        }
        if (str_contains($upper, 'KONTRAK') || str_contains($upper, 'SPK') || str_contains($upper, 'BELANJA')) {
            return 'Kontrak';
        }
        if (str_contains($upper, 'BAST') || str_contains($upper, 'PENERIMAAN')) {
            return 'Penerimaan';
        }
        if (str_contains($upper, 'CAIR') || str_contains($upper, 'BAYAR') || str_contains($upper, 'SP2D')) {
            return 'Pembayaran';
        }

        return 'Sistem';
    }
}
