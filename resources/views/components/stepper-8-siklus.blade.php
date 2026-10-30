@props([
    'currentStep' => 1,
    'status' => 'diajukan',
    'mode' => 'horizontal',
    'interactive' => false,
    'stepsData' => [],
])

@php
    $stages = [
        1 => ['title' => 'Diajukan', 'desc' => 'Pengajuan Usulan Srikandi', 'actor' => 'Unit / Pemohon'],
        2 => ['title' => 'Verifikasi Paralel', 'desc' => 'Telaah B2, Wadir 2 & MAK', 'actor' => 'Bidang 2 & Perencanaan'],
        3 => ['title' => 'Registrasi Paket', 'desc' => 'Pembuatan Paket & Routing HPS', 'actor' => 'Koordinator PPBJ'],
        4 => ['title' => 'Reviu Dokumen', 'desc' => 'Reviu Teknis & Negosiasi', 'actor' => 'PP / PPK'],
        5 => ['title' => 'Kontrak SPK', 'desc' => 'Penerbitan Kontrak & Kunci MAK', 'actor' => 'PPBJ & PPK'],
        6 => ['title' => 'Pengingat & Pengiriman', 'desc' => 'Pengiriman & Alarm H-2', 'actor' => 'Vendor & Sistem'],
        7 => ['title' => 'Penerimaan BAST', 'desc' => 'Pemeriksaan Fisik & TTD BAST', 'actor' => 'Perlengkapan BMN'],
        8 => ['title' => 'SP2D Lunas', 'desc' => 'Memo Bayar, SPP & Cair SP2D', 'actor' => 'PPK, Wadir 2 & Keuangan'],
    ];

    $totalSteps = 8;
    $curr = max(1, min(8, (int) $currentStep));
    $st = strtolower((string) $status);
    $isRejected = in_array($st, ['ditolak', 'batal']);
    $isHold = in_array($st, ['hold', 'ditahan', 'revisi', 'perlu_revisi']);
@endphp

