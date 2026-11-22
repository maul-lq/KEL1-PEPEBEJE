<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis berkas bootstrap aplikasi ini untuk menyatukan pendaftaran rute, tumpukan middleware keamanan, dan penanganan eksepsi global Laravel.
 */

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SessionTimeout;
use App\Http\Middleware\TriggerDueReminders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Konfigurasi Bootstrapping Aplikasi SIM-PBJ
 *
 * membangun blok konfigurasi ini untuk menginisialisasi kernel HTTP/CLI Laravel,
 * mendaftarkan middleware wajib di grup web, serta mencegah error mentah saat unggahan melebihi batas server.
 *
 * Parameter   : basePath = direktori root proyek (dirname(__DIR__)).
 * Nilai kembali: Application — instance aplikasi Laravel yang siap menangani request HTTP maupun Artisan CLI.
 * Contoh pemanggilan:
 * `$app = require_once __DIR__.'/../bootstrap/app.php';`
 */
return Application::configure(basePath: dirname(__DIR__))
    // mendaftarkan rute web, perintah konsol, dan endpoint health-check dari satu titik agar mudah diaudit.
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // menyuntikkan pengaman idle timeout dan pemicu reminder berkala ke seluruh request grup 'web'.
    ->withMiddleware(function (Middleware $middleware): void {
        // menaruh kedua middleware ini di akhir grup 'web' supaya sesi autentikasi sudah dimuat lebih dulu.
        $middleware->appendToGroup('web', SessionTimeout::class);
        $middleware->appendToGroup('web', TriggerDueReminders::class);

        // menyediakan alias singkat agar definisi rute di web.php tetap ringkas dan konsisten dengan matriks RBAC.
        $middleware->alias([
            'role' => CheckRole::class,
            'session.timeout' => SessionTimeout::class,
            'prevent.back' => PreventBackHistory::class,
        ]);
    })
    // menyeragamkan respons error agar klien JSON menerima JSON aman, sementara akses browser biasa (termasuk URL /api/*) merender halaman error HTML.
    ->withExceptions(function (Exceptions $exceptions): void {
        // membatasi respons JSON hanya untuk klien yang memang meminta JSON agar pengaksesan endpoint melalui browser tetap menampilkan antarmuka error HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );

        // menyamarkan nama kelas model Eloquent pada respons JSON 404 agar struktur internal aplikasi tidak bocor ke klien API.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Halaman atau data yang diminta tidak ditemukan.',
                ], 404);
            }

            return null;
        });

        // mencegat PostTooLargeException karena PHP/Laravel melemparnya sebelum validasi form berjalan; tanpa ini user hanya melihat error 413 polos.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $pesan = 'Ukuran berkas yang diunggah melebihi batas maksimal server. Pastikan setiap berkas tidak melebihi 10 MB dan total unggahan berada dalam batas yang diizinkan (NFR 11).';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $pesan,
                ], 413);
            }

            // menginisialisasi driver sesi secara manual karena ValidatePostSize berjalan di lapisan middleware terluar sebelum StartSession sempat aktif.
            if (! $request->hasSession()) {
                try {
                    $session = app('session')->driver();
                    $session->setId($request->cookies->get(config('session.cookie')) ?? Str::random(40));
                    $session->start();
                    $request->setLaravelSession($session);
                } catch (Throwable) {
                    // abaikan kegagalan inisialisasi sesi darurat agar aplikasi tetap mengembalikan redirect error ketimbang fatal 500.
                }
            }

            $response = back()->withInput()->withErrors([
                'file_upload' => $pesan,
            ])->with('error', $pesan);

            // memaksa penyimpanan sesi secara eksplisit karena middleware StartSession tidak membungkus fase render eksepsi global ini.
            if ($request->hasSession()) {
                $request->session()->save();
            }

            return $response;
        });
    })->create();
