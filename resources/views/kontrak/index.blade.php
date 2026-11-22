{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View Blade ini buat untuk halaman terpadu Reviu Dokumen dan Kontrak SPK yang
              menggabungkan tab reviu Pejabat Pengadaan/PPK, tab penerbitan kontrak SPK PPBJ,
              serta daftar kontrak/eksekusi belanja langsung sesuai izin RBAC aktif.
--}}
@extends('layouts.app')

@section('title', 'Reviu Dokumen & Kontrak Pengadaan - SIM-PBJ PNJ')

{{--
  Komentar Deskriptif: Seksi Konten Halaman Reviu Dokumen & Kontrak SPK (`kontrak.index`).

  Tujuan: menurunkan daftar tab yang diperbolehkan serta memetakan paket siap SPK menjadi
          JSON `kontrakConfig` untuk komponen Alpine `kontrakManager` agar prefill HPS dan vendor
          berlangsung instan tanpa script inline di Blade.
  Parameter (Data dari KontrakSPKController):
    - `$paketReviu`, `$paketReviuAksi`, `$paketSiapSpk`, `$paketList`, `$kontrakList`.
  Nilai kembali: Halaman HTML terpadu modul reviu teknis PP/PPK dan kontrak perikatan.
  Contoh pemanggilan: `return view('kontrak.index', compact(...));`
--}}
@section('content')
@php
    $authUser = Auth::user();
    $canReviuPp = $authUser?->hasPermission('reviu.pp') ?? false;
    $canReviuPpk = $authUser?->hasPermission('reviu.ppk') ?? false;
    $canMonitorPp = $authUser?->canMonitorPp() ?? false;
    $canCreateSpk = $authUser?->hasPermission('spk.create') ?? false;
    $canUnduhPdf = $authUser?->hasPermission('dokumen.unduh_pdf') ?? false;
    $hasViewAllPaket = $authUser?->hasPermission('paket.view_all') ?? false;

    $canTabReviu = $canReviuPp || $canReviuPpk || $canMonitorPp;
    $canTabSpk = $canCreateSpk;
    $canTabDaftar = true;

    // prioritaskan tab reviu saat PP/PPK membuka halaman, atau tab SPK bagi PPBJ tanpa hak reviu.
    $defaultTab = 'daftar';
    if ($canReviuPp || $canReviuPpk) {
        $defaultTab = 'reviu';
    } elseif ($canTabSpk) {
        $defaultTab = 'spk';
    } elseif ($canTabReviu) {
        $defaultTab = 'reviu';
    }

    $allowedTabs = array_values(array_filter([
        $canTabReviu ? 'reviu' : null,
        $canTabSpk ? 'spk' : null,
        $canTabDaftar ? 'daftar' : null,
    ]));

    $requestedTab = request('tab');
    if (! $requestedTab) {
        if ((old('kode_paket') || old('nomor_kontrak_spk')) && $canTabSpk) {
            $requestedTab = 'spk';
        } elseif (old('nomor_paket') && ($canReviuPp || $canReviuPpk)) {
            $requestedTab = 'reviu';
        }
    }
    $initialTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : $defaultTab;

    $paketReviuAksiList = $paketReviuAksi ?? $paketReviu;
    $paketSiapSpkList = $paketSiapSpk ?? $paketList->filter(
        fn ($pkt) => (float) ($pkt->nilai_hps ?: $pkt->alokasi_pagu_paket) > 50000000
            && ! $pkt->kontrakSpk
            && in_array($pkt->status_paket, [
                \App\Models\PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
                \App\Models\PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP,
                'reviu_dokumen_disetujui',
                'disetujui',
            ], true)
    );
    $paketSiapSpkMap = $paketSiapSpkList->mapWithKeys(fn ($pkt) => [
        $pkt->nomor_paket => [
            'hps' => (float) ($pkt->nilai_hps ?: $pkt->alokasi_pagu_paket),
            'nilai_default' => (float) ($pkt->nominal_harga_akhir ?: ($pkt->nilai_hps ?: $pkt->alokasi_pagu_paket)),
            'vendor' => $pkt->vendor?->nama_vendor ?? '',
        ],
    ])->all();

    $kontrakConfig = [
        'activeTab' => $initialTab,
        'selectedStatusReviu' => ($canReviuPpk && ! $canReviuPp) ? 'disetujui' : 'terverifikasi_pp',
        'spkSelectedPaket' => old('kode_paket', ''),
        'spkPaketMap' => (object) $paketSiapSpkMap,
        'spkNilaiKontrak' => old('nilai_kontrak', ''),
        'spkNamaVendor' => old('nama_vendor', ''),
    ];
