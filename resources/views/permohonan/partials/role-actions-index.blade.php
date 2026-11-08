{{-- resources/views/permohonan/partials/role-actions-index.blade.php
     Banner aksi cepat di bagian bawah panel detail ringkas (mode split pada permohonan/index.blade.php).
     Berbasis Hak Akses RBAC & Permissions:
     - Role verifikator berform (Wadir 2, Staf Bidang 2, Perencanaan, PPK) -> tombol membuka mode fullscreen.
     - Role modul pengadaan lain (PPBJ, PP, Perlengkapan, Keuangan)       -> tombol langsung ke halaman modulnya. --}}
@php
    $u = auth()->user();

    $canBidang2     = $u && ($u->hasRole('staff_bidang_2') || $u->hasPermission('verifikasi.bidang_2_view'));
    $canWadir2      = $u && ($u->hasRole('wadir_2') || $u->hasAnyPermission(['verifikasi.wadir_2_decide', 'verifikasi.wadir_2_view']));
    $canPerencanaan = $u && ($u->hasRole('perencanaan') || $u->hasAnyPermission(['anggaran.mak_alokasi', 'anggaran.mak_view', 'permohonan.split_mak']));
    $canPpk         = $u && ($u->hasRole('ppk') || $u->hasPermission('reviu.ppk'));
    $canPpbj        = $u && ($u->hasRole('ppbj') || $u->hasAnyPermission(['paket.create', 'paket.view_all', 'spk.create']));
    $canMonitorPp   = $u && $u->canMonitorPp();
    $canPp          = $u && ($u->hasRole('pp') || $u->hasPermission('reviu.pp'));
    $canPerlengkapan= $u && ($u->hasRole('perlengkapan') || $u->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create']));
    $canKeuangan    = $u && ($u->hasRole('keuangan') || $u->hasAnyPermission(['pembayaran.pencairan_sp2d', 'pembayaran.spp_terbitkan']));

    // [tone, judul, deskripsi, label tombol, nama route (null = buka fullscreen)]
    $banner = null;
    if ($canBidang2) {
        $banner = ['blue', 'Verifikasi Staf Bidang 2', 'Telaah berkas administrasi dan teruskan ke pimpinan.', 'Proses Telaah', null];
    } elseif ($canWadir2) {
        $banner = ['blue', 'Aksi Disposisi Wadir II', 'Berikan keputusan mutlak persetujuan tingkat pimpinan.', 'Proses Persetujuan', null];
    } elseif ($canPerencanaan) {
        $banner = ['blue', 'Alokasi MAK Perencanaan', 'Tetapkan Mata Anggaran (MAK) dan alokasi pagu DIPA.', 'Alokasikan MAK', null];
    } elseif ($canPpk) {
        $banner = ['blue', 'Persetujuan Awal PPK', 'Tinjau usulan pengadaan atas penetapan MAK Perencanaan.', 'Proses Persetujuan', null];
    } elseif ($canPpbj) {
        $banner = ['blue', 'Paket Pengadaan PPBJ', 'Bentuk paket pengadaan, tetapkan routing HPS, dan terbitkan SPK.', 'Buka Paket Pengadaan', 'paket.index'];
    } elseif ($canMonitorPp) {
        $banner = ['purple', 'Monitor Aktivitas PP', 'Pantau seluruh aktivitas verifikasi teknis dan negosiasi PP.', 'Buka Monitor PP', 'kontrak.index'];
    } elseif ($canPp) {
        $banner = ['amber', 'Reviu & Negosiasi PP', 'Reviu spesifikasi teknis dan lakukan negosiasi harga rekanan.', 'Buka Reviu PP', 'kontrak.index'];
    } elseif ($canPerlengkapan) {
        $banner = ['blue', 'Penerimaan Barang & BAST', 'Periksa fisik barang masuk dan terbitkan BAST bertoken resmi.', 'Buka Penerimaan', 'penerimaan.index'];
    } elseif ($canKeuangan) {
        $banner = ['emerald', 'Tagihan & Pembayaran SP2D', 'Verifikasi berkas tagihan dan unggah bukti pencairan SP2D.', 'Buka Pembayaran', 'pembayaran.index'];
    }

    $isLink = $banner && $banner[4] !== null;
    $url    = ($isLink && \Illuminate\Support\Facades\Route::has($banner[4])) ? route($banner[4]) : null;

    $tones = [
        'blue'    => ['box' => 'bg-blue-50/70 border-blue-200',       'icon' => 'bg-[#0969da] text-white',  'btn' => 'bg-[#0969da] hover:bg-blue-700'],
        'purple'  => ['box' => 'bg-purple-50/70 border-purple-200',   'icon' => 'bg-purple-700 text-white', 'btn' => 'bg-purple-700 hover:bg-purple-800'],
        'amber'   => ['box' => 'bg-amber-50/70 border-amber-200',     'icon' => 'bg-amber-600 text-white',  'btn' => 'bg-amber-600 hover:bg-amber-700'],
        'emerald' => ['box' => 'bg-emerald-50/70 border-emerald-200', 'icon' => 'bg-emerald-600 text-white', 'btn' => 'bg-emerald-600 hover:bg-emerald-700'],
    ];
    $t = $banner ? ($tones[$banner[0]] ?? $tones['blue']) : null;
@endphp

@if($banner && (!$isLink || $url))
    <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 rounded-xl border {{ $t['box'] }} shadow-xs">
        <div class="flex items-center gap-3 min-w-0">
            <div class="size-9 shrink-0 rounded-full {{ $t['icon'] }} flex items-center justify-center">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <span class="text-xs font-bold text-[#213153] block truncate">{{ $banner[1] }}</span>
                <span class="text-[11px] text-slate-500 block truncate">{{ $banner[2] }}</span>
            </div>
        </div>

        @if($isLink)
            <a href="{{ $url }}"
               class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg text-white font-bold text-xs shadow-xs transition-colors shrink-0 {{ $t['btn'] }}">
                <span>{{ $banner[3] }}</span>
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        @else
            <button @click="viewMode = 'fullscreen'" type="button"
                    class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg text-white font-bold text-xs shadow-xs transition-colors shrink-0 {{ $t['btn'] }}">
                <span>{{ $banner[3] }}</span>
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        @endif
    </div>
@endif
