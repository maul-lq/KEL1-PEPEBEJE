@extends('layouts.app')

@section('title', 'Manajemen Paket Pengadaan & Penugasan PPK - SIM-PBJ PNJ')

@section('content')
<div x-data="{ selectedPaket: '' }" class="space-y-6">

    {{-- 1. Header & Quick Actions --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                        Siklus 3: Registrasi Paket &amp; Routing
                    </span>
                    <span class="text-xs text-slate-400 font-medium">FR 7 &bull; FR 13</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Manajemen Paket Pengadaan &amp; Penugasan PPK</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Unit Kerja Pengadaan Barang dan Jasa (UKPBJ / PPBJ) Politeknik Negeri Jakarta
                </p>
            </div>

            @if(Auth::user() && in_array(Auth::user()->role, ['ppbj', 'admin']))
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="$dispatch('open-modal', 'modal-split-jenis')"
                        class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Split Jenis Pengadaan
                    </button>

                    <button
                        type="button"
                        @click="selectedPaket = ''; $dispatch('open-modal', 'modal-reassign-ppk')"
                        class="px-3.5 py-2 rounded-xl border border-amber-200 bg-amber-50 text-xs font-bold text-amber-800 hover:bg-amber-100 shadow-sm transition inline-flex items-center gap-1.5"
                    >
                        <svg class="size-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Alih Tugas PPK
                    </button>

                    <a
                        href="{{ route('paket.create') }}"
                        class="px-4 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm hover:shadow transition inline-flex items-center gap-1.5"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Registrasi Paket Baru
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- 2. KPI Stat Cards (4 Columns) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Paket Pengadaan"
            :value="$stats['total_paket'] ?? $paketList->total()"
            icon="document"
            color="navy"
            subtitle="Seluruh siklus pengadaan terdaftar"
        />

        <x-stat-card
            title="Pengadaan Langsung"
            :value="$stats['routing_langsung'] ?? 0"
            icon="box"
            color="teal"
            badge="&le; Rp 50 Jt"
            badgeColor="bg-teal-50 text-teal-700 border-teal-200"
            subtitle="Langsung terbit SPK oleh PPBJ"
        />

        <x-stat-card
            title="Pejabat Pengadaan (PP)"
            :value="$stats['routing_pp'] ?? 0"
            icon="users"
            color="amber"
            badge="Rp 50 - 200 Jt"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Telaah teknis &amp; negosiasi harga"
        />

        <x-stat-card
            title="PPK Mandiri"
            :value="$stats['routing_ppk'] ?? 0"
            icon="check"
            color="blue"
            badge="&ge; Rp 200 Jt"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="4 Akun PPK aktif (tanpa memo PPBJ)"
        />
    </div>

    {{-- 3. Filter Bar --}}
    <x-filter-bar
        :action="route('paket.index')"
        searchPlaceholder="Cari kode paket, nama paket, atau nomor surat..."
    >
        <x-slot:filters>
            <select
                name="status"
                class="h-10 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
            >
                <option value="">Semua Status Paket</option>
                <option value="terdaftar" {{ request('status') === 'terdaftar' ? 'selected' : '' }}>Terdaftar</option>
                <option value="reviu_dokumen" {{ request('status') === 'reviu_dokumen' ? 'selected' : '' }}>Reviu Dokumen</option>
                <option value="reviu_dokumen_disetujui" {{ request('status') === 'reviu_dokumen_disetujui' ? 'selected' : '' }}>Reviu Disetujui</option>
                <option value="spk_terbit" {{ request('status') === 'spk_terbit' ? 'selected' : '' }}>SPK Terbit</option>
                <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
            </select>

            <select
                name="jalur_routing"
                class="h-10 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
            >
                <option value="">Semua Jalur Routing</option>
                <option value="langsung_sd_50jt" {{ request('jalur_routing') === 'langsung_sd_50jt' ? 'selected' : '' }}>&le; 50 Juta (Langsung)</option>
                <option value="pp_50jt_sd_200jt" {{ request('jalur_routing') === 'pp_50jt_sd_200jt' ? 'selected' : '' }}>50 - 200 Juta (Pejabat Pengadaan)</option>
                <option value="ppk_diatas_200jt" {{ request('jalur_routing') === 'ppk_diatas_200jt' ? 'selected' : '' }}>&ge; 200 Juta (PPK Mandiri)</option>
            </select>

            <select
                name="jenis_pengadaan"
                class="h-10 rounded-xl border border-slate-200 bg-slate-50/60 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all"
            >
                <option value="">Semua Jenis Pengadaan</option>
                <option value="barang" {{ request('jenis_pengadaan') === 'barang' ? 'selected' : '' }}>Barang</option>
                <option value="jasa" {{ request('jenis_pengadaan') === 'jasa' ? 'selected' : '' }}>Jasa</option>
                <option value="konstruksi" {{ request('jenis_pengadaan') === 'konstruksi' ? 'selected' : '' }}>Konstruksi</option>
                <option value="jasa_lainnya" {{ request('jenis_pengadaan') === 'jasa_lainnya' ? 'selected' : '' }}>Jasa Lainnya</option>
                <option value="jasa_konsultansi" {{ request('jenis_pengadaan') === 'jasa_konsultansi' ? 'selected' : '' }}>Jasa Konsultansi</option>
            </select>
        </x-slot:filters>
    </x-filter-bar>

    {{-- 4. Tabel Paket Pengadaan --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Daftar Paket Pengadaan Terdaftar</h3>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan {{ $paketList->count() }} dari {{ $paketList->total() }} total berkas paket</p>
            </div>
        </div>

        @if($paketList->isEmpty())
            <div class="text-center py-12 px-4">
                <div class="mx-auto size-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-700">Tidak Ada Paket Pengadaan</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Belum ada paket pengadaan yang terdaftar atau sesuai dengan kriteria filter saat ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3.5">Kode &amp; Nama Paket</th>
                            <th class="px-5 py-3.5">Usulan &amp; Unit</th>
                            <th class="px-5 py-3.5">Jenis &amp; Metode</th>
                            <th class="px-5 py-3.5">Nilai HPS</th>
                            <th class="px-5 py-3.5">Jalur Routing</th>
                            <th class="px-5 py-3.5">Pejabat &amp; PPK</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($paketList as $pkt)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-5 py-4">
                                    <a href="{{ route('paket.show', $pkt->nomor_paket) }}" class="font-mono font-bold text-[#0969da] hover:underline block">
                                        {{ $pkt->kode_paket ?? $pkt->nomor_paket }}
                                    </a>
                                    <span class="text-xs font-semibold text-slate-800 line-clamp-1 mt-0.5">
                                        {{ $pkt->nama_paket }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="text-xs font-medium text-slate-800 block">
                                        {{ $pkt->permohonan->perihal ?? $pkt->nomor_surat }}
                                    </span>
                                    <span class="text-[11px] text-slate-400 block mt-0.5 font-mono">
                                        {{ $pkt->nomor_surat }} &bull; {{ $pkt->permohonan->unit->nama_unit ?? 'Unit PNJ' }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-slate-600">
                                    <span class="font-medium text-slate-800 block capitalize">{{ str_replace('_', ' ', $pkt->jenis_pengadaan) }}</span>
                                    <span class="text-[11px] text-slate-400 block mt-0.5">{{ $pkt->metode_pengadaan }}</span>
                                </td>

                                <td class="px-5 py-4 font-mono font-bold text-emerald-600">
                                    Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-4">
                                    @if($pkt->jalur_routing === 'ppk_diatas_200jt')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            &ge; 200 Jt (PPK Mandiri)
                                        </span>
                                    @elseif($pkt->jalur_routing === 'pp_50jt_sd_200jt')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            50 - 200 Jt (PP &amp; PPK)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                            &le; 50 Jt (Langsung)
                                        </span>
                                    @endif
                                    <div class="text-[10px] text-slate-400 mt-1 font-mono">
                                        @if($pkt->jalur_routing === 'ppk_diatas_200jt')
                                            <em>Tanpa Memo PPBJ</em>
                                        @else
                                            {{ $pkt->nomor_memo_ppbj ?? '-' }}
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4 text-slate-700">
                                    <div class="text-xs font-semibold text-slate-900">
                                        PPK: {{ $pkt->ppkUser->nama ?? ($pkt->jalur_routing === 'langsung_sd_50jt' ? '(Pengadaan Langsung)' : 'Belum Ditugaskan') }}
                                    </div>
                                    @if($pkt->ppUser)
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            PP: {{ $pkt->ppUser->nama }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    <x-status-badge :status="$pkt->status_paket" size="sm" />
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a
                                            href="{{ route('paket.show', $pkt->nomor_paket) }}"
                                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs"
                                            title="Lihat Detail Paket"
                                        >
                                            Detail
                                        </a>

                                        @if(Auth::user() && in_array(Auth::user()->role, ['ppbj', 'admin']))
                                            <button
                                                type="button"
                                                @click="selectedPaket = '{{ $pkt->nomor_paket }}'; $dispatch('open-modal', 'modal-reassign-ppk')"
                                                class="px-2.5 py-1 rounded-lg border border-amber-200 bg-amber-50 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition shadow-xs"
                                                title="Alih Tugas PPK"
                                            >
                                                Alih Tugas
                                            </button>
                                        @endif

                                        <a
                                            href="{{ route('kontrak.index') }}"
                                            class="px-2.5 py-1 rounded-lg bg-[#213153] text-xs font-semibold text-white hover:bg-slate-800 transition shadow-xs"
                                            title="Ke Modul Kontrak / SPK"
                                        >
                                            SPK &rarr;
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $paketList->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- 5. Tabel Jejak Audit Mutasi & Penugasan PPK (FR 13) --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Riwayat Mutasi &amp; Penugasan PPK (Audit Trail)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Catatan riwayat perpindahan penugasan paket antar akun PPK aktif (FR 13 &bull; NFR 4.2)</p>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $riwayatPenugasan->count() }} Transaksi</span>
        </div>

        @if($riwayatPenugasan->isEmpty())
            <div class="text-center py-8 px-4 text-slate-400 text-xs">
                Belum ada catatan riwayat mutasi penugasan PPK.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3">Waktu Penugasan</th>
                            <th class="px-5 py-3">Paket Pengadaan</th>
                            <th class="px-5 py-3">PPK Sebelumnya</th>
                            <th class="px-5 py-3">PPK Baru</th>
                            <th class="px-5 py-3">Ditetapkan Oleh</th>
                            <th class="px-5 py-3">Alasan Pengalihan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($riwayatPenugasan as $rw)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-3.5 font-mono text-slate-500 text-[11px]">
                                    {{ $rw->tanggal_penugasan ? \Carbon\Carbon::parse($rw->tanggal_penugasan)->format('d/m/Y H:i') : ($rw->created_at ? $rw->created_at->format('d/m/Y H:i') : '-') }}
                                </td>
                                <td class="px-5 py-3.5 font-mono font-bold text-slate-800">
                                    {{ $rw->kode_paket ?? $rw->nomor_paket }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-600">
                                    {{ $rw->ppkLama->nama ?? 'Penugasan Awal' }}
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-emerald-700">
                                    &rarr; {{ $rw->ppkBaru->nama ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ $rw->userPenetap->nama ?? ($rw->diubahOleh->nama ?? 'Sistem') }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-600 text-[11px] max-w-xs">
                                    {{ $rw->alasan_pengalihan ?? ($rw->alasan_perubahan ?? '-') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 6. Interactive Modal: Split Jenis Pengadaan (FR 7.2) --}}
    <x-modal id="modal-split-jenis" title="Pemecahan Jenis Pengadaan (Split Jenis)" subtitle="Memecah usulan pengadaan disetujui berdasarkan jenis (Barang / Jasa / Konstruksi) - FR 7.2" maxWidth="2xl">
        <form action="{{ route('paket.split-jenis') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="modal_nomor_surat_induk" class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Surat Permohonan Induk <span class="text-rose-500">*</span>
                </label>
                <select id="modal_nomor_surat_induk" name="nomor_surat_induk" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih Berkas Permohonan Induk Disetujui --</option>
                    @foreach($permohonanDisetujui as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} (Pagu: Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="modal_nomor_surat_baru" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Surat Anak / Split <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="modal_nomor_surat_baru" name="nomor_surat_baru" required placeholder="Contoh: 123/PNJ/2026.PBJ.1" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div>
                    <label for="modal_jenis_pengadaan_split" class="block text-xs font-bold text-slate-700 mb-1">
                        Jenis Pengadaan Pecahan <span class="text-rose-500">*</span>
                    </label>
                    <select id="modal_jenis_pengadaan_split" name="jenis_pengadaan_split" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        <option value="barang">Pengadaan Barang</option>
                        <option value="jasa">Pengadaan Jasa Lainnya</option>
                        <option value="konstruksi">Pekerjaan Konstruksi</option>
                        <option value="jasa_konsultansi">Jasa Konsultansi</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="modal_judul_pengadaan" class="block text-xs font-bold text-slate-700 mb-1">
                    Judul Pengadaan Paket Hasil Pemecahan <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="modal_judul_pengadaan" name="judul_pengadaan" required placeholder="Contoh: Pengadaan Komputer Server AI (Pecahan Barang)" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <div>
                <label for="modal_estimasi_total_pagu" class="block text-xs font-bold text-slate-700 mb-1">
                    Nominal Pagu Pecahan (Rupiah) <span class="text-rose-500">*</span>
                </label>
                <input type="number" id="modal_estimasi_total_pagu" name="estimasi_total_pagu" step="0.01" min="1" required placeholder="Contoh: 45000000" class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
            </div>

            <div>
                <label for="modal_alasan_pemecahan" class="block text-xs font-bold text-slate-700 mb-1">
                    Alasan Pemecahan Jenis Pengadaan <span class="text-rose-500">*</span>
                </label>
                <textarea id="modal_alasan_pemecahan" name="alasan_pemecahan" rows="3" required placeholder="Jelaskan kebutuhan pemisahan jenis pengadaan (misal: pemisahan belanja modal komputer server dan belanja jasa instalasi jaringan)..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-split-jenis')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition">
                    Proses Pemecahan Berkas
                </button>
            </div>
        </form>
    </x-modal>

    {{-- 7. Interactive Modal: Alih Tugas Penugasan PPK (FR 13) --}}
    <x-modal id="modal-reassign-ppk" title="Alih Tugas Penugasan PPK" subtitle="Pengalihan berkas paket pengadaan ke akun PPK baru dengan pencatatan audit - FR 13" maxWidth="lg">
        <form action="{{ route('paket.reassign-ppk') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="modal_kode_paket" class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Paket Pengadaan <span class="text-rose-500">*</span>
                </label>
                <select id="modal_kode_paket" name="kode_paket" x-model="selectedPaket" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList as $pkt)
                        <option value="{{ $pkt->nomor_paket }}">
                            [{{ $pkt->kode_paket ?? $pkt->nomor_paket }}] {{ $pkt->nama_paket }} (PPK saat ini: {{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="modal_id_user_ppk_baru" class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Akun PPK Pengganti <span class="text-rose-500">*</span>
                </label>
                <select id="modal_id_user_ppk_baru" name="id_user_ppk_baru" required class="w-full h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih 1 dari 4 Akun PPK Aktif PNJ --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}">
                            {{ $ppk->nama }} &bull; {{ $ppk->jabatan ?? 'Pejabat Pembuat Komitmen' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Hanya 4 akun PPK aktif yang berwenang menerima pendelegasian paket (NFR 4).</p>
            </div>

            <div>
                <label for="modal_alasan_pengalihan" class="block text-xs font-bold text-slate-700 mb-1">
                    Alasan Perubahan / Alih Tugas Penugasan PPK <span class="text-rose-500">*</span>
                </label>
                <textarea id="modal_alasan_pengalihan" name="alasan_pengalihan" rows="3" required placeholder="Tuliskan alasan resmi alih tugas (misal: rotasi tugas dinas, beban kerja paket, atau penugasan surat perintah pimpinan)..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" @click="$dispatch('close-modal', 'modal-reassign-ppk')" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 text-xs font-bold text-white hover:bg-amber-700 shadow-sm transition">
                    Simpan Alih Tugas PPK
                </button>
            </div>
        </form>
    </x-modal>

</div>
@endsection
