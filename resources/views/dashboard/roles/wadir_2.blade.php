{{-- resources/views/dashboard/roles/wadir_2.blade.php --}}
{{-- Role: Wakil Direktur II - Otoritas Persetujuan Eksekutif Mutlak (wadir_2) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Menunggu Disposisi / ACC"
            :value="$stats['menunggu_persetujuan'] ?? 0"
            color="rose"
            icon="bell"
            badge="Eksekutif"
            subtitle="Menunggu persetujuan mutlak Wadir 2"
            :href="route('verifikasi.wadir-2')"
        />

        <x-stat-card
            title="Memo Bayar Lvl 2 Pending"
            :value="$stats['memo_bayar_pending'] ?? 0"
            color="amber"
            icon="wallet"
            badge="Level 2"
            subtitle="Antrean tanda tangan persetujuan SPP"
            :href="route('pembayaran.index')"
        />

        <x-stat-card
            title="Total Disetujui (ACC)"
            :value="$stats['total_disetujui'] ?? 0"
            color="emerald"
            icon="check"
            subtitle="Diteruskan resmi ke PPBJ"
        />

        <x-stat-card
            title="Total Ditolak / Revisi"
            :value="$stats['total_ditolak'] ?? 0"
            color="navy"
            icon="document"
            subtitle="Dikembalikan ke unit pengusul"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (7 Cols): Antrean Disposisi Eksekutif Usulan --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Disposisi Eksekutif Pengadaan</h2>
                </div>
                <a href="{{ route('verifikasi.wadir-2') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Buka Panel Keputusan
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">No. Surat</th>
                                <th class="px-4 py-3">Unit Pengusul</th>
                                <th class="px-4 py-3">Perihal</th>
                                <th class="px-4 py-3">Estimasi Pagu</th>
                                <th class="px-4 py-3 text-right">Keputusan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['antrean_disposisi'] ?? [] as $disposisi)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $disposisi->nomor_surat }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $disposisi->unit->nama_unit ?? $disposisi->kode_unit ?? 'Unit Kerja' }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $disposisi->judul_pengadaan }}">
                                        {{ $disposisi->judul_pengadaan }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                        Rp {{ number_format((float) ($disposisi->estimasi_total_pagu ?? $disposisi->anggaran_diajukan ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('verifikasi.wadir-2') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-colors">
                                            Disposisi ACC/Tolak
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Tidak ada berkas usulan permohonan yang menunggu disposisi saat ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN (5 Cols): Antrean Persetujuan Memo Bayar Level 2 --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Memo Bayar Level 2</h2>
                </div>
                <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Lihat Semua
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['antrean_memo_bayar'] ?? [] as $memo)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-[#213153] truncate">
                                    {{ $memo->pembayaran->paket->nama_paket ?? $memo->nomor_pembayaran }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    Rekanan: {{ $memo->pembayaran->paket->vendor->nama_perusahaan ?? 'Penyedia Resmi' }}
                                </p>
                            </div>
                            <x-status-badge status="spp_terbit" text="Menunggu ACC Lvl 2" size="xs" />
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                            <span class="font-bold text-[#0969da]">
                                Rp {{ number_format((float) ($memo->pembayaran->nominal_pengajuan ?? 0), 0, ',', '.') }}
                            </span>
                            <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                                Tindak Lanjut &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Tidak ada antrean Memo Bayar Level 2 yang pending.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