@if($mode === 'horizontal')
    {{-- MODE 1: HORIZONTAL STEPPER BAR (PRESISI FIGMA CANVAS 276:1819 / FRAME 526:3083) --}}
    <div {{ $attributes->merge(['class' => 'w-full rounded-[20px] bg-gradient-to-t from-white to-white/60 p-6 md:px-8 border border-slate-200/80 shadow-sm']) }}>
        <div class="flex items-center justify-between mb-6">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-[#0088E8]">Alur 8 Siklus Pengadaan</span>
                <h3 class="text-xl md:text-2xl font-bold text-[#213153] mt-0.5">
                    Tahap {{ $curr }} dari {{ $totalSteps }}: <span class="text-[#0969da]">{{ $stages[$curr]['title'] }}</span>
                </h3>
            </div>
            <div>
                <x-status-badge :status="$status" size="md" />
            </div>
        </div>

        <!-- Horizontal Track -->
        <div class="overflow-x-auto pb-4 pt-2 -mx-2 px-2">
            <div class="relative min-w-[760px] flex items-center justify-between">
                <!-- Background Connection Line -->
                <div class="absolute left-[35px] right-[35px] top-6 h-[4px] -translate-y-1/2 bg-slate-200 rounded-full z-0"></div>
                <!-- Active Progress Fill -->
                @php
                    $progressPercent = $curr > 1 ? (($curr - 1) / ($totalSteps - 1)) * 100 : 0;
                    $fillWidth = $curr > 1 ? "calc({$progressPercent}% - ".($curr === 8 ? '0px' : '35px').")" : '0px';
                @endphp
                <div class="absolute left-[35px] top-6 h-[4px] -translate-y-1/2 bg-[#0088E8] rounded-full z-0 transition-all duration-500"
                     style="width: {{ $fillWidth }};"></div>

                <!-- 8 Step Nodes -->
                @for ($n = 1; $n <= $totalSteps; $n++)
                    @php
                        $isPast = $n < $curr;
                        $isCurrent = $n === $curr;
                        $isFuture = $n > $curr;

                        if ($isPast) {
                            $nodeClass = 'bg-[#0087E5] text-white border-[#0087E5] shadow-md shadow-blue-500/20';
                        } elseif ($isCurrent) {
                            if ($isRejected) {
                                $nodeClass = 'bg-rose-500 text-white border-rose-600 ring-4 ring-rose-100 shadow-md';
                            } elseif ($isHold) {
                                $nodeClass = 'bg-amber-500 text-white border-amber-600 ring-4 ring-amber-100 shadow-md';
                            } else {
                                $nodeClass = 'bg-white text-[#0087E5] border-[#0087E5] ring-4 ring-blue-100 shadow-lg';
                            }
                        } else {
                            $nodeClass = 'bg-white text-slate-400 border-slate-300';
                        }
                    @endphp

                    <div class="relative z-10 flex flex-col items-center group cursor-default">
                        <!-- Node Circle (Figma 70x70 styled down responsively to 48px - 56px) -->
                        <div class="flex size-12 md:size-14 items-center justify-center rounded-full border-[4px] text-lg md:text-xl font-extrabold transition-all duration-200 {{ $nodeClass }}">
                            @if($isPast)
                                <svg class="size-6 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @elseif($isCurrent && $isRejected)
                                <svg class="size-6 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            @else
                                {{ $n }}
                            @endif
                        </div>

                        <!-- Label Under Circle -->
                        <div class="mt-2 text-center w-24">
                            <p class="text-xs font-bold leading-tight {{ $isCurrent ? 'text-[#0969da]' : ($isPast ? 'text-slate-800' : 'text-slate-400') }}">
                                {{ $stages[$n]['title'] }}
                            </p>
                            <p class="text-[10px] text-slate-400 leading-tight mt-0.5 truncate hidden md:block">
                                {{ $stages[$n]['actor'] }}
                            </p>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

@elseif($mode === 'vertical')
    {{-- MODE 2: VERTICAL TIMELINE STEPPER (SEPERTI PADA permohonan/show.blade.php) --}}
    <div {{ $attributes->merge(['class' => 'w-full space-y-6']) }}>
        @for ($n = 1; $n <= $totalSteps; $n++)
            @php
                $isPast = $n < $curr;
                $isCurrent = $n === $curr;
                $isFuture = $n > $curr;
                $stepInfo = $stepsData[$n] ?? [];

                if ($isPast) {
                    $circleClass = 'bg-emerald-500 text-white';
                    $lineClass = 'bg-emerald-200';
                } elseif ($isCurrent) {
                    if ($isRejected) {
                        $circleClass = 'bg-rose-500 text-white';
                        $lineClass = 'bg-slate-200';
                    } elseif ($isHold) {
                        $circleClass = 'bg-amber-500 text-white';
                        $lineClass = 'bg-slate-200';
                    } else {
                        $circleClass = 'bg-[#0969da] text-white ring-4 ring-blue-100';
                        $lineClass = 'bg-slate-200';
                    }
                } else {
                    $circleClass = 'bg-slate-200 text-slate-500';
                    $lineClass = 'bg-slate-200';
                }
            @endphp

            <div class="flex gap-4">
                <div class="flex flex-col items-center">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full font-bold text-xs {{ $circleClass }}">
                        @if($isPast)
                            <svg class="size-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $n }}
                        @endif
                    </span>
                    @if($n < $totalSteps)
                        <div class="w-0.5 grow mt-2 {{ $lineClass }}"></div>
                    @endif
                </div>

                <div class="pb-5 min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-sm font-bold text-slate-900">
                            Tahap {{ $n }}: {{ $stages[$n]['title'] }}
                        </h4>
                        @if($isCurrent)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-[#0969da]">
                                Sedang Berjalan
                            </span>
                        @elseif($isPast)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                Selesai
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-slate-500 mt-0.5">
                        {{ $stages[$n]['actor'] }} &bull; {{ $stages[$n]['desc'] }}
                    </div>

                    @if(!empty($stepInfo['catatan']))
                        <p class="text-xs text-slate-700 bg-slate-50 border border-slate-200/60 rounded-lg p-2.5 mt-2">
                            {{ $stepInfo['catatan'] }}
                        </p>
                    @endif

                    @if(!empty($stepInfo['date']))
                        <span class="text-[10px] text-slate-400 mt-1 block">
                            {{ $stepInfo['date'] }}
                        </span>
                    @endif
                </div>
            </div>
        @endfor
    </div>

@else
    {{-- MODE 3: COMPACT STEPPER (UNTUK BARIS TABEL / PREVIEW KECIL) --}}
    <div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }} title="Tahap {{ $curr }} dari 8: {{ $stages[$curr]['title'] }}">
        @for ($n = 1; $n <= $totalSteps; $n++)
            <div class="h-1.5 rounded-full transition-all {{ $n <= $curr ? ($isRejected ? 'bg-rose-500' : 'bg-[#0088E8]') : 'bg-slate-200' }} {{ $n === $curr ? 'w-6' : 'w-2.5' }}"></div>
        @endfor
        <span class="text-[11px] font-bold text-slate-600 ml-1 font-mono">{{ $curr }}/8</span>
    </div>
@endif
