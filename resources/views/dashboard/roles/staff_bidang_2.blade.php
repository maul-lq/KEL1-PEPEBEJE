{{-- resources/views/dashboard/roles/staff_bidang_2.blade.php --}}
{{-- Modul RBAC: Staf Bidang II - Pemantau Administrasi Read-Only (verifikasi_bidang2) --}}
@php
    $verifikasiB2Route = $user->hasAnyPermission(['verifikasi.bidang_2_view', 'permohonan.view_all'])
        ? route('verifikasi.bidang-2')
        : null;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Menunggu Telaah"
            :value="$stats['menunggu_telaah'] ?? 0"
            subtitle="Berkas Srikandi masuk"
            variant="action"
            :href="$verifikasiB2Route"
        />

        <x-figma-stat-card
            title="Dalam Reviu Paralel"
            :value="$stats['reviu_paralel'] ?? 0"
            subtitle="Telaah Perencanaan & Wadir II"
            variant="bell"
            :href="$verifikasiB2Route"
        />

        <x-figma-stat-card
            title="Selesai Diteruskan"
            :value="$stats['bidang2_disetujui'] ?? 0"
            subtitle="Telah diputuskan Wadir II"
            variant="done"
            :href="$verifikasiB2Route"
        />

        <x-figma-stat-card
            title="Total Berkas Masuk"
            :value="$stats['total_berkas'] ?? 0"
            subtitle="Akumulasi berkas pengadaan"
            variant="active"
            :href="$verifikasiB2Route"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$verifikasiB2Route"
        rightTitle="Berkas Permohonan Terkini"
        :rightHref="$verifikasiB2Route"
    >
        <x-slot:left>
            @forelse($roleData['antrean_telaah'] ?? [] as $usulan)
                @php
                    $unitNama = $usulan->unit->nama_unit ?? $usulan->kode_unit ?? 'Unit Kerja';
                    $tglSurat = $usulan->tanggal_pengajuan ? date('d M Y', strtotime($usulan->tanggal_pengajuan)) : '-';
                    $estimasiPagu = number_format((float) ($usulan->estimasi_total_pagu ?? $usulan->anggaran_diajukan ?? 0), 0, ',', '.');
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    variant="action"
                    :title="$usulan->judul_pengadaan"
                    :meta="$usulan->nomor_surat . ' • ' . $unitNama . ' • ' . $tglSurat"
                    :submeta="'Estimasi Pagu: Rp ' . $estimasiPagu . ' • Pemeriksaan Read-Only'"
                    badge="Read-Only"
                    badgeClass="bg-blue-50 text-[#0969da]"
                    :href="$verifikasiB2Route"
                    buttonLabel="Buka"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada berkas baru yang menunggu telaah"
                    subtitle="Seluruh berkas Srikandi yang masuk saat ini telah diteruskan sesuai alur."
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[20%] shrink-0">Waktu</div>
                <div class="w-[24%] shrink-0">No. Surat</div>
                <div class="flex-1 px-2">Nama Pengadaan</div>
                <div class="w-[20%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['bidang2_recent'] ?? [] as $berkas)
                    @php
                        $waktu = $berkas->updated_at?->format('d M Y, H:i')
                            ?? ($berkas->tanggal_pengajuan ? date('d M Y', strtotime($berkas->tanggal_pengajuan)) : '-');
                        $statusLabel = ucwords(str_replace('_', ' ', $berkas->status_permohonan ?? 'diajukan'));
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[20%] shrink-0 font-semibold truncate" title="{{ $waktu }}">
                            {{ $waktu }}
                        </div>
                        <div class="w-[24%] shrink-0 font-mono font-semibold truncate" title="{{ $berkas->nomor_surat }}">
                            {{ $berkas->nomor_surat }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $berkas->judul_pengadaan }}">
                            {{ $berkas->judul_pengadaan }}
                        </div>
                        <div class="w-[20%] shrink-0 font-semibold text-right truncate" title="{{ $statusLabel }}">
                            {{ $statusLabel }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada berkas permohonan masuk yang tercatat.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
