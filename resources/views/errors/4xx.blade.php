{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View cadangan HTTP 4xx ini saya sediakan sebagai penangkap otomatis Laravel untuk kode
              status klien lainnya agar tetap tampil dengan antarmuka standar SIM-PBJ.
--}}
@php
    $statusCode = (string) ($exception?->getStatusCode() ?? '400');
@endphp
@extends('errors.layout')

@section('code', $statusCode)
@section('title', 'Permintaan Tidak Dapat Diproses')
@section('tone', 'warning')
@section('heading', 'Permintaan Tidak Dapat Diproses')
@section('message', 'Permintaan ke halaman ini belum dapat dipenuhi karena kondisi atau format akses tidak sesuai. Kembali ke halaman sebelumnya dan periksa kembali langkah yang dilakukan.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
    </svg>
@endsection
