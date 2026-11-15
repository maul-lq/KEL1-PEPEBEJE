{{-- resources/views/dashboard/roles/ppbj.blade.php --}}
{{-- Modul RBAC: Koordinator Pengadaan Barang & Jasa (ppbj) --}}
@php
    $canManagePaket = $user->hasAnyPermission(['paket.view_all', 'paket.create', 'paket.reassign_ppk', 'permohonan.split_jenis']);
    $canCreatePaket = $user->hasPermission('paket.create');
    $canBayar = $user->hasAnyPermission(['pembayaran.pengajuan', 'pembayaran.spp_terbitkan']);
    $canMonitorPp = $user->canMonitorPp();

    $paketHref = $canManagePaket ? route('paket.index') : null;
    $createPaketHref = $canCreatePaket ? route('paket.create') : $paketHref;
    $bayarHref = $canBayar ? route('pembayaran.index') : null;
    $leftHref = $createPaketHref ?? $bayarHref ?? $paketHref;

    $bastSiapList = $roleData['bast_siap_bayar'] ?? collect();
    $siapPaketList = $roleData['siap_paket'] ?? collect();
    $recentPaketList = $roleData['ppbj_recent_paket'] ?? ($recentPaket ?? collect());
    $ppMonitoringList = $roleData['pp_monitoring_list'] ?? collect();
    $cardIdx = 0;

    $routingSummary = '<=50Jt: ' . ($stats['routing_langsung'] ?? 0) . ' • PP: ' . ($stats['routing_pp'] ?? 0) . ' • PPK: ' . ($stats['routing_ppk'] ?? 0);
@endphp

