<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis berkas ini untuk mendaftarkan daftar Service Provider yang dimuat saat aplikasi Laravel melakukan bootstrapping.
 */

use App\Providers\AppServiceProvider;

/**
 * Daftar Service Provider Aplikasi
 *
 * membatasi daftar ini hanya pada provider inti yang benar-benar dipakai agar waktu boot aplikasi tetap cepat dan prediktif.
 *
 * Parameter    : Tidak ada.
 * Nilai kembali: array<int, class-string> — daftar nama kelas Service Provider yang akan di-register dan di-boot oleh Laravel.
 * Contoh pemanggilan:
 * `// Dimuat otomatis oleh Laravel melalui Illuminate\Foundation\Application::withProviders()`
 */
return [
    // mendaftarkan AppServiceProvider sebagai satu-satunya pusat registrasi observer NotifikasiUser dan konfigurasi layanan aplikasi.
    AppServiceProvider::class,
];