@endphp
<div
    x-data="kontrakManager"
    data-config="{{ json_encode($kontrakConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
    class="space-y-6"
>

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="5" status="kontrak" />

    {{-- 2. Header & KPI Metrik --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Reviu Dokumen &amp; Kontrak Pengadaan</h1>
            </div>

            {{-- Role Badge Indicator --}}
            <div class="shrink-0">
                @if($canReviuPpk && ! $canCreateSpk && ! $canReviuPp)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-purple-600"></span>
                        {{ $authUser?->roleEntity?->label ?? 'PPK' }}: {{ $authUser?->nama }}{{ $hasViewAllPaket ? '' : ' (Tugas Saya)' }}
                    </span>
                @elseif($canReviuPp && ! $canCreateSpk)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-amber-600"></span>
                        {{ $authUser?->roleEntity?->label ?? 'Pejabat Pengadaan' }}: {{ $authUser?->nama }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-[#0969da] border border-blue-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-[#0969da]"></span>
                        {{ $authUser?->roleEntity?->label ?? 'PENGGUNA' }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. Top 4-Card KPI Stat Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Kontrak Diterbitkan"
            :value="$stats['total_spk'] ?? $kontrakList->total()"
            icon="document"
            color="navy"
            subtitle="Total kontrak sah"
        />

        <x-stat-card
            title="Antrean Reviu Dokumen"
            :value="$stats['antrean_reviu'] ?? $paketReviu->count()"
            icon="bell"
            color="amber"
            badge="Tindak Lanjut"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Menunggu reviu Pejabat Pengadaan &amp; PPK"
        />

        <x-stat-card
            title="Paket Siap Kontrak"
            :value="$stats['siap_spk'] ?? 0"
            icon="check"
            color="blue"
            badge="Siap Kontrak"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Reviu teknis disetujui"
        />

        <x-stat-card
            title="Akumulasi Nilai Kontrak"
            :value="'Rp ' . number_format($stats['total_nilai'] ?? 0, 0, ',', '.')"
            icon="wallet"
            color="emerald"
            subtitle="Total nilai kontrak aktif"
        />
    </div>

    {{-- Alert Error Validasi --}}
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
            <p class="font-bold mb-1">Periksa kembali isian formulir:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 4. Interactive Tab Bar --}}
    <div class="rounded-2xl bg-white p-2 shadow-sm border border-slate-200/80 flex flex-wrap gap-2">
        @if($canTabReviu)
            <button
                type="button"
                @click="activeTab = 'reviu'"
                :class="activeTab === 'reviu' ? 'bg-[#213153] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>1. Review Dokumen Pengadaan {{ $canMonitorPp && ! $canReviuPp && ! $canReviuPpk ? '(Monitoring PP & PPK)' : '(PP & PPK)' }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="activeTab === 'reviu' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                    {{ $paketReviu->count() }}
                </span>
            </button>
        @endif

        @if($canTabSpk)
            <button
                type="button"
                @click="activeTab = 'spk'"
                :class="activeTab === 'spk' ? 'bg-[#0969da] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>2. Terbitkan Kontrak Kerja</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="activeTab === 'spk' ? 'bg-white/20 text-white' : 'bg-blue-100 text-[#0969da]'">
                    {{ $stats['siap_spk'] ?? 0 }}
                </span>
            </button>
        @endif

        @if($canTabDaftar)
            <button
                type="button"
                @click="activeTab = 'daftar'"
                :class="activeTab === 'daftar' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
            >
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span>3. Daftar Kontrak Diterbitkan</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="activeTab === 'daftar' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                    {{ $kontrakList->total() }}
                </span>
            </button>
        @endif
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 1: PENELAAHAN & NEGOSIASI HARGA (PP & PPK) -- FEATURE 26          --}}
    {{-- ===================================================================== --}}
    @if($canTabReviu)
    <div x-show="activeTab === 'reviu'" class="space-y-6">

        @if($canMonitorPp && ! $canReviuPp && ! $canReviuPpk)
            {{-- Panel Monitoring Aktivitas Pejabat Pengadaan (PP) -- Fitur 'lihat_pp' / 'pp.monitoring_aktivitas' --}}
            <div class="rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50/60 p-6 shadow-sm border border-blue-200/80">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-blue-200/60">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-[#0969da] text-white flex items-center justify-center shrink-0 shadow-sm">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-[#213153]">Pantau Pejabat Pengadaan</h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-3 py-1.5 rounded-xl bg-white text-xs font-bold text-slate-700 border border-slate-200 shadow-xs">
                            Paket 50&ndash;200 Juta: <strong class="text-[#0969da] font-mono">{{ $paketReviu->whereIn('jalur_routing', ['pp_50jt_sd_200jt', 'pp_50_sd_200jt'])->count() }}</strong>
                        </span>
                        <a href="{{ route('monitor-pp.index') }}" class="px-3 py-1.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1">
                            <span>Monitor Lengkap</span> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @if($canReviuPp || $canReviuPpk)
            {{-- Form Penelaahan Dokumen & Negosiasi --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
                <div class="border-b border-slate-100 pb-4 mb-5">
                    <h2 class="text-base font-bold text-[#213153]">Form Reviu Dokumen</h2>
                </div>

                @if($paketReviuAksiList->isEmpty())
                    <div class="text-center py-10 px-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        <p class="text-xs text-slate-500">Tidak ada paket yang menunggu reviu saat ini.</p>
                    </div>
                @else
                    <form action="{{ route('kontrak.reviu') }}" method="POST" class="space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {{-- Pilih Paket --}}
                            <div class="md:col-span-2">
                                <label for="nomor_paket_reviu" class="block text-xs font-bold text-slate-700 mb-1">
                                    Pilih Paket Pengadaan Antrean Telaah <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="nomor_paket_reviu"
                                    name="nomor_paket"
                                    required
                                    @change="
                                        let sel = $event.target.options[$event.target.selectedIndex];
                                        hpsVal = sel.getAttribute('data-hps') || 0;
                                        selectedPaket = {
                                            nomor: sel.value,
                                            nama: sel.getAttribute('data-nama') || '',
                                            unit: sel.getAttribute('data-unit') || '',
                                            hps: sel.getAttribute('data-hps') || 0,
                                            status: sel.getAttribute('data-status') || '',
                                            surat: sel.getAttribute('data-surat') || '',
                                            fileDokumen: sel.getAttribute('data-file-dokumen') || '',
                                            fileKak: sel.getAttribute('data-file-kak') || '',
                                            fileSpek: sel.getAttribute('data-file-spek') || ''
                                        };
                                        if (['terdaftar', 'reviu_dokumen'].includes(selectedPaket.status) && {{ $canReviuPp ? 'true' : 'false' }}) {
                                            selectedStatusReviu = 'terverifikasi_pp';
                                        } else if (selectedPaket.status === 'menunggu_approval_ppk' && {{ $canReviuPpk ? 'true' : 'false' }}) {
                                            selectedStatusReviu = 'disetujui';
                                        }
                                    "
                                    class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                                >
                                    <option value="">-- Pilih Paket Pengadaan --</option>
                                    @foreach($paketReviuAksiList as $pktR)
                                        <option
                                            value="{{ $pktR->nomor_paket }}"
                                            data-hps="{{ $pktR->nilai_hps }}"
                                            data-nama="{{ $pktR->nama_paket }}"
                                            data-unit="{{ $pktR->permohonan->unit->nama_unit ?? ($pktR->permohonan->kode_unit ?? '-') }}"
                                            data-status="{{ $pktR->status_paket }}"
                                            data-surat="{{ $pktR->nomor_surat ?? '-' }}"
                                            data-file-dokumen="{{ $pktR->file_dokumen_pengadaan ? asset('storage/' . $pktR->file_dokumen_pengadaan) : '' }}"
                                            data-file-kak="{{ $pktR->permohonan && $pktR->permohonan->file_kak ? asset('storage/' . $pktR->permohonan->file_kak) : '' }}"
                                            data-file-spek="{{ $pktR->permohonan && $pktR->permohonan->file_spesifikasi_teknis ? asset('storage/' . $pktR->permohonan->file_spesifikasi_teknis) : '' }}"
                                        >
                                            [{{ $pktR->nomor_paket }}] {{ $pktR->nama_paket }} &bull; Perkiraan: Rp {{ number_format($pktR->nilai_hps, 0, ',', '.') }} &bull; {{ strtoupper(str_replace('_', ' ', $pktR->jalur_routing)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Keputusan Reviu Sesuai Permission --}}
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                                <span class="block text-xs font-bold text-slate-800">Keputusan Hasil Reviu <span class="text-rose-500">*</span></span>
                                @if($canReviuPp)
                                    <label
                                        @if($canReviuPpk) x-show="!selectedPaket.status || ['terdaftar', 'reviu_dokumen'].includes(selectedPaket.status)" @endif
                                        class="flex items-center text-xs text-slate-800 cursor-pointer gap-2"
                                    >
                                        <input type="radio" name="status_reviu" value="terverifikasi_pp" x-model="selectedStatusReviu" class="text-[#0969da] focus:ring-[#0969da]">
                                        <span><strong>Verifikasi Berhasil</strong> &mdash; Spesifikasi &amp; perkiraan harga sesuai. Teruskan ke PPK.</span>
                                    </label>
                                @endif
                                @if($canReviuPpk)
                                    <label
                                        @if($canReviuPp) x-show="selectedPaket.status === 'menunggu_approval_ppk'" @endif
                                        class="flex items-center text-xs text-slate-800 cursor-pointer gap-2"
                                    >
                                        <input type="radio" name="status_reviu" value="disetujui" x-model="selectedStatusReviu" class="text-[#087F79] focus:ring-[#087F79]">
                                        <span><strong>Disetujui</strong> &mdash; Dokumen disetujui, siap penerbitan kontrak oleh Unit Pengadaan.</span>
                                    </label>
                                @endif
                                <label class="flex items-center text-xs text-slate-800 cursor-pointer gap-2">
                                    <input type="radio" name="status_reviu" value="perlu_perbaikan" x-model="selectedStatusReviu" class="text-rose-600 focus:ring-rose-500">
                                    <span><strong>Perlu Perbaikan</strong> &mdash; Kembalikan berkas ke Unit Pengadaan.</span>
                                </label>
                            </div>

                            {{-- Info Box Spesifikasi Teknis & Perkiraan Harga --}}
                            @if($canReviuPp)
                                <div
                                    @if($canReviuPpk) x-show="!selectedPaket.status || ['terdaftar', 'reviu_dokumen'].includes(selectedPaket.status)" @endif
                                    class="p-4 rounded-xl bg-amber-50/60 border border-amber-200/80 space-y-3"
                                >
                                    <div class="flex items-center justify-between pb-2 border-b border-amber-200/60">
                                        <span class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                            <svg class="size-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Ringkasan Dokumen Pengadaan
                                        </span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200">Jalur 50 - 200 Juta</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div>
                                            <span class="text-slate-500 block text-[11px]">Nomor Surat Usulan:</span>
                                            <span class="font-mono font-semibold text-slate-800" x-text="selectedPaket.surat || '-'">-</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500 block text-[11px]">Unit Pengusul:</span>
                                            <span class="font-semibold text-slate-800" x-text="selectedPaket.unit || '-'">-</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500 block text-[11px]">Perkiraan Harga:</span>
                                            <span class="font-mono font-bold text-emerald-700" x-text="selectedPaket.hps ? formatRupiah(selectedPaket.hps) : 'Rp 0'">Rp 0</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-500 block text-[11px]">Tugas Pejabat Pengadaan:</span>
                                            <span class="text-slate-700 font-medium">Reviu spesifikasi teknis &amp; kewajaran harga</span>
                                        </div>
                                    </div>

                                    {{-- Dokumen Penunjang Pengadaan --}}
                                    <div class="pt-2 border-t border-amber-200/60 flex flex-wrap gap-2 text-xs">
                                        <template x-if="selectedPaket.fileDokumen">
                                            <a :href="selectedPaket.fileDokumen" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-amber-900 font-medium hover:bg-amber-100/50 shadow-xs">
                                                <svg class="size-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Dokumen Unit Pengadaan</span>
                                            </a>
                                        </template>
                                        <template x-if="selectedPaket.fileKak">
                                            <a :href="selectedPaket.fileKak" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-amber-900 font-medium hover:bg-amber-100/50 shadow-xs">
                                                <svg class="size-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Kerangka Acuan Kerja</span>
                                            </a>
                                        </template>
                                        <template x-if="selectedPaket.fileSpek">
                                            <a :href="selectedPaket.fileSpek" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-amber-900 font-medium hover:bg-amber-100/50 shadow-xs">
                                                <svg class="size-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                <span>Spesifikasi Teknis</span>
                                            </a>
                                        </template>
                                        <template x-if="!selectedPaket.fileDokumen && !selectedPaket.fileKak && !selectedPaket.fileSpek">
                                            <span class="text-amber-800 text-[11px] italic">Pilih paket untuk melihat tautan berkas telaah.</span>
                                        </template>
                                    </div>
                                </div>
                            @endif
                            @if($canReviuPpk)
                                <div
                                    @if($canReviuPp) x-show="selectedPaket.status === 'menunggu_approval_ppk'" @endif
                                    class="p-4 rounded-xl bg-purple-50/60 border border-purple-200/80 space-y-1"
                                >
                                    <h4 class="text-xs font-bold text-purple-900">Persetujuan PPK</h4>
                                    <p class="text-[11px] text-purple-800 leading-relaxed">
                                        Persetujuan PPK meneruskan paket ke tahap kontrak atau belanja.
                                    </p>
                                </div>
                            @endif

                            {{-- Catatan Perbaikan --}}
                            <div class="md:col-span-2">
                                <label for="catatan_perbaikan" class="block text-xs font-bold text-slate-700 mb-1">
                                    Catatan Rekomendasi &amp; Perbaikan
                                    <span class="text-slate-400 font-normal">(Wajib jika memilih 'Perlu Perbaikan')</span>
                                </label>
                                <textarea
                                    id="catatan_perbaikan"
                                    name="catatan_perbaikan"
                                    rows="3"
                                    placeholder="Tuliskan hasil reviu atau poin yang perlu diperbaiki Unit Pengadaan..."
                                    class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                                ></textarea>
                            </div>

                        </div>

                        <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                            <button
                                type="submit"
                                class="px-6 py-2.5 rounded-xl bg-[#213153] text-xs font-bold text-white hover:bg-slate-800 shadow-sm transition"
                            >
                                @if($canReviuPp && $canReviuPpk)
                                    <span x-text="selectedPaket.status === 'menunggu_approval_ppk' ? 'Kirim Persetujuan PPK' : 'Kirim Keputusan Pejabat Pengadaan'">Kirim Keputusan</span>
                                @elseif($canReviuPp)
                                    Kirim Keputusan Pejabat Pengadaan
                                @elseif($canReviuPpk)
                                    Kirim Persetujuan PPK
                                @endif
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        {{-- Tabel Antrean Paket Reviu --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Antrean Reviu Dokumen</h3>
                </div>
                <span class="text-xs font-bold text-slate-400 font-mono">{{ $paketReviu->count() }} Paket</span>
            </div>

            @if($paketReviu->isEmpty())
                <div class="text-center py-8 text-xs text-slate-400">Tidak ada paket pengadaan dalam antrean reviu.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-5 py-3">Paket Pengadaan</th>
                                <th class="px-5 py-3">Perkiraan Harga</th>
                                <th class="px-5 py-3">Jalur</th>
                                <th class="px-5 py-3">Pejabat Pengadaan</th>
                                <th class="px-5 py-3">Catatan Reviu</th>
                                <th class="px-5 py-3">PPK Penanggung Jawab</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($paketReviu as $pr)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('paket.show', $pr->nomor_paket) }}" class="font-mono font-bold text-[#0969da] hover:underline block">
                                            {{ $pr->nomor_paket }}
                                        </a>
                                        <span class="text-xs font-semibold text-slate-800 line-clamp-1 mt-0.5">{{ $pr->nama_paket }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono font-bold text-emerald-600">
                                        Rp {{ number_format($pr->nilai_hps, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                            {{ strtoupper(str_replace('_', ' ', $pr->jalur_routing)) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-700">
                                        <div class="font-semibold text-slate-800">{{ $pr->ppUser->nama ?? (in_array($pr->jalur_routing, ['pp_50jt_sd_200jt', 'pp_50_sd_200jt']) ? 'PP Belum Ditugaskan' : '-') }}</div>
                                        @if(in_array($pr->jalur_routing, ['pp_50jt_sd_200jt', 'pp_50_sd_200jt']) && $pr->ppUser)
                                            <div class="text-[10px] text-slate-400 font-mono">NIP: {{ $pr->ppUser->nip ?? '-' }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @php
                                            $reviuPpk = $pr->reviuDokumen->where('role_reviewer', 'ppk')->sortByDesc('putaran_ke')->first();
                                        @endphp
                                        @if($pr->catatan_verifikasi_pp)
                                            <div class="text-[11px] font-semibold text-slate-800">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 mr-1">Pejabat</span>
                                                "{{ $pr->catatan_verifikasi_pp }}"
                                            </div>
                                        @endif
                                        @if($reviuPpk && $reviuPpk->catatan_perbaikan)
                                            <div class="text-[11px] font-semibold text-purple-800 mt-1" title="{{ $reviuPpk->catatan_perbaikan }}">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 mr-1">PPK</span>
                                                "{{ $reviuPpk->catatan_perbaikan }}"
                                            </div>
                                        @endif
                                        @if(!$pr->catatan_verifikasi_pp && (!$reviuPpk || !$reviuPpk->catatan_perbaikan))
                                            <span class="text-slate-400 italic">Belum ada catatan</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-700">
                                        {{ $pr->ppkUser->nama ?? ($pr->jalur_routing === 'langsung_sd_50jt' ? '(Belanja Langsung)' : 'Belum Ditugaskan') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <x-status-badge :status="$pr->status_paket" size="sm" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- TAB 2: PENERBITAN KONTRAK / SPK & TEMBUSAN OTOMATIS -- FEATURE 27      --}}
    {{-- ===================================================================== --}}
    @if($canTabSpk)
    <div x-show="activeTab === 'spk'" class="space-y-6">

        {{-- Notice Banner: Tembusan Otomatis ke Perlengkapan & Keuangan --}}
        <div class="rounded-2xl p-4 bg-blue-50/70 border border-blue-200/80 text-blue-900">
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl bg-[#0969da] text-white flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xs leading-relaxed">
                    <h4 class="font-bold text-sm text-[#213153]">Pemberitahuan Otomatis</h4>
                    <p class="mt-0.5">
                        Setelah kontrak diterbitkan, salinan otomatis dikirim ke Unit Perlengkapan untuk pemeriksaan barang dan Bagian Keuangan untuk pembayaran.
                    </p>
                </div>
            </div>
        </div>

        {{-- Form Penerbitan Kontrak --}}
        <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-base font-bold text-[#213153]">Terbitkan Dokumen Kontrak Kerja</h2>
            </div>

            <form action="{{ route('kontrak.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {{-- Paket Pengadaan Siap SPK --}}
                    <div>
                        <label for="kode_paket" class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih Paket Pengadaan Siap Kontrak <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="kode_paket"
                            name="kode_paket"
                            x-model="spkSelectedPaket"
                            @change="onSelectSpkPaket(true)"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="">-- Pilih Paket Pengadaan (&gt; Rp 50 Juta) --</option>
                            @foreach($paketSiapSpkList as $pkt)
                                <option value="{{ $pkt->nomor_paket }}" {{ old('kode_paket') == $pkt->nomor_paket ? 'selected' : '' }}>
                                    [{{ $pkt->kode_paket ?? $pkt->nomor_paket }}] {{ $pkt->nama_paket }} &bull; Perkiraan: Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Rekanan Vendor (Inline Input & Dokumen Legalitas) --}}
                    <div class="space-y-4 rounded-xl p-4 bg-slate-50 border border-slate-200/80">
                        <div class="flex items-center gap-2">
                            <span class="size-6 rounded-lg bg-blue-100 text-[#0969da] flex items-center justify-center font-bold text-xs">V</span>
                            <span class="text-xs font-bold text-slate-800">Rekanan &amp; Dokumen Legalitas</span>
                        </div>

                        <div>
                            <label for="modal_nama_vendor" class="block text-xs font-bold text-slate-700 mb-1">
                                Nama Rekanan / Vendor <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="modal_nama_vendor"
                                name="nama_vendor"
                                x-model="spkNamaVendor"
                                list="vendor-datalist"
                                required
                                placeholder="Ketik nama rekanan atau pilih dari saran tersimpan"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            />
                            <datalist id="vendor-datalist">
                                @foreach($vendors as $v)
                                    <option value="{{ $v->nama_vendor }}">{{ $v->nama_vendor }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div>
                            <label for="file_dokumen_legalitas" class="block text-xs font-bold text-slate-700 mb-1">
                                Unggah Legalitas Rekanan (PDF/Gambar)
                            </label>
                            <input
                                type="file"
                                id="file_dokumen_legalitas"
                                name="file_dokumen_legalitas"
                                accept=".pdf,image/*"
                                class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">
                                Maksimal 10 MB.
                            </p>
                        </div>
                    </div>

                    {{-- Nomor Resmi Kontrak --}}
                    <div>
                        <label for="nomor_kontrak_spk" class="block text-xs font-bold text-slate-700 mb-1">
                            Nomor Kontrak Kerja <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="nomor_kontrak_spk"
                            name="nomor_kontrak_spk"
                            value="{{ old('nomor_kontrak_spk', 'SPK-PNJ/' . date('Ymd') . '/' . rand(100, 999)) }}"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>

                    {{-- Nilai Kontrak --}}
                    <div>
                        <label for="nilai_kontrak" class="block text-xs font-bold text-slate-700 mb-1">
                            Nilai Kontrak (Rupiah) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                            <input
                                type="number"
                                id="nilai_kontrak"
                                name="nilai_kontrak"
                                x-model="spkNilaiKontrak"
                                :max="spkHpsMax > 0 ? spkHpsMax : null"
                                value="{{ old('nilai_kontrak') }}"
                                step="0.01"
                                min="1"
                                required
                                placeholder="Contoh: 140000000"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            />
                        </div>
                        <p x-show="spkHpsMax > 0" x-cloak class="text-[11px] text-slate-400 mt-1">
                            Maksimal sesuai perkiraan harga: <span class="font-mono font-semibold text-slate-600" x-text="formatRupiah(spkHpsMax)"></span>.
                        </p>
                    </div>

                    {{-- Jenis Dokumen Kontrak --}}
                    <div>
                        <label for="tipe_dokumen" class="block text-xs font-bold text-slate-700 mb-1">
                            Jenis Dokumen Kontrak <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="tipe_dokumen"
                            name="tipe_dokumen"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="spk" {{ old('tipe_dokumen', 'spk') === 'spk' ? 'selected' : '' }}>Kontrak Kerja (SPK)</option>
                            <option value="surat_pesanan" {{ old('tipe_dokumen') === 'surat_pesanan' ? 'selected' : '' }}>Surat Pesanan (E-Katalog)</option>
                            <option value="surat_kontrak" {{ old('tipe_dokumen') === 'surat_kontrak' ? 'selected' : '' }}>Surat Perjanjian Kontrak</option>
                        </select>
                    </div>

                    {{-- Sifat Kontrak --}}
                    <div>
                        <label for="sifat_kontrak" class="block text-xs font-bold text-slate-700 mb-1">
                            Sifat / Bentuk Pembayaran Kontrak
                        </label>
                        <select
                            id="sifat_kontrak"
                            name="sifat_kontrak"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="lump_sum" {{ old('sifat_kontrak', 'lump_sum') === 'lump_sum' ? 'selected' : '' }}>Lump Sum (Sekali Selesai)</option>
                            <option value="harga_satuan" {{ old('sifat_kontrak') === 'harga_satuan' ? 'selected' : '' }}>Harga Satuan</option>
                            <option value="sekali_selesai" {{ old('sifat_kontrak') === 'sekali_selesai' ? 'selected' : '' }}>Sekali Selesai</option>
                            <option value="termin_bulanan" {{ old('sifat_kontrak') === 'termin_bulanan' ? 'selected' : '' }}>Termin Bulanan</option>
                            <option value="kontrak_tahunan" {{ old('sifat_kontrak') === 'kontrak_tahunan' ? 'selected' : '' }}>Kontrak Tahunan</option>
                        </select>
                    </div>

                    {{-- Tanggal Pelaksanaan --}}
                    <div>
                        <label for="tanggal_mulai" class="block text-xs font-bold text-slate-700 mb-1">
                            Tanggal Mulai Pekerjaan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal_mulai"
                            name="tanggal_mulai"
                            value="{{ old('tanggal_mulai', date('Y-m-d')) }}"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>

                    <div>
                        <label for="tanggal_selesai" class="block text-xs font-bold text-slate-700 mb-1">
                            Tanggal Selesai Pekerjaan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="tanggal_selesai"
                            name="tanggal_selesai"
                            value="{{ old('tanggal_selesai', date('Y-m-d', strtotime('+30 days'))) }}"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>

                    {{-- Jadwal Pengiriman Rekanan --}}
                    <div>
                        <label for="jadwal_pengiriman" class="block text-xs font-bold text-slate-700 mb-1">
                            Target Pengiriman Barang
                        </label>
                        <input
                            type="date"
                            id="jadwal_pengiriman"
                            name="jadwal_pengiriman"
                            value="{{ old('jadwal_pengiriman', date('Y-m-d', strtotime('+28 days'))) }}"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>

                    {{-- Berkas Pindaian PDF --}}
                    <div>
                        <label for="file_spk" class="block text-xs font-bold text-slate-700 mb-1">
                            Unggah Berkas Kontrak (PDF, Maks. 10 MB)
                        </label>
                        <input
                            type="file"
                            id="file_spk"
                            name="file_spk"
                            accept=".pdf,application/pdf"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white p-2 text-xs text-slate-800 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100"
                        />
                    </div>

                    {{-- Catatan / Ketentuan Khusus --}}
                    <div class="md:col-span-2">
                        <label for="catatan_spk" class="block text-xs font-bold text-slate-700 mb-1">
                            Catatan / Ketentuan Khusus Pekerjaan
                        </label>
                        <textarea
                            id="catatan_spk"
                            name="catatan_spk"
                            rows="3"
                            placeholder="Tuliskan spesifikasi jaminan garansi, ketentuan denda keterlambatan, atau instruksi serah terima khusus..."
                            class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        ></textarea>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Terbitkan Dokumen Kontrak
                    </button>
                </div>
            </form>
        </div>

    </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- TAB 3: DIREKTORI KONTRAK DITERBITKAN                                   --}}
    {{-- ===================================================================== --}}
    @if($canTabDaftar)
    <div x-show="activeTab === 'daftar'" class="space-y-6">

        <x-filter-bar
            :action="route('kontrak.index')"
            searchPlaceholder="Cari nomor kontrak, paket, atau rekanan..."
        >
            <x-slot:filters>
                <input type="hidden" name="tab" value="daftar">
                <select
                    name="status_spk"
                    class="h-10 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
                >
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status_spk') === 'aktif' ? 'selected' : '' }}>Aktif Berjalan</option>
                    <option value="terbit" {{ request('status_spk') === 'terbit' ? 'selected' : '' }}>Terbit</option>
                    <option value="selesai" {{ request('status_spk') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </x-slot:filters>
        </x-filter-bar>

        <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Daftar Kontrak Diterbitkan</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $kontrakList->count() }} dari {{ $kontrakList->total() }} total kontrak diterbitkan</p>
                </div>
            </div>

            @if($kontrakList->isEmpty())
                <div class="text-center py-12 px-4">
                    <p class="text-xs text-slate-500">Belum ada kontrak yang diterbitkan.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-5 py-3.5">Nomor Kontrak &amp; Tanggal</th>
                                <th class="px-5 py-3.5">Paket Pengadaan</th>
                                <th class="px-5 py-3.5">Rekanan Vendor</th>
                                <th class="px-5 py-3.5">Nilai Kontrak</th>
                                <th class="px-5 py-3.5">Tipe &amp; Jadwal Kirim</th>
                                <th class="px-5 py-3.5">Tembusan Otomatis</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-center">Dokumen PDF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($kontrakList as $k)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-5 py-4">
                                        <span class="font-mono font-bold text-[#0969da] block">
                                            {{ $k->nomor_spk }}
                                        </span>
                                        <span class="text-[11px] text-slate-400 mt-0.5 block">
                                            {{ $k->tanggal_spk ? \Carbon\Carbon::parse($k->tanggal_spk)->format('d/m/Y') : '-' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <a href="{{ route('paket.show', $k->nomor_paket) }}" class="font-semibold text-slate-800 hover:underline block line-clamp-1">
                                            {{ $k->paket->nama_paket ?? $k->nomor_paket }}
                                        </a>
                                        <span class="text-[11px] text-slate-400 font-mono block mt-0.5">
                                            PPK: {{ $k->paket->ppkUser->nama ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="font-semibold block">{{ $k->paket->vendor->nama_vendor ?? '-' }}</span>
                                        @if($k->paket->vendor?->file_dokumen_legalitas)
                                            <a href="{{ asset('storage/' . $k->paket->vendor->file_dokumen_legalitas) }}" target="_blank" class="text-[10px] text-[#0969da] hover:underline font-medium block mt-0.5">
                                                Lihat Dokumen Legalitas &rarr;
                                            </a>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 font-mono font-bold text-emerald-600">
                                        Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}
                                    </td>

                                    <td class="px-5 py-4 text-slate-600">
                                        <span class="font-medium capitalize block">{{ str_replace('_', ' ', $k->tipe_dokumen ?? 'SPK') }}</span>
                                        <span class="text-[11px] text-slate-400 mt-0.5 block">
                                            Kirim: {{ $k->jadwal_pengiriman ? \Carbon\Carbon::parse($k->jadwal_pengiriman)->format('d/m/Y') : '-' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700">
                                                <svg class="size-3 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Unit Perlengkapan
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700">
                                                <svg class="size-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Bagian Keuangan
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <x-status-badge :status="$k->status_spk" size="sm" />
                                    </td>

                                    <td class="px-5 py-4 text-center">
                                        @if($canUnduhPdf)
                                            <a
                                                href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_spk]) }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                                title="Unduh Berkas Kontrak (PDF)"
                                            >
                                                <svg class="size-3.5 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                                PDF
                                            </a>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $kontrakList->withQueryString()->links() }}
                </div>
            @endif
        </div>

    </div>
    @endif

</div>
@endsection
