{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : View error HTTP 403 ini saya rancang untuk menyampaikan penolakan akses secara sopan
              dalam Bahasa Indonesia sekaligus menampilkan otorisasi atau peran yang dibutuhkan.
--}}
@php
    $rawMessage = trim((string) ($exception?->getMessage() ?? ''));
    // hanya meneruskan pesan 403 yang sudah dikurasi dalam Bahasa Indonesia oleh middleware/controller SIM-PBJ agar pesan internal mentah tidak pernah tampil.
    $isSafeCuratedMessage = $rawMessage !== '' && (
        str_starts_with($rawMessage, 'Halaman ini tidak dapat diakses')
        || str_starts_with($rawMessage, 'Akses Ditolak:')
    );

    if ($isSafeCuratedMessage && str_starts_with($rawMessage, 'Akses Ditolak:')) {
        $reasonDetail = trim(substr($rawMessage, strlen('Akses Ditolak:')));
        $displayMessage = $reasonDetail !== ''
            ? 'Halaman ini tidak dapat diakses. '.$reasonDetail
            : 'Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi yang dibutuhkan untuk membuka fitur ini.';
    } elseif ($isSafeCuratedMessage) {
        $displayMessage = $rawMessage;
    } else {
        $displayMessage = 'Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi yang dibutuhkan untuk membuka fitur ini.';
    }
@endphp
@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses Ditolak')
@section('tone', 'danger')
@section('heading', 'Akses Ditolak')
@section('message', $displayMessage)
@section('icon')
    <svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
    </svg>
@endsection
