<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui titik masuk HTTP utama (front controller) bawaan Laravel ini agar alur maintenance mode, autoloading, dan lifecycle request terdokumentasi jelas.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/**
 * Titik Masuk HTTP Utama Aplikasi (Front Controller)
 *
 * mempertahankan struktur standar Laravel pada berkas ini untuk menangkap setiap permintaan HTTP dari web server
 * sebelum diteruskan ke kernel aplikasi di bootstrap/app.php.
 *
 * Parameter    : Tidak menerima parameter langsung (membaca global HTTP request PHP via Request::capture()).
 * Nilai kembali: void — mengirimkan respons HTTP langsung ke klien.
 * Contoh pemanggilan:
 * `// Dieksekusi otomatis oleh Nginx/Apache/PHP Built-in Server pada setiap request ke public/`
 */

// mencatat timestamp awal eksekusi agar metrik waktu respons dan profiling performa (NFR) bisa dihitung akurat.
define('LARAVEL_START', microtime(true));

// memeriksa berkas maintenance lebih dulu supaya permintaan langsung dihentikan sebelum memuat dependency Composer yang berat.
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// memuat autoloader Composer agar seluruh kelas aplikasi dan paket vendor tersedia tanpa require manual.
require __DIR__.'/../vendor/autoload.php';

// mem-bootstrap kernel aplikasi dan langsung memproses request HTTP yang masuk dalam satu siklus lifecycle.
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
