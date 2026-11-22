<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi layanan pihak ketiga ini untuk menyimpan kredensial OAuth Google (Laravel Socialite) dan layanan eksternal lainnya.
 */

/**
 * Konfigurasi Layanan Pihak Ketiga (config/services.php)
 *
 * memusatkan kredensial API pihak ketiga termasuk blok 'google' (Client ID, Client Secret, dan Redirect URI)
 * yang dibutuhkan oleh GoogleAuthController untuk fitur login serta penautan akun Google pengguna.
 *
 * Parameter    : Tidak ada (membaca kunci rahasia dari .env).
 * Nilai kembali: array<string, array<string, mixed>> — peta konfigurasi tiap penyedia layanan eksternal.
 * Contoh pemanggilan:
 * `config('services.google.redirect')` atau `Socialite::driver('google')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | menyimpan kredensial layanan eksternal di berkas standar Laravel ini
    | agar tidak ada API key yang tertulis langsung di dalam controller.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // menambahkan konfigurasi 'google' untuk mengaktifkan login dan penautan akun Google lewat Laravel Socialite.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // menyediakan fallback URL lokal 127.0.0.1:8000 agar Socialite tetap cocok dengan redirect URI pengembangan jika .env belum mengaturnya.
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8000/auth/google/callback'),
    ],

];
