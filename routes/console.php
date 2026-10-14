<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/**
 * Command bawaan Laravel untuk menampilkan kutipan motivasi / inspiratif di konsol terminal.
 * Perintah ini dapat dijalankan via: `php artisan inspire`.
 */
Artisan::command('inspire', function () {
    // Menampilkan kutipan acak dari pustaka Inspiring Laravel ke output terminal.
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
