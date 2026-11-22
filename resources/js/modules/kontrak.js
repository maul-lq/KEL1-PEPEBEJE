/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini rancang untuk mengelola tab dokumen perikatan, pratinjau paket, dan
 *             pengisian otomatis nilai batas kontrak definitif pada formulir pembuatan SPK/Kontrak.
 */

import { formatRupiahId, parseJsonDataset } from './ui_helpers.js';

/**
 * Mengelola state antarmuka Kontrak/SPK dan sinkronisasi nilai default berdasarkan paket terpilih.
 *
 * Tujuan: mengisi otomatis batas maksimum HPS (`spkHpsMax`), nominal default kontrak, dan
 * nama vendor bawaan ketika PPBJ memilih nomor paket pada formulir penerbitan SPK.
 * Parameter: Tidak menerima argumen (konfigurasi awal dibaca dari atribut `data-config`).
 * Nilai kembali: Objek state dan method interaktif Kontrak/SPK untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="kontrakManager()" data-config="...">`
 */
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

        /**
         * Memuat konfigurasi awal halaman kontrak dan melakukan prefill bila ada paket terpilih.
         *
         * Tujuan: memanggil `onSelectSpkPaket(false)` saat inisialisasi supaya nilai input lama
         * (misalnya `old('nilai_kontrak')` pasca gagal validasi) tidak tertimpa nilai default.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis oleh siklus hidup Alpine `init()`.
         */
        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.activeTab = cfg.activeTab || 'semua';
            this.selectedStatusReviu = cfg.selectedStatusReviu || 'terverifikasi_pp';
            this.spkSelectedPaket = cfg.spkSelectedPaket || '';
            this.spkPaketMap = cfg.spkPaketMap || {};
            this.spkNilaiKontrak = cfg.spkNilaiKontrak ?? '';
            this.spkNamaVendor = cfg.spkNamaVendor || '';

            if (this.spkSelectedPaket && this.spkPaketMap[this.spkSelectedPaket]) {
                // lewatkan overrideNilai=false pada load awal agar input lama hasil old() tetap dipertahankan.
                this.onSelectSpkPaket(false);
            }
        },

        /**
         * Memperbarui batas maksimal HPS, nominal kontrak default, dan vendor saat paket berubah.
         *
         * Tujuan: membatasi nilai kontrak tidak melampaui HPS paket serta membulatkan 2 desimal
         * demi menghindari artefak floating-point JavaScript pada input angka.
         * Parameter: `{boolean} [overrideNilai=true]` - Apakah nominal kontrak diisi ulang dengan nilai default paket.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="onSelectSpkPaket()"`
         */
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

        /**
         * Memformat angka numerik menjadi teks mata uang Rupiah untuk pratinjau antarmuka.
         *
         * Tujuan: meneruskan pemformatan ke helper terpusat agar format rupiah seragam di semua modul.
         * Parameter: `{number|string} val` - Angka nominal rupiah.
         * Nilai kembali: `{string}` String rupiah terformat.
         * Contoh pemanggilan: `x-text="formatRupiah(spkHpsMax)"`
         */
        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}
