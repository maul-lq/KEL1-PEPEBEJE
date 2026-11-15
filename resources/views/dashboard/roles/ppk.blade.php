{{-- resources/views/dashboard/roles/ppk.blade.php --}}
{{-- Modul RBAC: Pejabat Pembuat Komitmen - Portofolio Terisolasi & Memo Level 2 (ppk) --}}
@php
    $hasReviuPpk = $user->hasPermission('reviu.ppk');
    $hasMemoPpk = $user->hasPermission('pembayaran.memo_ppk');

    $verifPpkHref = $hasReviuPpk ? route('verifikasi.ppk') : null;
    $dokumenPpkHref = $hasReviuPpk ? route('ppk.persetujuan-dokumen.index') : null;
    $kontrakPpkHref = $hasReviuPpk ? route('kontrak.index') : null;
    $memoPpkHref = $hasMemoPpk ? route('pembayaran.index') : null;
    $leftHref = $dokumenPpkHref ?? $verifPpkHref ?? $memoPpkHref;

    $memoList = $hasMemoPpk ? ($roleData['antrean_memo'] ?? collect()) : collect();
    $dokumenList = $hasReviuPpk ? ($roleData['antrean_dokumen_pengadaan_list'] ?? collect()) : collect();
    $awalList = $hasReviuPpk ? ($roleData['antrean_persetujuan_awal_list'] ?? collect()) : collect();
    $cardIdx = 0;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Persetujuan Awal"
            :value="$stats['antrean_persetujuan_awal'] ?? 0"
            subtitle="Usulan perlu persetujuan PPK"
            variant="active"
            :href="$verifPpkHref"
        />

        <x-figma-stat-card
            title="Reviu Dokumen Paket"
            :value="$stats['antrean_dokumen_pengadaan'] ?? 0"
            subtitle="Dokumen paket siap disetujui"
            variant="action"
            :href="$dokumenPpkHref"
        />

        <x-figma-stat-card
            title="Paket Tugas Saya"
            :value="$stats['total_paket_ppk'] ?? 0"
            :subtitle="'Tugas Saya • Kontrak Aktif: ' . ($stats['spk_aktif'] ?? 0)"
            variant="done"
            :href="$kontrakPpkHref"
        />

        <x-figma-stat-card
            title="Memo Bayar Lvl 2"
            :value="$stats['memo_bayar_pending'] ?? 0"
            :subtitle="$hasMemoPpk ? 'Persetujuan akhir tagihan' : 'Akses Memo tidak aktif'"
            variant="bell"
            :href="$memoPpkHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$leftHref"
        rightTitle="Paket Tugas Saya"
        :rightHref="$kontrakPpkHref"
    >
        <x-slot:left>
            @if($memoList->isEmpty() && $dokumenList->isEmpty() && $awalList->isEmpty())
                <x-figma-empty
                    title="Tidak ada item yang memerlukan tindakan PPK"
                    subtitle=""
                />
            @else
                @foreach($memoList as $memoLvl2)
                    @php
                        $paketNama = $memoLvl2->pembayaran->paket->nama_paket ?? $memoLvl2->nomor_pembayaran ?? 'Memo Pembayaran';
                        $vendorNama = $memoLvl2->pembayaran->paket->vendor->nama_vendor ?? 'Penyedia Resmi';
                        $nominal = number_format((float) ($memoLvl2->pembayaran->nominal_pengajuan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="bell"
                        :title="$paketNama"
                        :meta="$memoLvl2->nomor_pembayaran . ' • ' . $vendorNama"
                        :submeta="'Nominal Tagihan: Rp ' . $nominal . ' • Memo Bayar Level 2'"
                        badge="Memo Lvl 2"
                        badgeClass="bg-rose-100 text-rose-700"
                        :href="$memoPpkHref"
                        buttonLabel="Buka"
                    />
                @endforeach

                @foreach($dokumenList as $pktDok)
                    @php
                        $unitNama = $pktDok->permohonan->unit->nama_unit ?? $pktDok->permohonan->kode_unit ?? '-';
                        $hps = number_format((float) ($pktDok->nilai_hps ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="action"
                        :title="$pktDok->nama_paket"
                        :meta="($pktDok->kode_paket ?? $pktDok->nomor_paket) . ' • ' . $unitNama"
                        :submeta="'Perkiraan Harga: Rp ' . $hps . ' • Persetujuan Dokumen'"
                        badge="Setujui Dokumen"
                        badgeClass="bg-[#fff5df] text-[#99660d]"
                        :href="$dokumenPpkHref"
                        buttonLabel="Buka"
                    />
                @endforeach

                @foreach($awalList as $usulan)
                    @php
                        $unitNama = $usulan->unit->nama_unit ?? $usulan->kode_unit ?? '-';
                        $estimasiPagu = number_format((float) ($usulan->estimasi_total_pagu ?? $usulan->anggaran_diajukan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="active"
                        :title="$usulan->judul_pengadaan ?? $usulan->perihal ?? $usulan->nomor_surat"
                        :meta="$usulan->nomor_surat . ' • ' . $unitNama"
                        :submeta="'Estimasi Pagu: Rp ' . $estimasiPagu . ' • Persetujuan Awal'"
                        badge="Persetujuan Awal"
                        badgeClass="bg-teal-50 text-teal-800"
                        :href="$verifPpkHref"
                        buttonLabel="Buka"
                    />
                @endforeach
            @endif
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Kode Paket</div>
                <div class="w-[26%] shrink-0">Rekanan &amp; Harga</div>
                <div class="flex-1 px-2">Nama Paket</div>
                <div class="w-[18%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['paket_komitmen'] ?? [] as $komitmen)
                    @php
                        $kodePkt = $komitmen->kode_paket ?? $komitmen->nomor_paket;
                        $rekananHps = ($komitmen->vendor->nama_vendor ?? 'Penyedia Terpilih') . ' • Rp ' . number_format((float) ($komitmen->nilai_hps ?? 0), 0, ',', '.');
                        $statusLabel = ucwords(str_replace('_', ' ', $komitmen->status_paket ?? '-'));
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $kodePkt }}">
                            {{ $kodePkt }}
                        </div>
                        <div class="w-[26%] shrink-0 font-medium truncate" title="{{ $rekananHps }}">
                            {{ $rekananHps }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $komitmen->nama_paket }}">
                            {{ $komitmen->nama_paket }}
                        </div>
                        <div class="w-[18%] shrink-0 font-semibold text-right truncate" title="{{ $statusLabel }}">
                            {{ $statusLabel }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada paket yang ditugaskan kepada Anda.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
