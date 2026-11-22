{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 500 ini saya susun untuk menampilkan pemberitahuan gangguan server yang
              aman bagi pengguna akhir tanpa memaparkan rincian teknis internal maupun stack trace.
--}}
@extends('errors.layout')

@section('code', '500')
@section('title', 'Gangguan pada Server')
@section('tone', 'danger')
@section('heading', 'Terjadi Gangguan pada Server')
@section('message', 'Terjadi kendala teknis di server saat memproses permintaan ini. Silakan kembali ke halaman sebelumnya, coba beberapa saat lagi, atau hubungi administrator sistem.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
