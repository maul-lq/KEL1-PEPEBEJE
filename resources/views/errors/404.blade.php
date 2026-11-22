{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 404 ini saya buat untuk menggantikan halaman Not Found bawaan Laravel
              dengan pesan Bahasa Indonesia standar yang aman dan tombol kembali ke halaman sebelumnya.
--}}
@extends('errors.layout')

@section('code', '404')
@section('title', 'Halaman Tidak Ditemukan')
@section('tone', 'primary')
@section('heading', 'Halaman Tidak Ditemukan')
@section('message', 'Halaman atau data yang Anda cari tidak ditemukan, telah dihapus, atau alamat tautannya tidak tersedia. Pastikan alamat halaman yang dituju sudah benar.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
    </svg>
@endsection
