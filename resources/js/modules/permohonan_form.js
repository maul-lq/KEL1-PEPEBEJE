/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini rancang untuk mengelola interaksi formulir permohonan pengadaan,
 *             termasuk drag-and-drop berkas tunggal KAK/Surat/Spek, manajemen unggah multi-berkas
 *             lampiran di modal pengajuan cepat, formatter Rupiah, serta modal pembatalan usulan.
 */

import { formatRupiahInput } from './ui_helpers.js';

/**
 * Mengelola state halaman penuh pembuatan surat permohonan pengadaan baru.
 *
 * Tujuan: menghubungkan aksi drop file dari desktop ke input `<input type="file">` tersembunyi
 * melalui `DataTransfer` API supaya validasi file asli browser dan Laravel tetap bekerja identik.
 * Parameter: Tidak menerima argumen (nominal awal dibaca dari `data-initial-nominal`).
 * Nilai kembali: Objek state dan method drop berkas untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="permohonanCreatePage()" data-initial-nominal="{{ old('anggaran_diajukan') }}">`
 */
export function permohonanCreatePage() {
    return {
        nominal: '',
        fileNameSurat: '',
        fileNameKak: '',
        fileNameSpek: '',
        isDraggingSurat: false,
        isDraggingKak: false,
        isDraggingSpek: false,

        /**
         * Menginisialisasi nilai nominal awal apabila formulir dimuat ulang setelah gagal validasi.
         *
         * Tujuan: menjaga agar ketikan anggaran sebelumnya tidak hilang saat server mengembalikan
         * kesalahan validasi field lain.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis saat komponen halaman buat permohonan diinisialisasi.
         */
        init() {
            this.nominal = this.$el.dataset.initialNominal || '';
        },

        /**
         * Memindahkan berkas pertama hasil drag-and-drop ke input file DOM target dan memicu event change.
         *
         * Tujuan: menyuntikkan file lewat objek `DataTransfer` karena properti `input.files`
         * bersifat read-only jika diisi array langsung, lalu memicu event `change` untuk memperbarui label nama file.
         * Parameter: `{DragEvent} event` - Event drop browser; `{string} field` - ID elemen input file.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@drop.prevent="isDraggingKak = false; handleDrop($event, 'file_kak')"`
         */
        handleDrop(event, field) {
            const file = event.dataTransfer?.files?.[0];
            if (!file) {
                return;
            }
            const input = document.getElementById(field);
            if (input) {
                // bungkus file ke DataTransfer karena FileList input tidak dapat dimutasi secara langsung.
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    };
}

/**
 * Mengelola unggah multi-berkas lampiran pendukung pada modal pengajuan permohonan cepat.
 *
 * Tujuan: menegakkan batas maksimal 5 file dan total ukuran 15 MB di sisi klien lebih awal
 * sebelum pengguna mengirim formulir berat ke server, lengkap dengan lencana warna tipe berkas.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state antrean berkas, validasi kapasitas, dan sinkronizer input file.
 * Contoh pemanggilan: `<div x-data="permohonanCreateModalUpload()">`
 */
export function permohonanCreateModalUpload() {
    return {
        files: [],
        maxFiles: 5,
        maxTotalSizeMB: 15,
        isDragging: false,
        errorMessage: '',

        /**
         * Memvalidasi jumlah dan total ukuran berkas yang dipilih/di-drop lalu memasukkannya ke pratinjau.
         *
         * Tujuan: menolak seluruh batch tambahan jika penambahan file akan melampaui 5 berkas
         * atau akumulasi 15 MB agar tidak membebani kuota upload PHP/Laravel.
         * Parameter: `{FileList|Array<File>} selectedFiles` - Daftar objek File hasil pilihan atau drop.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@change="handleFiles($event.target.files)"`
         */
        handleFiles(selectedFiles) {
            this.errorMessage = '';
            const incomingFiles = Array.from(selectedFiles);

            if (this.files.length + incomingFiles.length > this.maxFiles) {
                this.errorMessage = `Maksimal hanya dapat mengunggah ${this.maxFiles} berkas.`;
                return;
            }

            const currentTotalBytes = this.files.reduce((acc, f) => acc + f.size, 0);
            const incomingTotalBytes = incomingFiles.reduce((acc, f) => acc + f.size, 0);
            const totalMB = (currentTotalBytes + incomingTotalBytes) / (1024 * 1024);

            // validasi akumulasi ukuran di browser agar server tidak menolak payload mendadak di tengah upload.
            if (totalMB > this.maxTotalSizeMB) {
                this.errorMessage = `Total ukuran seluruh berkas melebihi batas ${this.maxTotalSizeMB} MB.`;
                return;
            }

            incomingFiles.forEach((file) => {
                const ext = file.name.split('.').pop().toUpperCase();

                let badgeColor = 'bg-[#0969da]';
                if (ext === 'PDF') {
                    badgeColor = 'bg-rose-600';
                } else if (['XLS', 'XLSX', 'CSV'].includes(ext)) {
                    badgeColor = 'bg-emerald-600';
                } else if (['PNG', 'JPG', 'JPEG', 'WEBP'].includes(ext)) {
                    badgeColor = 'bg-sky-500';
                }

                this.files.push({
                    raw: file,
                    name: file.name,
                    size: `${(file.size / 1024).toFixed(0)} KB`,
                    type: ext,
                    colorClass: badgeColor,
                    url: URL.createObjectURL(file),
                });
            });

            this.updateFileInput();
        },

        /**
         * Menghapus satu berkas dari daftar pratinjau unggahan dan menyinkronkan ulang `<input type="file">`.
         *
         * Tujuan: memastikan file yang sudah dibatalkan pengguna benar-benar tidak ikut terkirim
         * saat formulir disubmit.
         * Parameter: `{number} index` - Posisi indeks berkas pada array `this.files`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="removeFile(idx)"`
         */
        removeFile(index) {
            this.files.splice(index, 1);
            this.errorMessage = '';
            this.updateFileInput();
        },

        /**
         * Menyinkronkan daftar file di memori Alpine ke atribut `files` elemen `$refs.fileInput`.
         *
         * Tujuan: membangun ulang `DataTransfer` tiap kali ada penambahan atau penghapusan
         * karena multipart/form-data membaca isi resmi elemen `<input>` saat form disubmit.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.updateFileInput();`
         */
        updateFileInput() {
            const dt = new DataTransfer();
            this.files.forEach((f) => dt.items.add(f.raw));
            if (this.$refs.fileInput) {
                this.$refs.fileInput.files = dt.files;
            }
        },
    };
}

/**
 * Menyediakan helper state dan pemformatan teks Rupiah bertitik saat mengetik anggaran.
 *
 * Tujuan: mempermudah pengikatan input anggaran mentah beserta pratinjau rupiah terformat.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `rawAnggaran` dan method `formatRupiah` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="rupiahField()">`
 */
export function rupiahField() {
    return {
        rawAnggaran: '',

        /**
         * Mengonversi string angka ketikan menjadi format ribuan Rupiah (`Rp 1.000.000`).
         *
         * Tujuan: delegasikan logika parsing angka ke utilitas terpusat di `ui_helpers.js`.
         * Parameter: `{string|number} val` - Nilai masukan pengguna.
         * Nilai kembali: `{string}` String berformat Rupiah.
         * Contoh pemanggilan: `x-text="formatRupiah(rawAnggaran)"`
         */
        formatRupiah(val) {
            return formatRupiahInput(val);
        },
    };
}

/**
 * Mengelola nominal input sementara pada modal pengajuan usulan cepat di halaman daftar.
 *
 * Tujuan: menyediakan ruang lingkup reaktif ringkas untuk pratinjau nominal modal cepat.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi `nominalModal`.
 * Contoh pemanggilan: `<div x-data="permohonanQuickModal()">`
 */
export function permohonanQuickModal() {
    return {
        nominalModal: '',
    };
}
