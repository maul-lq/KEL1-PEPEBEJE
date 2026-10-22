@extends('layouts.app')

@section('title', 'Alur Pembayaran & Pencairan Dana - SIM-PBJ PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-8">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#1E3156]">Alur Pembayaran Tagihan &amp; Pencairan SP2D</h1>
            <p class="text-sm text-slate-500 mt-1">
                Pengelolaan tahapan pembayaran tagihan pengadaan barang dan jasa: pengajuan berkas oleh PPBJ, peninjauan memo bertingkat oleh PPK dan Wadir II, penerbitan SPP, serta pencairan dana SP2D oleh Keuangan.
            </p>
        </div>
        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 uppercase">
            Peran Aktif: {{ str_replace('_', ' ', Auth::user()->role) }}
        </span>
    </div>

    {{-- 1. PANEL PENGAJUAN BERKAS PEMBAYARAN OLEH PPBJ --}}
    @if(Auth::user()->role === 'ppbj')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
            <svg class="size-5 text-[#0088E8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            1. Pengajuan Berkas Tagihan Pembayaran &amp; Memo Internal (Koordinator PBJ)
        </h2>
        <p class="text-xs text-slate-500 mb-4">PPBJ mengajukan berkas pembayaran untuk paket pengadaan yang telah menyelesaikan penerimaan fisik BAST.</p>

        <form action="{{ route('pembayaran.pengajuan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nomor_paket_fe" class="block text-xs font-semibold text-slate-700 mb-1">Pilih Paket Pengadaan (BAST Selesai)</label>
                    <select id="nomor_paket_fe" name="nomor_paket" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Pilih Paket Hasil Penerimaan Barang --</option>
                        @foreach($bastSiapBayar as $b)
                            <option value="{{ $b->nomor_paket }}">
                                [{{ $b->nomor_bast }}] Paket: {{ $b->nomor_paket }} &bull; Tgl: {{ $b->tanggal_penerimaan ? $b->tanggal_penerimaan->format('d/m/Y') : '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nomor_pembayaran_fe" class="block text-xs font-semibold text-slate-700 mb-1">Nomor Pengajuan SPP</label>
                    <input type="text" id="nomor_pembayaran_fe" name="nomor_pembayaran" value="SPP/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="tahap_termin_fe" class="block text-xs font-semibold text-slate-700 mb-1">Tahap Pembayaran / Termin</label>
                    <input type="number" id="tahap_termin_fe" name="tahap_termin" value="1" min="1" max="12" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="nominal_pengajuan_fe" class="block text-xs font-semibold text-slate-700 mb-1">Nominal Tagihan (Rp)</label>
                    <input type="number" id="nominal_pengajuan_fe" name="nominal_pengajuan" step="0.01" min="1" required placeholder="Contoh: 150000000"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="jatuh_tempo_fe" class="block text-xs font-semibold text-slate-700 mb-1">Batas Jatuh Tempo Termin</label>
                    <input type="date" id="jatuh_tempo_fe" name="jatuh_tempo" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label for="nomor_memo_fe" class="block text-xs font-semibold text-slate-700 mb-1">Nomor Memo Pembayaran Internal</label>
                <input type="text" id="nomor_memo_fe" name="nomor_memo" value="MEMO/BHP/{{ date('Y') }}/{{ rand(10, 99) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Invoice / Kuitansi Rekanan (PDF/Gambar)</label>
                    <input type="file" name="file_tagihan_vendor" accept=".pdf,image/*" class="text-xs text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Resume Kontrak / Penunjang (PDF/Gambar)</label>
                    <input type="file" name="file_dokumen_penunjang" accept=".pdf,image/*" class="text-xs text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Berkas Fisik Memo Pembayaran (PDF)</label>
                    <input type="file" name="file_memo" accept=".pdf" class="text-xs text-slate-600">
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-[#0088E8] px-5 py-2.5 text-xs font-semibold text-white shadow hover:bg-blue-600 transition-colors">
                Kirim Berkas Pengajuan Pembayaran ke PPK &amp; Wadir II &rarr;
            </button>
        </form>
    </div>
    @endif

    {{-- 2. PANEL PERSETUJUAN MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR II) --}}
    @if(in_array(Auth::user()->role, ['ppk', 'wadir_2']))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
            <svg class="size-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            2. Peninjauan &amp; Persetujuan Memo Pembayaran ({{ strtoupper(str_replace('_', ' ', Auth::user()->role)) }})
        </h2>
        <p class="text-xs text-slate-500 mb-4">Persetujuan memo pembayaran secara bertingkat: Level 1 oleh Pejabat Pembuat Komitmen (PPK), dilanjutkan Level 2 oleh Wakil Direktur II.</p>

        @if($memoMenungguReview->isEmpty())
            <p class="text-xs text-slate-400 italic">Saat ini tidak ada disposisi memo pembayaran yang menunggu persetujuan Anda.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 uppercase text-slate-500 font-semibold">
                            <th class="py-2.5 px-3">Nomor SPP</th>
                            <th class="py-2.5 px-3">Paket Pengadaan</th>
                            <th class="py-2.5 px-3">Tingkat Verifikasi</th>
                            <th class="py-2.5 px-3">Nominal Tagihan</th>
                            <th class="py-2.5 px-3">Aksi Keputusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($memoMenungguReview as $m)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-3 font-mono font-bold">{{ $m->nomor_pembayaran }}</td>
                                <td class="py-3 px-3">{{ $m->pembayaran->nomor_paket ?? '-' }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-blue-700 font-semibold">
                                        Level {{ $m->urutan_level }} ({{ strtoupper($m->role_approver) }})
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-bold text-emerald-700">Rp {{ number_format($m->pembayaran->nominal_pengajuan ?? 0, 0, ',', '.') }}</td>
                                <td class="py-3 px-3">
                                    <form action="{{ route('pembayaran.memo.approve') }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="id_memo_bayar" value="{{ $m->id }}">
                                        <input type="text" name="catatan_memo" placeholder="Catatan persetujuan..." class="rounded border border-slate-300 px-2 py-1 text-xs bg-white">
                                        <button type="submit" name="status_memo" value="disetujui" class="rounded bg-emerald-600 px-3 py-1 font-semibold text-white hover:bg-emerald-700">Setujui</button>
                                        <button type="submit" name="status_memo" value="ditolak" class="rounded bg-rose-600 px-3 py-1 font-semibold text-white hover:bg-rose-700">Tolak</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endif

    {{-- 3. PANEL PENERBITAN SPP OLEH PPBJ --}}
    @if(Auth::user()->role === 'ppbj')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
            <svg class="size-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            3. Penerbitan Surat Perintah Pembayaran / SPP (Koordinator PBJ)
        </h2>
        <p class="text-xs text-slate-500 mb-4">Setelah Memo Pembayaran disetujui penuh oleh PPK dan Wadir II, PPBJ mengunggah SPP resmi dan meneruskannya ke Bagian Keuangan.</p>

        @if($siapTerbitkanSpp->isEmpty())
            <p class="text-xs text-slate-400 italic">Tidak ada berkas yang telah disetujui memo-nya secara lengkap untuk penerbitan SPP.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 uppercase text-slate-500 font-semibold">
                            <th class="py-2.5 px-3">Nomor SPP</th>
                            <th class="py-2.5 px-3">Paket</th>
                            <th class="py-2.5 px-3">Penyedia</th>
                            <th class="py-2.5 px-3">Nominal Tagihan</th>
                            <th class="py-2.5 px-3">Form Unggah SPP &amp; Teruskan ke Keuangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($siapTerbitkanSpp as $s)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-3 font-mono font-bold">{{ $s->nomor_pembayaran }}</td>
                                <td class="py-3 px-3">{{ $s->nomor_paket }}</td>
                                <td class="py-3 px-3">{{ $s->paket->kontrak->vendor->nama_vendor ?? '-' }}</td>
                                <td class="py-3 px-3 font-bold text-emerald-700">Rp {{ number_format($s->nominal_pengajuan, 0, ',', '.') }}</td>
                                <td class="py-3 px-3">
                                    <form action="{{ route('pembayaran.terbitkan-spp') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="nomor_pembayaran" value="{{ $s->nomor_pembayaran }}">
                                        <input type="file" name="file_spp" accept=".pdf" required class="text-[10px]">
                                        <button type="submit" class="rounded bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700 whitespace-nowrap">
                                            Terbitkan SPP &rarr;
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endif

    {{-- 4. PANEL PENCAIRAN SP2D OLEH KEUANGAN --}}
    @if(Auth::user()->role === 'keuangan')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
            <svg class="size-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            4. Eksekusi Pencairan Dana &amp; Pembayaran SP2D (Bagian Keuangan)
        </h2>
        <p class="text-xs text-slate-500 mb-4">Bagian Keuangan mengeksekusi pencairan SP2D atas SPP yang telah diterbitkan oleh PPBJ, dan mengunggah bukti transfer resmi bank.</p>

        <form action="{{ route('pembayaran.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nomor_pembayaran_cair" class="block text-xs font-semibold text-slate-700 mb-1">Pilih Berkas SPP Siap Cair</label>
                    <select id="nomor_pembayaran_cair" name="nomor_pembayaran" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">-- Pilih SPP yang Siap Dicairkan --</option>
                        @foreach($siapCairKeuangan as $sc)
                            <option value="{{ $sc->nomor_pembayaran }}">
                                [{{ $sc->nomor_pembayaran }}] Paket: {{ $sc->nomor_paket }} &bull; Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nomor_sp2d_fe" class="block text-xs font-semibold text-slate-700 mb-1">Nomor SP2D Kas Negara / Kampus</label>
                    <input type="text" id="nomor_sp2d_fe" name="nomor_sp2d" value="SP2D/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="tanggal_transfer_fe" class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Transfer Pemindahbukuan</label>
                    <input type="date" id="tanggal_transfer_fe" name="tanggal_transfer" value="{{ date('Y-m-d') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="jumlah_bayar_fe" class="block text-xs font-semibold text-slate-700 mb-1">Jumlah Realisasi Dana (Rp)</label>
                    <input type="number" id="jumlah_bayar_fe" name="jumlah_bayar" step="0.01" min="1" required placeholder="Contoh: 150000000"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="bank_asal_fe" class="block text-xs font-semibold text-slate-700 mb-1">Bank Penyalur Institusi</label>
                    <input type="text" id="bank_asal_fe" name="bank_asal" value="Bank Mandiri KCP PNJ" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label for="file_bukti_bayar_fe" class="block text-xs font-semibold text-slate-700 mb-1">Unggah Slip Bukti Transfer Bank / SP2D (PDF/JPG, Maks 10MB)</label>
                <input type="file" id="file_bukti_bayar_fe" name="file_bukti_bayar" accept=".pdf,image/*" required class="text-xs text-slate-600">
            </div>

            <div>
                <label for="keterangan_pencairan_fe" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Realisasi Pembayaran (Opsional)</label>
                <textarea id="keterangan_pencairan_fe" name="keterangan" rows="2" placeholder="Keterangan pembayaran termin / pelunasan..."
                          class="w-full rounded-lg border border-slate-300 p-2.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow hover:bg-emerald-700 transition-colors">
                Eksekusi Pencairan SP2D &amp; Selesaikan Siklus Pengadaan &rarr;
            </button>
        </form>
    </div>
    @endif

    {{-- 5. RIWAYAT TRANSAKSI SP2D & BUKTI TRANSFER --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
        <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="size-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Riwayat Realisasi Pencairan SP2D (Transfer Selesai)
        </h2>

        @if($transaksiList->isEmpty())
            <p class="text-xs text-slate-400 italic">Belum ada realisasi transaksi pencairan dana kas yang tercatat.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 uppercase text-slate-500 font-semibold">
                            <th class="py-2.5 px-3">Nomor SP2D</th>
                            <th class="py-2.5 px-3">Nomor SPP</th>
                            <th class="py-2.5 px-3">Paket Pengadaan</th>
                            <th class="py-2.5 px-3">Tanggal Cair</th>
                            <th class="py-2.5 px-3">Jumlah Realisasi</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Unduh Bukti Transfer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($transaksiList as $t)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-3 px-3 font-mono font-bold">{{ $t->nomor_sp2d }}</td>
                                <td class="py-3 px-3 font-mono">{{ $t->nomor_pembayaran }}</td>
                                <td class="py-3 px-3">{{ $t->pembayaran->nomor_paket ?? '-' }}</td>
                                <td class="py-3 px-3">{{ $t->tanggal_transfer ? date('d/m/Y', strtotime($t->tanggal_transfer)) : '-' }}</td>
                                <td class="py-3 px-3 font-bold text-emerald-700">Rp {{ number_format($t->jumlah_bayar, 0, ',', '.') }}</td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800 font-semibold">
                                        {{ strtoupper($t->status_transaksi ?? 'CAIR') }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'transfer', 'id' => $t->id_transaksi]) }}" target="_blank"
                                       class="inline-flex items-center gap-1 text-blue-600 hover:underline">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Unduh PDF Bukti
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $transaksiList->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
