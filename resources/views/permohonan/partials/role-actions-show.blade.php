{{-- resources/views/permohonan/partials/role-actions-show.blade.php

     Dipakai di 2 tempat:
     1) permohonan/index.blade.php  (di dalam Alpine, memakai selectedItem)  -> tanpa variabel tambahan
     2) permohonan/show.blade.php   (halaman detail biasa)                   -> kirim: nomorSurat, pagu, statusLabel

     Semua form memakai route verifikasi yang sudah ada, bukan lagi POST ke permohonan.index. --}}
@php
    $role = str_replace([' ', '-'], '_', strtolower(trim(auth()->user()->role ?? '')));
    $role = ['wadir2' => 'wadir_2', 'bidang_2' => 'staff_bidang_2', 'logistik' => 'perlengkapan', 'finance' => 'keuangan'][$role] ?? $role;

    // Mode Alpine (halaman tracking) atau mode server (halaman detail)
    $inAlpine   = !isset($nomorSurat);
    $nomorField = $inAlpine
        ? ':value="selectedItem ? selectedItem.nomor : \'\'"'
        : 'value="' . e($nomorSurat) . '"';
    $paguField  = $inAlpine
        ? ':value="selectedItem ? selectedItem.pagu : \'\'"'
        : 'value="' . e($pagu ?? '') . '"';

    // Role yang bekerja di modul lain -> arahkan ke halamannya
    // [judul, deskripsi, route, label tombol, tone]
    $modul = [
        'ppbj'         => ['Paket & Kontrak PPBJ',       'Bentuk paket pengadaan, tetapkan routing HPS, dan terbitkan SPK.',          'paket.index',      'Buka Paket Pengadaan', 'blue'],
        'pp'           => ['Kontrak SPK & Reviu',        'Reviu dan proses kontrak SPK / surat pesanan.',                             'kontrak.index',    'Buka Kontrak SPK',     'blue'],
        'ppk'          => ['Persetujuan PPK',            'Reviu dan setujui kontrak SPK sebagai Pejabat Pembuat Komitmen.',           'kontrak.index',    'Buka Kontrak SPK',     'blue'],
        'perlengkapan' => ['Penerimaan Barang & BAST',   'Periksa fisik barang, unggah foto bukti, dan terbitkan BAST.',              'penerimaan.index', 'Buka Penerimaan',      'blue'],
        'keuangan'     => ['Tagihan & Pembayaran SP2D',  'Verifikasi tagihan dan unggah bukti pembayaran / kuitansi.',                'pembayaran.index', 'Buka Pembayaran',      'emerald'],
    ];
    $mod    = $modul[$role] ?? null;
    $modUrl = ($mod && \Illuminate\Support\Facades\Route::has($mod[2])) ? route($mod[2]) : null;

    // Role berform: [route POST, route halaman (fallback), judul, deskripsi]
    // Jika route POST belum didaftarkan, form TIDAK dirender (mencegah RouteNotFoundException)
    // dan diganti tautan ke halaman verifikasinya.
    $formRoutes = [
        'staff_bidang_2' => ['verifikasi.bidang-2.teruskan',     'verifikasi.bidang-2',  'Verifikasi Staf Bidang 2',      'Telaah berkas lalu teruskan ke antrean Wadir 2.'],
        'wadir_2'        => ['verifikasi.wadir-2.keputusan',     'verifikasi.wadir-2',   'Persetujuan Wadir 2',           'Berikan keputusan persetujuan tingkat Wakil Direktur 2.'],
        'perencanaan'    => ['verifikasi.perencanaan.alokasi',   'verifikasi.perencanaan', 'Verifikasi MAK & Pagu Perencanaan', 'Tetapkan Mata Anggaran dan alokasi pagu usulan.'],
    ];
    $fr      = $formRoutes[$role] ?? null;
    $formUrl = ($fr && \Illuminate\Support\Facades\Route::has($fr[0])) ? route($fr[0]) : null;
    $pageUrl = ($fr && \Illuminate\Support\Facades\Route::has($fr[1])) ? route($fr[1]) : null;

    $field = 'w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-700 placeholder:text-slate-400 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 outline-none transition';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $btn   = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-lg text-white font-semibold text-xs shadow-sm transition-colors';
@endphp

