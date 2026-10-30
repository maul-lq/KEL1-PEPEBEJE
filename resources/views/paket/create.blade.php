@extends('layouts.app')

@section('title', 'Registrasi Paket Pengadaan Baru & Auto-Routing HPS - SIM-PBJ PNJ')

@section('content')
<div
    x-data="{
        hps: '{{ old('nilai_hps', '') }}',
        routing() {
            let n = parseFloat(this.hps) || 0;
            if (n <= 0) return null;
            if (n <= 50000000) return 'langsung';
            if (n < 200000000) return 'pp';
            return 'ppk';
        },
        formatRupiah(val) {
            let n = parseFloat(val) || 0;
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
        }
    }"
    class="space-y-6"
>

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="3" status="diajukan" />

    {{-- 2. Card Header & Form --}}
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 pb-5 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Feature 23: Auto-Routing HPS Presisi
                    </span>
                    <span class="text-xs text-slate-400 font-mono">BPMN PBJ 2026</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Registrasi Paket Pengadaan Baru</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unit Kerja Pengadaan Barang &amp; Jasa (UKPBJ / PPBJ) Politeknik Negeri Jakarta
                </p>
            </div>

            <a
                href="{{ route('paket.index') }}"
                class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shrink-0 inline-flex items-center gap-1.5"
            >
                &larr; Kembali ke Daftar Paket
            </a>
        </div>

        {{-- 3. Dynamic Reactive Routing Banner --}}
        <div class="mb-6 rounded-2xl p-4 transition-all duration-300"
             :class="{
                'bg-slate-50 border border-slate-200 text-slate-600': routing() === null,
                'bg-teal-50 border border-teal-200 text-teal-900': routing() === 'langsung',
                'bg-amber-50 border border-amber-200 text-amber-900': routing() === 'pp',
                'bg-purple-50 border border-purple-200 text-purple-900': routing() === 'ppk'
             }"
        >
            <div class="flex items-start gap-3">
                <div class="size-9 rounded-xl flex items-center justify-center shrink-0"
                     :class="{
                        'bg-slate-200 text-slate-600': routing() === null,
                        'bg-teal-600 text-white': routing() === 'langsung',
                        'bg-amber-600 text-white': routing() === 'pp',
                        'bg-purple-600 text-white': routing() === 'ppk'
                     }"
                >
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider">Deteksi Otomatis Jalur Routing HPS:</span>
                        <template x-if="routing() === 'langsung'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-100 text-teal-800">
                                &le; Rp 50 Juta &bull; Pengadaan Langsung PPBJ
                            </span>
                        </template>
                        <template x-if="routing() === 'pp'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                &gt; Rp 50 Jt s.d &lt; Rp 200 Jt &bull; Pejabat Pengadaan (PP)
                            </span>
                        </template>
                        <template x-if="routing() === 'ppk'">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                &ge; Rp 200 Juta &bull; PPK Mandiri (1 dari 4 Akun)
                            </span>
                        </template>
                        <template x-if="routing() === null">
                            <span class="text-xs text-slate-400 italic">Masukkan nilai HPS untuk melihat jalur routing otomatis</span>
                        </template>
                    </div>

                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'langsung'">
                        <strong>Alur Kerja:</strong> Nilai HPS &le; Rp 50 Juta tidak memerlukan verifikasi Pejabat Pengadaan atau reviu PPK. PPBJ langsung menerbitkan Surat Kontrak/SPK dan sistem menjadwalkan Reminder pengiriman ke Unit Perlengkapan.
                    </p>
                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'pp'">
                        <strong>Alur Kerja:</strong> Ditugaskan ke Pejabat Pengadaan (PP) untuk verifikasi teknis dan negosiasi harga definitif &rarr; PPK meninjau dokumen &amp; mengunci MAK permanen &rarr; PPBJ menerbitkan SPK.
                    </p>
                    <p class="text-xs mt-1 leading-relaxed" x-show="routing() === 'ppk'">
                        <strong>Alur Kerja:</strong> Ditugaskan langsung ke salah satu dari 4 akun PPK aktif untuk penelaahan dokumen dan penguncian MAK permanen. <em>Catatan: Paket &ge; Rp 200 Juta tidak menggunakan memo pengadaan PPBJ (otomatis null).</em>
                    </p>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <p class="font-bold mb-1">Perhatian: Terjadi kesalahan validasi data form:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 4. Form Registrasi Paket --}}
        <form action="{{ route('paket.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Permohonan Induk --}}
                <div class="md:col-span-2">
                    <label for="nomor_surat" class="block text-xs font-bold text-slate-700 mb-1">
                        Pilih Usulan Permohonan Pengadaan Disetujui <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="nomor_surat"
                        name="nomor_surat"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih Surat Permohonan Pengadaan Disetujui --</option>
                        @foreach($permohonanDisetujui as $p)
                            <option value="{{ $p->nomor_surat }}" {{ old('nomor_surat') == $p->nomor_surat ? 'selected' : '' }}>
                                [{{ $p->nomor_surat }}] {{ $p->perihal }} &bull; Pagu: Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }} ({{ $p->unit->nama_unit ?? $p->kode_unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Kode Paket --}}
                <div>
                    <label for="kode_paket" class="block text-xs font-bold text-slate-700 mb-1">
                        Kode Unik Paket Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="kode_paket"
                        name="kode_paket"
                        value="{{ old('kode_paket', 'PKT-PNJ-' . date('Ymd') . '-' . rand(100, 999)) }}"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                {{-- Nama Paket --}}
                <div>
                    <label for="nama_paket" class="block text-xs font-bold text-slate-700 mb-1">
                        Nama Paket Pengadaan Barang/Jasa <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="nama_paket"
                        name="nama_paket"
                        value="{{ old('nama_paket') }}"
                        required
                        placeholder="Contoh: Pengadaan Fasilitas Komputer AI Lab TIK"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    />
                </div>

                {{-- Jenis Pengadaan --}}
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
                        <option value="Barang" {{ old('jenis_pengadaan') == 'Barang' ? 'selected' : '' }}>Barang</option>
                        <option value="Jasa Lainnya" {{ old('jenis_pengadaan') == 'Jasa Lainnya' ? 'selected' : '' }}>Jasa Lainnya</option>
                        <option value="Pekerjaan Konstruksi" {{ old('jenis_pengadaan') == 'Pekerjaan Konstruksi' ? 'selected' : '' }}>Pekerjaan Konstruksi</option>
                        <option value="Jasa Konsultansi" {{ old('jenis_pengadaan') == 'Jasa Konsultansi' ? 'selected' : '' }}>Jasa Konsultansi</option>
                    </select>
                </div>

                {{-- Metode Pengadaan --}}
                <div>
                    <label for="metode_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                        Mekanisme / Metode Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="metode_pengadaan"
                        name="metode_pengadaan"
                        required
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="Pengadaan Langsung">Pengadaan Langsung (&le; 50 Juta / Non-Tender)</option>
                        <option value="E-Purchasing">E-Purchasing (Katalog Elektronik / Inaproc)</option>
                        <option value="Tender">Tender Terbuka (SPSE)</option>
                        <option value="Seleksi">Seleksi Konsultansi</option>
                        <option value="Penunjukan Langsung">Penunjukan Langsung</option>
                    </select>
                </div>

                {{-- Nilai HPS (Reactive with x-model) --}}
                <div>
                    <label for="nilai_hps" class="block text-xs font-bold text-slate-700 mb-1">
                        Nilai HPS (Harga Perkiraan Sendiri) Rupiah <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-slate-400">
                            Rp
                        </span>
                        <input
                            type="number"
                            id="nilai_hps"
                            name="nilai_hps"
                            x-model="hps"
                            step="0.01"
                            min="1"
                            required
                            placeholder="75000000"
                            class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-xs text-slate-800 font-mono font-bold focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                        />
                    </div>
                    <div class="flex items-center justify-between text-[11px] mt-1">
                        <span class="text-slate-400">Nominal terformat:</span>
                        <span class="font-bold font-mono text-[#0969da]" x-text="formatRupiah(hps)">Rp 0</span>
                    </div>
                </div>

                {{-- Nomor Memo Pengadaan PPBJ --}}
                <div>
                    <label for="nomor_memo_ppbj" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Memo Pengadaan PPBJ
                        <span class="text-slate-400 font-normal">(Khusus HPS &lt; 200 Juta)</span>
                    </label>
                    <input
                        type="text"
                        id="nomor_memo_ppbj"
                        name="nomor_memo_ppbj"
                        value="{{ old('nomor_memo_ppbj', 'MEMO-PPBJ/' . date('Y') . '/' . rand(100, 999)) }}"
                        :disabled="routing() === 'ppk'"
                        :placeholder="routing() === 'ppk' ? 'Tanpa Memo PPBJ (Otomatis Kosong untuk HPS >= 200 Juta)' : 'MEMO-PPBJ/...'"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 disabled:bg-slate-100 disabled:text-slate-400"
                    />
                    <p class="text-[11px] text-slate-400 mt-1" x-show="routing() === 'ppk'">
                        Aturan bisnis K2/Q7: Paket &ge; Rp 200 Juta tidak menggunakan memo PPBJ.
                    </p>
                </div>

                {{-- Penugasan PPK (Mandatory if >= 200 Juta) --}}
                <div>
                    <label for="id_user_ppk" class="block text-xs font-bold text-slate-700 mb-1">
                        Pejabat Pembuat Komitmen (PPK)
                        <span class="text-rose-500" x-show="routing() === 'ppk'">* (Wajib untuk HPS &ge; 200 Jt)</span>
                        <span class="text-slate-400 font-normal" x-show="routing() !== 'ppk'">(Opsional / Otomatis)</span>
                    </label>
                    <select
                        id="id_user_ppk"
                        name="id_user_ppk"
                        :required="routing() === 'ppk'"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih 1 dari 4 Akun PPK Aktif PNJ --</option>
                        @foreach($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}" {{ old('id_user_ppk') == $ppk->id_user ? 'selected' : '' }}>
                                {{ $ppk->nama }} &bull; {{ $ppk->jabatan ?? 'PPK PNJ' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Sistem membatasi penugasan PPK pada 4 akun PPK definitif (NFR 4).
                    </p>
                </div>

                {{-- Penugasan Pejabat Pengadaan (PP) --}}
                <div>
                    <label for="id_user_pp" class="block text-xs font-bold text-slate-700 mb-1">
                        Pejabat Pengadaan (PP)
                        <span class="text-slate-400 font-normal">(Untuk Jalur 50jt s.d 200jt)</span>
                    </label>
                    <select
                        id="id_user_pp"
                        name="id_user_pp"
                        class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                    >
                        <option value="">-- Pilih Pejabat Pengadaan Aktif --</option>
                        @foreach($ppUsers as $pp)
                            <option value="{{ $pp->id_user }}" {{ old('id_user_pp') == $pp->id_user ? 'selected' : '' }}>
                                {{ $pp->nama }} &bull; Pejabat Pengadaan
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Form Submit Actions --}}
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm hover:shadow transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Daftarkan &amp; Terbitkan Paket Pengadaan
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
