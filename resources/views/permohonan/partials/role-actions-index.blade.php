{{-- resources/views/permohonan/partials/role-actions-index.blade.php
     Banner aksi di bagian bawah panel detail (mode split).
     - Role yang punya form (Wadir 2, Staf Bidang 2, Perencanaan) -> tombol membuka mode fullscreen.
     - Role modul lain (PPBJ, PP, PPK, Keuangan, Perlengkapan)    -> tombol langsung ke halaman modulnya. --}}
@php
    $role = str_replace([' ', '-'], '_', strtolower(trim(auth()->user()->role ?? '')));
    $role = ['wadir2' => 'wadir_2', 'bidang_2' => 'staff_bidang_2', 'logistik' => 'perlengkapan', 'finance' => 'keuangan'][$role] ?? $role;

    // [tone, judul, deskripsi, label tombol, nama route (null = buka fullscreen)]
    $defs = [
        'wadir_2'        => ['blue',    'Aksi Persetujuan Wadir 2',          'Berikan keputusan persetujuan tingkat Wakil Direktur 2.', 'Proses Persetujuan',  null],
        'staff_bidang_2' => ['blue',    'Aksi Verifikasi Staf Bidang 2',     'Telaah berkas lalu teruskan ke antrean Wadir 2.',          'Proses Verifikasi',   null],
        'perencanaan'    => ['blue',    'Verifikasi MAK & Pagu Perencanaan', 'Tetapkan Mata Anggaran dan alokasi pagu usulan.',          'Proses Alokasi',      null],
        'ppbj'           => ['blue',    'Paket Pengadaan PPBJ',              'Bentuk paket, tetapkan routing HPS, dan terbitkan kontrak.', 'Buka Paket Pengadaan', 'paket.index'],
        'pp'             => ['blue',    'Kontrak SPK & Reviu',               'Reviu dan proses kontrak SPK / surat pesanan.',            'Buka Kontrak SPK',    'kontrak.index'],
        'ppk'            => ['blue',    'Persetujuan PPK',                   'Reviu dan setujui kontrak SPK sebagai Pejabat Pembuat Komitmen.', 'Buka Kontrak SPK', 'kontrak.index'],
        'perlengkapan'   => ['blue',    'Penerimaan Barang & BAST',          'Periksa fisik barang dan terbitkan BAST.',                 'Buka Penerimaan',     'penerimaan.index'],
        'keuangan'       => ['emerald', 'Tagihan & Pembayaran SP2D',         'Verifikasi tagihan dan unggah bukti pembayaran.',          'Buka Pembayaran',     'pembayaran.index'],
    ];

    $d      = $defs[$role] ?? null;
    $isLink = $d && $d[4] !== null;
    $url    = ($isLink && \Illuminate\Support\Facades\Route::has($d[4])) ? route($d[4]) : null;

    $tones = [
        'blue'    => ['box' => 'bg-blue-50/60 border-blue-100',       'icon' => 'bg-[#0969da] text-white',  'btn' => 'bg-[#0969da] hover:bg-blue-700'],
        'emerald' => ['box' => 'bg-emerald-50/60 border-emerald-100', 'icon' => 'bg-emerald-600 text-white', 'btn' => 'bg-emerald-600 hover:bg-emerald-700'],
    ];
    $t = $d ? $tones[$d[0]] : null;
@endphp

@if($d && (!$isLink || $url))
    <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-xl border {{ $t['box'] }}">
        <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 shrink-0 rounded-full {{ $t['icon'] }} flex items-center justify-center">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <span class="text-xs font-semibold text-[#213153] block">{{ $d[1] }}</span>
                <span class="text-[11px] text-slate-500 block">{{ $d[2] }}</span>
            </div>
        </div>

        @if($isLink)
            <a href="{{ $url }}"
               class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg text-white font-semibold text-xs shadow-sm transition-colors shrink-0 {{ $t['btn'] }}">
                {{ $d[3] }}
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        @else
            <button @click="viewMode = 'fullscreen'" type="button"
                    class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg text-white font-semibold text-xs shadow-sm transition-colors shrink-0 {{ $t['btn'] }}">
                {{ $d[3] }}
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        @endif
    </div>
@endif