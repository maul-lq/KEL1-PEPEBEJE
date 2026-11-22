/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini buat untuk mengelola state visibilitas kata sandi pada formulir masuk
 *             dan daftar serta tombol pengisian otomatis akun uji coba saat pengembangan.
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
 * Mengelola state tampilan dua field kata sandi pada halaman pendaftaran akun.
 *
 * Tujuan: memberi kontrol toggle independen untuk kolom password utama dan konfirmasi
 * password agar pengguna mudah memeriksa ketikan sebelum mendaftar.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state `showPassword` dan `showPasswordConfirm` untuk Alpine.js.
 * Contoh pemanggilan: `<form x-data="registerForm()">`
 */
export function registerForm() {
    return {
        showPassword: false,
        showPasswordConfirm: false,
    };
}

/**
 * Mengikat listener klik pada tombol autofill akun demo untuk pengujian cepat di layar login.
 *
 * Tujuan: mengisi input username dan password otomatis lalu menggulir halus ke atas halaman
 * ketika tombol `.btn-autofill-login` diklik, tanpa melakukan apa pun bila elemennya absen di halaman.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Tidak mengembalikan nilai (mengikat event click DOM).
 * Contoh pemanggilan: `initLoginAutofill();`
 */
export function initLoginAutofill() {
    const autofillButtons = document.querySelectorAll('.btn-autofill-login');
    // keluar lebih awal bila tidak berada di halaman login agar tidak membuang query DOM pada halaman lain.
    if (!autofillButtons.length) {
        return;
    }

    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');

    autofillButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const username = this.getAttribute('data-username');
            const password = this.getAttribute('data-password');

            if (loginInput) {
                loginInput.value = username || '';
            }
            if (passwordInput) {
                passwordInput.value = password || '';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
}
