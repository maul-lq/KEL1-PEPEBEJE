@extends('layouts.app')

@section('title', 'Verifikasi Eksekutif Wadir 2 - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#1E3156]">Antrean Keputusan Eksekutif Wakil Direktur II</h1>
            <p class="text-sm text-slate-500 mt-1">
                Wakil Direktur Bidang Umum dan Keuangan memegang wewenang eksekutif institusi untuk memberikan keputusan mutlak persetujuan (ACC) atau penolakan/revisi atas usulan pengadaan barang dan jasa.
            </p>
        </div>
        <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">
            Peran: Wakil Direktur II (Otoritas Eksekutif)
        </span>
    </div>

    @if($permohonanAntrean->isEmpty())
        <div class="rounded-2xl bg-white p-12 text-center shadow-sm border border-slate-100">
            <svg class="mx-auto size-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="mt-2 text-base font-medium text-slate-800">Tidak Ada Antrean Usulan</h3>
            <p class="mt-1 text-sm text-slate-400">Saat ini tidak ada permohonan pengadaan yang menunggu keputusan persetujuan Wadir II.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($permohonanAntrean as $p)
                <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <div>
                            <span class="font-mono text-sm font-bold text-slate-900">{{ $p->nomor_surat }}</span>
                            <span class="ml-2 text-xs text-slate-500">Unit Pengusul: <strong>{{ $p->unit->nama_unit ?? $p->kode_unit }}</strong> ({{ $p->kode_unit }})</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400">Tanggal Usulan: {{ $p->tanggal_pengajuan ? date('d/m/Y', strtotime($p->tanggal_pengajuan)) : '-' }}</span>
                            <div class="text-sm font-bold text-[#0088E8]">Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h2 class="text-lg font-semibold text-slate-800">{{ $p->perihal }}</h2>
                        <p class="text-xs text-slate-600 mt-1">{{ $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa institusi PNJ' }}</p>
                    </div>

                    {{-- Catatan Telaah Staf Bidang 2 --}}
                    @php
                        $vpStaff = $p->verifikasiParalel->where('role_verifikator', 'staff_bidang_2')->first();
                    @endphp
                    @if($vpStaff && $vpStaff->catatan_alasan)
                        <div class="mt-4 rounded-xl bg-blue-50/70 p-3.5 border border-blue-100 text-xs">
                            <div class="font-semibold text-blue-900 flex items-center gap-1.5">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Rekomendasi Hasil Telaah Staf Tata Usaha Bidang II:
                            </div>
                            <p class="text-blue-800 mt-1">{{ $vpStaff->catatan_alasan }}</p>
                        </div>
                    @endif

                    <div class="mt-4 flex items-center gap-3">
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors">
                            <svg class="size-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Lihat Dokumen Surat Srikandi (PDF)
                        </a>
                        <a href="{{ route('permohonan.show', $p->nomor_surat) }}" class="text-xs text-blue-600 hover:underline">
                            Detail &amp; Lacak Permohonan &rarr;
                        </a>
                    </div>

                    {{-- Form Keputusan Eksekutif Wadir 2 --}}
                    <form action="{{ route('verifikasi.wadir-2.keputusan') }}" method="POST" class="mt-6 rounded-xl bg-slate-50 p-5 border border-slate-200/70 space-y-4">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Keputusan Mutlak Wakil Direktur II</div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-3 cursor-pointer hover:bg-emerald-50/40 hover:border-emerald-300 transition-colors">
                                <input type="radio" name="keputusan" value="disetujui" required class="text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-xs font-bold text-emerald-800">SETUJUI USULAN (ACC)</div>
                                    <div class="text-[10px] text-slate-500">Disetujui untuk diproses lebih lanjut ke PPBJ. Catatan bersifat opsional.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-3 cursor-pointer hover:bg-rose-50/40 hover:border-rose-300 transition-colors">
                                <input type="radio" name="keputusan" value="ditolak" required class="text-rose-600 focus:ring-rose-500">
                                <div>
                                    <div class="text-xs font-bold text-rose-800">TOLAK / MINTA REVISI</div>
                                    <div class="text-[10px] text-slate-500">Usulan dikembalikan ke pengusul. <u>Alasan wajib diisi</u>.</div>
                                </div>
                            </label>
                        </div>

                        <div>
                            <label for="catatan_alasan_{{ $p->nomor_surat }}" class="block text-xs font-semibold text-slate-700 mb-1">
                                Catatan / Alasan Keputusan Wadir II
                            </label>
                            <textarea id="catatan_alasan_{{ $p->nomor_surat }}" name="catatan_alasan" rows="2"
                                      placeholder="Wajib diisi jika menolak (misal: 'Spesifikasi perlu disesuaikan dengan standar laboratorium atau pagu tidak efisien'). Opsional jika disetujui."
                                      class="w-full rounded-lg border border-slate-300 p-2.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow hover:bg-indigo-700 transition-colors">
                                Simpan Keputusan Wadir II
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
