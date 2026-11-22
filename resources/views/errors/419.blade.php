{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 419 ini saya buat untuk memberitahukan pengguna bahwa token keamanan
              formulir telah kedaluwarsa sehingga halaman perlu dimuat ulang sebelum mengirim ulang data.
--}}
@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi Halaman Kedaluwarsa')
@section('tone', 'warning')
@section('heading', 'Sesi Halaman Kedaluwarsa')
@section('message', 'Halaman ini sudah terlalu lama dibuka sehingga token keamanan formulir telah habis masa berlakunya. Muat ulang halaman atau kembali ke halaman sebelumnya untuk mengirim ulang data.')
@section('extra_action')
    <button type="button"
            onclick="window.location.reload()"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#0969da]/30 bg-[#F0F6FF] px-5 py-2.5 text-sm font-semibold text-[#0969da] hover:bg-blue-100 transition-colors cursor-pointer">
        Muat Ulang Halaman
    </button>
@endsection
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection
