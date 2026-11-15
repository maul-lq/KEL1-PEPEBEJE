{{-- resources/views/dashboard/roles/wadir_2.blade.php --}}
{{-- Modul RBAC: Wakil Direktur II - Disposisi Eksekutif & Memo Bayar Level 1 (verifikasi_wadir2) --}}
@php
    $canVerifWadir = $user->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide']);
    $canMemoWadir = $user->hasPermission('pembayaran.memo_wadir2');
    $wadirVerifHref = $canVerifWadir ? route('verifikasi.wadir-2') : null;
    $memoWadirHref = $canMemoWadir ? route('pembayaran.index') : null;
    $leftHref = $wadirVerifHref ?? $memoWadirHref;

    $disposisiItems = $canVerifWadir ? ($roleData['antrean_disposisi'] ?? collect()) : collect();
    $memoItems = $canMemoWadir ? ($roleData['antrean_memo_bayar'] ?? collect()) : collect();
    $cardIdx = 0;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Menunggu Persetujuan"
            :value="$stats['menunggu_persetujuan'] ?? 0"
            subtitle="Menunggu keputusan Wadir II"
            variant="action"
            :href="$wadirVerifHref"
        />

        <x-figma-stat-card
            title="Memo Bayar Lvl 1"
            :value="$stats['memo_bayar_pending'] ?? 0"
            :subtitle="$canMemoWadir ? 'Antrean persetujuan memo' : 'Akses Memo tidak aktif'"
            variant="bell"
            :href="$memoWadirHref"
        />

        <x-figma-stat-card
            title="Usulan Disetujui"
            :value="$stats['total_disetujui'] ?? 0"
            subtitle="Diteruskan ke Unit Pengadaan"
            variant="done"
            :href="$wadirVerifHref"
        />

        <x-figma-stat-card
            title="Ditolak / Revisi"
            :value="$stats['total_ditolak'] ?? 0"
            subtitle="Dikembalikan ke unit pengusul"
            variant="active"
            :href="$wadirVerifHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$leftHref"
        rightTitle="Aktivitas Terbaru"
        :rightHref="$leftHref"
    >
        <x-slot:left>
            @if($disposisiItems->isEmpty() && $memoItems->isEmpty())
                <x-figma-empty
                    title="Tidak ada disposisi atau Memo Bayar menunggu"
                    subtitle=""
                />
            @else
                @foreach($disposisiItems as $disposisi)
                    @php
                        $unitNama = $disposisi->unit->nama_unit ?? $disposisi->kode_unit ?? 'Unit Kerja';
                        $estimasiPagu = number_format((float) ($disposisi->estimasi_total_pagu ?? $disposisi->anggaran_diajukan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="action"
                        :title="$disposisi->judul_pengadaan"
                        :meta="$disposisi->nomor_surat . ' • ' . $unitNama"
                        :submeta="'Estimasi Pagu: Rp ' . $estimasiPagu"
                        badge="Perlu Persetujuan"
                        badgeClass="bg-[#fff5df] text-[#99660d]"
                        :href="$wadirVerifHref"
                        buttonLabel="Buka"
                    />
                @endforeach

                @foreach($memoItems as $memo)
                    @php
                        $paketNama = $memo->pembayaran->paket->nama_paket ?? $memo->nomor_pembayaran ?? 'Memo Pembayaran';
                        $vendorNama = $memo->pembayaran->paket->vendor->nama_vendor ?? 'Penyedia Resmi';
                        $nominalBayar = number_format((float) ($memo->pembayaran->nominal_pengajuan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="bell"
                        :title="$paketNama"
                        :meta="$memo->nomor_pembayaran . ' • ' . $vendorNama"
                        :submeta="'Nominal Pengajuan: Rp ' . $nominalBayar . ' • Persetujuan Level 1'"
                        badge="Memo Lvl 1"
                        badgeClass="bg-blue-50 text-[#0969da]"
                        :href="$memoWadirHref"
                        buttonLabel="Buka"
                    />
                @endforeach
            @endif
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Waktu</div>
                <div class="w-[24%] shrink-0">Aktivitas</div>
                <div class="flex-1 px-2">Nama Pengadaan / Paket</div>
                <div class="w-[18%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['wadir2_recent'] ?? [] as $akt)
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $akt['waktu'] ?? '-' }}">
                            {{ $akt['waktu'] ?? '-' }}
                        </div>
                        <div class="w-[24%] shrink-0 font-semibold truncate" title="{{ $akt['aktivitas'] ?? '-' }}">
                            {{ $akt['aktivitas'] ?? '-' }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $akt['subjek'] ?? '-' }}">
                            {{ $akt['subjek'] ?? '-' }}
                        </div>
                        <div class="w-[18%] shrink-0 font-semibold text-right truncate" title="{{ $akt['status'] ?? '-' }}">
                            {{ $akt['status'] ?? '-' }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada riwayat disposisi atau Memo Bayar Level 1 yang tercatat.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
