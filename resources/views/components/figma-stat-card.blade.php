{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini rancang untuk merender kartu metrik KPI dasbor bergaya Figma
              dengan dukungan elemen `<a>` interaktif atau `<div>` statis serta mode teks nominal ringkas.
--}}
{{--
  Komentar Deskriptif: Komponen Kartu Metrik KPI Dasbor Figma (`<x-figma-stat-card>`).

  Tujuan: menstandarkan tampilan 4 kartu statistik atas di semua dasbor peran PNJ supaya
          angka besar maupun nominal rupiah terpotong rapi dengan `truncate` dan tooltip penuh.
  Parameter (@props):
    - `title` (string, wajib): Label nama metrik KPI.
    - `value` (int|string, default 0): Nilai angka atau teks rupiah metrik.
    - `subtitle` (string, default ''): Keterangan singkat di baris bawah kartu.
    - `variant` (string, default 'active'): Variasi ikon ('active' | 'action' | 'done' | 'bell').
    - `href` (string|null): URL tujuan bila kartu bersifat dapat diklik.
    - `compact` (bool, default false): Mengecilkan ukuran font angka agar nominal rupiah panjang muat.
    - `fullTitle` (string|null): Teks tooltip HTML penuh apabila `title` atau `value` terpotong.
  Nilai kembali: Kartu statistik `<a>` atau `<div>` siap pakai dalam grid dasbor.
  Contoh pemanggilan: `<x-figma-stat-card title="Usulan Aktif" :value="$count" variant="active" :href="route('permohonan.index')" />`
--}}
@props([
    'title',
    'value' => 0,
    'subtitle' => '',
    'variant' => 'active',
    'href' => null,
    'compact' => false,
    'fullTitle' => null,
])

@php
    $iconMap = [
        'active' => 'icon-card-active.svg',
        'action' => 'icon-card-action.svg',
        'done' => 'icon-card-done.svg',
        'bell' => 'icon-card-bell.svg',
    ];
    $iconFile = $iconMap[$variant] ?? 'icon-card-active.svg';
    // alihkan tag root menjadi <a> hanya ketika href diisi agar elemen non-link tetap semantik.
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    class="block bg-white border border-[#d2d2d2] rounded-[5px] p-5 h-[150px] relative transition-shadow {{ $href ? 'hover:shadow-md group' : '' }}"
    @if($fullTitle) title="{{ $fullTitle }}" @endif
>
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-4 min-w-0 flex-1">
            <div class="size-[59px] shrink-0">
                <img src="{{ asset('images/dashboard/' . $iconFile) }}" alt="{{ $title }}" class="size-full">
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[#4a4a4a] text-[15px] xl:text-[16px] font-semibold truncate" title="{{ $title }}">
                    {{ $title }}
                </div>
                <div class="{{ $compact ? 'text-[24px] xl:text-[28px] mt-2 mb-1' : 'text-[38px] my-1' }} font-semibold text-black leading-none truncate">
                    {{ $value }}
                </div>
            </div>
        </div>
        <div class="text-[#657a7c] {{ $href ? 'group-hover:text-[#3b82f6] group-hover:translate-x-0.5' : 'opacity-60' }} transition-all mt-2 shrink-0">
            <svg class="size-4 -rotate-90" viewBox="0 0 14.314 8.657" fill="none">
                <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
            </svg>
        </div>
    </div>
    <div class="text-[#969696] text-[13px] font-medium mt-3 pl-[75px] truncate" title="{{ $subtitle }}">
        {{ $subtitle }}
    </div>
</{{ $tag }}>
