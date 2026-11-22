{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 503 ini saya rancang untuk kondisi pemeliharaan sistem atau gangguan
              ketersediaan layanan sementara pada aplikasi SIM-PBJ.
--}}
@extends('errors.layout')

@section('code', '503')
@section('title', 'Layanan Sedang Tidak Tersedia')
@section('tone', 'warning')
@section('heading', 'Layanan Sedang Tidak Tersedia')
@section('message', 'Sistem SIM-PBJ sedang dalam pemeliharaan atau mengalami beban layanan sementara. Silakan tunggu beberapa saat dan coba muat ulang kembali.')
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085" />
    </svg>
@endsection
