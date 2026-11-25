{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View Blade ini buat untuk halaman pemantauan aktivitas Pejabat Pengadaan (paket
              Rp 50–200 Juta) berisi 4 metrik kinerja termasuk selisih efisiensi negosiasi dan tabel
              progres penelaahan beserta status kelulusan PPK.
--}}
@extends('layouts.app')

@section('title', 'Pantau Aktivitas Pejabat Pengadaan - SIM-PBJ PNJ')

{{--
   Seksi Konten Monitoring Aktivitas Pejabat Pengadaan (`pp.monitoring`).

  Tujuan: menyajikan ikhtisar agregat efisiensi biaya hasil negosiasi PP vs HPS awal sehingga
          Koordinator PPBJ atau pimpinan pemegang izin `pp.monitoring_aktivitas` mudah mengawasi transaksi paket.
  Parameter (Data dari Controller):
    - `$totalPaketPp`, `$paketMenungguReviu`, `$paketSelesaiReviu`, `$totalPaguPp`, `$totalNegosiasiPp`.
  Nilai kembali: Halaman HTML KPI negosiasi PP dan tabel pemantauan paket.
  Contoh pemanggilan: `return view('pp.monitoring', compact(...));`
--}}
@section('content')
<div class="space-y-6">

    {{-- 1. Header & Deskripsi Wewenang --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#213153] mt-2">Pantau Aktivitas Pejabat Pengadaan</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Paket pengadaan jalur Pejabat Pengadaan (&gt; Rp 50 Juta s.d. Rp 200 Juta).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a
                    href="{{ route('paket.index') }}"
                    class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali ke Paket Pengadaan
                </a>
                <a
                    href="{{ route('kontrak.index', ['tab' => 'reviu']) }}"
                    class="px-3.5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Buka Workspace Telaah &amp; Kontrak
                </a>
            </div>
        </div>
    </div>

    {{-- 2. 4 Stat Cards Ringkasan Agregat --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Paket PP --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">Total Paket</span>
                <span class="size-9 rounded-xl bg-blue-50 text-[#0969da] flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold text-[#213153] font-mono">{{ $totalPaketPp }}</span>
                <span class="text-[11px] text-slate-400 ml-1">paket (50-200 Jt)</span>
            </div>
            <div class="mt-2 text-[11px] text-slate-500 flex items-center gap-1.5">
                <span class="inline-block size-1.5 rounded-full bg-[#0969da]"></span>
                <span>Nilai &gt; Rp 50&ndash;200 Juta</span>
            </div>
        </div>

        {{-- Card 2: Menunggu Reviu --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">Menunggu Reviu</span>
                <span class="size-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold text-amber-600 font-mono">{{ $paketMenungguReviu }}</span>
                <span class="text-[11px] text-slate-400 ml-1">paket proses</span>
            </div>
            <div class="mt-2 text-[11px] text-slate-500 flex items-center gap-1.5">
                <span class="inline-block size-1.5 rounded-full bg-amber-500"></span>
                <span>Verifikasi spek &amp; negosiasi</span>
            </div>
        </div>

        {{-- Card 3: Selesai Reviu --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">Selesai Reviu</span>
                <span class="size-9 rounded-xl bg-teal-50 text-[#087F79] flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold text-[#087F79] font-mono">{{ $paketSelesaiReviu }}</span>
                <span class="text-[11px] text-slate-400 ml-1">paket disetujui</span>
            </div>
            <div class="mt-2 text-[11px] text-slate-500 flex items-center gap-1.5">
                <span class="inline-block size-1.5 rounded-full bg-[#087F79]"></span>
                <span>Diteruskan ke PPK</span>
            </div>
        </div>

        {{-- Card 4: Total Pagu vs Negosiasi --}}
        @php
            $hemat = max(0, $totalPaguPp - $totalNegosiasiPp);
            $persenHemat = $totalPaguPp > 0 ? ($hemat / $totalPaguPp) * 100 : 0;
        @endphp
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">Selisih Negosiasi</span>
                <span class="size-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-lg font-bold text-emerald-600 font-mono">
                    Rp {{ number_format($hemat, 0, ',', '.') }}
                </div>
                <div class="text-[11px] text-slate-400">
                    {{ number_format($persenHemat, 1) }}% lebih hemat dari perkiraan Rp {{ number_format($totalPaguPp, 0, ',', '.') }}
                </div>
            </div>
            <div class="mt-2 text-[11px] text-slate-500 flex items-center gap-1.5">
                <span class="inline-block size-1.5 rounded-full bg-emerald-500"></span>
                <span>Hasil negosiasi harga</span>
            </div>
        </div>
    </div>

    {{-- 3. Filter & Pencarian --}}
    <div class="rounded-2xl bg-white p-4 shadow-sm border border-slate-200/80">
        <form method="GET" action="{{ route('monitor-pp.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <div class="relative flex-1">
                <svg class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    type="text"
                    name="keyword"
                    value="{{ request('keyword') }}"
                    placeholder="Cari nomor paket, nama paket, vendor, atau Pejabat Pengadaan..."
                    class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                >
            </div>

            <div class="w-full sm:w-48">
                <select
                    name="status"
                    class="w-full h-10 px-3 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"
                >
                    <option value="">-- Semua Status --</option>
                    <option value="terdaftar" {{ request('status') === 'terdaftar' ? 'selected' : '' }}>Terdaftar (Menunggu Reviu)</option>
                    <option value="reviu_dokumen" {{ request('status') === 'reviu_dokumen' ? 'selected' : '' }}>Sedang Direviu</option>
                    <option value="perlu_perbaikan" {{ request('status') === 'perlu_perbaikan' ? 'selected' : '' }}>Perlu Perbaikan</option>
                    <option value="menunggu_approval_ppk" {{ request('status') === 'menunggu_approval_ppk' ? 'selected' : '' }}>Menunggu Persetujuan PPK</option>
                    <option value="disetujui_ppk_siap_belanja_pp" {{ request('status') === 'disetujui_ppk_siap_belanja_pp' ? 'selected' : '' }}>Disetujui PPK (Siap Belanja)</option>
                    <option value="spk_terbit" {{ request('status') === 'spk_terbit' ? 'selected' : '' }}>Kontrak Terbit</option>
                    <option value="penerimaan_selesai" {{ request('status') === 'penerimaan_selesai' ? 'selected' : '' }}>Barang Diterima</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="h-10 px-4 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
                @if(request()->hasAny(['keyword', 'status']))
                    <a
                        href="{{ route('monitor-pp.index') }}"
                        class="h-10 px-3 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600 hover:bg-slate-50 transition inline-flex items-center"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- 4. Tabel Monitoring Aktivitas PP --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-[#213153] flex items-center gap-2">
                <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Daftar Paket Pejabat Pengadaan (&gt; 50 Juta s.d. 200 Juta)
            </h2>
            <span class="text-xs text-slate-500">
                Menampilkan <strong>{{ $paketPpList->total() }}</strong> paket pengadaan
            </span>
        </div>

        @if($paketPpList->isEmpty())
            <div class="text-center py-12 px-4 bg-slate-50/50">
                <div class="size-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-700">Belum Ada Paket Pejabat Pengadaan</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Paket dengan nilai di atas Rp 50 Juta s.d. Rp 200 Juta akan tampil di sini.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                            <th class="py-3 px-4">Paket &amp; Pengusul</th>
                            <th class="py-3 px-4">Pejabat Pengadaan &amp; PPK</th>
                            <th class="py-3 px-4">Rekanan / Vendor</th>
                            <th class="py-3 px-4 text-right">Perkiraan &amp; Negosiasi</th>
                            <th class="py-3 px-4">Status &amp; Telaah</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($paketPpList as $paket)
                            @php
                                $lastReviu = $paket->reviuDokumen->last();
                                $hps = (float) $paket->nilai_hps;
                                $nego = $paket->nilai_hasil_negosiasi ?? $paket->nominal_harga_akhir;
                                $selisih = $nego ? ($hps - (float) $nego) : null;
                            @endphp
                            <tr class="hover:bg-blue-50/30 transition-colors">
                                {{-- Kolom 1: Paket & Pengusul --}}
                                <td class="py-3 px-4">
                                    <div class="font-bold text-[#213153]">{{ $paket->nama_paket }}</div>
                                    <div class="text-[11px] font-mono text-[#0969da] mt-0.5">{{ $paket->nomor_paket }}</div>
                                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1.5">
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-medium">
                                            {{ $paket->permohonan?->unit?->nama_unit ?? 'Unit Kerja PNJ' }}
                                        </span>
                                        <span>&bull;</span>
                                        <span>{{ ucwords(str_replace('_', ' ', $paket->jenis_pengadaan)) }}</span>
                                    </div>
                                </td>

                                {{-- Kolom 2: Pejabat PP & PPK --}}
                                <td class="py-3 px-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-[#0969da]">Pejabat</span>
                                            <span class="font-medium text-slate-800">{{ $paket->ppUser?->nama ?? 'Belum Ditugaskan' }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700">PPK</span>
                                            <span class="text-slate-600">{{ $paket->ppkUser?->nama ?? 'Belum Ditugaskan' }}</span>
                                        </div>
                                    </div>
                                </td>

                                {{-- Kolom 3: Vendor --}}
                                <td class="py-3 px-4">
                                    @if($paket->vendor)
                                        <div class="font-bold text-slate-800">{{ $paket->vendor?->nama_vendor ?? '-' }}</div>
                                        @if($paket->vendor?->file_dokumen_legalitas)
                                            <a
                                                href="{{ asset('storage/' . $paket->vendor->file_dokumen_legalitas) }}"
                                                target="_blank"
                                                class="text-[11px] text-[#0969da] hover:underline inline-flex items-center gap-1 mt-0.5"
                                            >
                                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                Legalitas Vendor
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-[11px] italic text-slate-400">Belum ada rekanan</span>
                                    @endif
                                </td>

                                {{-- Kolom 4: Perkiraan & Hasil Negosiasi --}}
                                <td class="py-3 px-4 text-right">
                                    <div class="font-mono text-slate-600 text-[11px]">
                                        Perkiraan: Rp {{ number_format($hps, 0, ',', '.') }}
                                    </div>
                                    @if($nego)
                                        <div class="font-mono font-bold text-emerald-600 text-xs mt-0.5">
                                            Nego: Rp {{ number_format((float) $nego, 0, ',', '.') }}
                                        </div>
                                        @if($selisih && $selisih > 0)
                                            <div class="text-[10px] text-emerald-500 font-medium">
                                                Hemat: Rp {{ number_format($selisih, 0, ',', '.') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-[11px] italic text-amber-500 mt-0.5 block">Menunggu Negosiasi</span>
                                    @endif
                                </td>

                                {{-- Kolom 5: Status & Telaah --}}
                                <td class="py-3 px-4">
                                    @if($paket->status_paket === 'perlu_perbaikan')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            Perlu Perbaikan
                                        </span>
                                    @elseif($paket->status_paket === 'menunggu_approval_ppk')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            Menunggu Persetujuan PPK
                                        </span>
                                    @elseif($paket->status_paket === 'disetujui_ppk_siap_belanja_pp')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                            Disetujui PPK (Siap Belanja)
                                        </span>
                                    @elseif($paket->status_paket === 'spk_terbit')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            Kontrak Terbit
                                        </span>
                                    @elseif($paket->status_paket === 'penerimaan_selesai')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Barang Diterima
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ strtoupper(str_replace('_', ' ', $paket->status_paket)) }}
                                        </span>
                                    @endif

                                    @if($lastReviu && !empty($lastReviu->catatan_perbaikan))
                                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2" title="{{ $lastReviu->catatan_perbaikan }}">
                                            <strong>Catatan {{ strtoupper($lastReviu->role_reviewer) }}:</strong> {{ $lastReviu->catatan_perbaikan }}
                                        </p>
                                    @elseif(!empty($paket->catatan_verifikasi_pp))
                                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2" title="{{ $paket->catatan_verifikasi_pp }}">
                                            <strong>Catatan Pejabat Pengadaan:</strong> {{ $paket->catatan_verifikasi_pp }}
                                        </p>
                                    @endif
                                </td>

                                {{-- Kolom 6: Aksi --}}
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a
                                            href="{{ route('paket.show', $paket->nomor_paket) }}"
                                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#0969da] shadow-xs transition"
                                            title="Lihat Detail Siklus Paket"
                                        >
                                            Detail
                                        </a>
                                        @if($paket->status_paket === 'perlu_perbaikan' && Auth::user()->hasPermission('paket.create'))
                                            <a
                                                href="{{ route('paket.edit', $paket->nomor_paket) }}"
                                                class="px-2.5 py-1 rounded-lg bg-amber-500 text-xs font-semibold text-white hover:bg-amber-600 shadow-xs transition"
                                                title="Perbaiki Dokumen Pengadaan"
                                            >
                                                Perbaiki
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($paketPpList->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $paketPpList->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection
