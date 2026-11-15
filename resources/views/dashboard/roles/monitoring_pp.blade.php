{{-- resources/views/dashboard/roles/monitoring_pp.blade.php --}}
{{-- Modul RBAC: Monitoring Aktivitas Pejabat Pengadaan Standalone (pp.monitoring_aktivitas / lihat_pp) --}}
@php
    $monitorRoute = route('monitor-pp.index');
    $detailPpRoute = $user->hasAnyPermission(['reviu.pp', 'kontrak.view', 'kontrak.view_all'])
        ? route('kontrak.index')
        : $monitorRoute;
    $logHref = $user->hasAnyPermission(['laporan.view_all', 'laporan.view_unit'])
        ? route('laporan.index')
        : $monitorRoute;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Total Pejabat Pengadaan"
            :value="$stats['pp_total'] ?? 0"
            subtitle="Jalur pengadaan 50–200 Juta"
            variant="active"
            :href="$monitorRoute"
        />

        <x-figma-stat-card
            title="Sedang Reviu"
            :value="$stats['pp_dalam_reviu'] ?? 0"
            subtitle="Telaah spesifikasi & negosiasi"
            variant="action"
            :href="$monitorRoute"
        />

        <x-figma-stat-card
            title="Menunggu Persetujuan PPK"
            :value="$stats['pp_menunggu_ppk'] ?? 0"
            subtitle="Proses persetujuan dokumen"
            variant="bell"
            :href="$monitorRoute"
        />

        <x-figma-stat-card
            title="Siap Belanja / Kontrak"
            :value="$stats['pp_siap_belanja'] ?? 0"
            subtitle="Eksekusi belanja atau kontrak"
            variant="done"
            :href="$monitorRoute"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$monitorRoute"
        rightTitle="Aktivitas Pejabat Pengadaan"
        :rightHref="$logHref"
    >
        <x-slot:left>
            @forelse($roleData['pp_monitoring_list'] ?? [] as $ppPkt)
                @php
                    $kode = $ppPkt->kode_paket ?? $ppPkt->nomor_paket;
                    $hps = number_format((float) ($ppPkt->nilai_hps ?? 0), 0, ',', '.');
                    $vendor = $ppPkt->vendor->nama_vendor ?? 'Vendor belum dipilih';
                    $statusLabel = ucwords(str_replace('_', ' ', $ppPkt->status_paket ?? '-'));
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    variant="action"
                    :title="$ppPkt->nama_paket"
                    :meta="$kode . ' • Perkiraan Harga: Rp ' . $hps"
                    :submeta="'Penyedia: ' . $vendor"
                    :badge="$statusLabel"
                    badgeClass="bg-[#fff5df] text-[#99660d]"
                    :href="$detailPpRoute"
                    buttonLabel="Buka"
                />
            @empty
                <x-figma-empty
                    title="Belum ada paket jalur Pejabat Pengadaan"
                    subtitle=""
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Waktu</div>
                <div class="w-[22%] shrink-0">Aksi</div>
                <div class="flex-1 px-2">Paket &amp; Keterangan</div>
                <div class="w-[20%] shrink-0 text-right">Pelaksana</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['pp_audit_logs'] ?? [] as $audit)
                    @php
                        $waktu = $audit->waktu_log ? \Carbon\Carbon::parse($audit->waktu_log)->format('d M Y, H:i') : '-';
                        $aksi = ucwords(strtolower(str_replace('_', ' ', $audit->jenis_aksi ?? 'Aktivitas')));
                        $detail = $audit->alasan ?: trim(($audit->nama_entitas ?? 'Paket') . ' #' . ($audit->id_entitas ?? '-'));
                        $pelaksana = $audit->user->nama ?? $audit->user->name ?? 'Pejabat Pengadaan';
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $waktu }}">
                            {{ $waktu }}
                        </div>
                        <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $aksi }}">
                            {{ $aksi }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $detail }}">
                            {{ $detail }}
                        </div>
                        <div class="w-[20%] shrink-0 font-semibold text-right truncate" title="{{ $pelaksana }}">
                            {{ $pelaksana }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada log aktivitas Pejabat Pengadaan yang tercatat.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
