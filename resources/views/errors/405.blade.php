{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 405 ini saya susun untuk menangani permintaan metode HTTP yang tidak
              didukung saat pengguna mengakses endpoint pengiriman data secara langsung lewat browser.
--}}
@extends('errors.layout')

@section('code', '405')
@section('title', 'Metode Tidak Diizinkan')
@section('tone', 'warning')
@section('heading', 'Metode Permintaan Tidak Diizinkan')
@section('message', 'Alamat ini tidak dapat dibuka langsung dengan metode permintaan tersebut. Gunakan tautan atau formulir resmi di dalam aplikasi untuk memproses tindakan ini.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
    </svg>
@endsection
