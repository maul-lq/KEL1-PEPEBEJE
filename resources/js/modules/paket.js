/**
 * Modul Manajemen Paket Pengadaan SIM-PBJ (paket.js)
 *
 * Menyatukan aturan penentuan jalur metode pengadaan berdasarkan besaran HPS,
 * perhitungan selisih pagu MAK, serta state pembuatan/perbaikan/daftar paket.
 */

import { formatRupiahId, parseJsonDataset } from './ui_helpers.js';

function resolvePaketRouting(hps) {
    const n = parseFloat(hps) || 0;
    if (n <= 0) {
        return null;
    }
    if (n <= 50000000) {
        return 'langsung';
    }
    if (n <= 200000000) {
        return 'pp';
    }
    return 'ppk';
}

function resolveDefaultMetode(routing, currentMetode) {
    if (routing === 'langsung') {
        return 'Pengadaan Langsung';
    }
    if (routing === 'pp') {
        if (currentMetode === 'Pengadaan Langsung' || !['SPK', 'Surat Pesanan', 'Surat Kontrak', 'E-Purchasing'].includes(currentMetode)) {
            return 'SPK';
        }
    } else if (routing === 'ppk') {
        if (currentMetode === 'Pengadaan Langsung' || !['Tender', 'E-Purchasing', 'Seleksi', 'Penunjukan Langsung'].includes(currentMetode)) {
            return 'Tender';
        }
    }
    return currentMetode;
}

export function paketCreate() {
    return {
        hps: '',
        namaPaket: '',
        metode: '',
        selectedSurat: '',
        permohonanMap: {},

        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.hps = cfg.hps ?? '';
            this.namaPaket = cfg.namaPaket ?? '';
            this.metode = cfg.metode ?? '';
            this.selectedSurat = cfg.selectedSurat ?? '';
            this.permohonanMap = cfg.permohonanMap || {};

            if (this.selectedSurat) {
                this.onSelectSurat(this.selectedSurat);
            }
        },

        onSelectSurat(nomor) {
            this.selectedSurat = nomor;
            if (nomor && this.permohonanMap[nomor]) {
                const item = this.permohonanMap[nomor];
                this.hps = item.pagu;
                if (!this.namaPaket || this.namaPaket.trim() === '') {
                    this.namaPaket = item.perihal ? `Pengadaan ${item.perihal}` : '';
                }
                this.syncMetodeWithRouting();
            }
        },

        routing() {
            return resolvePaketRouting(this.hps);
        },

        syncMetodeWithRouting() {
            this.metode = resolveDefaultMetode(this.routing(), this.metode);
        },

        getDeltaMak() {
            if (!this.selectedSurat || !this.permohonanMap[this.selectedSurat]) {
                return 0;
            }
            const n = parseFloat(this.hps) || 0;
            return n - this.permohonanMap[this.selectedSurat].pagu;
        },

        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}

export function paketEdit() {
    return {
        hps: '',
        hpsAwal: 0,
        sisaMak: 0,
        metode: '',

        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.hps = cfg.hps ?? '';
            this.hpsAwal = parseFloat(cfg.hpsAwal || 0) || 0;
            this.sisaMak = parseFloat(cfg.sisaMak || 0) || 0;
            this.metode = cfg.metode ?? '';
        },

        routing() {
            return resolvePaketRouting(this.hps);
        },

        syncMetodeWithRouting() {
            this.metode = resolveDefaultMetode(this.routing(), this.metode);
        },

        getDeltaMak() {
            const n = parseFloat(this.hps) || 0;
            return n - this.hpsAwal;
        },

        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}

export function paketIndexPage() {
    return {
        selectedPaket: '',
    };
}
