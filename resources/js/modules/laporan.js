/**
 * Modul Frontend Riwayat dan Laporan SIM-PBJ Politeknik Negeri Jakarta (laporan.js)
 *
 * Mengelola interaktivitas reaktif Alpine.js pada halaman /laporan:
 * - Tab switcher (Riwayat Aktivitas & Laporan)
 * - Pencarian dan filter instan tanpa reload halaman
 * - Pagination interaktif client-side
 * - Sinkronisasi URL query string (deep linking & bookmarking)
 */

export function laporanApp(config = {}) {
    return {
        // State Tab Aktif
        activeTab: config.initialTab || 'riwayat',

        // Data Master dari Server
        rawRiwayat: Array.isArray(config.riwayatData) ? config.riwayatData : [],
        laporanCards: Array.isArray(config.laporanCards) ? config.laporanCards : [],
        rawLaporanRows: Array.isArray(config.laporanRows) ? config.laporanRows : [],
        ringkasanTitle: config.ringkasanTitle || 'Ringkasan Realisasi & Pengadaan',
        tableTitle: config.tableTitle || 'Distribusi Anggaran per Jurusan dan Unit Kerja',
        tableColumns: Array.isArray(config.tableColumns) ? config.tableColumns : [],
        userRole: config.userRole || 'user',

        // State Filter Tab Riwayat
        riwayatSearch: '',
        riwayatFilterAktivitas: '',
        riwayatFilterWaktu: '',
        riwayatFilterStatus: '',
        riwayatPage: 1,
        riwayatPerPage: 10,

        // State Filter Tab Laporan
        laporanSearch: '',
        laporanFilterTahun: config.currentYear || new Date().getFullYear().toString(),
        laporanFilterBulan: config.currentMonth || '',
        laporanFilterUnit: config.currentUnit || '',
        laporanPage: 1,
        laporanPerPage: 10,

        /**
         * Inisialisasi komponen: baca parameter dari URL query string
         */
        init() {
            const params = new URLSearchParams(window.location.search);
            if (params.has('tab')) {
                const tabParam = params.get('tab');
                if (tabParam === 'riwayat' || tabParam === 'laporan') {
                    this.activeTab = tabParam;
                }
            }

            if (params.has('q')) {
                this.riwayatSearch = params.get('q');
                this.laporanSearch = params.get('q');
            }

            if (params.has('aktivitas')) {
                this.riwayatFilterAktivitas = params.get('aktivitas');
            }

            if (params.has('waktu')) {
                this.riwayatFilterWaktu = params.get('waktu');
            }

            if (params.has('status')) {
                this.riwayatFilterStatus = params.get('status');
            }

            if (params.has('tahun')) {
                this.laporanFilterTahun = params.get('tahun');
            }

            if (params.has('bulan')) {
                this.laporanFilterBulan = params.get('bulan');
            }

            if (params.has('unit')) {
                this.laporanFilterUnit = params.get('unit');
            }
        },

        /**
         * Ganti Tab aktif dan sinkronkan URL
         */
        switchTab(tab) {
            this.activeTab = tab;
            this.syncUrl();
        },

        /**
         * Sinkronkan parameter aktif ke URL query string (Deep Linking)
         */
        syncUrl() {
            const url = new URL(window.location);
            url.searchParams.set('tab', this.activeTab);

            if (this.activeTab === 'riwayat') {
                if (this.riwayatSearch) url.searchParams.set('q', this.riwayatSearch);
                else url.searchParams.delete('q');

                if (this.riwayatFilterAktivitas) url.searchParams.set('aktivitas', this.riwayatFilterAktivitas);
                else url.searchParams.delete('aktivitas');

                if (this.riwayatFilterWaktu) url.searchParams.set('waktu', this.riwayatFilterWaktu);
                else url.searchParams.delete('waktu');

                if (this.riwayatFilterStatus) url.searchParams.set('status', this.riwayatFilterStatus);
                else url.searchParams.delete('status');
            } else {
                if (this.laporanSearch) url.searchParams.set('q', this.laporanSearch);
                else url.searchParams.delete('q');

                if (this.laporanFilterTahun) url.searchParams.set('tahun', this.laporanFilterTahun);
                if (this.laporanFilterBulan) url.searchParams.set('bulan', this.laporanFilterBulan);
                else url.searchParams.delete('bulan');

                if (this.laporanFilterUnit) url.searchParams.set('unit', this.laporanFilterUnit);
                else url.searchParams.delete('unit');
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
         * Reset seluruh filter pada Tab Laporan
         */
        resetLaporanFilter() {
            this.laporanSearch = '';
            this.laporanFilterBulan = '';
            if (this.userRole !== 'user') {
                this.laporanFilterUnit = '';
            }
            this.laporanPage = 1;
            this.syncUrl();
        },

        /**
         * Reset kedua tab sekaligus
         */
        resetAllFilters() {
            this.resetRiwayatFilter();
            this.resetLaporanFilter();
        },

        // =====================================================================
        // GETTER & FILTER REAKTIF: TAB RIWAYAT
        // =====================================================================
        get filteredRiwayat() {
            return this.rawRiwayat.filter(item => {
                // Filter Pencarian Teks
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

                // Filter Jenis Aktivitas
                if (this.riwayatFilterAktivitas) {
                    if ((item.jenis_aktivitas || '').toLowerCase() !== this.riwayatFilterAktivitas.toLowerCase()) {
                        return false;
                    }
                }

                // Filter Status
                if (this.riwayatFilterStatus) {
                    if ((item.status || '').toLowerCase() !== this.riwayatFilterStatus.toLowerCase()) {
                        return false;
                    }
                }

                // Filter Waktu
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
                        if (itemDate.getFullYear().toString() !== this.laporanFilterTahun.toString()) return false;
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
            return this.rawLaporanRows.filter(row => {
                // Filter Pencarian Teks
                if (this.laporanSearch) {
                    const q = this.laporanSearch.toLowerCase();
                    const c1 = (row.col1 || '').toLowerCase().includes(q);
                    const c2 = (row.col2 || '').toLowerCase().includes(q);
                    const c3 = (row.col3 || '').toLowerCase().includes(q);
                    const c4 = (row.col4 || '').toLowerCase().includes(q);
                    const c5 = (row.col5 || '').toLowerCase().includes(q);
                    if (!c1 && !c2 && !c3 && !c4 && !c5) {
                        return false;
                    }
                }

                // Filter Unit Kerja (Jika role bukan user)
                if (this.laporanFilterUnit && this.userRole !== 'user') {
                    const matchUnit = (row.col1 || '').toLowerCase() === this.laporanFilterUnit.toLowerCase() ||
                                      (row.nomor_surat || '').toLowerCase().includes(this.laporanFilterUnit.toLowerCase());
                    if (!matchUnit) {
                        return false;
                    }
                }

                return true;
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
        }
    };
}
