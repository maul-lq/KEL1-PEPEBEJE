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
