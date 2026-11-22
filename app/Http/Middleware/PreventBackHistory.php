<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis middleware PreventBackHistory ini untuk menyematkan header HTTP anti-cache pada halaman terautentikasi agar tombol Back browser tidak menampilkan data privat setelah logout atau sesi kedaluwarsa.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Middleware Pencegahan Cache Riwayat Back Browser (PreventBackHistory)
 *
 * menonaktifkan penyimpanan snapshot halaman terproteksi di disk maupun Back/Forward Cache (BFCache)
 * peramban sehingga setiap navigasi Back sesudah logout memaksa permintaan ulang ke server dan dialihkan ke Login.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance middleware yang dipasang pada grup rute terautentikasi.
 *
 * Contoh pemanggilan:
 * `Route::middleware(['auth', 'prevent.back'])->group(function () { ... });`
 */
class PreventBackHistory
{
    /**
     * menyisipkan header Cache-Control, Pragma, dan Expires pada respons halaman privat non-unduhan.
     *
     * @param  Request  $request  menerima request HTTP yang telah melewati autentikasi pengguna.
     * @param  Closure(Request): (Response)  $next  meneruskan request ke controller tujuan dan memodifikasi header respons keluar.
     * @return Response mengembalikan respons dengan header anti-cache tanpa merusak respons unduhan berkas.
     *
     * Contoh pemanggilan:
     * `$middleware->handle($request, $next);`
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // mengecualikan respons unduhan file atau stream biner supaya pengunduhan berkas PDF/lampiran tidak terganggu header no-store.
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        if (str_contains(strtolower($disposition), 'attachment')) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }
}
