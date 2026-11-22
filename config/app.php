<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi aplikasi bawaan Laravel ini agar pengaturan identitas, zona waktu, lokal, dan enkripsi SIM-PBJ terdokumentasi jelas.
 */

/**
 * Konfigurasi Inti Aplikasi (config/app.php)
 *
 * mengembalikan array konfigurasi utama Laravel yang mengatur nama aplikasi, mode debug, URL dasar,
 * zona waktu, bahasa antarmuka, kunci enkripsi AES-256, serta mode pemeliharaan.
 *
 * Parameter    : Tidak ada (membaca variabel lingkungan melalui helper env()).
 * Nilai kembali: array<string, mixed> — peta konfigurasi inti yang dibaca oleh ConfigRepository Laravel.
 * Contoh pemanggilan:
 * `config('app.name')` atau `config('app.key')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | menggunakan nilai ini sebagai nama resmi aplikasi (SIM-PBJ) yang
    | ditampilkan pada judul halaman, templat notifikasi email, dan header PDF.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | menaruh penanda lingkungan runtime di sini agar perilaku logging,
    | debug, dan kebijakan cookie menyesuaikan nilai APP_ENV di berkas .env.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | mengaktifkan mode debug hanya di lokal via APP_DEBUG karena pada
    | produksi stack trace mentah berisiko membocorkan kredensial dan skema DB.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | menetapkan URL root aplikasi supaya generator tautan di Artisan CLI,
    | email reminder, dan deep-link notifikasi menghasilkan host yang benar.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | mempertahankan default UTC pada tingkat konfigurasi dasar Laravel
    | agar penyimpanan stempel waktu di PostgreSQL tetap netral zona waktu.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | mengatur lokal utama, lokal cadangan, dan lokal Faker agar pesan
    | validasi serta data tiruan uji mengikuti pengaturan bahasa di .env.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | mengandalkan cipher AES-256-CBC dan APP_KEY bukan hanya untuk enkripsi
    | cookie/sesi, tetapi juga sebagai secret key penandatanganan HMAC BAST.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    // memecah APP_PREVIOUS_KEYS dan menyaring entri kosong supaya rotasi kunci aplikasi tidak gagal membaca token lama.
    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | mempertahankan driver file/database untuk mengelola status maintenance
    | mode ketika aplikasi menjalani migrasi skema atau pemeliharaan terjadwal.
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
