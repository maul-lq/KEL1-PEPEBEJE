@extends('layouts.app')

@section('title', 'Detail Permohonan - ' . $permohonan->nomor_surat)

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('permohonan.index') }}" class="inline-flex size-9 items-center justify-center rounded-lg bg-white shadow-sm text-slate-600 hover:text-blue-600 transition-colors">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-3xl font-semibold text-[#1E3156]">{{ $permohonan->perihal }}</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                No. Surat Srikandi: <span class="font-mono font-bold text-slate-700">{{ $permohonan->nomor_surat }}</span> &bull;
                Diajukan pada: {{ $permohonan->tanggal_pengajuan ? $permohonan->tanggal_pengajuan->format('d M Y H:i') : '-' }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $permohonan->nomor_surat]) }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg bg-[#0088E8] px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-600 transition-colors">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Unduh PDF Resmi
            </a>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider
                {{ in_array($permohonan->status_permohonan, ['disetujui', 'selesai']) ? 'bg-emerald-100 text-emerald-800' : (in_array($permohonan->status_permohonan, ['ditolak', 'revisi']) ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                {{ str_replace('_', ' ', $permohonan->status_permohonan ?? $permohonan->status) }}
            </span>
        </div>
    </div>

    {{-- KARTU RINGKASAN METADATA --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="text-xs font-medium text-slate-400 uppercase">Unit Pengusul</div>
            <div class="mt-1 text-lg font-semibold text-slate-800">{{ $permohonan->unit->nama_unit ?? $permohonan->kode_unit }}</div>
            <div class="text-xs text-slate-500 font-mono">Kode: {{ $permohonan->kode_unit }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="text-xs font-medium text-slate-400 uppercase">Pegawai Pemohon</div>
            <div class="mt-1 text-lg font-semibold text-slate-800">{{ $permohonan->user->nama ?? '-' }}</div>
            <div class="text-xs text-slate-500">NIP: {{ $permohonan->user->nip ?? '-' }}</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="text-xs font-medium text-slate-400 uppercase">Estimasi Total Pagu</div>
            <div class="mt-1 text-xl font-bold text-[#0088E8]">Rp {{ number_format($permohonan->estimasi_total_pagu, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500">Sesuai KAK usulan</div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="text-xs font-medium text-slate-400 uppercase">Mata Anggaran (MAK)</div>
            <div class="mt-1 text-lg font-semibold text-slate-800">
                @if($permohonan->kode_mak)
                    <span class="font-mono text-emerald-600">{{ $permohonan->kode_mak }}</span>
                @else
                    <span class="text-slate-400 italic">Menunggu penetapan</span>
                @endif
            </div>
            <div class="text-xs text-slate-500">{{ $permohonan->anggaranMak->nama_mak ?? 'Ditetapkan Bagian Perencanaan' }}</div>
        </div>
    </div>

    {{-- KONTEN UTAMA DUA KOLOM: TIMELINE SIKLUS & CATATAN VERIFIKASI --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- KOLOM KIRI (2 SPAN): 8 TAHAPAN SIKLUS PENGADAAN --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
                <h2 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <svg class="size-5 text-[#0088E8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Pelacakan 8 Tahapan Siklus Pengadaan
                </h2>

                <div class="space-y-6">
                    {{-- Tahap 1 --}}
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full bg-emerald-500 text-white font-bold text-xs">1</span>
                            <div class="w-0.5 grow bg-emerald-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 1: Pengajuan Usulan Permohonan</div>
                            <div class="text-xs text-slate-500">Unit Pengusul ({{ $permohonan->kode_unit }}) &bull; Selesai</div>
                            <p class="text-xs text-slate-600 mt-1">Dokumen Srikandi terdaftar resmi dengan pagu Rp {{ number_format($permohonan->estimasi_total_pagu, 0, ',', '.') }}.</p>
                        </div>
                    </div>

                    {{-- Tahap 2 & 3: Bidang 2 & Wadir 2 --}}
                    @php
                        $vpB2 = $permohonan->verifikasiParalel->where('peran_verifikator', 'staff_bidang_2')->first();
                        $vpWadir = $permohonan->verifikasiParalel->where('peran_verifikator', 'wadir_2')->first();
                        $b2Done = $vpB2 && $vpB2->status_verifikasi === 'disetujui';
                        $wadirDone = $vpWadir && $vpWadir->status_verifikasi === 'disetujui';
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $b2Done ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">2</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 2: Telaah Berkas Staf Bidang 2</div>
                            <div class="text-xs text-slate-500">Status: {{ $vpB2 ? ucfirst($vpB2->status_verifikasi) : 'Dalam Antrean' }}</div>
                            <p class="text-xs text-slate-600 mt-1">{{ $vpB2->catatan ?? 'Pemeriksaan kelengkapan berkas fisik & digital oleh Staf Bidang II.' }}</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $wadirDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">3</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 3: Persetujuan Eksekutif Wadir 2</div>
                            <div class="text-xs text-slate-500">Status: {{ $vpWadir ? ucfirst($vpWadir->status_verifikasi) : 'Menunggu Telaah' }}</div>
                            <p class="text-xs text-slate-600 mt-1">{{ $vpWadir->catatan ?? 'Disposisi dan keputusan persetujuan pengadaan oleh Wakil Direktur II.' }}</p>
                        </div>
                    </div>

                    {{-- Tahap 4: Perencanaan --}}
                    @php
                        $vpRenc = $permohonan->verifikasiParalel->where('peran_verifikator', 'perencanaan')->first();
                        $rencDone = !empty($permohonan->kode_mak) || ($vpRenc && $vpRenc->status_verifikasi === 'disetujui');
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $rencDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">4</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 4: Verifikasi & Penetapan MAK Perencanaan</div>
                            <div class="text-xs text-slate-500">Status: {{ $rencDone ? 'MAK Terkunci' : 'Verifikasi Anggaran' }}</div>
                            <p class="text-xs text-slate-600 mt-1">
                                @if($permohonan->kode_mak)
                                    Dialokasikan pada MAK <span class="font-mono font-bold">{{ $permohonan->kode_mak }}</span>.
                                @else
                                    Pemeriksaan ketersediaan pagu pada DIPA anggaran Politeknik Negeri Jakarta.
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Tahap 5: Paket Pengadaan PPBJ --}}
                    @php
                        $paket = $permohonan->paket;
                        $paketDone = !empty($paket);
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $paketDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">5</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 5: Pembentukan Paket & Routing PPBJ</div>
                            <div class="text-xs text-slate-500">Koordinator PPBJ &bull; {{ $paket ? 'Paket Terdaftar' : 'Menunggu Persetujuan' }}</div>
                            @if($paket)
                                <p class="text-xs text-slate-600 mt-1">Kode Paket: <span class="font-mono font-bold">{{ $paket->kode_paket }}</span> &bull; PPK: {{ $paket->ppkUser->nama ?? $paket->id_ppk }}</p>
                            @else
                                <p class="text-xs text-slate-600 mt-1">Registrasi paket dan penetapan routing HPS (&le;50jt / 50-200jt / &ge;200jt).</p>
                            @endif
                        </div>
                    </div>

                    {{-- Tahap 6: Kontrak SPK --}}
                    @php
                        $kontrak = $paket ? $paket->kontrak : null;
                        $kontrakDone = !empty($kontrak);
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $kontrakDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">6</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 6: Kontrak SPK / Surat Pesanan</div>
                            <div class="text-xs text-slate-500">Penerbit Tunggal: PPBJ &bull; {{ $kontrak ? 'SPK Terbit' : 'Menunggu Reviu' }}</div>
                            @if($kontrak)
                                <p class="text-xs text-slate-600 mt-1">No. SPK: <span class="font-mono font-bold">{{ $kontrak->nomor_spk }}</span> &bull; Rekanan: {{ $kontrak->vendor->nama_vendor ?? '-' }}</p>
                            @else
                                <p class="text-xs text-slate-600 mt-1">Pembuatan dokumen kontrak / pesanan pengadaan setelah reviu PP / PPK.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Tahap 7: BAST Perlengkapan --}}
                    @php
                        $bast = $kontrak ? $kontrak->penerimaan : null;
                        $bastDone = !empty($bast);
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $bastDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">7</span>
                            <div class="w-0.5 grow bg-slate-200 mt-2"></div>
                        </div>
                        <div class="pb-4">
                            <div class="text-sm font-semibold text-slate-800">Tahap 7: Pemeriksaan Fisik & Penerbitan BAST</div>
                            <div class="text-xs text-slate-500">Unit Perlengkapan &bull; {{ $bast ? 'BAST Sah' : 'Menunggu Pengiriman Fisik' }}</div>
                            @if($bast)
                                <p class="text-xs text-slate-600 mt-1">No. BAST: <span class="font-mono font-bold">{{ $bast->nomor_bast }}</span> &bull; Kategori: {{ strtoupper(str_replace('_', ' ', $bast->kategori_belanja ?? 'BARANG')) }}</p>
                            @else
                                <p class="text-xs text-slate-600 mt-1">Pemeriksaan fisik barang dan kesesuaian spesifikasi oleh Perlengkapan.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Tahap 8: SPP & SP2D Keuangan --}}
                    @php
                        $transaksi = $bast && $bast->memoBayar ? $bast->memoBayar->transaksiPencairan : null;
                        $transaksiDone = $transaksi && $transaksi->status_transaksi === 'cair';
                    @endphp
                    <div class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex size-8 items-center justify-center rounded-full {{ $transaksiDone ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600' }} font-bold text-xs">8</span>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800">Tahap 8: Pembayaran SPP & Pencairan SP2D</div>
                            <div class="text-xs text-slate-500">Bagian Keuangan &bull; {{ $transaksiDone ? 'Dana Cair' : 'Menunggu Berkas Tagihan' }}</div>
                            @if($transaksi)
                                <p class="text-xs text-slate-600 mt-1">No. SP2D: <span class="font-mono font-bold">{{ $transaksi->nomor_sp2d ?? '-' }}</span> &bull; Realisasi: Rp {{ number_format($transaksi->jumlah_bayar, 0, ',', '.') }}</p>
                            @else
                                <p class="text-xs text-slate-600 mt-1">Verifikasi dokumen SPP oleh Keuangan dan pencairan dana resmi.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- FORM REVISI JIKA USULAN DITOLAK --}}
            @if(in_array($permohonan->status_permohonan, ['ditolak', 'revisi']) && (Auth::user()->hasRole('user') || Auth::user()->id_user === $permohonan->id_user))
            <div class="rounded-2xl bg-amber-50 p-6 border border-amber-200">
                <h3 class="text-lg font-bold text-amber-900 mb-2">Ajukan Revisi Usulan Permohonan</h3>
                <p class="text-xs text-amber-700 mb-4">Usulan ini membutuhkan perbaikan berkas atau penyesuaian pagu. Silakan isi form di bawah ini untuk mengirimkan surat perbaikan baru.</p>

                <form action="{{ route('permohonan.revisi') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="nomor_surat_lama" value="{{ $permohonan->nomor_surat }}">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Surat Revisi Baru Srikandi</label>
                        <input type="text" name="nomor_surat_baru" required placeholder="Contoh: B/{{ date('Ymd') }}.REV/PL3/TIK/{{ date('Y') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Perihal Pengadaan Baru</label>
                        <input type="text" name="perihal" value="{{ $permohonan->perihal }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Penyesuaian Anggaran Pagu (Rp)</label>
                        <input type="number" name="anggaran_diajukan" value="{{ $permohonan->estimasi_total_pagu }}" step="0.01" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan Perbaikan Berkas</label>
                        <textarea name="keterangan_revisi" rows="3" required placeholder="Jelaskan poin perbaikan yang telah dilakukan..."
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Unggah KAK Baru (PDF)</label>
                        <input type="file" name="file_kak" accept=".pdf" class="text-xs text-slate-600">
                    </div>
                    <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-amber-700 transition-colors">
                        Kirimkan Dokumen Revisi
                    </button>
                </form>
            </div>
            @endif

        </div>

        {{-- KOLOM KANAN (1 SPAN): CATATAN VERIFIKASI & SILSILAH DOKUMEN --}}
        <div class="space-y-6">

            {{-- CATATAN VERIFIKASI PARALEL --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="size-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Catatan Verifikasi Tiga Pihak
                </h3>

                @if($permohonan->verifikasiParalel->isEmpty())
                    <p class="text-xs text-slate-500 italic">Belum ada catatan verifikasi paralel pada berkas ini.</p>
                @else
                    <div class="space-y-4">
                        @foreach($permohonan->verifikasiParalel as $vp)
                            <div class="rounded-xl border border-slate-100 p-4 bg-slate-50/50">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 uppercase">{{ str_replace('_', ' ', $vp->peran_verifikator) }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $vp->status_verifikasi === 'disetujui' ? 'bg-emerald-100 text-emerald-800' : ($vp->status_verifikasi === 'ditolak' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') }}">
                                        {{ ucfirst($vp->status_verifikasi) }}
                                    </span>
                                </div>
                                <div class="text-xs font-medium text-slate-600 mt-1">{{ $vp->user->nama ?? '-' }}</div>
                                <p class="text-xs text-slate-600 mt-2 bg-white p-2.5 rounded-lg border border-slate-100">{{ $vp->catatan ?? 'Tidak ada catatan tertulis.' }}</p>
                                <div class="text-[10px] text-slate-400 mt-2 text-right">{{ $vp->tanggal_verifikasi ? $vp->tanggal_verifikasi->format('d/m/Y H:i') : '-' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- SILSILAH DOKUMEN REVISI & SPLIT --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="size-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Silsilah Usulan (Revisi & Split)
                </h3>

                <div class="text-xs space-y-3">
                    <div>
                        <span class="font-semibold text-slate-700">Pohon Revisi:</span>
                        @if($permohonan->isRevisi())
                            <p class="text-slate-600 mt-1">Revisi ke-{{ $permohonan->revisi_ke }} dari: <a href="{{ route('permohonan.show', $permohonan->nomor_surat_revisi) }}" class="font-mono text-blue-600 hover:underline">{{ $permohonan->nomor_surat_revisi }}</a></p>
                        @else
                            <p class="text-slate-400 italic mt-0.5">Surat usulan awal.</p>
                        @endif
                    </div>

                    <div class="pt-2 border-t border-slate-100">
                        <span class="font-semibold text-slate-700">Pemecahan (Split):</span>
                        @if($permohonan->isSplit())
                            <p class="text-slate-600 mt-1">Hasil split dari: <a href="{{ route('permohonan.show', $permohonan->nomor_surat_induk_split) }}" class="font-mono text-blue-600 hover:underline">{{ $permohonan->nomor_surat_induk_split }}</a></p>
                        @else
                            <p class="text-slate-400 italic mt-0.5">Usulan mandiri (Bukan hasil pemecahan).</p>
                        @endif

                        @if($permohonan->anakSplit && $permohonan->anakSplit->isNotEmpty())
                            <div class="mt-2 font-semibold text-slate-700">Usulan Anak Split:</div>
                            <ul class="list-disc pl-4 space-y-1 text-slate-600">
                                @foreach($permohonan->anakSplit as $anak)
                                    <li>
                                        <a href="{{ route('permohonan.show', $anak->nomor_surat) }}" class="font-mono text-blue-600 hover:underline">{{ $anak->nomor_surat }}</a>
                                        ({{ $anak->perihal }})
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
