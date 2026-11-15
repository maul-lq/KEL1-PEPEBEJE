@props([
    'action' => '',
    'method' => 'GET',
    'searchPlaceholder' => 'Cari nomor surat, perihal, atau rekanan...',
    'searchValue' => null,
    'searchName' => 'q',
    'showReset' => true,
    'resetUrl' => null,
])

@php
    $formAction = $action !== '' ? $action : url()->current();
    $val = $searchValue ?? request($searchName, request('search', ''));
    $cleanResetUrl = $resetUrl ?? url()->current();
    $hasActiveFilters = request()->filled($searchName) || count(request()->except(['page', $searchName])) > 0;
@endphp

<div {{ $attributes->merge(['class' => 'w-full rounded-2xl bg-white p-4 shadow-sm border border-slate-200/80 mb-6']) }}>
    <form action="{{ $formAction }}" method="{{ $method }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Bagian Kiri: Input Pencarian & Dropdown Filters -->
        <div class="flex flex-wrap items-center gap-3 flex-1">
            
            <!-- Input Pencarian Standar -->
            <div class="relative min-w-[240px] flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input
                    type="search"
                    name="{{ $searchName }}"
                    value="{{ $val }}"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50/60 pl-10 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
                />
            </div>

            <!-- Slot Dropdown Filter Kustom -->
            @if(isset($filters))
                {{ $filters }}
            @endif

            <!-- Tombol Submit Pencarian -->
            <button
                type="submit"
                class="h-10 px-4 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm hover:shadow transition-all inline-flex items-center gap-1.5 shrink-0"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Cari
            </button>

            <!-- Tombol Reset Filter -->
            @if($showReset && $hasActiveFilters)
                <a
                    href="{{ $cleanResetUrl }}"
                    class="h-10 px-3 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-all inline-flex items-center gap-1 shrink-0"
                    title="Bersihkan Semua Filter"
                >
                    <svg class="size-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </a>
            @endif

        </div>

        <!-- Bagian Kanan: Slot Aksi Kustom (cth: Tombol Tambah, Export) -->
        @if(isset($actions))
            <div class="flex items-center gap-2.5 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                {{ $actions }}
            </div>
        @endif

    </form>
</div>
