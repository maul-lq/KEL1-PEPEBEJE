@extends('layouts.app')

@section('title', 'Perbaikan Dokumen Pengadaan - ' . $paket->nomor_paket)

@section('content')
@php
    $lastReviu = $paket->reviuDokumen->where('status_reviu', 'perlu_perbaikan')->last() ?? $paket->reviuDokumen->last();
    $hpsVal = (float) old('nilai_hps', $paket->nilai_hps);
    $makObj = $paket->permohonan?->anggaranMak;
    $sisaMak = (float) ($makObj?->sisa_pagu_mak ?? 0);
@endphp

<div
    x-data="{
        hps: '{{ $hpsVal }}',
        hpsAwal: {{ (float) $paket->nilai_hps }},
        sisaMak: {{ $sisaMak }},
        metode: '{{ old('metode_pengadaan', $paket->metode_pengadaan) }}',
        routing() {
            let n = parseFloat(this.hps) || 0;
            if (n <= 0) return null;
            if (n <= 50000000) return 'langsung';
            if (n <= 200000000) return 'pp';
            return 'ppk';
        },
        syncMetodeWithRouting() {
            let r = this.routing();
            if (r === 'langsung') {
                this.metode = 'Pengadaan Langsung';
            } else if (r === 'pp') {
                if (this.metode === 'Pengadaan Langsung' || !['SPK', 'Surat Pesanan', 'Surat Kontrak', 'E-Purchasing'].includes(this.metode)) {
                    this.metode = 'SPK';
                }
            } else if (r === 'ppk') {
                if (this.metode === 'Pengadaan Langsung' || !['Tender', 'E-Purchasing', 'Seleksi', 'Penunjukan Langsung'].includes(this.metode)) {
                    this.metode = 'Tender';
                }
            }
        },
        getDeltaMak() {
            let n = parseFloat(this.hps) || 0;
            return n - this.hpsAwal;
        },
        formatRupiah(val) {
            let n = parseFloat(val) || 0;
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
        }
    }"
    class="space-y-6"
