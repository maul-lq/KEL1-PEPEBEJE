@extends('layouts.app')

@section('title', 'Detail Permohonan - ' . $permohonan->nomor_surat)

@section('content')
@php
    $st = strtolower((string) ($permohonan->status_permohonan ?? $permohonan->status ?? 'diajukan'));
    $currentStep = 1;
    if (in_array($st, ['reviu_paralel', 'pending_anggaran', 'hold', 'perlu_revisi', 'revisi'])) $currentStep = 2;
    elseif (in_array($st, ['disetujui', 'diproses_ppbj'])) $currentStep = 3;
    elseif (in_array($st, ['reviu_dokumen'])) $currentStep = 4;
    elseif (in_array($st, ['spk_terbit', 'kontrak'])) $currentStep = 5;
    elseif (in_array($st, ['pengingat_h2', 'pengiriman'])) $currentStep = 6;
    elseif (in_array($st, ['pemeriksaan_fisik', 'bast_terbit'])) $currentStep = 7;
    elseif (in_array($st, ['selesai', 'sp2d_lunas'])) $currentStep = 8;
@endphp

<div class="mx-auto max-w-[1650px] space-y-6">

    <!-- 1. HEADER HALAMAN & TOMBOL AKSI -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('permohonan.index') }}" 
               class="inline-flex size-10 items-center justify-center rounded-xl border border-slate-200/80 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-[#0969da] transition-all"
               title="Kembali ke Daftar Permohonan">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-[#213153]">
                        {{ $permohonan->perihal ?? $permohonan->judul_pengadaan }}
                    </h1>
                </div>
                <p class="text-xs md:text-sm text-slate-500 mt-1">
                    No. Surat Srikandi: <span class="font-mono font-bold text-[#0969da]">{{ $permohonan->nomor_surat }}</span> &bull;
                    Diajukan pada: {{ $permohonan->tanggal_pengajuan ? $permohonan->tanggal_pengajuan->format('d M Y H:i') : '-' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <x-status-badge :status="$permohonan->status_permohonan ?? $permohonan->status" size="md" />

            <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $permohonan->nomor_surat]) }}" 
               target="_blank"
               class="inline-flex items-center gap-2 rounded-xl bg-[#0969da] px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Unduh PDF Resmi
            </a>

            @if(in_array($st, ['draft', 'diajukan', 'ditolak']) && (Auth::user()->hasRole('admin') || Auth::user()->id_user === $permohonan->id_user))
                <form action="{{ route('permohonan.destroy', $permohonan->nomor_surat) }}" 
                      method="POST" 
                      onsubmit="return confirm('Apakah Anda yakin ingin membatalkan dan menghapus usulan pengadaan ini? Tindakan ini tidak dapat dibatalkan.');"
                      class="inline-block">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition-colors cursor-pointer">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Batalkan Usulan
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- 2. KARTU RINGKASAN METADATA (x-stat-card) -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card title="Unit Pengusul" 
                     :value="$permohonan->unit->nama_unit ?? $permohonan->kode_unit" 
                     :subtitle="'Kode Unit: ' . $permohonan->kode_unit" 
                     color="blue" />

        <x-stat-card title="Pegawai Pemohon" 
                     :value="$permohonan->user->nama ?? '-'" 
                     :subtitle="'NIP: ' . ($permohonan->user->nip ?? '-')" 
                     color="navy" />

        <x-stat-card title="Estimasi Pagu" 
                     :value="'Rp ' . number_format($permohonan->estimasi_total_pagu ?? $permohonan->anggaran_diajukan, 0, ',', '.')" 
                     subtitle="Sesuai KAK usulan" 
                     color="teal" />

        <x-stat-card title="Mata Anggaran (MAK)" 
                     :value="$permohonan->kode_mak ?: 'Belum Ditetapkan'" 
                     :subtitle="$permohonan->anggaranMak->uraian_mak ?? 'Ditetapkan Bagian Perencanaan'" 
                     color="amber" />
    </div>

    <!-- 3. KONTEN UTAMA DUA KOLOM -->
    <div class="grid gap-6 lg:grid-cols-3 items-start">

        <!-- KOLOM KIRI (2 COLS): PELACAKAN 8 SIKLUS & FORM REVISI -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Stepper 8 Siklus Pelacakan -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-[#0969da]">Alur Eksekusi Pengadaan</span>
                        <h2 class="text-xl font-bold text-[#213153] mt-0.5">Pelacakan 8 Tahapan Siklus Pengadaan</h2>
                    </div>
                    <span class="text-xs font-bold font-mono text-[#0969da]">Tahap {{ $currentStep }} dari 8</span>
                </div>

                <x-stepper-8-siklus mode="vertical" 
                                    :currentStep="$currentStep" 
                                    :status="$permohonan->status_permohonan ?? $permohonan->status" />
            </div>

            <!-- Formulir Revisi (Tampil jika Ditolak / Perlu Revisi) -->
            @if(in_array($st, ['ditolak', 'revisi', 'perlu_revisi']) && (Auth::user()->hasRole('user') || Auth::user()->hasRole('admin') || Auth::user()->id_user === $permohonan->id_user))
                <div class="rounded-2xl bg-amber-50/80 border border-amber-200 p-6 shadow-xs space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="size-8 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-amber-900">Ajukan Revisi Usulan Permohonan (Figma 509:1651)</h3>
                            <p class="text-xs text-amber-800 mt-0.5">
                                Usulan ini memerlukan penyesuaian berkas atau rincian harga. Silakan isi form di bawah ini untuk menerbitkan surat revisi baru.
                            </p>
                        </div>
                    </div>

                    @if($permohonan->alasan_revisi)
                        <div class="rounded-xl bg-white/80 p-3.5 border border-amber-200 text-xs text-amber-900">
                            <strong>Catatan Telaah Terakhir:</strong>
                            <p class="mt-0.5 text-slate-700">{{ $permohonan->alasan_revisi }}</p>
                        </div>
                    @endif

                    <form action="{{ route('permohonan.revisi') }}" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2">
                        @csrf
                        <input type="hidden" name="nomor_surat_lama" value="{{ $permohonan->nomor_surat }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">Nomor Surat Revisi Baru Srikandi <span class="text-rose-500">*</span></label>
                                <input type="text" 
                                       name="nomor_surat_baru" 
                                       value="{{ $permohonan->nomor_surat }}-REV" 
                                       required
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-bold text-slate-700">Penyesuaian Anggaran Pagu (Rp) <span class="text-rose-500">*</span></label>
                                <input type="number" 
                                       name="anggaran_diajukan" 
                                       value="{{ $permohonan->estimasi_total_pagu ?? $permohonan->anggaran_diajukan }}" 
                                       step="0.01" 
                                       min="1" 
                                       required
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-mono font-medium focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                            </div>

                            <div class="space-y-1 md:col-span-2">
                                <label class="text-xs font-bold text-slate-700">Perihal Kegiatan Baru <span class="text-rose-500">*</span></label>
                                <input type="text" 
                                       name="perihal" 
                                       value="{{ $permohonan->perihal ?? $permohonan->judul_pengadaan }}" 
                                       required
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20">
                            </div>

                            <div class="space-y-1 md:col-span-2">
                                <label class="text-xs font-bold text-slate-700">Keterangan Perbaikan Berkas <span class="text-rose-500">*</span></label>
                                <textarea name="keterangan_revisi" 
                                          rows="3" 
                                          required 
                                          placeholder="Jelaskan poin perbaikan yang telah dilakukan pada KAK atau spesifikasi..."
                                          class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium focus:border-[#0969da] focus:outline-none focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                            </div>

                            <div class="space-y-1 md:col-span-2">
                                <label class="text-xs font-bold text-slate-700">Unggah KAK Baru (PDF Maks. 10 MB)</label>
                                <input type="file" 
                                       name="file_kak" 
                                       accept=".pdf"
                                       class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 focus:outline-none">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" 
                                    class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-6 py-2.5 text-xs font-bold shadow-md shadow-amber-600/20 transition-all cursor-pointer">
                                Kirimkan Dokumen Revisi
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>

        <!-- KOLOM KANAN (1 COL): CATATAN VERIFIKASI & SILSILAH BERKAS -->
        <div class="space-y-6">

            <!-- CATATAN VERIFIKASI TIGA PIHAK -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <svg class="size-5 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <h3 class="text-base font-bold text-[#213153]">Catatan Verifikasi Tiga Pihak</h3>
                </div>

                @if($permohonan->verifikasiParalel->isEmpty())
                    <p class="text-xs text-slate-400 italic">Belum ada catatan verifikasi paralel pada berkas ini.</p>
                @else
                    <div class="space-y-3">
                        @foreach($permohonan->verifikasiParalel as $vp)
                            <div class="rounded-xl border border-slate-100 p-4 bg-slate-50/50 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 uppercase">
                                        {{ str_replace('_', ' ', $vp->role_verifikator ?? $vp->peran_verifikator) }}
                                    </span>
                                    <x-status-badge :status="$vp->status_keputusan ?? $vp->status_verifikasi" size="sm" />
                                </div>
                                <div class="text-[11px] text-slate-500">{{ $vp->user->nama ?? '-' }}</div>
                                <p class="text-xs text-slate-700 bg-white p-2.5 rounded-lg border border-slate-100 leading-relaxed">
                                    {{ $vp->catatan_alasan ?? $vp->catatan ?? 'Tidak ada catatan tertulis.' }}
                                </p>
                                <div class="text-[10px] text-slate-400 text-right">
                                    {{ $vp->tanggal_keputusan ? date('d/m/Y H:i', strtotime($vp->tanggal_keputusan)) : ($vp->tanggal_verifikasi ? $vp->tanggal_verifikasi->format('d/m/Y H:i') : '-') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- SILSILAH USULAN (REVISI & SPLIT) -->
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <svg class="size-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <h3 class="text-base font-bold text-[#213153]">Silsilah Berkas (Revisi &amp; Split)</h3>
                </div>

                <div class="text-xs space-y-3">
                    <div>
                        <span class="font-bold text-slate-700">Pohon Revisi:</span>
                        @if($permohonan->isRevisi())
                            <p class="text-slate-600 mt-1">
                                Revisi ke-{{ $permohonan->revisi_ke }} dari: 
                                <a href="{{ route('permohonan.show', $permohonan->nomor_surat_revisi) }}" class="font-mono text-[#0969da] hover:underline font-bold">
                                    {{ $permohonan->nomor_surat_revisi }}
                                </a>
                            </p>
                        @else
                            <p class="text-slate-400 italic mt-0.5">Surat usulan awal.</p>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-slate-100">
                        <span class="font-bold text-slate-700">Pemecahan (Split MAK):</span>
                        @if($permohonan->isSplit())
                            <p class="text-slate-600 mt-1">
                                Hasil split dari: 
                                <a href="{{ route('permohonan.show', $permohonan->nomor_surat_induk_split) }}" class="font-mono text-[#0969da] hover:underline font-bold">
                                    {{ $permohonan->nomor_surat_induk_split }}
                                </a>
                            </p>
                        @else
                            <p class="text-slate-400 italic mt-0.5">Usulan mandiri (Bukan hasil pemecahan).</p>
                        @endif

                        @if($permohonan->anakSplit && $permohonan->anakSplit->isNotEmpty())
                            <div class="mt-2.5 font-bold text-slate-700">Berkas Pecahan (Anak Split):</div>
                            <ul class="list-disc pl-4 space-y-1 text-slate-600 mt-1">
                                @foreach($permohonan->anakSplit as $anak)
                                    <li>
                                        <a href="{{ route('permohonan.show', $anak->nomor_surat) }}" class="font-mono text-[#0969da] hover:underline font-bold">
                                            {{ $anak->nomor_surat }}
                                        </a>
                                        ({{ $anak->perihal ?? $anak->judul_pengadaan }})
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
