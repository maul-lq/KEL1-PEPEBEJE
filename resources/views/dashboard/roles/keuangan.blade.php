{{-- resources/views/dashboard/roles/keuangan.blade.php --}}
{{-- Modul RBAC: Bagian Keuangan / Bendahara Pengeluaran (keuangan - Figma Node 568:36411) --}}
@php
    $canSp2d = $user->hasPermission('pembayaran.pencairan_sp2d');
    $keuanganHref = $canSp2d ? route('pembayaran.index') : null;
    $notifikasiHref = route('notifikasi.index');

    $tagihanList = $roleData['antrean_tagihan'] ?? collect();
    $reminderList = $roleData['reminders'] ?? collect();
    $totalDanaCairFull = 'Rp ' . number_format((float) ($stats['total_dana_cair'] ?? 0), 0, ',', '.');
    $cardIdx = 0;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Paket Siap Cair (SPP)"
            :value="$stats['paket_masuk_ppbj'] ?? ($stats['sp2d_siap_cair'] ?? 0)"
            subtitle="Menunggu pencairan kas SP2D"
            variant="active"
            :href="$keuanganHref"
        />

        <x-figma-stat-card
            title="Pengingat Masuk"
            :value="$stats['reminder_masuk'] ?? 0"
            subtitle="Notifikasi termin dari PPBJ"
            variant="bell"
            :href="$notifikasiHref"
        />

        <x-figma-stat-card
            title="Pencairan Selesai"
            :value="$stats['transaksi_lunas'] ?? 0"
            subtitle="Transaksi kas SP2D lunas"
            variant="done"
            :href="$keuanganHref"
        />

        <x-figma-stat-card
            title="Total Dana Dicairkan"
            :value="$stats['total_dana_cair_compact'] ?? $totalDanaCairFull"
            :fullTitle="$totalDanaCairFull"
            :compact="true"
            subtitle="Realisasi pembayaran ke rekanan"
            variant="action"
            :href="$keuanganHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$keuanganHref"
        rightTitle="Riwayat Pencairan SP2D"
        :rightHref="$keuanganHref"
    >
        <x-slot:left>
            @if($tagihanList->isEmpty() && $reminderList->isEmpty())
                <x-figma-empty
                    title="Tidak ada tagihan SP2D atau pengingat pending"
                    subtitle="Seluruh paket pembayaran dari PPBJ dan pengingat termin sudah tertangani."
                />
            @else
                @foreach($tagihanList as $tagihan)
                    @php
                        $vendorObj = $tagihan->paket->kontrakSpk->vendor ?? ($tagihan->paket->kontrak->vendor ?? ($tagihan->paket->vendor ?? null));
                        $vendorNama = $vendorObj->nama_vendor ?? 'Penyedia Resmi';
                        $sppTgl = $tagihan->tanggal_perintah_bayar
                            ? \Carbon\Carbon::parse($tagihan->tanggal_perintah_bayar)->format('d M Y')
                            : '-';
                        $nominal = number_format((float) ($tagihan->nominal_pengajuan ?? 0), 0, ',', '.');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="action"
                        :title="$tagihan->paket->nama_paket ?? ('Pembayaran ' . $tagihan->nomor_pembayaran)"
                        :meta="$tagihan->nomor_pembayaran . ' • Paket: ' . $tagihan->nomor_paket . ' • Termin Ke-' . ($tagihan->tahap_termin ?? 1)"
                        :submeta="$vendorNama . ' • Nominal: Rp ' . $nominal . ' • SPP: ' . $sppTgl"
                        badge="SPP Siap Cair"
                        badgeClass="bg-[#fff5df] text-[#99660d]"
                        :href="$keuanganHref"
                        buttonLabel="Buka"
                    />
                @endforeach

                @foreach($reminderList as $rem)
                    @php
                        $judulRem = $rem instanceof \App\Models\NotifikasiUser ? $rem->judul : ($rem->judul_alert ?? 'Pengingat Keuangan');
                        $pesanRem = $rem instanceof \App\Models\NotifikasiUser ? $rem->pesan : ($rem->pesan_alert ?? '-');
                        $waktuRem = $rem->created_at ?? ($rem->waktu_trigger ?? now());
                        $waktuFormatted = \Carbon\Carbon::parse($waktuRem)->format('d M Y, H:i');
                    @endphp
                    <x-figma-action-card
                        :index="$cardIdx++"
                        variant="bell"
                        :title="$judulRem"
                        :meta="'Pengingat Operasional • ' . $waktuFormatted"
                        :submeta="$pesanRem"
                        badge="Pengingat"
                        badgeClass="bg-blue-50 text-[#0969da]"
                        :href="$notifikasiHref"
                        buttonLabel="Buka"
                    />
                @endforeach
            @endif
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[18%] shrink-0">Tanggal Cair</div>
                <div class="w-[24%] shrink-0">No. Transaksi</div>
                <div class="flex-1 px-2">Rekanan &amp; Paket</div>
                <div class="w-[22%] shrink-0 text-right">Nominal</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['transaksi_cair'] ?? [] as $tx)
                    @php
                        $tglCair = $tx->tanggal_transfer ? date('d M Y', strtotime($tx->tanggal_transfer)) : '-';
                        $rekanan = $tx->pembayaran->paket->vendor->nama_vendor ?? 'Penyedia Rekanan';
                        $paketNama = $tx->pembayaran?->paket?->nama_paket;
                        $rekananPaket = $paketNama ? ($rekanan . ' • ' . $paketNama) : $rekanan;
                        $nominalCair = 'Rp ' . number_format((float) ($tx->nominal_transfer ?? 0), 0, ',', '.');
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[18%] shrink-0 font-semibold truncate" title="{{ $tglCair }}">
                            {{ $tglCair }}
                        </div>
                        <div class="w-[24%] shrink-0 font-mono font-semibold truncate" title="{{ $tx->nomor_transaksi_bank }}">
                            {{ $tx->nomor_transaksi_bank }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $rekananPaket }}">
                            {{ $rekananPaket }}
                        </div>
                        <div class="w-[22%] shrink-0 font-semibold text-right truncate text-emerald-700" title="{{ $nominalCair }}">
                            {{ $nominalCair }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada rekaman pencairan dana SP2D yang tercatat.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
