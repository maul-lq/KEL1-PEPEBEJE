@props(['pengadaan'])

@php
    $currentStep = $pengadaan->step_index;
    $steps = [
        1 => ['title' => 'Permohonan User', 'desc' => 'Surat Pengajuan & Srikandi'],
        2 => ['title' => 'Persetujuan Wadir 2', 'desc' => 'Disposisi Pimpinan'],
        3 => ['title' => 'Penetapan MAK', 'desc' => 'Perencanaan & Anggaran'],
        4 => ['title' => 'Registrasi & Reviu PPK', 'desc' => 'Routing Sesuai Nominal'],
        5 => ['title' => 'Penerbitan SPK', 'desc' => 'Kontrak & Rekanan Vendor'],
        6 => ['title' => 'Penerimaan Barang', 'desc' => 'Pemeriksaan & TTD BAST'],
        7 => ['title' => 'Penerbitan SPP', 'desc' => 'Memo Pembayaran Berjenjang'],
        8 => ['title' => 'Pencairan Selesai', 'desc' => 'Bukti Transfer Keuangan'],
    ];
@endphp

<div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs mb-6">
    <div class="flex items-center justify-between mb-4 border-b border-zinc-200 pb-3">
        <div>
            <h4 class="text-base font-bold text-zinc-900">Alur & Tracking Berkas Pengadaan</h4>
            <p class="text-xs text-zinc-500">Tahapan proses terintegrasi dari pengajuan hingga pencairan lunas</p>
        </div>
        <div class="text-xs font-bold px-3 py-1 bg-zinc-100 rounded-full border border-zinc-300 text-zinc-800">
            Tahap {{ $currentStep }} dari 8
        </div>
    </div>

    <!-- Stepper horizontal -->
    <div class="overflow-x-auto pb-2">
        <div class="grid grid-cols-8 gap-2 min-w-[760px]">
            @foreach ($steps as $idx => $step)
                @php
                    $isPassed = $idx < $currentStep;
                    $isCurrent = $idx === $currentStep;
                    $isUpcoming = $idx > $currentStep;
                @endphp
                <div class="text-center">
                    <div class="flex items-center justify-center mb-2">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm transition
                            {{ $isCurrent ? 'bg-zinc-900 text-white ring-4 ring-zinc-300' : '' }}
                            {{ $isPassed ? 'bg-zinc-700 text-white' : '' }}
                            {{ $isUpcoming ? 'bg-zinc-200 text-zinc-500 border border-zinc-300' : '' }}
                        ">
                            @if ($isPassed)
                                &#10003;
                            @else
                                {{ $idx }}
                            @endif
                        </div>
                    </div>
                    <div class="text-xs font-bold leading-tight {{ $isCurrent ? 'text-zinc-900 underline underline-offset-2' : ($isPassed ? 'text-zinc-700' : 'text-zinc-400') }}">
                        {{ $step['title'] }}
                    </div>
                    <div class="text-[11px] text-zinc-500 mt-0.5 leading-snug">
                        {{ $step['desc'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
