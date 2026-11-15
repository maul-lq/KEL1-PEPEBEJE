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
    $accentColors = ['#ffd900', '#5d60ff', '#fc3dff'];
    $borderColor = $accentColors[$index % count($accentColors)];

    $iconMap = [
        'active' => 'icon-card-active.svg',
        'action' => 'icon-card-action.svg',
        'done' => 'icon-card-done.svg',
        'bell' => 'icon-card-bell.svg',
    ];
    $iconFile = $iconMap[$variant] ?? 'icon-card-active.svg';
@endphp

<div class="bg-white rounded-[5px] shadow-[0px_0px_5px_0px_rgba(0,0,0,0.20)] p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 border-l-4 transition-all hover:shadow-md"
     style="border-left-color: {{ $borderColor }};">
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

        @if(isset($actions) && ! $actions->isEmpty())
            {{ $actions }}
        @elseif($href)
            <a href="{{ $href }}" class="bg-[#3b82f6] hover:bg-blue-600 border border-[#3b82f6] text-white text-[14px] md:text-[15px] font-medium px-4 py-1.5 rounded-[5px] whitespace-nowrap transition-colors flex items-center gap-1 shadow-xs">
                {{ $buttonLabel }} &rarr;
            </a>
        @endif
    </div>
</div>
