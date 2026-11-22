<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis kelas dasar abstrak ini sebagai induk bersama bagi seluruh HTTP controller aplikasi SIM-PBJ.
 */

namespace App\Http\Controllers;

/**
 * Base Controller Abstrak SIM-PBJ
 *
 * memisahkan kelas dasar ini sebagai titik perluasan tunggal apabila seluruh controller membutuhkan helper, trait, atau middleware bersama di masa depan.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Tidak dapat diinstansiasi langsung (abstract class); diwarisi oleh seluruh controller turunan.
 *
 * Contoh pemanggilan:
 * `class HomeController extends Controller`
 */
abstract class Controller
{
    // membiarkan tubuh kelas kosong karena otorisasi kini ditangani per-rute dan Policy tanpa controller middleware lama.
}
