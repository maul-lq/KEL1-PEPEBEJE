{{-- resources/views/dashboard/roles/ppbj.blade.php --}}
{{-- Role: Koordinator Pengadaan Barang & Jasa (ppbj) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Total Paket Terdaftar"
            :value="$stats['total_paket'] ?? 0"
            color="navy"
            icon="package"
            subtitle="Paket aktif dalam proses"
            :href="route('paket.index')"
        />

        <x-stat-card
            title="Pengadaan Langsung (<=50jt)"
            :value="$stats['routing_langsung'] ?? 0"
            color="teal"
            icon="box"
            subtitle="Auto-routing mandiri SPK"
        />

        <x-stat-card
            title="Jalur PP (50jt - 200jt)"
            :value="$stats['routing_pp'] ?? 0"
            color="blue"
            icon="users"
            subtitle="Reviu & negosiasi harga PP"
        />

        <x-stat-card
            title="Jalur PPK (>200jt)"
            :value="$stats['routing_ppk'] ?? 0"
            color="amber"
            icon="users"
            badge="Tender"
            subtitle="Ditugaskan ke 4 akun PPK"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (5 Cols): Usulan Disetujui Siap Registrasi Paket --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-blue-600 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Siap Registrasi Paket</h2>
                </div>
                <a href="{{ route('paket.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-all">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    + Paket Baru
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['siap_paket'] ?? [] as $siap)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-[#213153] truncate">
                                    {{ $siap->nomor_surat }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2" title="{{ $siap->judul_pengadaan }}">
                                    {{ $siap->judul_pengadaan }}
                                </p>
                                <span class="text-[10px] text-slate-400 mt-1 block">
                                    Unit: {{ $siap->unit->nama_unit ?? $siap->kode_unit ?? 'Unit' }}
                                </span>
                            </div>
                            <x-status-badge status="disetujui" text="Siap Paket" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="font-bold text-[#0969da]">
                                Rp {{ number_format((float) ($siap->estimasi_total_pagu ?? $siap->anggaran_diajukan ?? 0), 0, ',', '.') }}
                            </span>
                            <a href="{{ route('paket.create') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                Buat Paket &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Seluruh usulan yang disetujui telah memiliki paket pengadaan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KOLOM KANAN (7 Cols): Tabel Monitoring Paket & Jalur Routing --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[#087F79]"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Monitoring Paket & Penugasan PPK</h2>
                </div>
                <a href="{{ route('paket.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Kelola Seluruh Paket
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Kode Paket</th>
                                <th class="px-4 py-3">Nama Paket</th>
                                <th class="px-4 py-3">Nilai HPS</th>
                                <th class="px-4 py-3">Jalur Routing</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($recentPaket as $pkt)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $pkt->kode_paket ?? $pkt->nomor_paket }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $pkt->nama_paket }}">
                                        {{ $pkt->nama_paket }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                        Rp {{ number_format((float) ($pkt->nilai_hps ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-status-badge :status="$pkt->jalur_routing ?? 'langsung_sd_50jt'" size="xs" />
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('paket.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 transition-colors">
                                            Kelola / Reassign
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Belum ada paket pengadaan terdaftar.
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
