{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View cadangan HTTP 5xx ini saya sediakan untuk menangani kode status gangguan server
              lainnya secara aman tanpa menampilkan rincian internal aplikasi.
--}}
@php
    $statusCode = (string) ($exception?->getStatusCode() ?? '500');
@endphp
@extends('errors.layout')

@section('code', $statusCode)
@section('title', 'Gangguan Layanan Server')
@section('tone', 'danger')
@section('heading', 'Terjadi Gangguan Layanan Server')
@section('message', 'Layanan sistem sedang mengalami gangguan saat memproses permintaan ini. Silakan kembali ke halaman sebelumnya atau coba beberapa saat lagi.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
