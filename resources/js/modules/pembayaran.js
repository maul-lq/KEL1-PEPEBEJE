/**
 * Modul Pengajuan & Pencairan Pembayaran SIM-PBJ (pembayaran.js)
 *
 * Mengelola pengisian otomatis data BAST/kwitansi saat memilih paket tagihan
 * serta mekanisme pemilihan nomor tagihan untuk dieksekusi bagian Keuangan.
 */

import { parseJsonDataset } from './ui_helpers.js';

export function pembayaranPengajuan() {
    return {
        selectedPaket: '',
        bastMap: {},
        nominal: '',
        nomorKwitansi: '',
        hasExistingKwitansi: false,
        existingKwitansiUrl: '',
        hasExistingBast: false,
        existingBastUrl: '',
        fotoList: [],

        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.selectedPaket = cfg.selectedPaket || '';
            this.bastMap = cfg.bastMap || {};
            this.nominal = cfg.nominal ?? '';
            this.nomorKwitansi = cfg.nomorKwitansi || '';

            if (this.selectedPaket && this.bastMap[this.selectedPaket]) {
                const prevNominal = this.nominal;
                const prevKwitansi = this.nomorKwitansi;
                this.onPaketChange();
                if (prevNominal) {
                    this.nominal = prevNominal;
                }
                if (prevKwitansi) {
                    this.nomorKwitansi = prevKwitansi;
                }
            }
        },

        onPaketChange() {
            const data = this.bastMap[this.selectedPaket];
            if (data) {
                this.nominal = data.nominal || '';
                this.nomorKwitansi = data.nomor_kwitansi || '';
                this.hasExistingKwitansi = !!data.file_kwitansi;
                this.existingKwitansiUrl = data.file_kwitansi || '';
                this.hasExistingBast = !!data.file_bast_signed;
                this.existingBastUrl = data.file_bast_signed || '';
                this.fotoList = data.foto_dokumentasi || [];
            } else {
                this.nominal = '';
                this.nomorKwitansi = '';
                this.hasExistingKwitansi = false;
                this.existingKwitansiUrl = '';
                this.hasExistingBast = false;
                this.existingBastUrl = '';
                this.fotoList = [];
            }
        },
    };
}

export function pembayaranRejectRow() {
    return {
        openEdit: false,
    };
}

function selectPembayaranCair(nomorPembayaran, nominal) {
    const sel = document.getElementById('nomor_pembayaran_cair');
    if (sel) {
        sel.value = nomorPembayaran || '';
    }

    const nomInput = document.getElementById('nominal_cair');
    if (nomInput && nominal) {
        nomInput.value = nominal;
    }

    const formEl = document.getElementById('form-pencairan-sp2d');
    if (formEl) {
        formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

export function initPembayaranPencairan() {
    const selectSpp = document.getElementById('nomor_pembayaran_cair');
    if (selectSpp) {
        selectSpp.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const nom = opt?.getAttribute('data-nominal');
            const nomInput = document.getElementById('nominal_cair');
            if (nom && nomInput) {
                nomInput.value = nom;
            }
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const btn = target.closest('[data-select-spp]');
        if (btn) {
            selectPembayaranCair(
                btn.dataset.selectSpp,
                btn.dataset.selectNominal
            );
        }
    });
}
