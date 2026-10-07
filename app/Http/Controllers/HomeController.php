<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Tampilkan halaman tes beranda (home).
     */
    public function index(): View
    {
        return view('tes_home');
    }

    /**
     * Tampilkan halaman tes API backend.
     */
    public function testApi(): View
    {
        return view('tes_api');
    }
}
