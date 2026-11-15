/**
 * Modul Frontend Riwayat dan Laporan Terpadu SIM-PBJ Politeknik Negeri Jakarta (laporan.js)
 *
 * Mengelola interaktivitas reaktif Alpine.js pada halaman /laporan:
 * - Tab switcher (Riwayat Aktivitas & Laporan)
 * - Switcher sub-modul laporan berbasis izin RBAC aktif (termasuk gabungan Multi-Modul / Semua Modul)
 * - Pembaruan data KPI dan tabel dari server secara reaktif saat filter berubah (tahun, bulan, unit, status, role)
 * - Pencarian instan & pagination client-side
 * - Sinkronisasi URL query string (deep linking)
 * - Unduh Laporan simultan (PDF terformat + RAW CSV) sesuai modul & filter aktif
 */

import { parseJsonDataset } from './ui_helpers.js';

function applyLaporanConfig(target, config) {
    const cfg = config && typeof config === 'object' ? config : {};
    const modular = cfg.modularPayload && typeof cfg.modularPayload === 'object'
        ? cfg.modularPayload
        : {};

    target.activeTab = cfg.initialTab || 'riwayat';
    target.rawRiwayat = Array.isArray(cfg.riwayatData) ? cfg.riwayatData : [];
    target.modularPayload = modular;
    target.selectedModule = modular.selected_module || 'semua';
    target.rolesList = Array.isArray(modular.roles_list) ? modular.roles_list : [];
    target.canDownload = Boolean(cfg.canDownload);
    target.userUnitCode = cfg.userUnitCode || '';
    target.userUnitName = cfg.userUnitName || '';
    target.endpointUrl = cfg.endpointUrl || '/laporan';
    target.pdfUrl = cfg.pdfUrl || '/laporan/unduh-pdf';
    target.csvUrl = cfg.csvUrl || '/laporan/unduh-csv';
    target.defaultYear = cfg.defaultYear || new Date().getFullYear().toString();
    target.laporanSearch = cfg.currentSearch || '';
    target.serverFilteredQ = cfg.currentSearch || '';
    target.laporanFilterTahun = cfg.currentYear || new Date().getFullYear().toString();
    target.laporanFilterBulan = cfg.currentMonth || '';
    target.laporanFilterUnit = cfg.currentUnit || '';
    target.laporanFilterStatus = cfg.currentStatus || '';
    target.laporanFilterRole = cfg.currentRoleFilter || '';
}

