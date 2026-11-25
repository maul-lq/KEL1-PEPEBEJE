{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : Layout dasar halaman error SIM-PBJ ini saya buat tanpa sidebar dan tanpa query basis data
              agar tetap aman tampil pada kondisi tamu, pengguna login, maupun gangguan server 500/503.
--}}
{{--
   Template Induk Halaman Error HTTP SIM-PBJ (`errors.layout`).

  Tujuan: Saya menyediakan kerangka halaman error mandiri dengan bilah atas SIM-PBJ, kartu informasi
          error di tengah layar, tombol kembali ke halaman sebelumnya, serta tautan aman ke beranda/login.
  Parameter (Blade Sections): `@yield('code')`, `@yield('title')`, `@yield('heading')`, `@yield('message')`,
                              `@yield('tone')`, `@yield('icon')`, `@yield('extra_action')`, `@yield('hide_home_button')`.
  Nilai kembali: Dokumen HTML5 mandiri yang aman terhadap gangguan database maupun Vite manifest.
  Contoh pemanggilan: `@extends('errors.layout')`
--}}
@php
    $viteCssTag = '';
    try {
        // memuat stylesheet Vite dalam blok try/catch supaya manifest yang belum dibangun tidak memicu error 500 sekunder.
        if (app()->bound(\Illuminate\Foundation\Vite::class)) {
            $viteCssTag = (string) app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']);
        }
    } catch (\Throwable) {
        $viteCssTag = '';
    }

    $isAuthenticated = false;
    try {
        // mengecek status autentikasi dalam try/catch agar gangguan koneksi basis data pada error 500/503 tidak menggagalkan render halaman.
        $isAuthenticated = \Illuminate\Support\Facades\Auth::check();
    } catch (\Throwable) {
        $isAuthenticated = false;
    }

    $homeUrl = '/';
    $homeLabel = 'Ke Beranda';
    try {
        if ($isAuthenticated && \Illuminate\Support\Facades\Route::has('dashboard')) {
            $homeUrl = route('dashboard');
            $homeLabel = 'Ke Dashboard';
        } elseif (! $isAuthenticated && \Illuminate\Support\Facades\Route::has('login')) {
            $homeUrl = route('login');
            $homeLabel = 'Ke Halaman Masuk';
        }
    } catch (\Throwable) {
        $homeUrl = '/';
        $homeLabel = 'Ke Beranda';
    }

    $tone = trim($__env->yieldContent('tone', 'primary'));
    $badgeClasses = match ($tone) {
        'danger' => 'bg-[#FFEBE9] text-[#CF222E] border-rose-200',
        'warning' => 'bg-[#FFF5DF] text-[#99660D] border-amber-200',
        default => 'bg-[#F0F6FF] text-[#0969da] border-blue-200',
    };
    $iconWrapClasses = match ($tone) {
        'danger' => 'bg-[#FFEBE9] text-[#CF222E]',
        'warning' => 'bg-[#FFF5DF] text-[#99660D]',
        default => 'bg-[#F0F6FF] text-[#0969da]',
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Terjadi Kendala') - SIM-PBJ Politeknik Negeri Jakarta</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    {!! $viteCssTag !!}

    @if ($viteCssTag === '')
        {{-- menyediakan gaya cadangan minimalis hanya saat manifest/aset CSS Vite gagal dimuat ketika gangguan build atau error 500. --}}
        <style>
            .simpbj-error-shell { min-height: 100vh; display: flex; flex-direction: column; background-color: #F4F8FA; color: #1e293b; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; }
            .simpbj-error-header { height: 73px; background: #ffffff; border-bottom: 1px solid rgba(226, 232, 240, 0.9); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 2px rgba(0, 0, 0, 0.06); }
            .simpbj-error-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
            .simpbj-error-card { width: 100%; max-width: 36rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 36px 28px; text-align: center; box-shadow: 0 4px 16px rgba(33, 49, 83, 0.06); }
        </style>
    @endif
</head>
<body class="simpbj-error-shell min-h-screen flex flex-col bg-[#F4F8FA] text-slate-800 antialiased">

    <header id="error-top-banner" class="simpbj-error-header sticky top-0 z-30 flex h-[73px] w-full shrink-0 items-center justify-between border-b border-slate-200/80 bg-white px-4 sm:px-6 shadow-[0px_2px_2px_rgba(0,0,0,0.08)]">
        <a href="{{ $homeUrl }}" class="flex items-center gap-3.5 group">
            <img src="{{ asset('favicon.ico') }}" alt="Logo SIM-PBJ" class="size-[44px] sm:size-[50px] rounded-full object-contain transition-transform group-hover:scale-105">
            <span class="font-iceland text-[32px] sm:text-[38px] font-normal tracking-normal text-black group-hover:text-[#213153] transition-colors leading-none">
                SIM-PBJ
            </span>
        </a>

        <a href="{{ $homeUrl }}"
           class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs sm:text-sm font-semibold text-[#213153] hover:bg-slate-100 transition-colors">
            {{ $homeLabel }}
        </a>
    </header>

    <main class="simpbj-error-main flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
        <section class="simpbj-error-card w-full max-w-xl rounded-2xl border border-slate-200/90 bg-white p-8 sm:p-10 text-center shadow-sm" aria-labelledby="error-heading">
            <div class="mx-auto mb-5 flex size-16 items-center justify-center rounded-2xl {{ $iconWrapClasses }}">
                @hasSection('icon')
                    @yield('icon')
                @else
                    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                @endif
            </div>

            <div class="mb-3">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold tracking-wide {{ $badgeClasses }}">
                    Kode HTTP @yield('code', 'Error')
                </span>
            </div>

            <h1 id="error-heading" class="text-2xl sm:text-3xl font-bold tracking-tight text-[#213153]">
                @yield('heading', 'Terjadi Kendala Permintaan')
            </h1>

            <p class="mt-3 text-sm sm:text-base leading-relaxed text-slate-600">
                @yield('message', 'Sistem belum dapat memproses permintaan Anda saat ini.')
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                {{-- memakai window.history.back() saat histori tersedia agar pengguna dapat kembali ke halaman mana pun sebelumnya, dan jatuh ke $homeUrl bila dibuka di tab baru. --}}
                <a href="{{ $homeUrl }}"
                   data-error-back
                   onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#213153] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#192642] transition-colors">
                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    <span>Kembali ke Halaman Sebelumnya</span>
                </a>

                @yield('extra_action')

                @if(! $__env->hasSection('hide_home_button'))
                    <a href="{{ $homeUrl }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        {{ $homeLabel }}
                    </a>
                @endif
            </div>
        </section>
    </main>

    <footer class="py-4 px-6 text-center text-xs text-slate-500 border-t border-slate-200/60 bg-white">
        &copy; {{ date('Y') }} <strong>SIM-PBJ Politeknik Negeri Jakarta</strong>. Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425.
    </footer>

</body>
</html>
