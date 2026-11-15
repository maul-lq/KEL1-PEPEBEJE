@props([
    'leftTitle' => 'Perlu Tindakan Anda',
    'leftHref' => null,
    'leftLinkLabel' => 'Lihat Semua',
    'rightTitle' => 'Aktivitas Terbaru',
    'rightHref' => null,
    'rightLinkLabel' => 'Lihat Semua',
])

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    {{-- KOLOM KIRI: PERLU TINDAKAN ANDA --}}
    <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
        <div>
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset('images/dashboard/icon-action-needed.svg') }}" alt="{{ $leftTitle }}" class="size-[25px] shrink-0">
                    <h3 class="text-[#4a4a4a] text-[18px] font-semibold truncate">{{ $leftTitle }}</h3>
                </div>
                @if($leftHref)
                    <a href="{{ $leftHref }}" class="text-[#3b82f6] text-[15px] md:text-[17px] font-semibold flex items-center gap-1.5 hover:underline group shrink-0">
                        {{ $leftLinkLabel }}
                        <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                            <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                        </svg>
                    </a>
                @endif
            </div>

            <div class="mt-4 space-y-3">
                {{ $left }}
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN: AKTIVITAS TERBARU / RINGKASAN PRIORITAS ROLE --}}
    <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
        <div>
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset('images/dashboard/icon-recent-activity.svg') }}" alt="{{ $rightTitle }}" class="size-[22px] shrink-0">
                    <h3 class="text-[#4a4a4a] text-[18px] md:text-[20px] font-semibold truncate">{{ $rightTitle }}</h3>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    @if(isset($rightHeaderExtra))
                        {{ $rightHeaderExtra }}
                    @endif
                    @if($rightHref)
                        <a href="{{ $rightHref }}" class="text-[#3b82f6] text-[15px] md:text-[17px] font-semibold flex items-center gap-1.5 hover:underline group">
                            {{ $rightLinkLabel }}
                            <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                                <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                {{ $right }}
            </div>
        </div>
    </div>
</div>
