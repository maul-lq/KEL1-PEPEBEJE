<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SessionTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Konfigurasi Bootstrapping Aplikasi Laravel 11 / 12
 *
 * Mengatur rute, registrasi middleware global & alias, serta penanganan eksepsi API/Web.
 */
return Application::configure(basePath: dirname(__DIR__))
    // 1. Pendaftaran Berkas Routing Aplikasi
    ->withRouting(
        web: __DIR__.'/../routes/web.php',       // Seluruh rute web dan API pengadaan
        commands: __DIR__.'/../routes/console.php', // Perintah baris perintah (Artisan CLI)
        health: '/up',                          // Endpoint pengecekan kesehatan server aplikasi
    )
    // 2. Konfigurasi dan Pendaftaran Middleware
    ->withMiddleware(function (Middleware $middleware): void {
        // Menerapkan middleware batas ketidakaktifan 15 menit ke grup rute 'web' (NFR Keamanan Sesi)
        $middleware->appendToGroup('web', SessionTimeout::class);

        // Mendaftarkan alias middleware agar dapat dipanggil langsung di file routes
        $middleware->alias([
            'role' => CheckRole::class,                  // Otorisasi 9 peran sistem (RBAC)
            'session.timeout' => SessionTimeout::class, // Deteksi idle pengguna 15 menit
        ]);
    })
    // 3. Penanganan Eksepsi dan Format Respons Error
    ->withExceptions(function (Exceptions $exceptions): void {
        // Memastikan request ke prefix 'api/*' selalu merender respons error dalam format JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
