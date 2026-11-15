{{-- resources/views/dashboard/roles/perlengkapan.blade.php --}}
{{-- Modul RBAC: Bagian Perlengkapan & Pengelola BMN (perlengkapan) --}}
@php
    $canViewPenerimaan = $user->hasPermission('penerimaan.view');
    $canCreateBast = $user->hasPermission('penerimaan.bast_create');
    $penerimaanHref = $canViewPenerimaan ? route('penerimaan.index') : null;
    $bastHref = $canCreateBast ? route('penerimaan.index') : $penerimaanHref;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Menunggu BAST"
            :value="$stats['belum_diperiksa'] ?? 0"
            subtitle="Barang tiba perlu pemeriksaan"
            variant="action"
            :href="$bastHref"
        />

        <x-figma-stat-card
            title="SPK & Kwitansi Masuk"
            :value="$stats['spk_masuk'] ?? 0"
            subtitle="Tembusan pengiriman dari PPBJ"
            variant="active"
            :href="$penerimaanHref"
        />

        <x-figma-stat-card
            title="BAST Terbit"
            :value="$stats['total_bast'] ?? 0"
            subtitle="Naskah BAST sah diteruskan"
            variant="done"
            :href="$penerimaanHref"
        />

        <x-figma-stat-card
            title="Barang / Modal BMN"
            :value="($stats['belanja_barang'] ?? 0) . ' / ' . ($stats['belanja_modal'] ?? 0)"
            subtitle="Persediaan vs aset tetap BMN"
            variant="bell"
            :href="$penerimaanHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$bastHref"
        rightTitle="Riwayat BAST Terbit"
        :rightHref="$penerimaanHref"
    >
        <x-slot:left>
            @forelse($roleData['jadwal_pengiriman'] ?? [] as $itemKirim)
                @php
                    $isLangsung = !empty($itemKirim['is_langsung']);
                    $batasKirimRaw = $itemKirim['batas_kirim'] ?? null;
                    $batasKirimLabel = (!empty($batasKirimRaw) && strtotime((string) $batasKirimRaw))
                        ? date('d M Y', strtotime((string) $batasKirimRaw))
                        : ($batasKirimRaw ?: '-');
                    $refPrefix = $isLangsung ? 'Belanja Langsung <=50Jt' : 'No. SPK';
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    variant="action"
                    :title="$itemKirim['judul_paket'] ?? 'Paket Pengadaan'"
                    :meta="$refPrefix . ': ' . ($itemKirim['nomor_referensi'] ?? '-') . ' • Batas: ' . $batasKirimLabel"
                    :submeta="'Rekanan: ' . ($itemKirim['vendor_nama'] ?? 'Penyedia Resmi')"
                    badge="Siap BAST"
                    badgeClass="bg-[#fff5df] text-[#99660d]"
                    :href="$bastHref"
                    buttonLabel="Buka"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada pengiriman menunggu penerbitan BAST"
                    subtitle="Seluruh barang yang diterima telah selesai diperiksa dan diterbitkan BAST."
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">No. BAST</div>
                <div class="w-[24%] shrink-0">Penyedia</div>
                <div class="flex-1 px-2">Nama Paket</div>
                <div class="w-[18%] shrink-0 text-right">Klasifikasi</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['bast_terbaru'] ?? [] as $bast)
                    @php
                        $noBast = $bast->nomor_bast ?? ('BAST-' . $bast->id_penerimaan);
                        $penyedia = $bast->paket->vendor->nama_vendor ?? 'Penyedia';
                        $paketNama = $bast->paket->nama_paket ?? 'Paket Pengadaan';
                        $klasifikasi = $bast->kategori_belanja === 'belanja_modal' ? 'Aset BMN' : 'Persediaan';
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $noBast }}">
                            {{ $noBast }}
                        </div>
                        <div class="w-[24%] shrink-0 font-medium truncate" title="{{ $penyedia }}">
                            {{ $penyedia }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $paketNama }}">
                            {{ $paketNama }}
                        </div>
                        <div class="w-[18%] shrink-0 font-semibold text-right truncate {{ $bast->kategori_belanja === 'belanja_modal' ? 'text-[#0969da]' : 'text-[#087F79]' }}">
                            {{ $klasifikasi }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada naskah BAST yang diterbitkan.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
