{{-- resources/views/dashboard/roles/keuangan.blade.php --}}
{{-- Role: Bagian Keuangan / Bendahara Pengeluaran (keuangan) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Berkas Tagihan / SPP Masuk"
            :value="$stats['spp_siap_verifikasi'] ?? 0"
            color="amber"
            icon="document"
            badge="Verifikasi"
            subtitle="Tagihan siap diverifikasi BAST & Memo"
            :href="route('pembayaran.index')"
        />

        <x-stat-card
            title="Perintah Bayar Siap Cair"
            :value="$stats['sp2d_siap_cair'] ?? 0"
            color="rose"
            icon="wallet"
            badge="Siap SP2D"
            subtitle="Menunggu eksekusi transfer pencairan"
            :href="route('pembayaran.index')"
        />

        <x-stat-card
            title="Realisasi Pencairan SP2D"
            :value="'Rp ' . number_format((float) ($stats['total_dana_cair'] ?? 0), 0, ',', '.')"
            color="emerald"
            icon="check"
            subtitle="Akumulasi dana cair ke rekanan"
        />

        <x-stat-card
            title="Transaksi Lunas Selesai"
            :value="$stats['transaksi_lunas'] ?? 0"
            color="navy"
            icon="check"
            subtitle="Transaksi terproteksi anti-duplikasi"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (6 Cols): Antrean Verifikasi Berkas Tagihan & SPP --}}
        <div class="lg:col-span-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Berkas Tagihan / SPP Siap Verifikasi</h2>
                </div>
                <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Proses Tagihan
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['antrean_tagihan'] ?? [] as $tagihan)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-mono font-bold text-slate-400 block">
                                    {{ $tagihan->nomor_pembayaran }}
                                </span>
                                <h4 class="text-xs font-bold text-[#213153] truncate mt-0.5" title="{{ $tagihan->paket->nama_paket ?? '-' }}">
                                    {{ $tagihan->paket->nama_paket ?? 'Pembayaran Pengadaan' }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    Penerima: {{ $tagihan->paket->vendor->nama_perusahaan ?? 'Penyedia Resmi' }}
                                </p>
                            </div>
                            <x-status-badge :status="$tagihan->status_pembayaran" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="font-bold text-[#0969da]">
                                Rp {{ number_format((float) ($tagihan->nominal_pengajuan ?? 0), 0, ',', '.') }}
                            </span>
                            <a href="{{ route('pembayaran.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                Cairkan SP2D &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Seluruh berkas tagihan SPP telah diproses dan dicairkan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KOLOM KANAN (6 Cols): Riwayat Transaksi Pencairan SP2D Anti-Duplikasi --}}
        <div class="lg:col-span-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Riwayat Pencairan SP2D (Anti-Duplikasi)</h2>
                </div>
                <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Lihat Pembukuan
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Tanggal Cair</th>
                                <th class="px-4 py-3">No. SPP / Bukti</th>
                                <th class="px-4 py-3">Penerima Rekanan</th>
                                <th class="px-4 py-3">Nominal Cair</th>
                                <th class="px-4 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['transaksi_cair'] ?? [] as $tx)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                        {{ $tx->tanggal_transfer ? date('d M Y', strtotime($tx->tanggal_transfer)) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $tx->nomor_pembayaran }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate">
                                        {{ $tx->pembayaran->paket->vendor->nama_perusahaan ?? 'Penyedia Rekanan' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-emerald-700">
                                        Rp {{ number_format((float) ($tx->nominal_transfer ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <x-status-badge status="sp2d_lunas" size="xs" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Belum ada rekaman pencairan dana SP2D yang tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
