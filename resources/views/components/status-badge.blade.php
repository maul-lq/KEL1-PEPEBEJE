@props([
    'status',
    'size' => 'md',
    'dot' => true,
    'variant' => 'soft',
    'text' => null,
])

@php
    $key = strtolower(trim((string) $status));

    $statusConfig = [
        'draft' => [
            'label' => 'Draft',
            'dot' => 'bg-slate-400',
            'soft' => 'bg-slate-100 text-slate-700 border-slate-200',
            'outline' => 'bg-transparent text-slate-700 border-slate-400',
            'solid' => 'bg-slate-600 text-white border-transparent',
        ],
        'diajukan' => [
            'label' => 'Diajukan',
            'dot' => 'bg-[#0969da] animate-pulse',
            'soft' => 'bg-blue-50 text-[#0969da] border-blue-200',
            'outline' => 'bg-transparent text-[#0969da] border-[#0969da]',
            'solid' => 'bg-[#0969da] text-white border-transparent',
        ],
        'menunggu_telaah' => [
            'label' => 'Menunggu Telaah',
            'dot' => 'bg-amber-500 animate-pulse',
            'soft' => 'bg-amber-50 text-amber-700 border-amber-200',
            'outline' => 'bg-transparent text-amber-700 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'menunggu_persetujuan' => [
            'label' => 'Menunggu Persetujuan',
            'dot' => 'bg-amber-500 animate-pulse',
            'soft' => 'bg-amber-50 text-amber-800 border-amber-300',
            'outline' => 'bg-transparent text-amber-800 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'pending' => [
            'label' => 'Menunggu Persetujuan',
            'dot' => 'bg-amber-500 animate-pulse',
            'soft' => 'bg-amber-50 text-amber-800 border-amber-300',
            'outline' => 'bg-transparent text-amber-800 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'menunggu_memo_cek_mak' => [
            'label' => 'Menunggu Memo Cek MAK',
            'dot' => 'bg-amber-500 animate-pulse',
            'soft' => 'bg-amber-50 text-amber-700 border-amber-200',
            'outline' => 'bg-transparent text-amber-700 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'menunggu_memo_ketersediaan_mak' => [
            'label' => 'Menunggu Ketersediaan MAK',
            'dot' => 'bg-amber-500 animate-pulse',
            'soft' => 'bg-amber-50 text-amber-700 border-amber-200',
            'outline' => 'bg-transparent text-amber-700 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'menunggu_persetujuan_wadir2_dan_ppk' => [
            'label' => 'Menunggu ACC Wadir 2 & PPK',
            'dot' => 'bg-indigo-500 animate-pulse',
            'soft' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-700 border-indigo-400',
            'solid' => 'bg-indigo-600 text-white border-transparent',
        ],
        'disetujui_wadir2_dan_ppk' => [
            'label' => 'Disetujui Wadir 2 & PPK',
            'dot' => 'bg-emerald-500',
            'soft' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'outline' => 'bg-transparent text-emerald-700 border-emerald-400',
            'solid' => 'bg-emerald-600 text-white border-transparent',
        ],
        'belanja_langsung' => [
            'label' => 'Belanja Langsung (<=50jt)',
            'dot' => 'bg-teal-500 animate-pulse',
            'soft' => 'bg-teal-50 text-teal-800 border-teal-200',
            'outline' => 'bg-transparent text-teal-800 border-teal-400',
            'solid' => 'bg-teal-600 text-white border-transparent',
        ],
        'belanja_langsung_selesai' => [
            'label' => 'Kwitansi Sah',
            'dot' => 'bg-emerald-600',
            'soft' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'outline' => 'bg-transparent text-emerald-800 border-emerald-400',
            'solid' => 'bg-emerald-600 text-white border-transparent',
        ],
        'menunggu_reviu_pp' => [
            'label' => 'Menunggu Reviu PP',
            'dot' => 'bg-sky-500 animate-pulse',
            'soft' => 'bg-sky-50 text-sky-800 border-sky-200',
            'outline' => 'bg-transparent text-sky-800 border-sky-400',
            'solid' => 'bg-sky-600 text-white border-transparent',
        ],
        'menunggu_reviu_ppk' => [
            'label' => 'Menunggu Reviu PPK',
            'dot' => 'bg-sky-600 animate-pulse',
            'soft' => 'bg-sky-50 text-sky-900 border-sky-200',
            'outline' => 'bg-transparent text-sky-900 border-sky-400',
            'solid' => 'bg-sky-700 text-white border-transparent',
        ],
        'surat_pesanan_terbit' => [
            'label' => 'Surat Pesanan Terbit',
            'dot' => 'bg-indigo-500',
            'soft' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-700 border-indigo-400',
            'solid' => 'bg-indigo-600 text-white border-transparent',
        ],
        'surat_kontrak_terbit' => [
            'label' => 'Surat Kontrak Terbit',
            'dot' => 'bg-indigo-600',
            'soft' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-800 border-indigo-400',
            'solid' => 'bg-indigo-700 text-white border-transparent',
        ],
        'disetujui' => [
            'label' => 'Disetujui',
            'dot' => 'bg-emerald-500',
            'soft' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'outline' => 'bg-transparent text-emerald-700 border-emerald-400',
            'solid' => 'bg-emerald-600 text-white border-transparent',
        ],
        'acc' => [
            'label' => 'Disetujui',
            'dot' => 'bg-emerald-500',
            'soft' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'outline' => 'bg-transparent text-emerald-700 border-emerald-400',
            'solid' => 'bg-emerald-600 text-white border-transparent',
        ],
        'ditolak' => [
            'label' => 'Ditolak',
            'dot' => 'bg-rose-500',
            'soft' => 'bg-rose-50 text-rose-700 border-rose-200',
            'outline' => 'bg-transparent text-rose-700 border-rose-400',
            'solid' => 'bg-rose-600 text-white border-transparent',
        ],
        'perlu_revisi' => [
            'label' => 'Perlu Revisi',
            'dot' => 'bg-amber-600 animate-pulse',
            'soft' => 'bg-amber-100 text-amber-900 border-amber-300',
            'outline' => 'bg-transparent text-amber-900 border-amber-500',
            'solid' => 'bg-amber-700 text-white border-transparent',
        ],
        'revisi' => [
            'label' => 'Perlu Revisi',
            'dot' => 'bg-amber-600 animate-pulse',
            'soft' => 'bg-amber-100 text-amber-900 border-amber-300',
            'outline' => 'bg-transparent text-amber-900 border-amber-500',
            'solid' => 'bg-amber-700 text-white border-transparent',
        ],
        'hold' => [
            'label' => 'Hold Anggaran',
            'dot' => 'bg-purple-500',
            'soft' => 'bg-purple-50 text-purple-700 border-purple-200',
            'outline' => 'bg-transparent text-purple-700 border-purple-400',
            'solid' => 'bg-purple-600 text-white border-transparent',
        ],
        'ditahan' => [
            'label' => 'Hold Anggaran',
            'dot' => 'bg-purple-500',
            'soft' => 'bg-purple-50 text-purple-700 border-purple-200',
            'outline' => 'bg-transparent text-purple-700 border-purple-400',
            'solid' => 'bg-purple-600 text-white border-transparent',
        ],
        'paket_terdaftar' => [
            'label' => 'Paket Terdaftar',
            'dot' => 'bg-[#0088E8]',
            'soft' => 'bg-blue-50 text-[#0088E8] border-blue-200',
            'outline' => 'bg-transparent text-[#0088E8] border-blue-400',
            'solid' => 'bg-[#0088E8] text-white border-transparent',
        ],
        'spk_terbit' => [
            'label' => 'SPK Terbit',
            'dot' => 'bg-indigo-500',
            'soft' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-700 border-indigo-400',
            'solid' => 'bg-indigo-600 text-white border-transparent',
        ],
        'kontrak' => [
            'label' => 'SPK Terbit',
            'dot' => 'bg-indigo-500',
            'soft' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-700 border-indigo-400',
            'solid' => 'bg-indigo-600 text-white border-transparent',
        ],
        'pengiriman' => [
            'label' => 'Pengiriman',
            'dot' => 'bg-sky-500 animate-pulse',
            'soft' => 'bg-sky-50 text-sky-700 border-sky-200',
            'outline' => 'bg-transparent text-sky-700 border-sky-400',
            'solid' => 'bg-sky-600 text-white border-transparent',
        ],
        'bast_terbit' => [
            'label' => 'BAST Terbit',
            'dot' => 'bg-[#087F79]',
            'soft' => 'bg-teal-50 text-[#087F79] border-teal-200',
            'outline' => 'bg-transparent text-[#087F79] border-teal-400',
            'solid' => 'bg-[#087F79] text-white border-transparent',
        ],
        'penerimaan_selesai' => [
            'label' => 'BAST Selesai',
            'dot' => 'bg-[#087F79]',
            'soft' => 'bg-teal-50 text-[#087F79] border-teal-200',
            'outline' => 'bg-transparent text-[#087F79] border-teal-400',
            'solid' => 'bg-[#087F79] text-white border-transparent',
        ],
        'pengajuan_pembayaran' => [
            'label' => 'Pengajuan Dokumen Bayar',
            'dot' => 'bg-blue-500 animate-pulse',
            'soft' => 'bg-blue-50 text-blue-800 border-blue-200',
            'outline' => 'bg-transparent text-blue-800 border-blue-400',
            'solid' => 'bg-blue-600 text-white border-transparent',
        ],
        'reviu_memo' => [
            'label' => 'Reviu Memo Wadir 2 & PPK',
            'dot' => 'bg-indigo-500 animate-pulse',
            'soft' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            'outline' => 'bg-transparent text-indigo-800 border-indigo-400',
            'solid' => 'bg-indigo-600 text-white border-transparent',
        ],
        'perintah_bayar' => [
            'label' => 'SPP Terbit (Siap Cair)',
            'dot' => 'bg-amber-500',
            'soft' => 'bg-amber-50 text-amber-800 border-amber-200',
            'outline' => 'bg-transparent text-amber-800 border-amber-400',
            'solid' => 'bg-amber-600 text-white border-transparent',
        ],
        'lunas' => [
            'label' => 'Lunas (SP2D)',
            'dot' => 'bg-emerald-600',
            'soft' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'outline' => 'bg-transparent text-emerald-800 border-emerald-400',
            'solid' => 'bg-emerald-600 text-white border-transparent',
        ],
        'spp_terbit' => [
            'label' => 'SPP Terbit',
            'dot' => 'bg-blue-600',
            'soft' => 'bg-blue-50 text-blue-800 border-blue-300',
            'outline' => 'bg-transparent text-blue-800 border-blue-400',
            'solid' => 'bg-blue-700 text-white border-transparent',
        ],
        'sp2d_lunas' => [
            'label' => 'Pembayaran',
            'dot' => 'bg-emerald-600',
            'soft' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'outline' => 'bg-transparent text-emerald-800 border-emerald-500',
            'solid' => 'bg-emerald-700 text-white border-transparent',
        ],
        'cair' => [
            'label' => 'Pembayaran',
            'dot' => 'bg-emerald-600',
            'soft' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'outline' => 'bg-transparent text-emerald-800 border-emerald-500',
            'solid' => 'bg-emerald-700 text-white border-transparent',
        ],
        'selesai' => [
            'label' => 'Selesai',
            'dot' => 'bg-emerald-600',
            'soft' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'outline' => 'bg-transparent text-emerald-800 border-emerald-500',
            'solid' => 'bg-emerald-700 text-white border-transparent',
        ],
    ];

    $cfg = $statusConfig[$key] ?? [
        'label' => ucfirst(str_replace('_', ' ', $key)),
        'dot' => 'bg-slate-400',
        'soft' => 'bg-slate-100 text-slate-700 border-slate-200',
        'outline' => 'bg-transparent text-slate-700 border-slate-300',
        'solid' => 'bg-slate-600 text-white border-transparent',
    ];

    $sizeClasses = [
        'xs' => 'text-[9px] px-1.5 py-0.5 gap-1',
        'sm' => 'text-[10px] px-2 py-0.5 gap-1.5',
        'md' => 'text-xs px-2.5 py-1 gap-1.5',
        'lg' => 'text-sm px-3.5 py-1.5 gap-2',
    ];

    $dotSizes = [
        'xs' => 'size-1',
        'sm' => 'size-1.5',
        'md' => 'size-2',
        'lg' => 'size-2.5',
    ];

    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    $dotSize = $dotSizes[$size] ?? $dotSizes['md'];

    $colorClass = $cfg[$variant] ?? $cfg['soft'];
    $labelText = $text ?? $cfg['label'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-bold border transition-colors {$sizeClass} {$colorClass}"]) }}>
    @if($dot)
        <span class="rounded-full shrink-0 {{ $dotSize }} {{ $cfg['dot'] }}"></span>
    @endif
    <span>{{ $labelText }}</span>
</span>
