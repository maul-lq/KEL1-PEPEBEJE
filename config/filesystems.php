<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi sistem berkas bawaan Laravel ini untuk mengatur disk penyimpanan privat, disk publik dokumen PBJ, dan tautan simbolik storage.
 */

/**
 * Konfigurasi Penyimpanan Berkas (config/filesystems.php)
 *
 * mengatur disk 'public' untuk menampung dokumen permohonan, memo, SPK, BAST, dan foto profil
 * agar dapat dipratinjau lewat '/storage', sambil mempertahankan disk 'local' privat sebagai default.
 *
 * Parameter    : Tidak ada (membaca path dan variabel env()).
 * Nilai kembali: array<string, mixed> — peta disk penyimpanan dan konfigurasi symlink.
 * Contoh pemanggilan:
 * `Storage::disk('public')->putFile('permohonan', $file)`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | menjaga disk 'local' sebagai default agar berkas tanpa spesifikasi
    | disk tidak otomatis terekspos ke direktori publik.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | menyediakan disk 'local' (app/private), 'public' (app/public untuk
    | lampiran PBJ yang disajikan melalui symlink), serta profil 's3'.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // menggunakan disk 'public' secara eksplisit di controller pengadaan agar dokumen PDF dan foto barang bisa dipratinjau di browser.
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // memangkas trailing slash dari APP_URL agar URL gabungan '/storage/...' tidak menghasilkan double slash.
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | memetakan public/storage ke storage/app/public agar perintah
    | `php artisan storage:link` mengekspos seluruh unggahan publik secara aman.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