export function laporanApp(config = null) {
    const state = {
        // State Tab Utama
        activeTab: 'riwayat',

        // Data Tab Riwayat
        rawRiwayat: [],

        // Data & Konfigurasi Tab Laporan Modular RBAC
        modularPayload: {},
        selectedModule: 'semua',
        rolesList: [],
        canDownload: false,
        userUnitCode: '',
        userUnitName: '',
        endpointUrl: '/laporan',
        pdfUrl: '/laporan/unduh-pdf',
        csvUrl: '/laporan/unduh-csv',
        defaultYear: new Date().getFullYear().toString(),

        // State Filter Tab Riwayat
        riwayatSearch: '',
        riwayatFilterAktivitas: '',
        riwayatFilterWaktu: '',
        riwayatFilterStatus: '',
        riwayatPage: 1,
        riwayatPerPage: 10,

        // State Filter Tab Laporan
        laporanSearch: '',
        serverFilteredQ: '',
        laporanFilterTahun: new Date().getFullYear().toString(),
        laporanFilterBulan: '',
        laporanFilterUnit: '',
        laporanFilterStatus: '',
        laporanFilterRole: '',
        laporanPage: 1,
        laporanPerPage: 10,
        isLoadingLaporan: false,
        isDownloading: false,
        downloadFeedback: '',
        fetchController: null,

        /**
         * Inisialisasi komponen: muat konfigurasi atribut data dan sinkronkan query string
         */
        init() {
            if (!config && this.$el?.dataset?.config) {
                applyLaporanConfig(this, parseJsonDataset(this.$el, 'config', {}));
            }
            const params = new URLSearchParams(window.location.search);
            if (params.has('tab')) {
                const tabParam = params.get('tab');
                if (tabParam === 'riwayat' || tabParam === 'laporan') {
                    this.activeTab = tabParam;
                }
            }

            if (params.has('module')) {
                const modParam = params.get('module');
                if (this.moduleTabs.some(t => t.key === modParam)) {
                    this.selectedModule = modParam;
                }
            }

            if (params.has('q')) {
                const qVal = params.get('q') || '';
                if (this.activeTab === 'riwayat') {
                    this.riwayatSearch = qVal;
                } else {
                    this.laporanSearch = qVal;
                }
            }

            if (params.has('aktivitas')) {
                this.riwayatFilterAktivitas = params.get('aktivitas') || '';
            }
            if (params.has('waktu')) {
                this.riwayatFilterWaktu = params.get('waktu') || '';
            }
            if (params.has('status')) {
                const st = params.get('status') || '';
                if (this.activeTab === 'riwayat') {
                    this.riwayatFilterStatus = st;
                } else {
                    this.laporanFilterStatus = st;
                }
            }
            if (params.has('tahun')) {
                const thn = params.get('tahun') || '';
                this.laporanFilterTahun = thn === '' ? 'all' : thn;
            }
            if (params.has('bulan')) {
                this.laporanFilterBulan = params.get('bulan') || '';
            }
            if (params.has('unit')) {
                this.laporanFilterUnit = params.get('unit') || '';
            }
            if (params.has('role_filter')) {
                this.laporanFilterRole = params.get('role_filter') || '';
            }
        },

        // =====================================================================
        // GETTER MODULAR RBAC (TAB LAPORAN)
        // =====================================================================
        get activeModuleKeys() {
            return Array.isArray(this.modularPayload.active_module_keys)
                ? this.modularPayload.active_module_keys
                : [];
        },

        get moduleTabs() {
            return Array.isArray(this.modularPayload.tabs)
                ? this.modularPayload.tabs
                : [];
        },

        get currentView() {
            if (this.selectedModule === 'semua' && this.modularPayload.semua_view) {
                return this.modularPayload.semua_view;
            }
            if (this.modularPayload.modules && this.modularPayload.modules[this.selectedModule]) {
                return this.modularPayload.modules[this.selectedModule];
            }
            return this.modularPayload.current_view || {
                key: 'kosong',
                tab_label: 'Belum Ada Modul Laporan',
                description: 'Akun Anda belum memiliki akses modul laporan aktif.',
                ringkasan_title: 'Ringkasan Realisasi & Pengadaan',
                table_title: 'Rincian Data Laporan',
                filters: {
                    tahun: false,
                    bulan: false,
                    unit: 'none',
                    status_label: null,
                    status_options: [],
                    has_role_filter: false,
                },
                cards: [],
                columns: ['No.', 'Referensi', 'Uraian', 'Tanggal', 'Nilai', 'Status'],
                rows: [],
            };
        },

        get currentFiltersConfig() {
            return this.currentView.filters || {
                tahun: false,
                bulan: false,
                unit: 'none',
                status_label: null,
                status_options: [],
                has_role_filter: false,
            };
        },

        get laporanCards() {
            return Array.isArray(this.currentView.cards) ? this.currentView.cards : [];
        },

        get ringkasanTitle() {
            return this.currentView.ringkasan_title || 'Ringkasan Realisasi & Pengadaan';
        },

        get tableTitle() {
            return this.currentView.table_title || 'Rincian Data Laporan';
        },

        get tableColumns() {
            return Array.isArray(this.currentView.columns) ? this.currentView.columns : [];
        },

        get rawLaporanRows() {
            return Array.isArray(this.currentView.rows) ? this.currentView.rows : [];
        },

        // =====================================================================
        // NAVIGASI TAB & SUB-MODUL RBAC
        // =====================================================================
        switchTab(tab) {
            this.activeTab = tab;
            this.syncUrl();
        },

        switchModule(moduleKey) {
            const exists = this.moduleTabs.some(t => t.key === moduleKey);
            if (!exists || this.selectedModule === moduleKey) {
                return;
            }

            this.selectedModule = moduleKey;
            this.laporanPage = 1;

            const opts = this.currentFiltersConfig.status_options || [];
            const validStatus = this.laporanFilterStatus !== ''
                && opts.some(o => String(o.value) === String(this.laporanFilterStatus));

            let shouldRefetch = false;
            if (this.laporanFilterStatus !== '' && !validStatus) {
                this.laporanFilterStatus = '';
                shouldRefetch = true;
            }

            this.syncUrl();
            if (shouldRefetch) {
                this.refreshLaporanFromServer();
            }
        },

        onQueryFilterChange() {
            this.laporanPage = 1;
            this.syncUrl();
            this.refreshLaporanFromServer();
        },

        onSearchInput() {
            this.laporanPage = 1;
            this.syncUrl();
            if (this.serverFilteredQ && !this.laporanSearch.toLowerCase().includes(this.serverFilteredQ.toLowerCase())) {
                this.refreshLaporanFromServer();
            }
        },

        /**
         * Menyusun query parameters laporan untuk AJAX JSON maupun ekspor PDF/CSV.
         */
        buildLaporanQueryParams(includeSearch = true, includeLaporanOnly = false) {
            const params = new URLSearchParams();

            if (includeLaporanOnly) {
                params.set('laporan_only', '1');
            }
            if (this.selectedModule) {
                params.set('module', this.selectedModule);
            }
            params.set('tahun', this.laporanFilterTahun || 'all');

            if (this.laporanFilterBulan) {
                params.set('bulan', this.laporanFilterBulan);
            }
            if (this.laporanFilterUnit && this.currentFiltersConfig.unit !== 'locked_own') {
                params.set('unit', this.laporanFilterUnit);
            }
            if (this.laporanFilterStatus) {
                params.set('status', this.laporanFilterStatus);
            }
            if (this.laporanFilterRole) {
                params.set('role_filter', this.laporanFilterRole);
            }
            if (includeSearch && this.laporanSearch) {
                params.set('q', this.laporanSearch);
            }

            return params;
        },

        /**
         * Memuat ulang kartu KPI dan baris rincian modul laporan dari server sesuai filter aktif.
         */
        async refreshLaporanFromServer() {
            if (this.fetchController) {
                this.fetchController.abort();
            }
            this.fetchController = new AbortController();
            this.isLoadingLaporan = true;

            try {
                const qs = this.buildLaporanQueryParams(false, true).toString();
                const res = await fetch(`${this.endpointUrl}?${qs}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: this.fetchController.signal,
                });

                if (!res.ok) {
                    return;
                }

                const data = await res.json();
                if (data && data.modular) {
                    this.modularPayload = data.modular;
                    this.serverFilteredQ = '';
                    if (Array.isArray(data.modular.roles_list)) {
                        this.rolesList = data.modular.roles_list;
                    }
                    if (data.modular.selected_module && !this.moduleTabs.some(t => t.key === this.selectedModule)) {
                        this.selectedModule = data.modular.selected_module;
                    }
                    this.laporanPage = 1;
                }
            } catch (err) {
                if (err?.name !== 'AbortError') {
                    console.error('Gagal memperbarui data laporan:', err);
                }
            } finally {
                this.isLoadingLaporan = false;
            }
        },

        /**
         * Mengunduh laporan PDF terformat dan CSV (RAW) secara simultan sesuai modul & filter aktif.
         */
        downloadLaporan() {
            if (!this.canDownload || this.isDownloading || this.activeModuleKeys.length === 0) {
                return;
            }

            const qs = this.buildLaporanQueryParams(true, false).toString();
            const pdfHref = `${this.pdfUrl}?${qs}`;
            const csvHref = `${this.csvUrl}?${qs}`;

            this.isDownloading = true;
            this.downloadFeedback = 'Mengunduh PDF & CSV...';

            const triggerFile = (href) => {
                const link = document.createElement('a');
                link.href = href;
                link.setAttribute('download', '');
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                setTimeout(() => link.remove(), 1000);
            };

            triggerFile(pdfHref);
            setTimeout(() => {
                triggerFile(csvHref);
            }, 450);

            setTimeout(() => {
                this.isDownloading = false;
                this.downloadFeedback = '';
            }, 1600);
        },

        /**
         * Sinkronkan parameter aktif ke URL query string (Deep Linking)
         */
        syncUrl() {
            const url = new URL(window.location);
            url.searchParams.set('tab', this.activeTab);

            if (this.activeTab === 'riwayat') {
                url.searchParams.delete('module');
                url.searchParams.delete('role_filter');

                if (this.riwayatSearch) url.searchParams.set('q', this.riwayatSearch);
                else url.searchParams.delete('q');

                if (this.riwayatFilterAktivitas) url.searchParams.set('aktivitas', this.riwayatFilterAktivitas);
                else url.searchParams.delete('aktivitas');

                if (this.riwayatFilterWaktu) url.searchParams.set('waktu', this.riwayatFilterWaktu);
                else url.searchParams.delete('waktu');

                if (this.riwayatFilterStatus) url.searchParams.set('status', this.riwayatFilterStatus);
                else url.searchParams.delete('status');
            } else {
                url.searchParams.delete('aktivitas');
                url.searchParams.delete('waktu');

                if (this.selectedModule) url.searchParams.set('module', this.selectedModule);
                else url.searchParams.delete('module');

                if (this.laporanSearch) url.searchParams.set('q', this.laporanSearch);
                else url.searchParams.delete('q');

                if (this.currentFiltersConfig.tahun && this.laporanFilterTahun) {
                    url.searchParams.set('tahun', this.laporanFilterTahun);
                } else {
                    url.searchParams.delete('tahun');
                }

                if (this.currentFiltersConfig.bulan && this.laporanFilterBulan) {
                    url.searchParams.set('bulan', this.laporanFilterBulan);
                } else {
                    url.searchParams.delete('bulan');
                }

                if (this.currentFiltersConfig.unit === 'all' && this.laporanFilterUnit) {
                    url.searchParams.set('unit', this.laporanFilterUnit);
                } else {
                    url.searchParams.delete('unit');
                }

                if ((this.currentFiltersConfig.status_options || []).length > 0 && this.laporanFilterStatus) {
                    url.searchParams.set('status', this.laporanFilterStatus);
                } else {
                    url.searchParams.delete('status');
                }

                if (this.currentFiltersConfig.has_role_filter && this.laporanFilterRole) {
                    url.searchParams.set('role_filter', this.laporanFilterRole);
                } else {
                    url.searchParams.delete('role_filter');
                }
            }

            window.history.replaceState({}, '', url);
        },

        /**
         * Reset seluruh filter pada Tab Riwayat
         */
        resetRiwayatFilter() {
            this.riwayatSearch = '';
            this.riwayatFilterAktivitas = '';
            this.riwayatFilterWaktu = '';
            this.riwayatFilterStatus = '';
            this.riwayatPage = 1;
            this.syncUrl();
        },

        /**
         * Reset seluruh filter pada Tab Laporan dan muat ulang data server
         */
        resetLaporanFilter() {
            this.laporanSearch = '';
            this.laporanFilterTahun = this.defaultYear;
            this.laporanFilterBulan = '';
            if (this.currentFiltersConfig.unit !== 'locked_own') {
                this.laporanFilterUnit = '';
            }
            this.laporanFilterStatus = '';
            this.laporanFilterRole = '';
            this.laporanPage = 1;
            this.syncUrl();
            this.refreshLaporanFromServer();
        },

        /**
         * Reset kedua tab sekaligus
         */
        resetAllFilters() {
            this.resetRiwayatFilter();
            this.resetLaporanFilter();
        },

        /**
         * Menentukan kelas badge status pada kolom terakhir tabel laporan.
         */
        statusBadgeClass(val) {
            const lower = (val || '').toLowerCase();
            if (['ditolak', 'batal', 'nonaktif', 'tidak sesuai', 'terkunci', 'gagal'].some(s => lower.includes(s))) {
                return 'bg-rose-50 text-rose-700 border border-rose-200';
            }
            if (['revisi', 'perlu perbaikan', 'kurang lengkap', 'menunggu'].some(s => lower.includes(s))) {
                return 'bg-amber-50 text-amber-700 border border-amber-200';
            }
            if (['aktif', 'disetujui', 'selesai', 'lunas', 'diterima', 'terima', 'lengkap', 'sesuai', 'spk terbit', 'spk_terbit', 'kontrak terbit', 'cair'].some(s => lower.includes(s))) {
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            }
            return 'bg-blue-50 text-[#0969da] border border-blue-200';
        },

        // =====================================================================
        // GETTER & FILTER REAKTIF: TAB RIWAYAT
        // =====================================================================
        get filteredRiwayat() {
            return this.rawRiwayat.filter(item => {
                if (this.riwayatSearch) {
                    const q = this.riwayatSearch.toLowerCase();
                    const matchNomor = (item.nomor_surat || '').toLowerCase().includes(q);
                    const matchJudul = (item.nama_pengadaan || '').toLowerCase().includes(q);
                    const matchAktivitas = (item.aktivitas || '').toLowerCase().includes(q);
                    const matchUnit = (item.kode_unit || '').toLowerCase().includes(q);
                    if (!matchNomor && !matchJudul && !matchAktivitas && !matchUnit) {
                        return false;
                    }
                }

                if (this.riwayatFilterAktivitas) {
                    if ((item.jenis_aktivitas || '').toLowerCase() !== this.riwayatFilterAktivitas.toLowerCase()) {
                        return false;
                    }
                }

                if (this.riwayatFilterStatus) {
                    if ((item.status || '').toLowerCase() !== this.riwayatFilterStatus.toLowerCase()) {
                        return false;
                    }
                }

                if (this.riwayatFilterWaktu && item.waktu_raw) {
                    const itemDate = new Date(item.waktu_raw);
                    const now = new Date();

                    if (this.riwayatFilterWaktu === 'hari_ini') {
                        if (itemDate.toDateString() !== now.toDateString()) return false;
                    } else if (this.riwayatFilterWaktu === 'minggu_ini') {
                        const diffTime = Math.abs(now - itemDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        if (diffDays > 7) return false;
                    } else if (this.riwayatFilterWaktu === 'bulan_ini') {
                        if (itemDate.getMonth() !== now.getMonth() || itemDate.getFullYear() !== now.getFullYear()) return false;
                    } else if (this.riwayatFilterWaktu === 'tahun_ini') {
                        const targetYear = this.laporanFilterTahun && this.laporanFilterTahun !== 'all'
                            ? this.laporanFilterTahun.toString()
                            : now.getFullYear().toString();
                        if (itemDate.getFullYear().toString() !== targetYear) return false;
                    }
                }

                return true;
            });
        },

        get totalRiwayatPages() {
            return Math.max(1, Math.ceil(this.filteredRiwayat.length / this.riwayatPerPage));
        },

        get paginatedRiwayat() {
            const start = (this.riwayatPage - 1) * this.riwayatPerPage;
            return this.filteredRiwayat.slice(start, start + this.riwayatPerPage);
        },

        get riwayatStartIndex() {
            if (this.filteredRiwayat.length === 0) return 0;
            return (this.riwayatPage - 1) * this.riwayatPerPage + 1;
        },

        get riwayatEndIndex() {
            return Math.min(this.riwayatPage * this.riwayatPerPage, this.filteredRiwayat.length);
        },

        // =====================================================================
        // GETTER & FILTER REAKTIF: TAB LAPORAN
        // =====================================================================
        get filteredLaporan() {
            if (!this.laporanSearch) {
                return this.rawLaporanRows;
            }
            const q = this.laporanSearch.toLowerCase();
            return this.rawLaporanRows.filter(row => {
                const fields = [
                    row.col1,
                    row.col2,
                    row.col3,
                    row.col4,
                    row.col5,
                    row.nomor_surat,
                    row.judul,
                    row.kode_unit,
                    row.modul,
                ];
                return fields.some(val => String(val || '').toLowerCase().includes(q));
            });
        },

        get totalLaporanPages() {
            return Math.max(1, Math.ceil(this.filteredLaporan.length / this.laporanPerPage));
        },

        get paginatedLaporan() {
            const start = (this.laporanPage - 1) * this.laporanPerPage;
            return this.filteredLaporan.slice(start, start + this.laporanPerPage);
        },

        get laporanStartIndex() {
            if (this.filteredLaporan.length === 0) return 0;
            return (this.laporanPage - 1) * this.laporanPerPage + 1;
        },

        get laporanEndIndex() {
            return Math.min(this.laporanPage * this.laporanPerPage, this.filteredLaporan.length);
        },
    };

    if (config && typeof config === 'object') {
        applyLaporanConfig(state, config);
    }

    return state;
}
