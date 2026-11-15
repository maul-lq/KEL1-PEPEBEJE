/**
 * Modul Kontrak & Belanja Pengadaan SIM-PBJ (kontrak.js)
 *
 * Mengatur perpindahan tab dokumen, pratinjau paket, dan pengisian
 * otomatis batas nilai kontrak definitif pada formulir perikatan.
 */

import { formatRupiahId, parseJsonDataset } from './ui_helpers.js';

export function kontrakManager() {
    return {
        activeTab: 'semua',
        hpsVal: 0,
        selectedStatusReviu: 'terverifikasi_pp',
        selectedPaket: {
            nomor: '',
            nama: '',
            unit: '',
            hps: 0,
            status: '',
            surat: '',
            fileDokumen: '',
            fileKak: '',
            fileSpek: '',
        },
        spkSelectedPaket: '',
        spkPaketMap: {},
        spkHpsMax: 0,
        spkNilaiKontrak: '',
        spkNamaVendor: '',

        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.activeTab = cfg.activeTab || 'semua';
            this.selectedStatusReviu = cfg.selectedStatusReviu || 'terverifikasi_pp';
            this.spkSelectedPaket = cfg.spkSelectedPaket || '';
            this.spkPaketMap = cfg.spkPaketMap || {};
            this.spkNilaiKontrak = cfg.spkNilaiKontrak ?? '';
            this.spkNamaVendor = cfg.spkNamaVendor || '';

            if (this.spkSelectedPaket && this.spkPaketMap[this.spkSelectedPaket]) {
                this.onSelectSpkPaket(false);
            }
        },

        onSelectSpkPaket(overrideNilai = true) {
            const data = this.spkPaketMap[this.spkSelectedPaket];
            if (!data) {
                this.spkHpsMax = 0;
                if (overrideNilai) {
                    this.spkNilaiKontrak = '';
                }
                return;
            }
            this.spkHpsMax = parseFloat(data.hps) || 0;
            if (overrideNilai) {
                const defVal = parseFloat(data.nilai_default) || this.spkHpsMax;
                this.spkNilaiKontrak = defVal ? Math.round(defVal * 100) / 100 : '';
            }
            if (data.vendor && !this.spkNamaVendor) {
                this.spkNamaVendor = data.vendor;
            }
        },

        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}
