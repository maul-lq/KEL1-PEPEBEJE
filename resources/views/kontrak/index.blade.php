@extends('layouts.app')

@section('title', 'Manajemen Penelaahan Dokumen & Kontrak SPK - SIM-PBJ PNJ')

@section('content')
<div
    x-data="{
        activeTab: '{{ request('tab', in_array(Auth::user()->role ?? '', ['pp', 'ppk']) ? 'reviu' : (Auth::user()->role === 'ppbj' ? 'spk' : 'daftar')) }}',
        hpsVal: 0,
        negoVal: 0,
        calcSavings() {
            let h = parseFloat(this.hpsVal) || 0;
            let n = parseFloat(this.negoVal) || 0;
            return Math.max(0, h - n);
        },
        formatRupiah(val) {
            let n = parseFloat(val) || 0;
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
        }
    }"
    class="space-y-6"
>

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="5" status="kontrak" />

    {{-- 2. Header & KPI Metrik --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Siklus 4 &amp; 5: Reviu &amp; Kontrak SPK
                    </span>
                    <span class="text-xs text-slate-400 font-mono">FR 9 &bull; FR 11</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Penelaahan Dokumen &amp; Penerbitan Kontrak SPK</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unit Kerja Pengadaan Barang &amp; Jasa, Pejabat Pengadaan, dan Pejabat Pembuat Komitmen (PPK) PNJ
                </p>
            </div>

            {{-- Role Badge Indicator --}}
            <div class="shrink-0">
                @if(Auth::user()->role === 'ppk')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-purple-600"></span>
                        PPK: {{ Auth::user()->nama }} (Isolasi Berkas Aktif)
                    </span>
                @elseif(Auth::user()->role === 'pp')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-amber-600"></span>
                        Pejabat Pengadaan (PP): {{ Auth::user()->nama }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-[#0969da] border border-blue-200 text-xs font-bold">
                        <span class="size-2 rounded-full bg-[#0969da]"></span>
                        PPBJ: Penerbit Kontrak &amp; SPK Sah
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
            subtitle="Akumulasi SPK / Kontrak sah"
        />

        <x-stat-card
            title="Antrean Reviu Dokumen"
            :value="$stats['antrean_reviu'] ?? $paketReviu->count()"
            icon="bell"
            color="amber"
            badge="Tindak Lanjut"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Menunggu telaah teknis PP &amp; PPK"
        />

        <x-stat-card
            title="Paket Siap Terbit SPK"
            :value="$stats['siap_spk'] ?? 0"
            icon="check"
            color="blue"
            badge="Siap SPK"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Telaah teknis disetujui"
        />

        <x-stat-card
            title="Akumulasi Nilai Kontrak"
            :value="'Rp ' . number_format($stats['total_nilai'] ?? 0, 0, ',', '.')"
            icon="wallet"
            color="emerald"
            subtitle="Komitmen pagu terkunci permanen"
        />
    </div>

    {{-- 4. Interactive Tab Bar --}}
    <div class="rounded-2xl bg-white p-2 shadow-sm border border-slate-200/80 flex flex-wrap gap-2">
        <button
            type="button"
            @click="activeTab = 'reviu'"
            :class="activeTab === 'reviu' ? 'bg-[#213153] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
        >
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>1. Penelaahan &amp; Negosiasi Harga (PP &amp; PPK)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="activeTab === 'reviu' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                {{ $paketReviu->count() }}
            </span>
        </button>

        <button
            type="button"
            @click="activeTab = 'spk'"
            :class="activeTab === 'spk' ? 'bg-[#0969da] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
        >
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span>2. Penerbitan Kontrak / SPK (PPBJ)</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="activeTab === 'spk' ? 'bg-white/20 text-white' : 'bg-blue-100 text-[#0969da]'">
                {{ $stats['siap_spk'] ?? 0 }}
            </span>
        </button>

        <button
            type="button"
            @click="activeTab = 'daftar'"
            :class="activeTab === 'daftar' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="px-4 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2"
        >
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            <span>3. Direktori Kontrak SPK Diterbitkan</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="activeTab === 'daftar' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                {{ $kontrakList->total() }}
            </span>
        </button>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 1: PENELAAHAN & NEGOSIASI HARGA (PP & PPK) -- FEATURE 26          --}}
    {{-- ===================================================================== --}}
    <div x-show="activeTab === 'reviu'" class="space-y-6">

        @if(Auth::user() && in_array(Auth::user()->role, ['pp', 'ppk', 'admin']))
            {{-- Form Penelaahan Dokumen & Negosiasi --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
                <div class="border-b border-slate-100 pb-4 mb-5">
                    <h2 class="text-base font-bold text-[#213153]">Form Penelaahan Dokumen &amp; Negosiasi Harga (Feature 26)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pejabat Pengadaan (PP) melakukan verifikasi &amp; input negosiasi harga definitif; PPK menyetujui dokumen serta mengunci Kode MAK secara permanen.
                    </p>
                </div>

                @if($paketReviu->isEmpty())
                    <div class="text-center py-10 px-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        <p class="text-xs text-slate-500">Tidak ada antrean paket pengadaan yang membutuhkan reviu atau telaah dokumen pada akun Anda saat ini.</p>
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
                                    "
                                    class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                                >
                                    <option value="">-- Pilih Paket Pengadaan --</option>
                                    @foreach($paketReviu as $pktR)
                                        <option
                                            value="{{ $pktR->nomor_paket }}"
                                            data-hps="{{ $pktR->nilai_hps }}"
                                        >
                                            [{{ $pktR->nomor_paket }}] {{ $pktR->nama_paket }} &bull; HPS: Rp {{ number_format($pktR->nilai_hps, 0, ',', '.') }} &bull; {{ strtoupper(str_replace('_', ' ', $pktR->jalur_routing)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Keputusan Reviu --}}
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                                <span class="block text-xs font-bold text-slate-800">Keputusan Hasil Penelaahan <span class="text-rose-500">*</span></span>
                                <label class="flex items-center text-xs text-slate-800 cursor-pointer gap-2">
                                    <input type="radio" name="status_reviu" value="disetujui" checked class="text-[#0969da] focus:ring-[#0969da]">
                                    <span><strong>Disetujui</strong> &mdash; Berkas lengkap, valid, dan siap diterbitkan SPK.</span>
                                </label>
                                <label class="flex items-center text-xs text-slate-800 cursor-pointer gap-2">
                                    <input type="radio" name="status_reviu" value="perlu_perbaikan" class="text-rose-600 focus:ring-rose-500">
                                    <span><strong>Perlu Perbaikan</strong> &mdash; Kembalikan berkas ke PPBJ disertai catatan revisi wajib.</span>
                                </label>
                            </div>

                            {{-- PP: Negosiasi Harga & Kalkulator Efisiensi --}}
                            @if(Auth::user()->role === 'pp' || Auth::user()->role === 'admin')
                                <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200/80 space-y-2">
                                    <label for="nilai_hasil_negosiasi" class="block text-xs font-bold text-amber-900">
                                        Nilai Hasil Negosiasi Harga (Rupiah Definitif)
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-amber-700 pointer-events-none">Rp</span>
                                        <input
                                            type="number"
                                            id="nilai_hasil_negosiasi"
                                            name="nilai_hasil_negosiasi"
                                            x-model="negoVal"
                                            step="0.01"
                                            min="0"
                                            placeholder="Contoh: 142000000"
                                            class="w-full h-10 rounded-xl border border-amber-300 bg-white pl-9 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                                        />
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] pt-1 border-t border-amber-200">
                                        <span class="text-amber-800 font-medium">Estimasi Efisiensi Anggaran:</span>
                                        <span class="font-mono font-bold text-emerald-700" x-text="formatRupiah(calcSavings())">Rp 0</span>
                                    </div>
                                </div>
                            @elseif(Auth::user()->role === 'ppk')
                                <div class="p-4 rounded-xl bg-purple-50/60 border border-purple-200/80 space-y-1">
                                    <h4 class="text-xs font-bold text-purple-900">Konfirmasi Penguncian MAK Permanen</h4>
                                    <p class="text-[11px] text-purple-800 leading-relaxed">
                                        Dengan menyetujui dokumen pengadaan ini, Anda selaku PPK secara resmi menyetujui spesifikasi teknis dan sistem akan <strong>mengunci alokasi MAK DIPA</strong> secara permanen (FR 9.2 &bull; NFR 6).
                                    </p>
                                </div>
                            @endif

                            {{-- Catatan Perbaikan / BA Negosiasi --}}
                            <div class="md:col-span-2">
                                <label for="catatan_perbaikan" class="block text-xs font-bold text-slate-700 mb-1">
                                    Berita Acara / Catatan Rekomendasi &amp; Catatan Perbaikan
                                    <span class="text-slate-400 font-normal">(Wajib jika memilih 'Perlu Perbaikan')</span>
                                </label>
                                <textarea
                                    id="catatan_perbaikan"
                                    name="catatan_perbaikan"
                                    rows="3"
                                    placeholder="Tuliskan berita acara hasil penelaahan teknis atau catatan butir spesifikasi yang harus diperbaiki PPBJ..."
                                    class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                                ></textarea>
                            </div>

                        </div>

                        <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                            <button
                                type="submit"
                                class="px-6 py-2.5 rounded-xl bg-[#213153] text-xs font-bold text-white hover:bg-slate-800 shadow-sm transition"
                            >
                                Kirim Keputusan Penelaahan Dokumen
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        {{-- Tabel Antrean Paket Telaah --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Daftar Antrean Paket Telaah (Reviu)</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Paket berstatus terdaftar, reviu dokumen, atau perbaikan</p>
                </div>
                <span class="text-xs font-bold text-slate-400 font-mono">{{ $paketReviu->count() }} Paket</span>
            </div>

            @if($paketReviu->isEmpty())
                <div class="text-center py-8 text-xs text-slate-400">Tidak ada paket pengadaan dalam antrean telaah.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-5 py-3">Paket Pengadaan</th>
                                <th class="px-5 py-3">Pagu HPS</th>
                                <th class="px-5 py-3">Jalur Routing</th>
                                <th class="px-5 py-3">Hasil Negosiasi PP</th>
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
                                    <td class="px-5 py-3.5">
                                        @if($pr->nilai_hasil_negosiasi)
                                            <span class="font-mono font-bold text-[#0969da]">Rp {{ number_format($pr->nilai_hasil_negosiasi, 0, ',', '.') }}</span>
                                            @if($pr->catatan_verifikasi_pp)
                                                <div class="text-[10px] text-slate-500 truncate max-w-xs mt-0.5">"{{ $pr->catatan_verifikasi_pp }}"</div>
                                            @endif
                                        @else
                                            <span class="text-slate-400 italic">Belum negosiasi</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-700">
                                        {{ $pr->ppkUser->nama ?? ($pr->jalur_routing === 'langsung_sd_50jt' ? '(Pengadaan Langsung)' : 'Belum Ditugaskan') }}
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

    {{-- ===================================================================== --}}
    {{-- TAB 2: PENERBITAN KONTRAK / SPK & TEMBUSAN OTOMATIS -- FEATURE 27      --}}
    {{-- ===================================================================== --}}
    <div x-show="activeTab === 'spk'" class="space-y-6">

        {{-- Notice Banner: Tembusan Otomatis ke Perlengkapan & Keuangan --}}
        <div class="rounded-2xl p-4 bg-blue-50/70 border border-blue-200/80 text-blue-900">
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl bg-[#0969da] text-white flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xs leading-relaxed">
                    <h4 class="font-bold text-sm text-[#213153]">Pemberitahuan Otomatis Tembusan SPK (Feature 27 &bull; FR 11)</h4>
                    <p class="mt-0.5">
                        Saat Kontrak / Surat Perintah Kerja (SPK) ini sah diterbitkan:
                    </p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        <li><strong>Unit Perlengkapan (BMN):</strong> Otomatis menerima tembusan SPK untuk persiapan pemeriksaan fisik dan penerbitan BAST.</li>
                        <li><strong>Bagian Keuangan:</strong> Otomatis menerima salinan SPK untuk pencadangan kas dan kesiapan penerbitan SPP/SP2D.</li>
                        <li><strong>Alokasi MAK:</strong> Pagu anggaran pada MAK terkait dikunci secara permanen (irreversible lock).</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Form Penerbitan Kontrak SPK --}}
        <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-base font-bold text-[#213153]">Formulir Penerbitan Dokumen Kontrak / SPK</h2>
                <p class="text-xs text-slate-500 mt-0.5">Diisi oleh Unit Kerja Pengadaan Barang &amp; Jasa (PPBJ) Politeknik Negeri Jakarta</p>
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
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="">-- Pilih Paket Pengadaan --</option>
                            @foreach($paketList as $pkt)
                                <option value="{{ $pkt->nomor_paket }}">
                                    [{{ $pkt->kode_paket ?? $pkt->nomor_paket }}] {{ $pkt->nama_paket }} &bull; HPS: Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Rekanan Vendor --}}
                    <div>
                        <label for="id_vendor" class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih Rekanan / Vendor Pemenang <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="id_vendor"
                            name="id_vendor"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="">-- Pilih Penyedia Barang/Jasa --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->npwp }}">
                                    {{ $v->nama_vendor }} &bull; NPWP: {{ $v->npwp }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nomor Resmi SPK --}}
                    <div>
                        <label for="nomor_kontrak_spk" class="block text-xs font-bold text-slate-700 mb-1">
                            Nomor Resmi Kontrak / SPK <span class="text-rose-500">*</span>
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

                    {{-- Nilai Kontrak Definitif --}}
                    <div>
                        <label for="nilai_kontrak" class="block text-xs font-bold text-slate-700 mb-1">
                            Nilai Kontrak Definitif (Rupiah) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                            <input
                                type="number"
                                id="nilai_kontrak"
                                name="nilai_kontrak"
                                value="{{ old('nilai_kontrak') }}"
                                step="0.01"
                                min="1"
                                required
                                placeholder="Contoh: 140000000"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            />
                        </div>
                    </div>

                    {{-- Tipe Dokumen --}}
                    <div>
                        <label for="tipe_dokumen" class="block text-xs font-bold text-slate-700 mb-1">
                            Tipe Dokumen Perjanjian
                        </label>
                        <select
                            id="tipe_dokumen"
                            name="tipe_dokumen"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                            <option value="SPK">Surat Perintah Kerja (SPK)</option>
                            <option value="surat_kontrak">Surat Perjanjian Kontrak</option>
                            <option value="surat_pesanan">Surat Pesanan (E-Purchasing)</option>
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
                            <option value="lump_sum">Lump Sum (Sekali Selesai)</option>
                            <option value="harga_satuan">Harga Satuan</option>
                            <option value="sekali_selesai">Sekali Selesai</option>
                            <option value="termin_bulanan">Termin Bulanan</option>
                            <option value="kontrak_tahunan">Kontrak Tahunan</option>
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
                            Jadwal Pengiriman Rekanan (Target Serah Terima)
                        </label>
                        <input
                            type="date"
                            id="jadwal_pengiriman"
                            name="jadwal_pengiriman"
                            value="{{ old('jadwal_pengiriman', date('Y-m-d', strtotime('+28 days'))) }}"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">Sistem otomatis memasang Reminder alarm H-2 sebelum tanggal ini.</p>
                    </div>

                    {{-- Berkas Pindaian PDF --}}
                    <div>
                        <label for="file_spk" class="block text-xs font-bold text-slate-700 mb-1">
                            Unggah Dokumen Kontrak / SPK Sah (PDF, Maks 10MB)
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
                        Terbitkan &amp; Tanda Tangani Kontrak SPK (Kunci MAK)
                    </button>
                </div>
            </form>
        </div>

    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 3: DIREKTORI KONTRAK SPK DITERBITKAN                              --}}
    {{-- ===================================================================== --}}
    <div x-show="activeTab === 'daftar'" class="space-y-6">

        <x-filter-bar
            :action="route('kontrak.index')"
            searchPlaceholder="Cari nomor SPK, paket, atau rekanan..."
        >
            <x-slot:filters>
                <input type="hidden" name="tab" value="daftar">
                <select
                    name="status_spk"
                    class="h-10 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
                >
                    <option value="">Semua Status SPK</option>
                    <option value="aktif" {{ request('status_spk') === 'aktif' ? 'selected' : '' }}>Aktif Berjalan</option>
                    <option value="terbit" {{ request('status_spk') === 'terbit' ? 'selected' : '' }}>Terbit</option>
                    <option value="selesai" {{ request('status_spk') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </x-slot:filters>
        </x-filter-bar>

        <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-[#213153]">Direktori Kontrak SPK Sah</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $kontrakList->count() }} dari {{ $kontrakList->total() }} total SPK diterbitkan</p>
                </div>
            </div>

            @if($kontrakList->isEmpty())
                <div class="text-center py-12 px-4">
                    <p class="text-xs text-slate-500">Belum ada Kontrak SPK yang diterbitkan atau cocok dengan pencarian.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="px-5 py-3.5">Nomor SPK &amp; Tanggal</th>
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
                                        <span class="text-[10px] text-slate-400 font-mono block mt-0.5">NPWP: {{ $k->paket->npwp_vendor ?? '-' }}</span>
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
                                        <a
                                            href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_spk]) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                            title="Unduh Berkas Resmi SPK (PDF)"
                                        >
                                            <svg class="size-3.5 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                            PDF
                                        </a>
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

</div>
@endsection
