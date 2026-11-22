<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi autentikasi bawaan Laravel ini untuk mendefinisikan guard sesi web, provider model User, dan kebijakan reset kata sandi.
 */

use App\Models\User;

/**
 * Konfigurasi Autentikasi & Guard Aplikasi (config/auth.php)
 *
 * mengembalikan pengaturan guard sesi dan provider Eloquent User agar seluruh login,
 * middleware auth, dan fitur reset kata sandi terikat ke model App\Models\User.
 *
 * Parameter    : Tidak ada (membaca fallback dari env()).
 * Nilai kembali: array<string, mixed> — struktur guard, provider, dan password broker Laravel.
 * Contoh pemanggilan:
 * `config('auth.providers.users.model')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | memilih guard 'web' berbasis sesi dan broker 'users' sebagai bawaan
    | agar seluruh endpoint web dan fitur lupa kata sandi bekerja konsisten.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | menggunakan driver 'session' untuk guard web karena aplikasi ini
    | menerapkan proteksi CSRF dan batas idle timeout 15 menit berbasis sesi.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | mengarahkan provider 'users' ke model App\Models\User agar kolom
    | primary key id_user serta relasi role/permission RBAC ikut dimuat.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | membatasi masa berlaku token reset 60 menit dan jeda throttle 60 detik
    | untuk mencegah penyalahgunaan spam email atau brute-force token pemulihan akun.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | mempertahankan batas 3 jam (10.800 detik) bawaan Laravel sebelum
    | pengguna diminta mengonfirmasi ulang kata sandi pada aksi sensitif.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
