{{-- resources/views/dashboard/roles/pp.blade.php --}}
{{-- Role: Pejabat Pengadaan (pp - Nilai <= Rp 200 Juta) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Paket Jalur PP Ditugaskan"
            :value="$stats['total_paket_pp'] ?? 0"
            color="navy"
            icon="package"
            subtitle="Nilai Rp 50jt s.d Rp 200jt"
            :href="route('kontrak.index')"
        />

        <x-stat-card
            title="Proses Negosiasi & Reviu"
            :value="$stats['paket_negosiasi'] ?? 0"
            color="amber"
            icon="bell"
            badge="Tindak Lanjut"
            subtitle="Klarifikasi teknis & harga vendor"
        />

        <x-stat-card
            title="SPK Diterbitkan"
            :value="$stats['paket_spk'] ?? 0"
            color="blue"
            icon="document"
            badge="SPK Terbit"
            subtitle="Kontrak rekanan berhasil terbit"
        />

        <x-stat-card
            title="Paket Selesai"
            :value="$stats['paket_selesai'] ?? 0"
            color="emerald"
            icon="check"
            subtitle="Tuntas BAST & pencairan dana"
        />
    </div>

    {{-- 2. MAIN OPERATION: ANTREAN REVIU TEKNIS & NEGOSIASI HARGA --}}
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-lg font-bold text-[#213153]">Antrean Paket Menunggu Reviu & Negosiasi Harga Definitif</h2>
            </div>
            <a href="{{ route('kontrak.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                Buka Ruang Kerja SPK
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                            <th class="px-4 py-3">Kode Paket</th>
                            <th class="px-4 py-3">Nama Paket Pengadaan</th>
                            <th class="px-4 py-3">Unit Pengusul</th>
                            <th class="px-4 py-3">Nilai HPS</th>
                            <th class="px-4 py-3">Rekanan Calon Penyedia</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                        @forelse($roleData['antrean_negosiasi'] ?? [] as $pktPp)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                    {{ $pktPp->kode_paket ?? $pktPp->nomor_paket }}
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $pktPp->nama_paket }}">
                                    {{ $pktPp->nama_paket }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $pktPp->permohonan->unit->nama_unit ?? $pktPp->permohonan->kode_unit ?? 'Unit' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                    Rp {{ number_format((float) ($pktPp->nilai_hps ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $pktPp->vendor->nama_perusahaan ?? 'Belum Ditunjuk' }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-status-badge :status="$pktPp->status_paket" size="xs" />
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('kontrak.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                        Reviu / Nego
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Tidak ada paket pengadaan langsung yang menunggu negosiasi saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
