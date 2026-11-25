/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini rancang untuk mengelola halaman profil pengguna SIM-PBJ meliputi
 *             kontrol tiga modal profil/sandi/hapus akun, pratinjau unggahan foto profil, toggle
 *             persisten pengingat email ke server, dan pembersihan otomatis input sensitif saat ditutup.
 */

/**
 * Mengelola status buka/tutup modal ubah profil, ganti kata sandi, dan hapus akun permanen.
 *
 * Tujuan: membuka kembali modal kata sandi atau hapus akun secara otomatis apabila server
 * mengembalikan error validasi dari pengiriman sebelumnya via atribut `data-*-modal-open`.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state boolean kontrol modal untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="profilePage()" data-password-modal-open="0" data-delete-modal-open="0">`
 */
export function profilePage() {
    return {
        editModalOpen: false,
        passwordModalOpen: false,
        deleteAccountModalOpen: false,

        /**
         * Memulihkan visibilitas modal terkait bila terjadi error validasi pasca redirect server.
         *
         * Tujuan: memastikan pengguna langsung melihat pesan kesalahan field di dalam modal tanpa
         * harus menekan ulang tombol pembuka modal.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis oleh Alpine saat halaman profil dimuat.
         */
        init() {
            this.editModalOpen = this.$el.dataset.editModalOpen === '1';
            this.passwordModalOpen = this.$el.dataset.passwordModalOpen === '1';
            this.deleteAccountModalOpen = this.$el.dataset.deleteModalOpen === '1';
        },
    };
}

/**
 * Menyimpan nama berkas foto profil terpilih untuk ditampilkan pada tombol unggah.
 *
 * Tujuan: memberi umpan balik instan nama file gambar sebelum pengguna menyimpan perubahan profil.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi properti `photoName`.
 * Contoh pemanggilan: `<div x-data="profilePhotoUpload()">`
 */
export function profilePhotoUpload() {
    return {
        photoName: '',
    };
}

/**
 * Mengelola sakelar pengingat email pengguna secara optimis yang tersimpan persisten via API.
 *
 * Tujuan: menerapkan pembaruan optimistik (optimistic UI) dengan rollback otomatis ke state
 * sebelumnya apabila permintaan POST ke server gagal agar kontrol terasa responsif sekaligus akurat.
 * Parameter: Tidak menerima argumen (URL dan status awal dibaca dari atribut `data-url` & `data-enabled`).
 * Nilai kembali: Objek state dan method asinkron `toggle()` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="profileReminderToggle()" data-enabled="1" data-url="/profile/reminder">`
 */
export function profileReminderToggle() {
    return {
        enabled: true,
        saving: false,
        updateUrl: '',
        feedback: '',
        feedbackError: false,

        /**
         * Membaca preferensi pengingat email awal dan endpoint pembaruan dari atribut data HTML.
         *
         * Tujuan: memperlakukan semua nilai selain `'0'` sebagai aktif sehingga pengguna baru
         * tetap terdata menerima pengingat email bawaan.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen sakelar dimuat.
         */
        init() {
            this.enabled = this.$el.dataset.enabled !== '0';
            this.updateUrl = this.$el.dataset.url || '';
        },

        /**
         * Mengubah preferensi pengingat email secara optimis lalu menyinkronkannya ke server via CSRF POST.
         *
         * Tujuan: memblokir klik ganda saat request masih berjalan (`this.saving`) dan memulihkan
         * nilai semula bila server gagal merespons sukses agar UI tidak menipu pengguna.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Promise<void>}` Promise penyimpanan asinkron ke backend.
         * Contoh pemanggilan: `@click="toggle()"`
         */
        async toggle() {
            if (this.saving || !this.updateUrl) {
                return;
            }

            const previousState = this.enabled;
            const nextState = !previousState;
            // ubah state lebih dulu (optimistic UI) agar sakelar langsung bereaksi tanpa menunggu jaringan.
            this.enabled = nextState;
            this.saving = true;
            this.feedback = '';
            this.feedbackError = false;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const response = await fetch(this.updateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ enabled: nextState }),
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'Gagal menyimpan preferensi pengingat email.');
                }

                this.enabled = Boolean(payload.enabled);
                this.feedback = payload.message || 'Preferensi pengingat email diperbarui.';
            } catch (error) {
                // rollback kondisi sakelar saat request gagal supaya tampilan konsisten dengan database.
                this.enabled = previousState;
                this.feedbackError = true;
                this.feedback = error.message || 'Gagal memperbarui pengaturan pengingat email.';
            } finally {
                this.saving = false;
            }
        },
    };
}

/**
 * Mengelola visibilitas ketiga kolom sandi pada modal ubah password dan meresetnya saat ditutup.
 *
 * Tujuan: memantau perubahan `passwordModalOpen` agar formulir sandi bersih kembali setiap
 * kali modal ditutup tanpa mengirim perubahan.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state toggle tiga kolom sandi untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="profilePasswordForm()">`
 */
export function profilePasswordForm() {
    return {
        showOld: false,
        showNew: false,
        showConfirm: false,

        /**
         * Memasang watcher penutupan modal untuk membersihkan isi input dan menyembunyikan sandi.
         *
         * Tujuan: mencegah sisa ketikan kata sandi tertinggal di memori DOM modal tertutup.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dipanggil otomatis saat komponen form sandi dimuat.
         */
        init() {
            this.$watch('passwordModalOpen', (open) => {
                if (!open) {
                    this.$refs.passwordForm?.reset();
                    this.showOld = false;
                    this.showNew = false;
                    this.showConfirm = false;
                }
            });
        },
    };
}

/**
 * Mengelola toggle kata sandi konfirmasi penghapusan akun serta mereset form ketika modal ditutup.
 *
 * Tujuan: memastikan kata sandi konfirmasi penghapusan akun ikut dibersihkan otomatis setiap
 * pengguna membatalkan penutupan akun.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi `showPass` dan watcher penutupan modal.
 * Contoh pemanggilan: `<div x-data="profileDeleteForm()">`
 */
export function profileDeleteForm() {
    return {
        showPass: false,

        /**
         * Mengawasi penutupan modal hapus akun guna mengosongkan input password.
         *
         * Tujuan: menjaga keamanan form destruktif agar kata sandi yang terlanjur diketik
         * tidak muncul lagi bila modal dibuka kembali.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis oleh siklus hidup Alpine `init()`.
         */
        init() {
            this.$watch('deleteAccountModalOpen', (open) => {
                if (!open) {
                    this.$refs.deleteForm?.reset();
                    this.showPass = false;
                }
            });
        },
    };
}
