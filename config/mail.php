<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi surel bawaan Laravel ini untuk mengatur transport pengiriman email notifikasi pengadaan dan tautan reset kata sandi.
 */

/**
 * Konfigurasi Mailer Aplikasi (config/mail.php)
 *
 * mendefinisikan mailer bawaan (SMTP atau 'log' untuk lingkungan lokal) serta identitas pengirim global
 * yang dipakai oleh NotifikasiUserObserver dan fitur lupa kata sandi.
 *
 * Parameter    : Tidak ada (membaca kredensial SMTP dari .env).
 * Nilai kembali: array<string, mixed> — daftar mailer, konfigurasi SMTP, dan alamat pengirim global.
 * Contoh pemanggilan:
 * `Mail::to($user)->send(...)` atau `config('mail.from.address')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | menggunakan default 'log' bila MAIL_MAILER tidak disetel agar
    | pengujian lokal tanpa server SMTP tidak gagal melempar exception koneksi.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | menyediakan profil SMTP dengan validasi URL serta transport cadangan
    | seperti failover/log agar pengiriman email reminder tetap dapat diandalkan.
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            // memvalidasi MAIL_URL dengan filter_var agar nilai kosong atau malformed di .env tidak merusak parsing DSN SMTP.
            'url' => (! empty(env('MAIL_URL')) && filter_var(env('MAIL_URL'), FILTER_VALIDATE_URL)) ? env('MAIL_URL') : null,
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            // menurunkan domain EHLO dari APP_URL agar server SMTP eksternal tidak menolak handshake karena hostname generik.
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | menetapkan identitas pengirim global di sini agar seluruh notifikasi
    | SIM-PBJ memiliki alamat dan nama pengirim yang seragam.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
