@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- HEADER & AKSI UTAMA -->
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3 mb-1">
                <span class="font-mono font-bold text-sm bg-zinc-100 text-zinc-800 px-3 py-1 rounded border border-zinc-300">
                    {{ $pengadaan->nomor_pengadaan }}
                </span>
                <x-badge :status="$pengadaan->status" :label="$pengadaan->status_label" />
            </div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">{{ $pengadaan->nama_pengadaan }}</h1>
            <p class="text-sm text-zinc-600 mt-1">
                Jalur Anggaran: <strong class="text-zinc-900">{{ $pengadaan->jalur_label }}</strong> • Dibuat: {{ $pengadaan->created_at->format('d F Y, H:i') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('pengadaan.cetak', $pengadaan) }}" target="_blank" class="px-4 py-2.5 bg-zinc-200 hover:bg-zinc-300 text-zinc-900 font-bold text-sm rounded-lg border border-zinc-300 shadow-xs inline-flex items-center space-x-2">
                <svg class="w-4 h-4 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span>Cetak / Unduh PDF</span>
            </a>
            <a href="{{ route('pengadaan.index') }}" class="px-4 py-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg border border-zinc-300">
                &larr; Daftar
            </a>
        </div>
    </div>

    <!-- 8-STAGE VISUAL STEPPER TRACKER -->
    <x-stepper :pengadaan="$pengadaan" />

    <!-- KARTU AKSI CEPAT ROLE AKTIF -->
    @php
        $role = Auth::user()->role;
    @endphp

    @if ($role === 'wadir2' && $pengadaan->status === 'diajukan')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan Wadir 2: Disposisi & Persetujuan Permohonan</h3>
                <p class="text-xs text-zinc-300 mt-1">Periksa kelayakan permohonan pengadaan untuk diteruskan ke Perencanaan dan PPBJ</p>
            </div>
            <a href="{{ route('wadir2.review', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Tinjau & Setujui Sekarang &rarr;
            </a>
        </div>
    @elseif ($role === 'perencanaan' && $pengadaan->status === 'disetujui_wadir2')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan Bagian Perencanaan: Penetapan MAK & Pagu</h3>
                <p class="text-xs text-zinc-300 mt-1">Tentukan nomor Mata Anggaran Kegiatan (MAK) dan sumber pembiayaan (SIGAP)</p>
            </div>
            <a href="{{ route('perencanaan.input-mak', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Input Nomor MAK &rarr;
            </a>
        </div>
    @elseif ($role === 'ppbj' && $pengadaan->status === 'mak_ditetapkan')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPBJ: Registrasi Pengadaan</h3>
                <p class="text-xs text-zinc-300 mt-1">Catat memo resmi Wadir 2 dan arahkan ke jalur reviu PPK/PP</p>
            </div>
            <a href="{{ route('ppbj.register', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Registrasi Berkas &rarr;
            </a>
        </div>
    @elseif ($role === 'ppk_pp' && $pengadaan->status === 'teregistrasi_ppbj')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPK/PP: Reviu Dokumen Pengadaan</h3>
                <p class="text-xs text-zinc-300 mt-1">Periksa spesifikasi teknis dan HPS. Setujui untuk pembuatan SPK atau minta perbaikan</p>
            </div>
            <a href="{{ route('ppk.review', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Reviu Dokumen Sekarang &rarr;
            </a>
        </div>
    @elseif ($role === 'ppbj' && in_array($pengadaan->status, ['disetujui_ppk', 'revisi_ppk']))
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPBJ: Penerbitan SPK & Jadwal Alarm</h3>
                <p class="text-xs text-zinc-300 mt-1">Tetapkan penyedia/vendor, nilai kontrak, upload berkas SPK, dan aktifkan alarm pengingat</p>
            </div>
            <a href="{{ route('ppbj.create-spk', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Terbitkan SPK / Kontrak &rarr;
            </a>
        </div>
    @elseif ($role === 'perlengkapan' && in_array($pengadaan->status, ['spk_diterbitkan', 'barang_pending']))
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan Perlengkapan: Penerimaan Barang & BAST</h3>
                <p class="text-xs text-zinc-300 mt-1">Verifikasi fisik barang di gudang BMN, unggah foto dokumentasi, dan lengkapi TTD digital</p>
            </div>
            <a href="{{ route('perlengkapan.penerimaan', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Catat Penerimaan & TTD &rarr;
            </a>
        </div>
    @elseif ($role === 'ppbj' && $pengadaan->status === 'barang_diterima')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPBJ: Pengajuan Berkas Pembayaran</h3>
                <p class="text-xs text-zinc-300 mt-1">Unggah faktur, kwitansi, dan BAST untuk persetujuan memo pembayaran bertingkat</p>
            </div>
            <a href="{{ route('ppbj.ajukan-pembayaran', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Unggah Dokumen Bayar &rarr;
            </a>
        </div>
    @elseif ($role === 'ppk_pp' && $pengadaan->status === 'pengajuan_pembayaran')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPK: Persetujuan Memo Pembayaran (Tahap 1)</h3>
                <p class="text-xs text-zinc-300 mt-1">Tinjau kelengkapan tagihan vendor dan setujui memo untuk Wadir 2</p>
            </div>
            <form method="POST" action="{{ route('ppk.acc-pembayaran', $pengadaan) }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0 cursor-pointer">
                    Setujui Memo Pembayaran (PPK) &rarr;
                </button>
            </form>
        </div>
    @elseif ($role === 'wadir2' && $pengadaan->status === 'memo_pembayaran_ppk')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan Wadir 2: Persetujuan Memo Pembayaran (Tahap 2)</h3>
                <p class="text-xs text-zinc-300 mt-1">Persetujuan akhir pimpinan sebelum penerbitan Surat Perintah Pembayaran (SPP)</p>
            </div>
            <form method="POST" action="{{ route('wadir2.acc-pembayaran', $pengadaan) }}">
                @csrf
                <button type="submit" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0 cursor-pointer">
                    ACC Pencairan (Wadir 2) &rarr;
                </button>
            </form>
        </div>
    @elseif ($role === 'ppbj' && $pengadaan->status === 'memo_pembayaran_wadir2')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan PPBJ: Terbitkan Surat Perintah Pembayaran (SPP)</h3>
                <p class="text-xs text-zinc-300 mt-1">Memo pembayaran telah disetujui Wadir 2. Terbitkan SPP resmi ke Bagian Keuangan</p>
            </div>
            <button onclick="document.getElementById('modal-spp').classList.remove('hidden')" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0 cursor-pointer">
                Terbitkan SPP &rarr;
            </button>
        </div>
    @elseif ($role === 'keuangan' && $pengadaan->status === 'sp_pembayaran_terbit')
        <div class="bg-zinc-900 text-white p-6 rounded-xl border border-zinc-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Tindakan Bagian Keuangan: Pencairan Dana & Upload Bukti Transfer</h3>
                <p class="text-xs text-zinc-300 mt-1">Catat transaksi pembayaran perbankan/SP2D dan ubah status pengadaan menjadi SELESAI</p>
            </div>
            <a href="{{ route('keuangan.pencairan', $pengadaan) }}" class="px-6 py-3 bg-white text-zinc-900 hover:bg-zinc-100 font-bold text-sm rounded-lg shrink-0">
                Input Bukti Bayar & Selesaikan &rarr;
            </a>
        </div>
    @endif

    <!-- GRID INFORMASI TAHAPAN -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- KARTU 1: DATA PERMOHONAN USER -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2 flex items-center justify-between">
                <span>1. Dokumen Permohonan User</span>
                <span class="text-xs font-normal text-zinc-500">Tahap Awal</span>
            </h3>
            <dl class="text-sm divide-y divide-zinc-100">
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Pemohon:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->user->name }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Unit / Jurusan:</dt>
                    <dd class="font-medium text-zinc-800 text-right">{{ $pengadaan->user->unit_kerja }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">No. Surat Permohonan:</dt>
                    <dd class="font-mono text-zinc-900 text-right">{{ $pengadaan->nomor_surat_user ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Tanggal Surat:</dt>
                    <dd class="text-zinc-900 text-right">{{ $pengadaan->tanggal_surat_user ? $pengadaan->tanggal_surat_user->format('d M Y') : '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Draft Web Srikandi:</dt>
                    <dd class="font-mono text-zinc-900 text-right">{{ $pengadaan->no_draft_srikandi ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Estimasi Anggaran:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->formatted_estimasi_anggaran }}</dd>
                </div>
                <div class="py-2 flex justify-between items-center">
                    <dt class="text-zinc-500">Berkas Surat Permohonan:</dt>
                    <dd class="text-right">
                        @if ($pengadaan->file_surat_permohonan)
                            <a href="{{ asset('storage/' . $pengadaan->file_surat_permohonan) }}" target="_blank" class="text-xs font-bold text-zinc-900 underline">
                                Unduh Surat &darr;
                            </a>
                        @else
                            <span class="text-xs text-zinc-400">Tidak ada lampiran</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <!-- KARTU 2: ANGGARAN & MAK (PERENCANAAN) -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2 flex items-center justify-between">
                <span>2. Alokasi Anggaran & MAK (Perencanaan)</span>
                <span class="text-xs font-normal text-zinc-500">SIGAP</span>
            </h3>
            <dl class="text-sm divide-y divide-zinc-100">
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Persetujuan Wadir 2:</dt>
                    <dd class="font-bold text-zinc-900 text-right">
                        @if ($pengadaan->tanggal_persetujuan_wadir2)
                            Disetujui ({{ $pengadaan->tanggal_persetujuan_wadir2->format('d/m/Y H:i') }})
                        @else
                            <span class="text-zinc-400 italic">Menunggu persetujuan</span>
                        @endif
                    </dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Catatan Wadir 2:</dt>
                    <dd class="text-zinc-800 text-right text-xs max-w-xs">{{ $pengadaan->catatan_wadir2 ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Nomor MAK:</dt>
                    <dd class="font-mono font-bold text-zinc-900 text-right">
                        {{ $pengadaan->nomor_mak ?? '-' }}
                    </dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Pagu Anggaran Disetujui:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->formatted_pagu_anggaran }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Sumber Dana:</dt>
                    <dd class="text-zinc-900 text-right">{{ $pengadaan->sumber_dana ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Verifikator Anggaran:</dt>
                    <dd class="text-zinc-800 text-right">{{ $pengadaan->perencanaan->name ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <!-- KARTU 3: KONTRAK & SPK (PPBJ & VENDOR) -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2 flex items-center justify-between">
                <span>3. Penerbitan SPK & Rekanan Penyedia</span>
                <span class="text-xs font-normal text-zinc-500">Kontrak Vendor</span>
            </h3>
            <dl class="text-sm divide-y divide-zinc-100">
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Nomor Register PPBJ:</dt>
                    <dd class="font-mono text-zinc-900 text-right">{{ $pengadaan->nomor_memo_ppbj ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Metode Pengadaan:</dt>
                    <dd class="font-semibold text-zinc-900 text-right">{{ $pengadaan->metode_pengadaan ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Penyedia / Rekanan:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->vendor->nama_perusahaan ?? 'Belum ditentukan' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Nomor SPK:</dt>
                    <dd class="font-mono text-zinc-900 text-right">{{ $pengadaan->nomor_spk ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Nilai Kontrak Realisasi:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->formatted_nilai_kontrak }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Target Jadwal Pengiriman:</dt>
                    <dd class="font-semibold text-zinc-900 text-right">
                        {{ $pengadaan->tanggal_selesai_jadwal ? $pengadaan->tanggal_selesai_jadwal->format('d M Y') : '-' }}
                    </dd>
                </div>
                @if ($pengadaan->id_paket_lkpp)
                    <div class="py-2 flex justify-between">
                        <dt class="text-zinc-500">ID / URL LKPP-Inaproc:</dt>
                        <dd class="font-mono text-xs text-zinc-900 text-right break-all">{{ $pengadaan->id_paket_lkpp }}</dd>
                    </div>
                @endif
                <div class="py-2 flex justify-between items-center">
                    <dt class="text-zinc-500">Dokumen SPK:</dt>
                    <dd class="text-right">
                        @if ($pengadaan->file_spk)
                            <a href="{{ asset('storage/' . $pengadaan->file_spk) }}" target="_blank" class="text-xs font-bold text-zinc-900 underline">
                                Unduh SPK &darr;
                            </a>
                        @else
                            <span class="text-xs text-zinc-400">Belum terbit</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <!-- KARTU 4: PENERIMAAN BARANG & BAST (PERLENGKAPAN) -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2 flex items-center justify-between">
                <span>4. Penerimaan Barang & BAST (Perlengkapan)</span>
                <span class="text-xs font-normal text-zinc-500">BMN</span>
            </h3>

            @if ($pengadaan->nama_penerima)
                <div class="p-3 bg-zinc-100 rounded-lg border border-zinc-300 text-xs text-zinc-800">
                    <span class="font-bold">Keterangan Resmi:</span> Barang telah diterima oleh <strong>{{ $pengadaan->nama_penerima }}</strong> ({{ $pengadaan->unit_penerima ?? 'Subbagian Perlengkapan' }}) dengan status <strong>{{ $pengadaan->status_penerimaan }}</strong>.
                </div>
            @endif

            <dl class="text-sm divide-y divide-zinc-100">
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Status Penerimaan:</dt>
                    <dd class="font-bold text-zinc-900 text-right">
                        @if ($pengadaan->status_penerimaan)
                            <span class="px-2 py-0.5 rounded text-xs font-bold {{ $pengadaan->status_penerimaan === 'Terima' ? 'bg-zinc-900 text-white' : 'bg-zinc-200 text-zinc-900' }}">
                                {{ $pengadaan->status_penerimaan }}
                            </span>
                        @else
                            <span class="text-zinc-400 italic">Belum diterima</span>
                        @endif
                    </dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Jenis Belanja BMN:</dt>
                    <dd class="font-bold text-zinc-900 text-right">{{ $pengadaan->jenis_belanja ?? '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Penerima Barang:</dt>
                    <dd class="text-zinc-900 text-right">{{ $pengadaan->nama_penerima ?? '-' }} ({{ $pengadaan->unit_penerima ?? '-' }})</dd>
                </div>
                <div class="py-2 flex justify-between">
                    <dt class="text-zinc-500">Tanggal Diterima:</dt>
                    <dd class="text-zinc-900 text-right">{{ $pengadaan->tanggal_penerimaan ? $pengadaan->tanggal_penerimaan->format('d M Y H:i') : '-' }}</dd>
                </div>
                <div class="py-2 flex justify-between items-center">
                    <dt class="text-zinc-500">Foto Fisik Barang:</dt>
                    <dd class="text-right">
                        @if ($pengadaan->foto_dokumentasi_barang)
                            <a href="{{ asset('storage/' . $pengadaan->foto_dokumentasi_barang) }}" target="_blank" class="text-xs font-bold text-zinc-900 underline">
                                Lihat Foto Dokumentasi
                            </a>
                        @else
                            <span class="text-xs text-zinc-400">Tidak ada</span>
                        @endif
                    </dd>
                </div>
                <div class="py-2 flex justify-between items-center">
                    <dt class="text-zinc-500">Tanda Tangan Digital BAST:</dt>
                    <dd class="text-right">
                        @if ($pengadaan->ttd_digital)
                            <span class="text-xs font-bold text-zinc-900 bg-zinc-100 px-2 py-1 rounded border border-zinc-300">
                                &#10003; Terverifikasi Sah
                            </span>
                        @else
                            <span class="text-xs text-zinc-400">Belum ditandatangani</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <!-- KARTU 5: PEMBAYARAN & PENCAIRAN (KEUANGAN) -->
        <div class="md:col-span-2 bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2 flex items-center justify-between">
                <span>5. Realisasi Pembayaran & Pencairan Dana (Keuangan)</span>
                <span class="text-xs font-normal text-zinc-500">Final</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                    <div class="text-xs text-zinc-500 font-bold uppercase">Memo Pembayaran PPK</div>
                    <div class="font-bold text-zinc-900 mt-1">
                        {{ $pengadaan->acc_pembayaran_ppk ? 'Disetujui (' . $pengadaan->tanggal_acc_pembayaran_ppk?->format('d/m/Y') . ')' : 'Menunggu' }}
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                    <div class="text-xs text-zinc-500 font-bold uppercase">Persetujuan Wadir 2</div>
                    <div class="font-bold text-zinc-900 mt-1">
                        {{ $pengadaan->acc_pembayaran_wadir2 ? 'Disetujui (' . $pengadaan->tanggal_acc_pembayaran_wadir2?->format('d/m/Y') . ')' : 'Menunggu' }}
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                    <div class="text-xs text-zinc-500 font-bold uppercase">No. SPP (Perintah Bayar)</div>
                    <div class="font-mono font-bold text-zinc-900 mt-1">
                        {{ $pengadaan->nomor_spp ?? '-' }}
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                    <div class="text-xs text-zinc-500 font-bold uppercase">No. Bukti Bayar / Ref Bank</div>
                    <div class="font-mono font-bold text-zinc-900 mt-1">
                        {{ $pengadaan->nomor_bukti_bayar ?? '-' }}
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200">
                    <div class="text-xs text-zinc-500 font-bold uppercase">Tanggal Pencairan</div>
                    <div class="font-bold text-zinc-900 mt-1">
                        {{ $pengadaan->tanggal_bayar ? $pengadaan->tanggal_bayar->format('d F Y') : '-' }}
                    </div>
                </div>
                <div class="p-3 bg-zinc-50 rounded-lg border border-zinc-200 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-zinc-500 font-bold uppercase">Bukti Transfer Dana</div>
                        <div class="text-xs text-zinc-700 mt-1">Lampiran SP2D/Transfer</div>
                    </div>
                    @if ($pengadaan->file_bukti_bayar)
                        <a href="{{ asset('storage/' . $pengadaan->file_bukti_bayar) }}" target="_blank" class="px-3 py-1 bg-zinc-900 text-white rounded text-xs font-bold">
                            Unduh Bukti
                        </a>
                    @else
                        <span class="text-xs text-zinc-400">Belum ada</span>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <!-- TABEL RINCIAN ITEM BARANG / JASA -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="text-base font-bold text-zinc-900">Rincian Kebutuhan Barang / Jasa ({{ $pengadaan->items->count() }} Item)</h3>
            <span class="text-xs font-mono font-bold text-zinc-700">Total: {{ $pengadaan->formatted_estimasi_anggaran }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-100 text-zinc-700 font-bold border-b border-zinc-300 text-xs uppercase">
                        <th class="py-2.5 px-4 w-12">No</th>
                        <th class="py-2.5 px-4">Nama Barang / Uraian Pekerjaan</th>
                        <th class="py-2.5 px-4">Spesifikasi</th>
                        <th class="py-2.5 px-4 text-center">Volume</th>
                        <th class="py-2.5 px-4">Satuan</th>
                        <th class="py-2.5 px-4 text-right">Harga Satuan</th>
                        <th class="py-2.5 px-4 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($pengadaan->items as $index => $item)
                        <tr>
                            <td class="py-3 px-4 text-center font-bold text-zinc-500 text-xs">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-zinc-900">{{ $item->nama_barang }}</td>
                            <td class="py-3 px-4 text-zinc-600 text-xs">{{ $item->spesifikasi ?? '-' }}</td>
                            <td class="py-3 px-4 text-center font-bold text-zinc-800">{{ $item->volume }}</td>
                            <td class="py-3 px-4 text-zinc-700">{{ $item->satuan }}</td>
                            <td class="py-3 px-4 text-right text-zinc-800 font-mono">{{ $item->formatted_harga_satuan }}</td>
                            <td class="py-3 px-4 text-right font-bold text-zinc-900 font-mono">{{ $item->formatted_total_harga }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-zinc-100 font-bold border-t-2 border-zinc-300">
                        <td colspan="6" class="py-3 px-4 text-right uppercase text-xs">Total Anggaran:</td>
                        <td class="py-3 px-4 text-right text-base text-zinc-900 font-mono">{{ $pengadaan->formatted_estimasi_anggaran }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- AUDIT TRAIL LOGS (FR 9.0) -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-200">
            <h3 class="text-base font-bold text-zinc-900">Audit Trail & Riwayat Perubahan Berkas (FR 9.0)</h3>
            <p class="text-xs text-zinc-500">Mencatat setiap tanggal, jam, aktor pelaku, tindakan, dan perubahan status berkas</p>
        </div>
        <div class="divide-y divide-zinc-200">
            @forelse ($pengadaan->logs as $log)
                <div class="p-4 flex items-start space-x-3 text-sm">
                    <div class="w-2 h-2 rounded-full bg-zinc-900 mt-2 shrink-0"></div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-zinc-900">{{ $log->action }}</span>
                            <span class="text-xs text-zinc-400 font-mono">{{ $log->created_at->format('d M Y, H:i:s') }}</span>
                        </div>
                        <p class="text-xs text-zinc-600 mt-1">{{ $log->keterangan }}</p>
                        <div class="text-[11px] text-zinc-400 mt-1 flex items-center space-x-2">
                            <span>Aktor: <strong>{{ $log->user->name ?? 'Sistem' }}</strong> ({{ $log->user->role_label ?? 'Sistem' }})</span>
                            <span>• IP: {{ $log->ip_address }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-zinc-400 text-xs">Belum ada riwayat audit trail.</div>
            @endforelse
        </div>
    </div>

</div>

<!-- MODAL INPUT TERBITKAN SPP (PPBJ) -->
<div id="modal-spp" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6 space-y-4 border border-zinc-300 shadow-xl">
        <h3 class="text-lg font-bold text-zinc-900">Terbitkan Surat Perintah Pembayaran (SPP)</h3>
        <p class="text-xs text-zinc-600">Perintah resmi pencairan anggaran pengadaan ke Bagian Keuangan</p>
        <form method="POST" action="{{ route('ppbj.terbitkan-spp', $pengadaan) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Nomor SPP</label>
                <input type="text" name="nomor_spp" required placeholder="Contoh: SPP/2026/03/089"
                    class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Tanggal SPP</label>
                <input type="date" name="tanggal_spp" required value="{{ date('Y-m-d') }}"
                    class="w-full px-3 py-2 border border-zinc-300 rounded-lg text-sm">
            </div>
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-spp').classList.add('hidden')" class="px-4 py-2 bg-zinc-200 text-zinc-800 rounded-lg text-xs font-bold">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-zinc-900 text-white rounded-lg text-xs font-bold">
                    Terbitkan ke Keuangan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
