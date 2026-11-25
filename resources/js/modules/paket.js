/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini susun untuk menyatukan logika penentuan jalur pengadaan berdasarkan
 *             ambang HPS (`resolvePaketRouting`), penyelarasan metode default, perhitungan selisih
 *             pagu MAK, serta state Alpine pada halaman daftar, pembuatan, dan perbaikan paket.
 */

import { formatRupiahId, parseJsonDataset } from './ui_helpers.js';

/**
 * Menentukan key jalur routing pengadaan berdasarkan nominal Harga Perkiraan Sendiri (HPS).
 *
 * Tujuan: menduplikasi aturan ambang Rp50 juta dan Rp200 juta di frontend supaya pratinjau
 * jalur muncul langsung saat PPBJ mengetik nilai HPS sebelum dikirim ke backend.
 * Parameter: `{number|string} hps` - Nominal HPS paket pengadaan.
 * Nilai kembali: `{string|null}` Salah satu dari `'langsung'`, `'pp'`, `'ppk'`, atau `null` bila <= 0.
 * Contoh pemanggilan: `const jalur = resolvePaketRouting(75000000);`
 */
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

/**
 * Menyelaraskan nilai pilihan metode pengadaan ketika jalur routing berubah.
 *
 * Tujuan: menjaga agar pilihan metode yang sudah valid pada jalur barunya tidak tertimpa,
 * sedangkan metode jalur sebelumnya otomatis diganti ke standar jalur tujuan (`SPK`/`Tender`).
 * Parameter: `{string|null} routing` - Jalur aktif; `{string} currentMetode` - Metode yang sedang dipilih.
 * Nilai kembali: `{string}` Nama metode pengadaan yang sesuai dengan jalur aktif.
 * Contoh pemanggilan: `this.metode = resolveDefaultMetode(this.routing(), this.metode);`
 */
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

/**
 * Mengelola state reaktif formulir registrasi paket pengadaan baru oleh Koordinator PPBJ.
 *
 * Tujuan: menghubungkan pilihan surat permohonan ke nilai HPS bawaan, judul paket, kalkulasi
 * selisih pagu MAK (`getDeltaMak`), serta penentuan jalur pengadaan otomatis.
 * Parameter: Tidak menerima argumen (konfigurasi awal dibaca dari atribut `data-config`).
 * Nilai kembali: Objek state dan handler interaktif pembuatan paket untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="paketCreate()" data-config="...">`
 */
