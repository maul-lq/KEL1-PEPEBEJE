{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini susun untuk dialog modal reusable berbasis Alpine (`modalDialog`)
              dengan backdrop blur, animasi transisi, jebakan fokus keyboard a11y, dan slot header/footer.
--}}
{{--
  Komentar Deskriptif: Komponen Dialog Modal Reusable (`<x-modal>`).

  Tujuan: menyediakan pembungkus modal terpusat yang mendukung pembukaan/penutupan lewat
          event global `open-modal`/`close-modal`, lebar responsif, serta navigasi keyboard Tab/Escape.
  Parameter (@props & Slots):
    - `id` (string, wajib): ID DOM unik sasaran event `open-modal` / `close-modal`.
    - `title` (string|null), `subtitle` (string|null): Judul dan subjudul header standar.
    - `maxWidth` (string, default 'lg'): Kunci ukuran lebar maksimum modal ('sm'..'5xl', '596', 'full').
    - `show` (bool, default false): State buka awal modal.
    - `focusable` (bool, default true): Mengaktifkan focus-trap keyboard di dalam dialog.
    - `borderTop` (string|null): Kelas border aksen atas opsional.
    - Slot `$header`, `$slot` (body), dan `$footer`.
  Nilai kembali: Elemen HTML dialog modal aksesibel yang dikontrol `modalDialog`.
  Contoh pemanggilan:
    `<x-modal id="modal-detail" title="Detail Berkas" maxWidth="2xl">...</x-modal>`
--}}
@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'lg',
    'show' => false,
    'focusable' => true,
    'borderTop' => null,
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '596' => 'sm:max-w-[596px]',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        'full' => 'sm:max-w-[95vw]',
    ];

    $widthClass = $maxWidthClasses[$maxWidth] ?? $maxWidthClasses['lg'];
@endphp

<div
    x-data="modalDialog"
    data-show="{{ $show ? '1' : '0' }}"
    data-focusable="{{ $focusable ? '1' : '0' }}"
    x-on:open-modal.window="onWindowOpen($event.detail)"
    x-on:close-modal.window="onWindowClose($event.detail)"
    x-on:close.stop="close()"
    x-on:keydown.escape.window="close()"
    x-on:keydown.tab.prevent="$event.shiftKey ? prevFocusable().focus() : nextFocusable().focus()"
    x-show="show"
    x-cloak
    id="{{ $id }}"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    role="dialog"
    aria-modal="true"
    @if($title) aria-labelledby="modal-title-{{ $id }}" @endif
>
    <!-- 1. Backdrop Blur Overlay -->
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
        @click="show = false"
        aria-hidden="true"
    ></div>

    <!-- 2. Modal Dialog Card -->
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        @click.stop
        class="relative w-full {{ $widthClass }} {{ $borderTop ?? '' }} my-8 overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200/80 transition-all flex flex-col max-h-[90vh]"
    >
        <!-- Modal Header -->
        @if(isset($header))
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                {{ $header }}
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition-colors">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @elseif($title)
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                <div>
                    <h3 id="modal-title-{{ $id }}" class="text-lg font-bold text-[#213153] leading-snug">
                        {{ $title }}
                    </h3>
                    @if($subtitle)
                        <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition-colors" title="Tutup">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <!-- Modal Body (Scrollable if content overflows) -->
        <div class="p-6 overflow-y-auto grow">
            {{ $slot }}
        </div>

        <!-- Modal Footer -->
        @if(isset($footer))
            <div class="px-6 py-3.5 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 rounded-b-2xl">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
