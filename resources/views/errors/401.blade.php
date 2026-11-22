{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 401 ini saya buat untuk memberitahukan pengguna bahwa akses halaman
              memerlukan autentikasi atau sesi masuknya belum terverifikasi.
--}}
@extends('errors.layout')

@section('code', '401')
@section('title', 'Autentikasi Diperlukan')
@section('tone', 'warning')
@section('heading', 'Autentikasi Diperlukan')
@section('message', 'Halaman ini tidak dapat diakses karena Anda belum masuk ke sistem atau sesi autentikasi Anda telah berakhir. Silakan masuk terlebih dahulu untuk melanjutkan.')
@section('hide_home_button', '1')
@section('extra_action')
    @php
        $loginUrl = '/';
        try {
            if (\Illuminate\Support\Facades\Route::has('login')) {
                $loginUrl = route('login');
            }
        } catch (\Throwable) {
            $loginUrl = '/login';
        }
    @endphp
    <a href="{{ $loginUrl }}"
       class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0969da] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#0858b8] transition-colors">
        Masuk ke Akun
    </a>
@endsection
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
    </svg>
@endsection