>

    {{-- 1. Header & Breadcrumb --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        Status: Perlu Perbaikan
                    </span>
                    <span class="text-xs text-slate-400 font-mono">{{ $paket->nomor_paket }}</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-2">Penyusunan Ulang Dokumen Pengadaan</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Paket dikembalikan oleh Pejabat Pengadaan (PP) atau PPK untuk penyesuaian spesifikasi, HPS, atau dokumen teknis sebelum diteruskan kembali ke alur pengadaan.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Batal &amp; Kembali
                </a>
                <a
                    href="{{ route('paket.show', $paket->nomor_paket) }}"
                    class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-[#0969da] hover:bg-blue-50 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Lihat Riwayat Siklus
                </a>
            </div>
        </div>
    </div>

    {{-- 2. Banner Catatan Perbaikan dari Reviewer (PP / PPK) --}}
    @if($lastReviu && !empty($lastReviu->catatan_perbaikan))
        <div class="rounded-2xl bg-rose-50/80 p-5 shadow-sm border border-rose-200">
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-rose-900">
                            Catatan Pengembalian Berkas dari {{ strtoupper($lastReviu->role_reviewer) }}: {{ $lastReviu->user?->nama ?? 'Pejabat Penelaah' }}
                        </h3>
                        <span class="text-[10px] text-rose-700 bg-rose-100/80 px-2 py-0.5 rounded-full font-mono">
                            {{ $lastReviu->tanggal_reviu ? date('d M Y H:i', strtotime($lastReviu->tanggal_reviu)) : 'Baru Saja' }}
                        </span>
                    </div>
                    <p class="text-xs text-rose-800 mt-1 whitespace-pre-line leading-relaxed font-medium">
                        {{ $lastReviu->catatan_perbaikan }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. Form Perbaikan Dokumen Pengadaan --}}
    <form action="{{ route('paket.update', $paket->nomor_paket) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Kolom Kiri: Form Input Teknis --}}
            <div class="lg:col-span-2 space-y-6">

                <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-[#213153] flex items-center gap-2">
                            <span class="size-6 rounded-lg bg-blue-50 text-[#0969da] flex items-center justify-center text-xs font-bold">1</span>
                            Informasi Paket &amp; Penetapan Ulang HPS
                        </h2>
                    </div>

                    {{-- Nama Paket --}}
                    <div>
                        <label for="nama_paket" class="block text-xs font-bold text-slate-700 mb-1">
                            Nama Paket Pengadaan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="nama_paket"
                            name="nama_paket"
                            value="{{ old('nama_paket', $paket->nama_paket) }}"
                            required
                            class="w-full h-11 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                        @error('nama_paket')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Jenis & Metode Pengadaan --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="jenis_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                                Jenis Pengadaan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="jenis_pengadaan"
                                name="jenis_pengadaan"
                                required
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            >
                                @foreach(['barang' => 'Pengadaan Barang', 'pekerjaan_konstruksi' => 'Pekerjaan Konstruksi', 'jasa_konsultansi' => 'Jasa Konsultansi', 'jasa_lainnya' => 'Jasa Lainnya'] as $jKey => $jLabel)
                                    <option value="{{ $jKey }}" {{ old('jenis_pengadaan', $paket->jenis_pengadaan) === $jKey ? 'selected' : '' }}>
                                        {{ $jLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="metode_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                                Metode / Mekanisme Pengadaan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="metode_pengadaan"
                                name="metode_pengadaan"
                                x-model="metode"
                                required
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            >
                                <option value="Pengadaan Langsung">Pengadaan Langsung (&le; 50 Jt)</option>
                                <option value="SPK">Surat Perintah Kerja (SPK)</option>
                                <option value="Surat Pesanan">Surat Pesanan (E-Katalog / Inaproc)</option>
                                <option value="Surat Kontrak">Surat Perjanjian Kontrak</option>
                                <option value="E-Purchasing">E-Purchasing</option>
                                <option value="Tender">Tender Terbuka (SPSE &gt; 200 Jt)</option>
                                <option value="Seleksi">Seleksi Konsultansi</option>
                                <option value="Penunjukan Langsung">Penunjukan Langsung</option>
                            </select>
                        </div>
                    </div>

                    {{-- Penetapan HPS --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="nilai_hps" class="block text-xs font-bold text-slate-700">
                                Nilai Harga Perkiraan Sendiri (HPS) <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[11px] text-slate-500">
                                Pagu Awal: <strong>Rp {{ number_format($paket->nilai_hps, 0, ',', '.') }}</strong>
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                            <input
                                type="number"
                                id="nilai_hps"
                                name="nilai_hps"
                                x-model="hps"
                                @input="syncMetodeWithRouting()"
                                min="1000"
                                step="1000"
                                required
                                class="w-full h-11 pl-9 pr-3 rounded-xl border border-slate-200 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            >
                        </div>
                        @error('nilai_hps')
                            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
                        @enderror

                        {{-- Alert Kalkulasi Selisih Terhadap MAK --}}
                        <div x-show="getDeltaMak() !== 0" x-cloak class="mt-2 text-xs p-3 rounded-xl"
                             :class="getDeltaMak() > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200'">
                            <template x-if="getDeltaMak() > 0">
                                <div>
                                    Penyesuaian HPS menambah anggaran sebesar <strong x-text="formatRupiah(getDeltaMak())"></strong>. Sisa pagu MAK saat ini: <strong x-text="formatRupiah(sisaMak)"></strong>.
                                </div>
                            </template>
                            <template x-if="getDeltaMak() < 0">
                                <div>
                                    Efisiensi anggaran sebesar <strong x-text="formatRupiah(Math.abs(getDeltaMak()))"></strong> akan dikembalikan ke sisa pagu MAK.
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Penugasan PP / PPK & Vendor --}}
                <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-5">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-[#213153] flex items-center gap-2">
                            <span class="size-6 rounded-lg bg-blue-50 text-[#0969da] flex items-center justify-center text-xs font-bold">2</span>
                            Penugasan Pejabat &amp; Rekanan Vendor
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Penugasan PPK --}}
                        <div>
                            <label for="id_user_ppk" class="block text-xs font-bold text-slate-700 mb-1">
                                Pejabat Pembuat Komitmen (PPK)
                            </label>
                            <select
                                id="id_user_ppk"
                                name="id_user_ppk"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            >
                                <option value="">-- Warisi PPK Surat Permohonan --</option>
                                @foreach($ppkUsers as $ppk)
                                    <option value="{{ $ppk->id_user }}" {{ (int) old('id_user_ppk', $paket->id_user_ppk) === (int) $ppk->id_user ? 'selected' : '' }}>
                                        {{ $ppk->nama }} ({{ $ppk->nip ?? 'NIP PNJ' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Penugasan PP (Aktif jika rute PP) --}}
                        <div x-show="routing() === 'pp'" x-cloak>
                            <label for="id_user_pp" class="block text-xs font-bold text-slate-700 mb-1">
                                Pejabat Pengadaan (PP) <span class="text-rose-500">*</span>
                            </label>
                            <select
                                id="id_user_pp"
                                name="id_user_pp"
                                class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                            >
                                <option value="">-- Pilih Pejabat PP --</option>
                                @foreach($ppUsers as $pp)
                                    <option value="{{ $pp->id_user }}" {{ (int) old('id_user_pp', $paket->id_user_pp) === (int) $pp->id_user ? 'selected' : '' }}>
                                        {{ $pp->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Rekanan Vendor --}}
                    <div>
                        <label for="nama_vendor" class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih atau Masukkan Rekanan Vendor (Opsional)
                        </label>
                        <input
                            type="text"
                            id="nama_vendor"
                            name="nama_vendor"
                            list="vendorList"
                            value="{{ old('nama_vendor', $paket->vendor?->nama_vendor) }}"
                            placeholder="Ketik atau pilih nama vendor rekanan..."
                            class="w-full h-11 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >
                        <datalist id="vendorList">
                            @foreach($vendors as $v)
                                <option value="{{ $v->nama_vendor }}">{{ $v->npwp ? 'NPWP: ' . $v->npwp : '' }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    {{-- Catatan Perbaikan PPBJ --}}
                    <div>
                        <label for="catatan_perbaikan_ppbj" class="block text-xs font-bold text-slate-700 mb-1">
                            Catatan Perbaikan / Tindak Lanjut PPBJ
                        </label>
                        <textarea
                            id="catatan_perbaikan_ppbj"
                            name="catatan_perbaikan_ppbj"
                            rows="3"
                            placeholder="Jelaskan penyesuaian yang telah dilakukan pada spesifikasi, dokumen, atau HPS..."
                            class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        >{{ old('catatan_perbaikan_ppbj') }}</textarea>
                    </div>
                </div>

            </div>

            {{-- Kolom Kanan: Preview Gateway BPMN N28 & Berkas Usulan --}}
            <div class="space-y-6">

                {{-- Status Klasifikasi Alur Pengadaan --}}
                <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80 space-y-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Klasifikasi Alur Berdasarkan Nilai HPS
                    </h3>

                    {{-- Jalur 1: <= 50 Juta --}}
                    <div
                        class="p-4 rounded-xl border transition-all"
                        :class="routing() === 'langsung' ? 'bg-blue-50 border-blue-300 ring-2 ring-[#0969da]/20' : 'bg-slate-50 border-slate-200 opacity-60'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="size-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0"
                                  :class="routing() === 'langsung' ? 'bg-[#0969da] text-white' : 'bg-slate-200 text-slate-600'">
                                1
                            </span>
                            <span class="text-xs font-bold text-slate-800">&le; Rp 50.000.000</span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-2 leading-relaxed">
                            <strong>Belanja Langsung:</strong> Eksekusi Kwitansi langsung oleh PPBJ tanpa review PP maupun persetujuan PPK.
                        </p>
                    </div>

                    {{-- Jalur 2: 50 s.d. 200 Juta --}}
                    <div
                        class="p-4 rounded-xl border transition-all"
                        :class="routing() === 'pp' ? 'bg-blue-50 border-blue-300 ring-2 ring-[#0969da]/20' : 'bg-slate-50 border-slate-200 opacity-60'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="size-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0"
                                  :class="routing() === 'pp' ? 'bg-[#0969da] text-white' : 'bg-slate-200 text-slate-600'">
                                2
                            </span>
                            <span class="text-xs font-bold text-slate-800">&gt; Rp 50 Jt s.d. Rp 200 Jt</span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-2 leading-relaxed">
                            <strong>Jalur PP:</strong> Ditelaah oleh Pejabat Pengadaan (PP) terlebih dahulu sebelum diteruskan ke PPK.
                        </p>
                    </div>

                    {{-- Jalur 3: > 200 Juta --}}
                    <div
                        class="p-4 rounded-xl border transition-all"
                        :class="routing() === 'ppk' ? 'bg-blue-50 border-blue-300 ring-2 ring-[#0969da]/20' : 'bg-slate-50 border-slate-200 opacity-60'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="size-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0"
                                  :class="routing() === 'ppk' ? 'bg-[#0969da] text-white' : 'bg-slate-200 text-slate-600'">
                                3
                            </span>
                            <span class="text-xs font-bold text-slate-800">&gt; Rp 200.000.000</span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-2 leading-relaxed">
                            <strong>Jalur PPK:</strong> Langsung ke antrean verifikasi &amp; Approval PPK tanpa melalui Pejabat Pengadaan (PP).
                        </p>
                    </div>
                </div>

                {{-- Informasi Berkas Usulan Permohonan --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80 space-y-3">
                    <h3 class="text-xs font-bold text-slate-700">Rujukan Surat &amp; Anggaran</h3>
                    <div class="text-xs space-y-2">
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Nomor Surat:</span>
                            <span class="font-mono font-bold text-slate-800">{{ $paket->nomor_surat }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Unit Pengusul:</span>
                            <span class="font-medium text-slate-800">{{ $paket->permohonan?->unit?->nama_unit ?? 'Unit Kerja PNJ' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Kode MAK:</span>
                            <span class="font-mono font-bold text-[#0969da]">{{ $makObj?->kode_mak ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Sisa Pagu MAK:</span>
                            <span class="font-mono font-bold text-emerald-600">Rp {{ number_format($sisaMak, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Tombol Aksi Simpan & Submit Ulang --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80 space-y-3">
                    <button
                        type="submit"
                        class="w-full h-11 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm hover:shadow transition flex items-center justify-center gap-2"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Simpan &amp; Teruskan Ulang Dokumen
                    </button>
                    <a
                        href="{{ route('paket.index') }}"
                        class="w-full h-10 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition flex items-center justify-center"
                    >
                        Batal
                    </a>
                </div>

            </div>

        </div>
    </form>

</div>
@endsection