{{-- ============ 1. STAF BIDANG 2 ============ --}}
@if($role === 'staff_bidang_2' && $formUrl)
    <form action="{{ $formUrl }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Form Verifikasi Staf Bidang 2</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 whitespace-nowrap">Telaah Berkas</span>
        </div>

        <div>
            <label class="{{ $label }}">Catatan Telaah Staf Bidang II <span class="text-rose-500">*</span></label>
            <textarea name="catatan_staff" rows="3" required placeholder="Tulis catatan kelengkapan berkas KAK & spesifikasi..." class="{{ $field }}"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                Teruskan ke Wadir 2
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

{{-- ============ 2. WADIR 2 ============ --}}
@elseif($role === 'wadir_2' && $formUrl)
    <form action="{{ $formUrl }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Form Persetujuan Wadir 2</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-[#0969da] whitespace-nowrap">Keputusan Eksekutif</span>
        </div>

        <div>
            <label class="{{ $label }}">Catatan / Alasan Keputusan</label>
            <textarea name="catatan_alasan" rows="3" placeholder="Isi alasan bila menolak, atau instruksi revisi..." class="{{ $field }}"></textarea>
            <p class="mt-1 text-[11px] text-slate-400">Disarankan diisi jika pengajuan ditolak / dikembalikan.</p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" name="keputusan" value="ditolak" class="{{ $btn }} bg-amber-500 hover:bg-amber-600">
                Tolak / Minta Revisi
            </button>
            <button type="submit" name="keputusan" value="disetujui" class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Setujui Pengajuan
            </button>
        </div>
    </form>

{{-- ============ 3. PERENCANAAN ============ --}}
@elseif($role === 'perencanaan' && $formUrl)
    <form action="{{ $formUrl }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Alokasi MAK & Anggaran</h3>
            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700 whitespace-nowrap">Perencanaan</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">Kode MAK (Mata Anggaran) <span class="text-rose-500">*</span></label>
                <input type="text" name="kode_mak" required placeholder="Contoh: 521111.001" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Nominal Alokasi (Rp) <span class="text-rose-500">*</span></label>
                <input type="number" name="nominal_alokasi" required step="0.01" {!! $paguField !!} class="{{ $field }}">
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Catatan Perencanaan</label>
            <textarea name="catatan_anggaran" rows="2" placeholder="Catatan ketersediaan pagu..." class="{{ $field }}"></textarea>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" name="keputusan_anggaran" value="tahan" class="{{ $btn }} bg-amber-500 hover:bg-amber-600">
                Tahan (Anggaran Belum Siap)
            </button>
            <button type="submit" name="keputusan_anggaran" value="setuju" class="{{ $btn }} bg-indigo-600 hover:bg-indigo-700">
                Setujui & Kunci MAK
            </button>
        </div>
    </form>

{{-- ============ 3b. FALLBACK: role berform, tetapi route POST belum terdaftar ============ --}}
@elseif($fr && $pageUrl)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">{{ $fr[2] }}</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-[#0969da] whitespace-nowrap">Modul Terkait</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">{{ $fr[3] }}</p>

        <div class="flex justify-end">
            <a href="{{ $pageUrl }}" class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                Buka Halaman Verifikasi
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 4. ROLE MODUL LAIN (PPBJ, PP, PPK, KEUANGAN, PERLENGKAPAN) ============ --}}
@elseif($mod && $modUrl)
    @php $isEm = $mod[4] === 'emerald'; @endphp
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">{{ $mod[0] }}</h3>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ $isEm ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-[#0969da]' }}">Modul Terkait</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">{{ $mod[1] }}</p>

        <div class="flex justify-end">
            <a href="{{ $modUrl }}" class="{{ $btn }} {{ $isEm ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#0969da] hover:bg-blue-700' }}">
                {{ $mod[3] }}
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 5. USER PEMOHON / ROLE LAIN: INFO STATUS ============ --}}
@else
    <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-blue-50/70 border border-blue-100">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="size-8 rounded-full bg-[#0969da] text-white flex items-center justify-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h4 class="font-semibold text-xs text-[#213153]">Status Pengajuan Saat Ini</h4>
                <p class="text-[11px] text-slate-500">Berkas sedang diproses oleh verifikator terkait.</p>
            </div>
        </div>

        @if($inAlpine)
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#0969da] border border-blue-200 shadow-sm whitespace-nowrap"
                  x-text="selectedItem ? statusMeta(selectedItem.statusRaw).label : 'Diproses'"></span>
        @else
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#0969da] border border-blue-200 shadow-sm whitespace-nowrap">
                {{ $statusLabel ?? 'Diproses' }}
            </span>
        @endif
    </div>
@endif