<div class="space-y-6" x-data="{ rightTab: 'paket' }">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Total Paket"
            :value="$stats['total_paket'] ?? 0"
            :subtitle="$routingSummary"
            variant="active"
            :href="$paketHref"
        />

        <x-figma-stat-card
            title="Siap Registrasi Paket"
            :value="$stats['usulan_siap_paket'] ?? 0"
            subtitle="Usulan disetujui belum terpaket"
            variant="action"
            :href="$createPaketHref"
        />

        <x-figma-stat-card
            title="Menunggu SPK"
            :value="$stats['belum_spk'] ?? 0"
            subtitle="Paket belum diterbitkan SPK"
            variant="bell"
            :href="$paketHref"
        />

        <x-figma-stat-card
            title="BAST Siap Bayar"
            :value="$stats['bast_siap_bayar_count'] ?? 0"
            subtitle="Menunggu pengajuan SPP/bayar"
            variant="done"
            :href="$bayarHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$leftHref"
        rightTitle="Monitoring Paket"
        :rightHref="$paketHref"
    >
        @if($canMonitorPp)
            <x-slot:rightHeaderExtra>
                <div class="inline-flex items-center rounded-[5px] border border-[#d2d2d2] bg-slate-50 p-0.5 text-[11px] font-semibold">
                    <button
                        type="button"
                        @click="rightTab = 'paket'"
                        :class="rightTab === 'paket' ? 'bg-white text-[#14213d] shadow-xs' : 'text-[#657a7c] hover:text-[#14213d]'"
                        class="px-2.5 py-1 rounded-[4px] transition-all cursor-pointer"
                    >
                        Paket
                    </button>
                    <button
                        type="button"
                        @click="rightTab = 'pp'"
                        :class="rightTab === 'pp' ? 'bg-white text-[#14213d] shadow-xs' : 'text-[#657a7c] hover:text-[#14213d]'"
                        class="px-2.5 py-1 rounded-[4px] transition-all cursor-pointer"
                    >
                        Monitor PP ({{ $stats['pp_total'] ?? 0 }})
                    </button>
                </div>
            </x-slot:rightHeaderExtra>
        @endif

        <x-slot:left>
            @if($bastSiapList->isEmpty() && $siapPaketList->isEmpty())
                <x-figma-empty
                    title="Tidak ada BAST atau usulan siap registrasi"
                    subtitle="Seluruh usulan yang disetujui telah didaftarkan menjadi paket pengadaan."
                />
            @else
                @foreach($bastSiapList as $bast)
                    @php
                        $nilaiBast = number_format((float) ($bast->paket?->nominal_harga_akhir ?: ($bast->paket?->nilai_hps ?: 0)), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="done"
                        :title="$bast->paket?->nama_paket ?? ('BAST ' . $bast->nomor_bast)"
                        :meta="$bast->nomor_bast . ' • Paket: ' . $bast->nomor_paket"
                        :submeta="'Nilai Realisasi: Rp ' . $nilaiBast . ' • BAST Sah Siap Pengajuan Bayar'"
                        badge="BAST Sah"
                        badgeClass="bg-emerald-100 text-emerald-800"
                        :href="$canBayar ? route('pembayaran.index', ['nomor_paket' => $bast->nomor_paket]) : null"
                        buttonLabel="Dokumen Bayar"
                    />
                @endforeach

                @foreach($siapPaketList as $siap)
                    @php
                        $unitNama = $siap->unit->nama_unit ?? $siap->kode_unit ?? 'Unit Kerja';
                        $estimasi = number_format((float) ($siap->estimasi_total_pagu ?? $siap->anggaran_diajukan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="action"
                        :title="$siap->judul_pengadaan"
                        :meta="$siap->nomor_surat . ' • ' . $unitNama"
                        :submeta="'Estimasi Pagu: Rp ' . $estimasi . ' • Usulan Siap Dibuat Paket'"
                        badge="Siap Paket"
                        badgeClass="bg-[#fff5df] text-[#99660d]"
                        :href="$canCreatePaket ? route('paket.create', ['nomor_surat' => $siap->nomor_surat]) : $paketHref"
                        buttonLabel="Buat Paket"
                    />
                @endforeach
            @endif
        </x-slot:left>

        <x-slot:right>
            <div x-show="rightTab === 'paket'">
                <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                    <div class="w-[22%] shrink-0">Kode Paket</div>
                    <div class="w-[22%] shrink-0">Jalur</div>
                    <div class="flex-1 px-2">Nama Paket</div>
                    <div class="w-[20%] shrink-0 text-right">Status</div>
                </div>

                <div class="divide-y divide-[#d2d2d2]">
                    @forelse($recentPaketList as $pkt)
                        @php
                            $kode = $pkt->kode_paket ?? $pkt->nomor_paket;
                            $jalur = match ($pkt->jalur_routing) {
                                'langsung_sd_50jt' => '<= 50 Jt',
                                'pp_50jt_sd_200jt', 'pp_50_sd_200jt' => 'Jalur PP',
                                'ppk_diatas_200jt' => 'Jalur PPK',
                                default => ucwords(str_replace('_', ' ', $pkt->jalur_routing ?? '-')),
                            };
                            $statusLabel = ucwords(str_replace('_', ' ', $pkt->status_paket ?? '-'));
                        @endphp
                        <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                            <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $kode }}">
                                {{ $kode }}
                            </div>
                            <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $jalur }}">
                                {{ $jalur }}
                            </div>
                            <div class="flex-1 px-2 font-medium truncate" title="{{ $pkt->nama_paket }}">
                                {{ $pkt->nama_paket }}
                            </div>
                            <div class="w-[20%] shrink-0 font-semibold text-right truncate" title="{{ $statusLabel }}">
                                {{ $statusLabel }}
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-xs text-slate-400">
                            Belum ada paket pengadaan terdaftar.
                        </div>
                    @endforelse
                </div>
            </div>

            @if($canMonitorPp)
                <div x-show="rightTab === 'pp'" x-cloak>
                    <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                        <div class="w-[22%] shrink-0">Kode Paket</div>
                        <div class="w-[24%] shrink-0">Nilai / Negosiasi</div>
                        <div class="flex-1 px-2">Nama Paket &amp; Vendor</div>
                        <div class="w-[18%] shrink-0 text-right">Status</div>
                    </div>

                    <div class="divide-y divide-[#d2d2d2]">
                        @forelse($ppMonitoringList as $ppPkt)
                            @php
                                $kodePp = $ppPkt->kode_paket ?? $ppPkt->nomor_paket;
                                $nilaiPp = number_format((float) ($ppPkt->nominal_harga_akhir ?: ($ppPkt->nilai_hasil_negosiasi ?: ($ppPkt->nilai_hps ?? 0))), 0, ',', '.');
                                $namaVendor = $ppPkt->nama_paket . ($ppPkt->vendor?->nama_vendor ? ' • ' . $ppPkt->vendor->nama_vendor : '');
                                $statusPp = ucwords(str_replace('_', ' ', $ppPkt->status_paket ?? '-'));
                            @endphp
                            <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                                <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $kodePp }}">
                                    {{ $kodePp }}
                                </div>
                                <div class="w-[24%] shrink-0 font-semibold truncate" title="Rp {{ $nilaiPp }}">
                                    Rp {{ $nilaiPp }}
                                </div>
                                <div class="flex-1 px-2 font-medium truncate" title="{{ $namaVendor }}">
                                    {{ $namaVendor }}
                                </div>
                                <div class="w-[18%] shrink-0 font-semibold text-right truncate" title="{{ $statusPp }}">
                                    {{ $statusPp }}
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-xs text-slate-400">
                                Belum ada paket yang sedang berada dalam jalur Pejabat Pengadaan (PP).
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-3 pt-2 border-t border-slate-100 flex justify-end">
                        <a href="{{ route('monitor-pp.index') }}" class="text-xs font-semibold text-[#3b82f6] hover:underline">
                            Buka Halaman Monitor PP Lengkap &rarr;
                        </a>
                    </div>
                </div>
            @endif
        </x-slot:right>
    </x-figma-workspace>
</div>
