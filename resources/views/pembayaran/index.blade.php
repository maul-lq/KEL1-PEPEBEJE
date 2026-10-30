@extends('layouts.app')

@section('title', 'Alur Pembayaran Tagihan & Pencairan SP2D - SIM-PBJ PNJ')

@section('content')
<div class="space-y-6">

    {{-- 1. Stepper 8 Siklus --}}
    <x-stepper-8-siklus :currentStep="8" status="spp_terbit" />

    {{-- 2. Header --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Siklus 8: Tagihan, SPP &amp; SP2D Cair
                    </span>
                    <span class="text-xs text-slate-400 font-mono">FR 15 &bull; FR 18</span>
                </div>
                <h1 class="text-2xl font-bold text-[#213153] mt-1">Alur Pembayaran Tagihan &amp; Pencairan SP2D</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Pengelolaan alur verifikasi memo bayar bertingkat, penerbitan SPP oleh PPBJ, dan pencairan dana kas oleh Bagian Keuangan PNJ
                </p>
            </div>

            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-[#213153] border border-slate-200 text-xs font-bold uppercase">
                    Peran: {{ str_replace('_', ' ', Auth::user()->role) }}
                </span>
            </div>
        </div>
    </div>

    {{-- 3. Top 4-Card KPI Stat Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card
            title="Total Tagihan Diajukan"
            :value="$pembayaranList->total()"
            icon="document"
            color="navy"
            subtitle="Pengajuan berkas pembayaran"
        />

        <x-stat-card
            title="Memo Menunggu Review"
            :value="$memoMenungguReview->count()"
            icon="bell"
            color="amber"
            badge="Tindak Lanjut"
            badgeColor="bg-amber-50 text-amber-700 border-amber-200"
            subtitle="Menunggu persetujuan PPK / Wadir 2"
        />

        <x-stat-card
            title="SPP Siap Cair"
            :value="$siapCairKeuangan->count()"
            icon="check"
            color="blue"
            badge="Siap Cair"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Perintah bayar kas negara"
        />

        <x-stat-card
            title="Total Dana Dicairkan"
            :value="'Rp ' . number_format($transaksiList->sum('nominal_transfer'), 0, ',', '.')"
            icon="wallet"
            color="emerald"
            subtitle="Realisasi pemindahbukuan bank"
        />
    </div>

    {{-- 4. PANEL PENGAJUAN BERKAS PEMBAYARAN OLEH PPBJ (FR 15) --}}
    @if(Auth::user()->role === 'ppbj' || Auth::user()->role === 'admin')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>1. Pengajuan Berkas Tagihan Pembayaran &amp; Memo Internal</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Koordinator PPBJ)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">PPBJ mengajukan berkas pembayaran untuk paket pengadaan yang telah menyelesaikan pemeriksaan fisik BAST.</p>
        </div>

        @if($bastSiapBayar->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Tidak ada paket pengadaan dengan BAST selesai yang siap diajukan pembayaran saat ini.
            </div>
        @else
            <form action="{{ route('pembayaran.pengajuan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_paket_fe" class="block text-xs font-bold text-slate-700 mb-1">Pilih Paket Pengadaan (BAST Selesai) <span class="text-rose-500">*</span></label>
                        <select id="nomor_paket_fe" name="nomor_paket" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                            <option value="">-- Pilih Paket Hasil Penerimaan Barang --</option>
                            @foreach($bastSiapBayar as $b)
                                <option value="{{ $b->nomor_paket }}">
                                    [{{ $b->nomor_bast }}] Paket: {{ $b->nomor_paket }} &bull; Tgl: {{ $b->tanggal_penerimaan ? \Carbon\Carbon::parse($b->tanggal_penerimaan)->format('d/m/Y') : '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="nomor_pembayaran_fe" class="block text-xs font-bold text-slate-700 mb-1">Nomor Pengajuan SPP <span class="text-rose-500">*</span></label>
                        <input type="text" id="nomor_pembayaran_fe" name="nomor_pembayaran" value="SPP/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="tahap_termin_fe" class="block text-xs font-bold text-slate-700 mb-1">Tahap Pembayaran / Termin <span class="text-rose-500">*</span></label>
                        <input type="number" id="tahap_termin_fe" name="tahap_termin" value="1" min="1" max="12" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>

                    <div>
                        <label for="nominal_pengajuan_fe" class="block text-xs font-bold text-slate-700 mb-1">Nominal Tagihan (Rp) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                            <input type="number" id="nominal_pengajuan_fe" name="nominal_pengajuan" step="0.01" min="1" required placeholder="Contoh: 150000000" class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    <div>
                        <label for="jatuh_tempo_fe" class="block text-xs font-bold text-slate-700 mb-1">Batas Jatuh Tempo Termin <span class="text-rose-500">*</span></label>
                        <input type="date" id="jatuh_tempo_fe" name="jatuh_tempo" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>
                </div>

                <div>
                    <label for="nomor_memo_fe" class="block text-xs font-bold text-slate-700 mb-1">Nomor Memo Pembayaran Internal</label>
                    <input type="text" id="nomor_memo_fe" name="nomor_memo" value="MEMO/BHP/{{ date('Y') }}/{{ rand(10, 99) }}" class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Invoice Rekanan (PDF/Gambar)</label>
                        <input type="file" name="file_tagihan_vendor" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Dokumen Penunjang (PDF/Gambar)</label>
                        <input type="file" name="file_dokumen_penunjang" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Berkas Fisik Memo (PDF)</label>
                        <input type="file" name="file_memo" accept=".pdf" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                    </div>
                </div>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition inline-flex items-center gap-1.5">
                        Kirim Berkas Pengajuan ke PPK &amp; Wadir II &rarr;
                    </button>
                </div>
            </form>
        @endif
    </div>
    @endif

    {{-- 5. PANEL PERSETUJUAN MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR II - FR 16) --}}
    @if(in_array(Auth::user()->role, ['ppk', 'wadir_2', 'admin']))
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>2. Penelaahan &amp; Persetujuan Memo Pembayaran Bertingkat</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(PPK &amp; Wadir II)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Peninjauan bertingkat: Level 1 oleh PPK terisolasi; Level 2 oleh Wakil Direktur II.</p>
        </div>

        @if($memoMenungguReview->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Tidak ada memo pembayaran yang membutuhkan persetujuan pada akun Anda saat ini.
            </div>
        @else
            <div class="space-y-4">
                @foreach($memoMenungguReview as $memo)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-[#0969da]">{{ $memo->nomor_memo }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Level {{ $memo->level_approver }} ({{ strtoupper($memo->role_approver) }})</span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-800 mt-1">Paket: {{ $memo->pembayaran->nomor_paket ?? '-' }}</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Nominal: Rp {{ number_format($memo->pembayaran->nominal_pengajuan ?? 0, 0, ',', '.') }} &bull; SPP: {{ $memo->nomor_pembayaran }}</p>
                        </div>

                        <form action="{{ route('pembayaran.memo.approve') }}" method="POST" class="flex flex-wrap items-center gap-2 shrink-0">
                            @csrf
                            <input type="hidden" name="id_memo" value="{{ $memo->id_memo }}">
                            <input type="text" name="catatan" placeholder="Catatan persetujuan..." class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 w-48">

                            <button type="submit" name="status_memo" value="disetujui" class="h-9 px-3.5 rounded-lg bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700 transition">
                                Setujui (ACC)
                            </button>

                            <button type="submit" name="status_memo" value="ditolak" class="h-9 px-3.5 rounded-lg bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 transition">
                                Tolak
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- 6. PANEL PENERBITAN SPP OLEH PPBJ (FR 17) --}}
    @if(Auth::user()->role === 'ppbj' || Auth::user()->role === 'admin')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>3. Penerbitan Surat Perintah Pembayaran (SPP)</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Koordinator PPBJ)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Setelah Memo Pembayaran disetujui penuh oleh PPK dan Wadir II, PPBJ mengesahkan SPP dan meneruskannya ke Bagian Keuangan.</p>
        </div>

        @if($siapTerbitkanSpp->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Tidak ada berkas yang telah disetujui memo-nya secara lengkap untuk penerbitan SPP.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Nomor SPP</th>
                            <th class="px-4 py-3">Paket Pengadaan</th>
                            <th class="px-4 py-3">Penyedia Vendor</th>
                            <th class="px-4 py-3">Nominal Tagihan</th>
                            <th class="px-4 py-3">Pengesahan &amp; Teruskan ke Keuangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($siapTerbitkanSpp as $s)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-mono font-bold text-[#0969da]">{{ $s->nomor_pembayaran }}</td>
                                <td class="px-4 py-3">{{ $s->nomor_paket }}</td>
                                <td class="px-4 py-3">{{ $s->paket->kontrak->vendor->nama_vendor ?? '-' }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-emerald-600">Rp {{ number_format($s->nominal_pengajuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <form action="{{ route('pembayaran.terbitkan-spp') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="nomor_pembayaran" value="{{ $s->nomor_pembayaran }}">
                                        <input type="text" name="catatan_reviu_akhir" placeholder="Catatan reviu akhir..." class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-[11px] w-40">
                                        <input type="file" name="file_spp" accept=".pdf" class="text-[10px] w-36">
                                        <button type="submit" class="h-8 px-3 rounded-lg bg-indigo-600 text-[11px] font-bold text-white hover:bg-indigo-700 transition shrink-0">
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

    {{-- 7. PANEL PENCAIRAN SP2D OLEH KEUANGAN (FR 18) --}}
    @if(Auth::user()->role === 'keuangan' || Auth::user()->role === 'admin')
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200/80">
        <div class="border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-base font-bold text-[#213153] flex items-center gap-2">
                <span>4. Eksekusi Pencairan Dana &amp; Pembayaran SP2D</span>
                <span class="text-xs font-normal text-slate-400 font-mono">(Bagian Keuangan)</span>
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Bagian Keuangan mengeksekusi pencairan kas SP2D dan mengunggah slip bukti transfer resmi bank penyalur.</p>
        </div>

        @if($siapCairKeuangan->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                Tidak ada SPP yang berstatus siap cair saat ini.
            </div>
        @else
            <form action="{{ route('pembayaran.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_pembayaran_cair" class="block text-xs font-bold text-slate-700 mb-1">Pilih Berkas SPP Siap Cair <span class="text-rose-500">*</span></label>
                        <select id="nomor_pembayaran_cair" name="nomor_pembayaran" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                            <option value="">-- Pilih SPP Siap Cair --</option>
                            @foreach($siapCairKeuangan as $sc)
                                <option value="{{ $sc->nomor_pembayaran }}">
                                    [{{ $sc->nomor_pembayaran }}] Paket: {{ $sc->nomor_paket }} &bull; Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="nomor_transaksi" class="block text-xs font-bold text-slate-700 mb-1">Nomor Transaksi Bank / SP2D <span class="text-rose-500">*</span></label>
                        <input type="text" id="nomor_transaksi" name="nomor_transaksi" value="SP2D/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="tanggal_cair" class="block text-xs font-bold text-slate-700 mb-1">Tanggal Cair Pemindahbukuan <span class="text-rose-500">*</span></label>
                        <input type="date" id="tanggal_cair" name="tanggal_cair" value="{{ date('Y-m-d') }}" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>

                    <div>
                        <label for="nominal_cair" class="block text-xs font-bold text-slate-700 mb-1">Jumlah Realisasi Kas (Rp) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                            <input type="number" id="nominal_cair" name="nominal_cair" step="0.01" min="1" required placeholder="Contoh: 150000000" class="w-full h-11 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-xs font-mono font-bold text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                        </div>
                    </div>

                    <div>
                        <label for="bank_tujuan" class="block text-xs font-bold text-slate-700 mb-1">Bank Penyalur Institusi <span class="text-rose-500">*</span></label>
                        <input type="text" id="bank_tujuan" name="bank_tujuan" value="Bank Mandiri KCP PNJ" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    </div>
                </div>

                <div>
                    <label for="file_bukti_transfer" class="block text-xs font-bold text-slate-700 mb-1">Unggah Slip Bukti Transfer Bank / SP2D (PDF/JPG, Maks 10MB)</label>
                    <input type="file" id="file_bukti_transfer" name="file_bukti_transfer" accept=".pdf,image/*" class="w-full text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da]">
                </div>

                <div>
                    <label for="keterangan_cair" class="block text-xs font-bold text-slate-700 mb-1">Catatan Realisasi Pembayaran (Opsional)</label>
                    <textarea id="keterangan_cair" name="keterangan_cair" rows="2" placeholder="Keterangan pembayaran termin / pelunasan kas..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20"></textarea>
                </div>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700 shadow-sm transition inline-flex items-center gap-1.5">
                        Eksekusi Pencairan SP2D &amp; Selesaikan Siklus Pengadaan &rarr;
                    </button>
                </div>
            </form>
        @endif
    </div>
    @endif

    {{-- 8. RIWAYAT TRANSAKSI SP2D & BUKTI TRANSFER --}}
    <div class="rounded-2xl bg-white shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#213153]">Riwayat Realisasi Pencairan SP2D (Transfer Selesai)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Catatan bukti transfer kas dan pembayaran resmi yang telah cair</p>
            </div>
            <span class="text-xs font-bold text-slate-400 font-mono">{{ $transaksiList->total() }} Transaksi</span>
        </div>

        @if($transaksiList->isEmpty())
            <div class="text-center py-8 text-xs text-slate-400">Belum ada realisasi transaksi pencairan dana kas yang tercatat.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3">Nomor Transaksi Bank</th>
                            <th class="px-5 py-3">Nomor SPP</th>
                            <th class="px-5 py-3">Paket Pengadaan</th>
                            <th class="px-5 py-3">Tanggal Cair</th>
                            <th class="px-5 py-3">Jumlah Realisasi</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-center">Unduh Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($transaksiList as $t)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-5 py-3.5 font-mono font-bold text-[#0969da]">{{ $t->nomor_transaksi_bank }}</td>
                                <td class="px-5 py-3.5 font-mono">{{ $t->nomor_pembayaran }}</td>
                                <td class="px-5 py-3.5">{{ $t->pembayaran->nomor_paket ?? '-' }}</td>
                                <td class="px-5 py-3.5">{{ $t->tanggal_transfer ? \Carbon\Carbon::parse($t->tanggal_transfer)->format('d/m/Y') : '-' }}</td>
                                <td class="px-5 py-3.5 font-mono font-bold text-emerald-600">Rp {{ number_format($t->nominal_transfer, 0, ',', '.') }}</td>
                                <td class="px-5 py-3.5">
                                    <x-status-badge status="sp2d_lunas" size="sm" />
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <a
                                        href="{{ route('dokumen.pdf', ['jenis' => 'transfer', 'id' => $t->nomor_transaksi_bank]) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <svg class="size-3.5 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                        PDF Bukti
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-100">
                {{ $transaksiList->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
