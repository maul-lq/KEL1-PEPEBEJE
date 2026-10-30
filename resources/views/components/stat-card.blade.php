@props([
    'title',
    'value',
    'icon' => null,
    'color' => 'blue',
    'subtitle' => null,
    'badge' => null,
    'badgeColor' => null,
    'trend' => null,
    'href' => null,
])

@php
    $colorMap = [
        'navy' => [
            'iconBg' => 'bg-slate-100 text-[#213153]',
            'badge' => 'bg-slate-100 text-[#213153] border-slate-200',
            'borderHover' => 'hover:border-slate-300',
            'accent' => 'text-[#213153]',
        ],
        'blue' => [
            'iconBg' => 'bg-blue-50 text-[#0969da]',
            'badge' => 'bg-blue-50 text-[#0969da] border-blue-200',
            'borderHover' => 'hover:border-blue-300',
            'accent' => 'text-[#0969da]',
        ],
        'teal' => [
            'iconBg' => 'bg-teal-50 text-[#087F79]',
            'badge' => 'bg-teal-50 text-[#087F79] border-teal-200',
            'borderHover' => 'hover:border-teal-300',
            'accent' => 'text-[#087F79]',
        ],
        'amber' => [
            'iconBg' => 'bg-amber-50 text-amber-600',
            'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
            'borderHover' => 'hover:border-amber-300',
            'accent' => 'text-amber-600',
        ],
        'emerald' => [
            'iconBg' => 'bg-emerald-50 text-emerald-600',
            'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'borderHover' => 'hover:border-emerald-300',
            'accent' => 'text-emerald-600',
        ],
        'rose' => [
            'iconBg' => 'bg-rose-50 text-rose-600',
            'badge' => 'bg-rose-50 text-rose-700 border-rose-200',
            'borderHover' => 'hover:border-rose-300',
            'accent' => 'text-rose-600',
        ],
    ];

    $theme = $colorMap[$color] ?? $colorMap['blue'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => "group relative flex items-center justify-between p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md {$theme['borderHover']} transition-all duration-200"]) }}
>
    <div class="space-y-1.5 min-w-0 pr-3">
        <div class="flex items-center gap-2">
            <p class="text-xs font-medium text-slate-500 truncate">{{ $title }}</p>
            @if($badge !== null && $badge !== '')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeColor ?? $theme['badge'] }}">
                    @if($trend === 'up')
                        <svg class="size-2.5 mr-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                    @elseif($trend === 'down')
                        <svg class="size-2.5 mr-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25"/></svg>
                    @endif
                    {{ $badge }}
                </span>
            @endif
        </div>

        <h3 class="text-2xl font-bold tracking-tight text-[#213153] leading-none">
            {{ $value }}
        </h3>

        @if($subtitle)
            <p class="text-[11px] text-slate-400 truncate">{{ $subtitle }}</p>
        @endif
    </div>

    @if($icon || isset($iconSlot) || $slot->isNotEmpty())
        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $theme['iconBg'] }} transition-transform group-hover:scale-105">
            @if(isset($iconSlot))
                {{ $iconSlot }}
            @elseif($slot->isNotEmpty())
                {{ $slot }}
            @elseif($icon)
                @switch($icon)
                    @case('document')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        @break
                    @case('box')
                    @case('package')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        @break
                    @case('users')
                    @case('vendor')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0a2 2 0 012-2h2a2 2 0 012 2m-6 0v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                        @break
                    @case('bell')
                    @case('notification')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @break
                    @case('wallet')
                    @case('money')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        @break
                    @case('check')
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @break
                    @default
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endswitch
            @endif
        </div>
    @endif
</{{ $tag }}>
