<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi sesi bawaan Laravel ini untuk mengatur penyimpanan sesi di database, masa idle timeout, serta atribut keamanan cookie HTTP-only dan SameSite.
 */

use Illuminate\Support\Str;

/**
 * Konfigurasi Manajemen Sesi HTTP (config/session.php)
 *
 * mengatur penyimpanan sesi di tabel 'sessions' dengan parameter lifetime (yang juga dibaca
 * oleh middleware SessionTimeout) serta pengamanan cookie http_only dan same_site.
 *
 * Parameter    : Tidak ada (membaca variabel env()).
 * Nilai kembali: array<string, mixed> — konfigurasi driver sesi, masa hidup, dan atribut cookie.
 * Contoh pemanggilan:
 * `config('session.lifetime')` atau `session()->get('last_activity')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | menggunakan driver 'database' sebagai default agar sesi pengguna dapat
    | dilacak, diaudit, dan dibersihkan secara terpusat pada tabel sessions.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime & Idle Timeout
    |--------------------------------------------------------------------------
    |
    | memisahkan masa simpan record sesi (`lifetime`, 120 menit) dari batas waktu
    | ketidakaktifan (`idle_timeout`, 15 menit / 900 detik) agar middleware SessionTimeout
    | selalu sempat membaca `last_activity` dan mengirim peringatan saat sesi berakhir.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'idle_timeout' => (int) env('SESSION_IDLE_TIMEOUT', 15),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | menyediakan opsi enkripsi payload sesi bila diperlukan agar data
    | sesi di penyimpanan tidak terbaca secara langsung sebagai teks biasa.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | menetapkan direktori fallback ini apabila driver 'file' dipilih
    | pada lingkungan pengembangan tertentu.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | membiarkan koneksi sesi mengikuti DB_CONNECTION utama secara default
    | kecuali di-override eksplisit melalui SESSION_CONNECTION.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | menunjuk tabel 'sessions' untuk menyimpan ID sesi, IP, user_agent,
    | dan payload aktivitas terakhir pengguna.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | menyediakan konfigurasi store ini apabila di masa depan sesi
    | dipindahkan ke backend cache seperti Redis.
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | mempertahankan rasio pembersihan 2 dari 100 request untuk menyapu
    | sesi kedaluwarsa tanpa membebani setiap permintaan pengguna.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | menamai cookie sesi berdasarkan slug APP_NAME agar cookie SIM-PBJ
    | tidak tertukar dengan aplikasi Laravel lain pada host yang sama.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | membatasi cakupan cookie sesi ke path root '/' agar tersedia di
    | seluruh modul pengadaan aplikasi.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | membiarkan domain null secara default supaya cookie hanya berlaku
    | untuk host tempat aplikasi diakses dan tidak bocor ke subdomain lain.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | menyerahkan flag secure cookie ke env SESSION_SECURE_COOKIE agar
    | tetap bisa diuji di HTTP lokal namun bisa dikunci ke HTTPS di produksi.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | mengaktifkan http_only secara default agar skrip JavaScript sisi
    | klien tidak dapat membaca cookie sesi jika terjadi celah XSS.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | menyetel SameSite='lax' agar alur callback OAuth Google tetap bisa
    | membawa cookie sesi saat redirect balik sambil tetap menangkal CSRF.
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | menonaktifkan partitioned cookie secara default karena aplikasi ini
    | tidak berjalan di dalam iframe lintas-situs pihak ketiga.
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Serialization
    |--------------------------------------------------------------------------
    |
    | mempertahankan serialisasi JSON agar data sesi tetap aman dari
    | serangan deserialisasi PHP object injection bila APP_KEY terkompromi.
    |
    */

    'serialization' => 'json',

];
