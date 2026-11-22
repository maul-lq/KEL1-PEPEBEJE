{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 429 ini saya buat untuk memberi jeda instruksi yang jelas saat
              permintaan pengguna melebihi batas frekuensi yang diizinkan oleh sistem.
--}}
@extends('errors.layout')

@section('code', '429')
@section('title', 'Terlalu Banyak Permintaan')
@section('tone', 'warning')
@section('heading', 'Terlalu Banyak Permintaan')
@section('message', 'Sistem menerima terlalu banyak permintaan dari akun atau peramban Anda dalam waktu singkat. Tunggu beberapa saat sebelum mencoba mengakses kembali.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
