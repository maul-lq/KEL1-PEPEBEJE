/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini buat untuk mengotomatiskan pengisian nominal tagihan, nomor kwitansi,
 *             dan tautan arsip BAST pada pengajuan pembayaran PPBJ, modal penolakan baris, serta
 *             pemilihan tagihan SPP siap cair pada form eksekusi transfer SP2D Keuangan.
 */

import { parseJsonDataset } from './ui_helpers.js';

/**
 * Mengelola formulir pengajuan pembayaran oleh PPBJ dengan prefill metadata BAST & kwitansi.
 *
 * Tujuan: meringankan entri formulir tagihan dan menghindari kesalahan ketik nominal karena
 * sistem otomatis menautkan berkas BAST resmi serta foto inspeksi fisik paket terkait.
 * Parameter: Tidak menerima argumen (peta BAST dibaca dari atribut `data-config`).
 * Nilai kembali: Objek state dan handler perubahan paket untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="pembayaranPengajuan()" data-config="...">`
 */
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

        /**
         * Memuat peta paket-BAST awal serta mempertahankan nilai ketikan `old()` pasca validasi gagal.
         *
         * Tujuan: menyimpan sementara nilai lama lalu menimpakannya kembali sesudah `onPaketChange`
         * agar metadata BAST tetap tampil tanpa menimpa nominal editan pengguna saat error validasi.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen pengajuan pembayaran dimuat.
         */
        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.selectedPaket = cfg.selectedPaket || '';
            this.bastMap = cfg.bastMap || {};
            this.nominal = cfg.nominal ?? '';
            this.nomorKwitansi = cfg.nomorKwitansi || '';

            if (this.selectedPaket && this.bastMap[this.selectedPaket]) {
                // simpan dulu nilai old() sebelum memicu onPaketChange supaya koreksi pengguna tidak tertimpa nilai bawaan BAST.
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

        /**
         * Mengisi otomatis nominal, nomor kwitansi, URL berkas BAST, dan galeri foto saat paket dipilih.
         *
         * Tujuan: mereset seluruh state menjadi kosong bila pilihan paket dihapus agar tidak
         * menyisakan pratinjau dokumen milik paket lain.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="onPaketChange()"`
         */
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

/**
 * Mengelola state pembukaan modal alasan penolakan/revisi tagihan per baris tabel pembayaran.
 *
 * Tujuan: mengisolasi flag `openEdit` per baris supaya tombol revisi/tolak hanya membuka
 * form milik nomor pembayaran terkait.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js dengan properti `openEdit`.
 * Contoh pemanggilan: `<tr x-data="pembayaranRejectRow()">`
 */
export function pembayaranRejectRow() {
    return {
        openEdit: false,
    };
}

/**
 * Mengisi otomatis select tagihan cair dan input nominal transfer lalu menggulir layar ke form SP2D.
 *
 * Tujuan: memudahkan bendahara Keuangan memproses pencairan langsung dari baris tabel antrean
 * tanpa harus mencari ulang nomor pembayaran secara manual di dropdown.
 * Parameter: `{string} nomorPembayaran` - Nomor tagihan target; `{string|number} nominal` - Nominal tagihan.
 * Nilai kembali: Tidak mengembalikan nilai.
 * Contoh pemanggilan: `selectPembayaranCair('BYR-2026-001', '45000000');`
 */
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

/**
 * Mengikat listener dropdown tagihan dan delegasi klik tombol `[data-select-spp]` untuk SP2D.
 *
 * Tujuan: memakai event delegation agar tombol aksi pada daftar antrean selalu sinkron dengan
 * input `#nominal_cair` dan formulir `#form-pencairan-sp2d` tanpa script inline di view.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Tidak mengembalikan nilai.
 * Contoh pemanggilan: `initPembayaranPencairan();`
 */
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
