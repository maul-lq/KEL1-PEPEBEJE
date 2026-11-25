{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini susun untuk merender kartu item antrean tindakan di dasbor
              berbasis gaya Figma lengkap dengan garis aksen kiri berotasi, ikon status, lencana,
              serta slot aksi kustom atau tombol navigasi.
--}}
{{--
   Komponen Kartu Item Aksi Dasbor (`<x-figma-action-card>`).

  Tujuan: menyediakan template baris antrean kerja seragam untuk seluruh dasbor peran agar
          informasi judul berkas, metadata unit/tanggal, status lencana, dan tombol tindak lanjut konsisten.
  Parameter (@props):
    - `index` (int, default 0): Nomor urut baris untuk memutar 3 warna garis tepi kiri.
    - `variant` (string, default 'active'): Variasi ikon status ('active' | 'action' | 'done' | 'bell').
    - `title` (string, wajib): Judul utama item pengadaan atau pendaftaran.
    - `meta` (string, default ''): Subjudul metadata baris pertama.
    - `submeta` (string|null, default null): Catatan baris kedua opsional.
    - `badge` (string|null, default null): Teks lencana status opsional.
    - `badgeClass` (string): Kelas Tailwind CSS untuk warna lencana.
    - `href` (string|null): Tautan tombol aksi default jika slot `$actions` tidak disediakan.
    - `buttonLabel` (string, default 'Buka'): Label teks tombol tautan default.
  Nilai kembali: Kartu baris item aksi HTML siap pakai di dalam `x-figma-workspace`.
  Contoh pemanggilan:
    `<x-figma-action-card :index="$loop->index" variant="action" :title="$item->judul_pengadaan" :href="route('verifikasi.wadir_2')" />`
--}}
@props([
    'index' => 0,
    'variant' => 'active',
    'title',
    'meta' => '',
    'submeta' => null,
    'badge' => null,
    'badgeClass' => 'bg-[#fff5df] text-[#99660d]',
    'href' => null,
    'buttonLabel' => 'Buka',
])

@php
    // pakai modulo 3 agar warna aksen tepi kiri bergantian mengikuti 3 kelas CSS (.action-card-accent-0..2).
    $accentIndex = $index % 3;

    $iconMap = [
        'active' => 'icon-card-active.svg',
        'action' => 'icon-card-action.svg',
        'done' => 'icon-card-done.svg',
        'bell' => 'icon-card-bell.svg',
    ];
    $iconFile = $iconMap[$variant] ?? 'icon-card-active.svg';
@endphp

<div class="action-card-accent-{{ $accentIndex }} bg-white rounded-[5px] shadow-[0px_0px_5px_0px_rgba(0,0,0,0.20)] p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 border-l-4 transition-all hover:shadow-md">
    <div class="flex items-start gap-3.5 min-w-0 flex-1">
        <div class="shrink-0 size-[50px] md:size-[55px]">
            <img src="{{ asset('images/dashboard/' . $iconFile) }}" alt="" class="size-full">
        </div>
        <div class="min-w-0 flex-1">
            <h4 class="text-[15px] font-medium text-black truncate" title="{{ $title }}">
                {{ $title }}
            </h4>
            @if($meta)
                <p class="text-[11px] font-semibold text-[#657a7c] mt-0.5 truncate" title="{{ $meta }}">
                    {{ $meta }}
                </p>
            @endif
            @if($submeta)
                <p class="text-[11px] font-medium text-[#657a7c] mt-0.5 line-clamp-1" title="{{ $submeta }}">
                    {{ $submeta }}
                </p>
            @endif
        </div>
    </div>

    <div class="shrink-0 flex items-center gap-2.5 self-end md:self-center">
        @if($badge)
            <span class="{{ $badgeClass }} text-[11px] font-normal px-2.5 py-1 rounded-[4px] whitespace-nowrap">
                {{ $badge }}
            </span>
        @endif

        {{-- Slot actions didahulukan dibanding href agar halaman admin atau PPBJ bisa menyematkan tombol modal kustom. --}}
        @if(isset($actions) && ! $actions->isEmpty())
            {{ $actions }}
        @elseif($href)
            <a href="{{ $href }}" class="bg-[#3b82f6] hover:bg-blue-600 border border-[#3b82f6] text-white text-[14px] md:text-[15px] font-medium px-4 py-1.5 rounded-[5px] whitespace-nowrap transition-colors flex items-center gap-1 shadow-xs">
                {{ $buttonLabel }} &rarr;
            </a>
        @endif
    </div>
</div>
