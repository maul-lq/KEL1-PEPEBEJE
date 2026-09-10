@props(['status', 'label' => null])

@php
    $text = $label ?? ucfirst(str_replace('_', ' ', $status));
    $classes = match($status) {
        'selesai' => 'bg-zinc-900 text-white border-zinc-900',
        'diajukan' => 'bg-zinc-100 text-zinc-900 border-zinc-400 font-bold',
        'ditolak_wadir2', 'dibatalkan' => 'bg-zinc-200 text-zinc-900 line-through border-zinc-400',
        'barang_diterima' => 'bg-zinc-800 text-white border-zinc-700',
        'barang_pending', 'revisi_ppk' => 'bg-zinc-100 text-zinc-900 border-zinc-900 border-dashed',
        default => 'bg-zinc-200 text-zinc-800 border-zinc-300',
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border {{ $classes }}">
    {{ $text }}
</span>
