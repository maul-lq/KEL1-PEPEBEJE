/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini rancang untuk mengelola form pemecahan (Split) surat permohonan
 *             berdasarkan pos anggaran MAK oleh Bagian Perencanaan beserta validasi batas pagu
 *             induk secara real-time di frontend.
 */

import { parseJsonDataset } from './ui_helpers.js';

/**
 * Mengelola daftar baris sub-berkas pecahan MAK beserta kalkulasi sisa pagu dan kelayakan submit.
 *
 * Tujuan: mewajibkan minimal 2 baris pecahan dengan total nominal tidak melebihi pagu induk
 * agar pengalokasian banyak rekening MAK berjalan konsisten dengan validasi backend Perencanaan.
 * Parameter: `{Object} [initialData={}]` - Opsional daftar awal `permohonanList` dan `makList`.
 * Nilai kembali: Objek state, getter reaktif, dan handler baris pecahan untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="splitMakManager()" data-config="...">`
 */
export function splitMakManager(initialData = {}) {
    return {
        permohonanList: initialData?.permohonanList || [],
        makList: initialData?.makList || [],
        selectedIndukNomor: '',
        parentTotalPagu: 0,
        alasanPemecahan: '',
        subBerkas: [
            { nomor_surat_pecahan: '', judul_pengadaan: '', kode_mak: '', estimasi_pagu_pecahan: 0 },
            { nomor_surat_pecahan: '', judul_pengadaan: '', kode_mak: '', estimasi_pagu_pecahan: 0 }
        ],

        /**
         * Membaca konfigurasi JSON dari atribut `data-config` jika data awal tidak dipasok lewat argumen.
         *
         * Tujuan: mendukung dua cara inisialisasi (parameter langsung maupun dataset JSON) agar
         * kompatibel dengan berbagai pola render Blade tanpa skrip inline.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis oleh Alpine saat modul di-mount.
         */
        init() {
            if ((!initialData || (!initialData.permohonanList && !initialData.makList)) && this.$el?.dataset?.config) {
                const cfg = parseJsonDataset(this.$el, 'config', {});
                this.permohonanList = cfg.permohonanList || [];
                this.makList = cfg.makList || [];
            }
            if (this.selectedIndukNomor) {
                this.onSelectInduk();
            }
        },

        /**
         * Memperbarui pagu acuan dan menyarankan nomor serta judul pecahan saat surat induk dipilih.
         *
         * Tujuan: menomori otomatis akhiran `.1`, `.2` pada baris baru agar staf Perencanaan
         * terhindar dari bentrok primary key `nomor_surat` saat memecah usulan.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="onSelectInduk()"`
         */
        onSelectInduk() {
            const induk = this.permohonanList.find(p => p.nomor_surat === this.selectedIndukNomor);
            if (induk) {
                this.parentTotalPagu = parseFloat(induk.estimasi_total_pagu || induk.anggaran_diajukan || 0);
                this.subBerkas.forEach((row, idx) => {
                    if (!row.nomor_surat_pecahan || row.nomor_surat_pecahan.includes('.')) {
                        row.nomor_surat_pecahan = `${this.selectedIndukNomor}.${idx + 1}`;
                    }
                    if (!row.judul_pengadaan || row.judul_pengadaan.includes('(Bagian ')) {
                        row.judul_pengadaan = `${induk.perihal || induk.judul_pengadaan} (Bagian ${idx + 1})`;
                    }
                });
            } else {
                this.parentTotalPagu = 0;
            }
        },

        /**
         * Menambahkan satu baris pecahan MAK baru ke array `subBerkas`.
         *
         * Tujuan: otomatis mengisi saran nomor pecahan ke-n bila surat induk sudah dipilih.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="addRow()"`
         */
        addRow() {
            const nextIdx = this.subBerkas.length + 1;
            this.subBerkas.push({
                nomor_surat_pecahan: this.selectedIndukNomor ? `${this.selectedIndukNomor}.${nextIdx}` : '',
                judul_pengadaan: '',
                kode_mak: '',
                estimasi_pagu_pecahan: 0
            });
        },

        /**
         * Menghapus baris pecahan tertentu dengan menjaga minimal tersisa 2 baris pecahan.
         *
         * Tujuan: melarang penghapusan di bawah 2 baris karena split MAK secara aturan bisnis
         * harus menghasilkan setidaknya dua surat pecahan.
         * Parameter: `{number} index` - Indeks baris yang hendak dihapus.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="removeRow(idx)"`
         */
        removeRow(index) {
            if (this.subBerkas.length > 2) {
                this.subBerkas.splice(index, 1);
            }
        },

        /**
         * Menjumlahkan seluruh nominal estimasi pagu dari semua baris pecahan saat ini.
         *
         * Tujuan: menyediakan properti komputasi untuk deteksi real-time kelebihan pagu induk.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Akumulasi nominal pecahan MAK.
         * Contoh pemanggilan: `x-text="totalAllocated"`
         */
        get totalAllocated() {
            return this.subBerkas.reduce((acc, row) => acc + (parseFloat(row.estimasi_pagu_pecahan) || 0), 0);
        },

        /**
         * Menghitung sisa pagu usulan induk yang belum terbagi ke baris pecahan.
         *
         * Tujuan: membantu Perencanaan memastikan alokasi tiap pecahan pas dengan pagu induknya.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Sisa nominal (`parentTotalPagu - totalAllocated`).
         * Contoh pemanggilan: `x-text="remainingPagu"`
         */
        get remainingPagu() {
            return this.parentTotalPagu - this.totalAllocated;
        },

        /**
         * Memeriksa apakah total alokasi seluruh pecahan telah melampaui pagu usulan induk.
         *
         * Tujuan: memicu indikator peringatan visual dan mematikan tombol submit saat pagu melebihi batas.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{boolean}` Bernilai true bila total pecahan > pagu surat induk.
         * Contoh pemanggilan: `x-show="isExceeding"`
         */
        get isExceeding() {
            return this.totalAllocated > this.parentTotalPagu;
        },

        /**
         * Mengevaluasi kelayakan keseluruhan form split MAK sebelum tombol kirim diaktifkan.
         *
         * Tujuan: memastikan surat induk terpilih, minimal 2 baris lengkap terisi semua kolom
         * wajibnya, total > 0, dan tidak melebihi pagu induk sebelum submit diizinkan.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{boolean}` Bernilai true jika seluruh syarat split terpenuhi.
         * Contoh pemanggilan: `:disabled="!isValid"`
         */
        get isValid() {
            return this.selectedIndukNomor !== '' &&
                this.subBerkas.length >= 2 &&
                this.totalAllocated > 0 &&
                this.totalAllocated <= this.parentTotalPagu &&
                this.subBerkas.every(row => row.nomor_surat_pecahan && row.judul_pengadaan && row.kode_mak && parseFloat(row.estimasi_pagu_pecahan) > 0);
        }
    };
}
