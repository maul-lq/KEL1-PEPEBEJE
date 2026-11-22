/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Modul ini buat untuk mengelola state visibilitas kata sandi pada formulir masuk,
 *             pendaftaran akun, dan pengaturan ulang kata sandi.
 */

/**
 * Mengelola state tampilan kata sandi dan opsi ingat pada halaman masuk.
 *
 * Tujuan: memisahkan status toggle tampilkan/sembunyikan kata sandi ke komponen Alpine
 * ringkas agar perilaku ikon mata tetap konsisten.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `showPassword` dan `remember` untuk Alpine.js.
 * Contoh pemanggilan: `<form x-data="loginForm()">`
 */
export function loginForm() {
    return {
        showPassword: false,
        remember: false,
    };
}

/**
 * Membangun state visibilitas dua kolom kata sandi independen untuk formulir registrasi maupun reset kata sandi.
 *
 * Tujuan: menyatukan factory state dua input kata sandi agar tidak menduplikasi logika antar halaman autentikasi.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `showPassword` dan `showPasswordConfirm` untuk Alpine.js.
 * Contoh pemanggilan: `return createDualPasswordVisibilityState();`
 */
function createDualPasswordVisibilityState() {
    return {
        showPassword: false,
        showPasswordConfirm: false,
    };
}

/**
 * Mengelola state tampilan dua field kata sandi pada halaman pendaftaran akun.
 *
 * Tujuan: memberi kontrol toggle independen untuk kolom password utama dan konfirmasi
 * password agar pengguna mudah memeriksa ketikan sebelum mendaftar.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `showPassword` dan `showPasswordConfirm` untuk Alpine.js.
 * Contoh pemanggilan: `<form x-data="registerForm()">`
 */
export function registerForm() {
    return createDualPasswordVisibilityState();
}

/**
 * Mengelola state tampilan dua field kata sandi pada halaman reset kata sandi.
 *
 * Tujuan: memberi kontrol toggle independen untuk kolom kata sandi baru dan konfirmasi
 * kata sandi pada halaman `auth.reset-password`.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `showPassword` dan `showPasswordConfirm` untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="resetPasswordForm">`
 */
export function resetPasswordForm() {
    return createDualPasswordVisibilityState();
}
