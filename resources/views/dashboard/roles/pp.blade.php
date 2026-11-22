{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Sub-view dasbor ini rancang untuk modul Pejabat Pengadaan (paket Rp 50–200 Juta)
              yang memuat KPI negosiasi, antrean reviu/eksekusi belanja, dan riwayat paket PP.
--}}
{{--
  Komentar Deskriptif: Panel Workspace Dasbor Pejabat Pengadaan (`dashboard.roles.pp`).

  Tujuan: membedakan lencana status paket yang masih dalam tahap reviu teknis dengan yang
          sudah disetujui PPK agar Pejabat Pengadaan dapat memprioritaskan eksekusi belanja.
  Parameter: `$user` (User), `$stats` (array), `$roleData` (array).
  Nilai kembali: Fragmen HTML dasbor Pejabat Pengadaan dengan navigasi langsung ke tab reviu kontrak.
  Contoh pemanggilan: `@includeIf('dashboard.roles.pp', [...])`
--}}
@php
    $canReviuPp = $user->hasPermission('reviu.pp');
    $canCreateSpk = $user->hasPermission('spk.create');
    $ppRoute = $user->hasAnyPermission(['reviu.pp', 'spk.create', 'kontrak.view', 'kontrak.view_all'])
        ? route('kontrak.index', ['tab' => 'reviu'])
        : null;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Total Pejabat Pengadaan"
            :value="$stats['total_paket_pp'] ?? 0"
            subtitle="Jalur nilai Rp 50–200 Juta"
            variant="active"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Reviu & Negosiasi"
            :value="$stats['paket_negosiasi'] ?? 0"
            subtitle="Telaah dokumen & harga"
            variant="action"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Verifikasi Selesai"
            :value="$stats['paket_spk'] ?? 0"
            subtitle="Disetujui PPK atau kontrak"
            variant="bell"
            :href="$ppRoute"
        />

        <x-figma-stat-card
            title="Paket Selesai"
            :value="$stats['paket_selesai'] ?? 0"
            subtitle="Serah terima & dana cair"
            variant="done"
            :href="$ppRoute"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$ppRoute"
        rightTitle="Riwayat Pejabat Pengadaan"
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
                        : ($isSiapBelanja ? 'Disetujui PPK • Siap eksekusi belanja' : 'Menunggu reviu dokumen');
                    $ppActionHref = $isSiapBelanja
                        ? ($canCreateSpk ? route('kontrak.index', ['tab' => 'reviu']) : null)
                        : ($canReviuPp ? route('kontrak.index', ['tab' => 'reviu']) : null);
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    :variant="$isSiapBelanja ? 'done' : 'action'"
                    :title="$pktPp->nama_paket"
                    :meta="$kode . ' • ' . $unitNama . ' • Perkiraan Harga: Rp ' . $hps"
                    :submeta="$catatan"
                    :badge="$isSiapBelanja ? 'Siap Belanja' : 'Perlu Reviu'"
                    :badgeClass="$isSiapBelanja ? 'bg-emerald-100 text-emerald-800' : 'bg-[#fff5df] text-[#99660d]'"
                    :href="$ppActionHref"
                    :buttonLabel="$isSiapBelanja ? 'Eksekusi Belanja' : 'Buka'"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada paket menunggu reviu atau belanja"
                    subtitle=""
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Kode Paket</div>
                <div class="w-[22%] shrink-0">Perkiraan Harga</div>
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
                        Belum ada riwayat paket Pejabat Pengadaan.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
