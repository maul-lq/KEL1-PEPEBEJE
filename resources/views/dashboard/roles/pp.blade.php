{{-- resources/views/dashboard/roles/pp.blade.php --}}
{{-- Modul RBAC: Pejabat Pengadaan (pp - Nilai 50jt s.d 200jt) --}}
@php
    $ppRoute = $user->hasAnyPermission(['reviu.pp', 'kontrak.view', 'kontrak.view_all'])
        ? route('kontrak.index', ['tab' => 'reviu'])
        : null;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Total Paket PP"
            :value="$stats['total_paket_pp'] ?? 0"
            subtitle="Jalur nilai Rp 50jt - 200jt"
            variant="active"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Reviu & Negosiasi"
            :value="$stats['paket_negosiasi'] ?? 0"
            subtitle="Telaah dokumen & harga PP"
            variant="action"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Verifikasi Selesai"
            :value="$stats['paket_spk'] ?? 0"
            subtitle="ACC PPK atau proses SPK"
            variant="bell"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Paket Selesai"
            :value="$stats['paket_selesai'] ?? 0"
            subtitle="Tuntas BAST & pencairan dana"
            variant="done"
            :href="$ppRoute"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$ppRoute"
        rightTitle="Riwayat Paket PP"
        :rightHref="$ppRoute"
    >
        <x-slot:left>
            @forelse($roleData['antrean_negosiasi'] ?? [] as $pktPp)
                @php
                    $isSiapBelanja = $pktPp->status_paket === \App\Models\PaketPengadaan::STATUS_DISETUJUI_PPK_SIAP_BELANJA_PP;
                    $kode = $pktPp->kode_paket ?? $pktPp->nomor_paket;
                    $unitNama = $pktPp->permohonan->unit->nama_unit ?? $pktPp->permohonan->kode_unit ?? 'Unit';
                    $hps = number_format((float) ($pktPp->nilai_hps ?? 0), 0, ',', '.');
                    $catatan = $pktPp->catatan_verifikasi_pp
                        ? 'Catatan: ' . \Illuminate\Support\Str::limit($pktPp->catatan_verifikasi_pp, 50)
                        : ($isSiapBelanja ? 'Disetujui PPK • Siap eksekusi belanja' : 'Menunggu review dokumen PP');
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    :variant="$isSiapBelanja ? 'done' : 'action'"
                    :title="$pktPp->nama_paket"
                    :meta="$kode . ' • ' . $unitNama . ' • HPS: Rp ' . $hps"
                    :submeta="$catatan"
                    :badge="$isSiapBelanja ? 'Siap Belanja' : 'Perlu Reviu'"
                    :badgeClass="$isSiapBelanja ? 'bg-emerald-100 text-emerald-800' : 'bg-[#fff5df] text-[#99660d]'"
                    :href="$ppRoute"
                    :buttonLabel="$isSiapBelanja ? 'Eksekusi Belanja' : 'Buka'"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada paket menunggu review atau belanja"
                    subtitle="Seluruh paket pengadaan jalur PP saat ini telah ditindaklanjuti."
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Kode Paket</div>
                <div class="w-[22%] shrink-0">Nilai HPS</div>
                <div class="flex-1 px-2">Nama Paket &amp; Vendor</div>
                <div class="w-[20%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['riwayat_paket_pp'] ?? [] as $pktRiwayat)
                    @php
                        $kode = $pktRiwayat->kode_paket ?? $pktRiwayat->nomor_paket;
                        $hps = 'Rp ' . number_format((float) ($pktRiwayat->nilai_hps ?? 0), 0, ',', '.');
                        $namaVendor = $pktRiwayat->nama_paket . ($pktRiwayat->vendor?->nama_vendor ? ' • ' . $pktRiwayat->vendor->nama_vendor : '');
                        $statusLabel = ucwords(str_replace('_', ' ', $pktRiwayat->status_paket ?? '-'));
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $kode }}">
                            {{ $kode }}
                        </div>
                        <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $hps }}">
                            {{ $hps }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $namaVendor }}">
                            {{ $namaVendor }}
                        </div>
                        <div class="w-[20%] shrink-0 font-semibold text-right truncate" title="{{ $statusLabel }}">
                            {{ $statusLabel }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada riwayat paket pengadaan jalur PP.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
