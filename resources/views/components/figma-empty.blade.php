@props([
    'title' => 'Tidak ada antrean yang memerlukan tindakan Anda',
    'subtitle' => 'Seluruh item kerja saat ini sudah tertangani dengan baik.',
])

<div class="rounded-[5px] border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center my-4">
    <div class="size-10 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if($subtitle)
        <p class="text-xs text-slate-400 mt-0.5">{{ $subtitle }}</p>
    @endif
</div>