export function paketCreate() {
    return {
        hps: '',
        namaPaket: '',
        metode: '',
        selectedSurat: '',
        permohonanMap: {},

        /**
         * Memuat konfigurasi awal dan mengisi otomatis data paket bila surat sudah terpilih lewat query string.
         *
         * Tujuan: mendukung akses langsung tombol "Buat Paket" dari detail permohonan agar
         * formulir otomatis terisi tanpa PPBJ memilih ulang nomor surat.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis oleh Alpine saat halaman `paket.create` dimuat.
         */
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

        /**
         * Memperbarui HPS, judul paket default, dan metode saat nomor surat permohonan dipilih.
         *
         * Tujuan: hanya mengisi `namaPaket` jika masih kosong supaya ketikan manual PPBJ tidak hilang.
         * Parameter: `{string} nomor` - Nomor surat permohonan terpilih.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="onSelectSurat($event.target.value)"`
         */
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

        /**
         * Menghitung jalur pengadaan aktif berdasar nilai `hps` saat ini.
         *
         * Tujuan: membungkus pemanggilan helper agar ringkas dipakai pada binding Blade.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{string|null}` Kode jalur (`langsung`, `pp`, `ppk`, atau `null`).
         * Contoh pemanggilan: `x-show="routing() === 'ppk'"`
         */
        routing() {
            return resolvePaketRouting(this.hps);
        },

        /**
         * Menyesuaikan pilihan metode pengadaan terhadap jalur routing terhitung saat ini.
         *
         * Tujuan: mencegah kombinasi tidak valid seperti Belanja Langsung tetap terpilih untuk HPS > Rp200 juta.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@input="syncMetodeWithRouting()"`
         */
        syncMetodeWithRouting() {
            this.metode = resolveDefaultMetode(this.routing(), this.metode);
        },

        /**
         * Menghitung selisih antara HPS baru dengan estimasi pagu awal surat permohonan.
         *
         * Tujuan: memberi indikasi real-time potensi kelebihan kebutuhan pagu atau efisiensi MAK.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Nilai delta HPS terhadap pagu surat (positif = lebih besar dari estimasi awal).
         * Contoh pemanggilan: `x-text="formatRupiah(getDeltaMak())"`
         */
        getDeltaMak() {
            if (!this.selectedSurat || !this.permohonanMap[this.selectedSurat]) {
                return 0;
            }
            const n = parseFloat(this.hps) || 0;
            return n - this.permohonanMap[this.selectedSurat].pagu;
        },

        /**
         * Memformat angka ke representasi Rupiah Indonesia tanpa angka desimal.
         *
         * Tujuan: memakai utilitas global agar seluruh tampilan angka di formulir paket seragam.
         * Parameter: `{number|string} val` - Angka nominal yang akan diformat.
         * Nilai kembali: `{string}` String berformat mata uang IDR.
         * Contoh pemanggilan: `x-text="formatRupiah(hps)"`
         */
        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}

/**
 * Mengelola state reaktif formulir perubahan dan perbaikan paket pengadaan pasca reviu PP/PPK.
 *
 * Tujuan: menghitung perubahan delta pagu relatif terhadap `hpsAwal` serta memvalidasi secara
 * visual kecukupan sisa saldo rekening MAK ketika HPS paket dinaikkan.
 * Parameter: Tidak menerima argumen (state awal dibaca dari atribut `data-config`).
 * Nilai kembali: Objek state dan method interaktif sunting paket untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="paketEdit()" data-config="...">`
 */
export function paketEdit() {
    return {
        hps: '',
        hpsAwal: 0,
        sisaMak: 0,
        metode: '',

        /**
         * Menginisialisasi nilai HPS awal, sisa saldo MAK terkini, dan metode paket yang disunting.
         *
         * Tujuan: memparsing nilai numerik lebih awal agar perbandingan `getDeltaMak()` bebas
         * penggabungan string JavaScript.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis oleh Alpine saat halaman `paket.edit` dimuat.
         */
        init() {
            const cfg = parseJsonDataset(this.$el, 'config', {});
            this.hps = cfg.hps ?? '';
            this.hpsAwal = parseFloat(cfg.hpsAwal || 0) || 0;
            this.sisaMak = parseFloat(cfg.sisaMak || 0) || 0;
            this.metode = cfg.metode ?? '';
        },

        /**
         * Menghitung jalur routing berdasarkan nominal HPS perubahan terbaru.
         *
         * Tujuan: mendeteksi perubahan jalur misalnya dari PP menjadi PPK apabila HPS direvisi naik.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{string|null}` Jalur routing pengadaan aktif.
         * Contoh pemanggilan: `x-text="routing()"`
         */
        routing() {
            return resolvePaketRouting(this.hps);
        },

        /**
         * Menyelaraskan metode pengadaan jika perubahan HPS menggeser kelas jalur paket.
         *
         * Tujuan: menjaga validitas pilihan metode saat menyimpan perubahan ke backend.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@input="syncMetodeWithRouting()"`
         */
        syncMetodeWithRouting() {
            this.metode = resolveDefaultMetode(this.routing(), this.metode);
        },

        /**
         * Menghitung selisih antara HPS baru dengan HPS sebelum paket disunting.
         *
         * Tujuan: menampilkan apakah perubahan HPS meminta tambahan dana dari sisa MAK atau justru mengembalikan efisiensi.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Selisih nominal terhadap `hpsAwal`.
         * Contoh pemanggilan: `x-show="getDeltaMak() > sisaMak"`
         */
        getDeltaMak() {
            const n = parseFloat(this.hps) || 0;
            return n - this.hpsAwal;
        },

        /**
         * Memformat angka ke representasi mata uang Rupiah.
         *
         * Tujuan: menyediakan helper format rupiah langsung di konteks scope `paketEdit`.
         * Parameter: `{number|string} val` - Angka nominal target.
         * Nilai kembali: `{string}` String Rupiah Indonesia.
         * Contoh pemanggilan: `x-text="formatRupiah(sisaMak)"`
         */
        formatRupiah(val) {
            return formatRupiahId(val);
        },
    };
}

/**
 * Mengelola state pemilihan baris aktif pada halaman indeks daftar paket pengadaan.
 *
 * Tujuan: menyiapkan state ringan `selectedPaket` untuk menyorot paket yang sedang diperiksa.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi properti `selectedPaket`.
 * Contoh pemanggilan: `<div x-data="paketIndexPage()">`
 */
export function paketIndexPage() {
    return {
        selectedPaket: '',

        /**
         * Membaca parameter kueri `?selected=` saat halaman indeks paket dimuat.
         *
         * Tujuan: menyorot baris paket target secara otomatis ketika pengguna masuk melalui
         * tautan riwayat aktivitas atau notifikasi sistem.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis oleh Alpine saat halaman `paket.index` dimuat.
         */
        init() {
            const params = new URLSearchParams(window.location.search);
            const selected = params.get('selected');
            if (selected) {
                this.selectedPaket = selected;
            }
        },
    };
}
