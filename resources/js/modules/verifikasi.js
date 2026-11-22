/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini buat untuk mengendalikan antarmuka verifikasi paralel Bagian
 *             Perencanaan (pemilihan MAK dan pengecekan sisa saldo), keputusan awal PPK,
 *             keputusan Wakil Direktur II, serta persetujuan dokumen pengadaan oleh PPK.
 */

/**
 * Mengelola formulir alokasi pos anggaran MAK dan penahanan anggaran pada halaman Perencanaan.
 *
 * Tujuan: membaca nilai awal dari atribut data HTML agar status pilihan MAK, nominal usulan,
 * dan keputusan tetap sinkron pasca error validasi maupun ketika pengguna mengganti opsi rekening.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state dan handler perubahan rekening MAK untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="verifikasiMakForm()" data-selected-mak="...">`
 */
export function verifikasiMakForm() {
    return {
        selectedMak: '',
        paguSisa: 0,
        nominal: 0,
        keputusan: 'setuju',

        /**
         * Menginisialisasi kode MAK terpilih, nominal usulan, dan mode keputusan awal.
         *
         * Tujuan: mengonversi atribut `data-nominal` ke float agar perbandingan kecukupan sisa
         * pagu rekening bekerja akurat di template Blade.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen form verifikasi MAK dirender.
         */
        init() {
            this.selectedMak = this.$el.dataset.selectedMak || '';
            this.nominal = parseFloat(this.$el.dataset.nominal || '0') || 0;
            this.keputusan = this.$el.dataset.keputusan || 'setuju';
        },

        /**
         * Memperbarui informasi sisa saldo pagu MAK berdasarkan atribut `data-sisa` opsi terpilih.
         *
         * Tujuan: menampilkan sisa saldo rekening secara instan saat staf Perencanaan memilih
         * kode MAK dari dropdown tanpa perlu query tambahan ke server.
         * Parameter: `{Event} event` - Event `change` dari elemen `<select>` kode MAK.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="updateMakInfo($event)"`
         */
        updateMakInfo(event) {
            const opt = event.target.selectedOptions[0];
            this.paguSisa = opt ? parseFloat(opt.dataset.sisa || 0) : 0;
        },
    };
}

/**
 * Mengelola state baris ekspansi antrean usulan pada halaman verifikasi Bagian Perencanaan.
 *
 * Tujuan: melacak nomor surat yang sedang diperluas (`expandedSurat`) sehingga panel form
 * alokasi hanya terbuka pada satu baris antrean pada satu waktu.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi `expandedSurat`.
 * Contoh pemanggilan: `<div x-data="verifikasiMakList()">`
 */
export function verifikasiMakList() {
    return {
        expandedSurat: null,
    };
}

/**
 * Mengelola state keputusan persetujuan awal usulan oleh akun PPK terpilih.
 *
 * Tujuan: menandai kolom catatan alasan sebagai wajib (`isMandatory`) hanya ketika PPK
 * memilih keputusan `ditolak` selaras dengan aturan validasi backend.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `keputusan` dan getter `isMandatory` untuk Alpine.js.
 * Contoh pemanggilan: `<form x-data="verifikasiKeputusanPpk()">`
 */
export function verifikasiKeputusanPpk() {
    return {
        keputusan: 'disetujui',

        /**
         * Menentukan apakah kolom catatan penolakan wajib diisi untuk keputusan PPK saat ini.
         *
         * Tujuan: mencegah submit tanpa alasan saat opsi `ditolak` aktif agar pengguna langsung
         * mendapat umpan balik sebelum halaman di-reload error validasi server.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{boolean}` Bernilai true jika `keputusan === 'ditolak'`.
         * Contoh pemanggilan: `:required="isMandatory"`
         */
        get isMandatory() {
            return this.keputusan === 'ditolak';
        },
    };
}

/**
 * Mengelola state keputusan kegiatan Wakil Direktur II (Setujui, Revisi, atau Tolak).
 *
 * Tujuan: menegakkan kewajiban pengisian catatan alasan saat Wadir II memilih penolakan atau
 * permintaan revisi guna memberi umpan balik jelas bagi unit pengusul.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `keputusan` dan getter `isMandatory` untuk Alpine.js.
 * Contoh pemanggilan: `<form x-data="verifikasiKeputusanWadir2()">`
 */
export function verifikasiKeputusanWadir2() {
    return {
        keputusan: 'disetujui',

        /**
         * Menentukan kewajiban pengisian alasan berdasarkan keputusan Wadir II.
         *
         * Tujuan: mewajibkan alasan apabila usulan ditolak ataupun dikembalikan untuk revisi.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{boolean}` Bernilai true bila keputusan bernilai `'ditolak'` atau `'revisi'`.
         * Contoh pemanggilan: `:required="isMandatory"`
         */
        get isMandatory() {
            return this.keputusan === 'ditolak' || this.keputusan === 'revisi';
        },
    };
}

/**
 * Mengelola state keputusan persetujuan dokumen pengadaan pada halaman PPK.
 *
 * Tujuan: menyediakan pengikatan state radio/select `disetujui` atau `perlu_perbaikan`
 * secara terpusat pada halaman tinjauan dokumen paket PPK.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi `keputusan`.
 * Contoh pemanggilan: `<div x-data="ppkPersetujuanDokumen()">`
 */
export function ppkPersetujuanDokumen() {
    return {
        keputusan: 'disetujui',
    };
}
