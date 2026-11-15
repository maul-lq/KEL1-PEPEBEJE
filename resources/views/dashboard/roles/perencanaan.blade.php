{{-- resources/views/dashboard/roles/perencanaan.blade.php --}}
{{-- Modul RBAC: Subbagian Perencanaan & Anggaran MAK (perencanaan) --}}
@php
    $canViewMak = $user->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'permohonan.split_mak']);
    $canAlokasiMak = $user->hasAnyPermission(['anggaran.mak_alokasi', 'permohonan.split_mak']);
    $perencanaanHref = $canViewMak ? route('verifikasi.perencanaan') : null;
    $alokasiHref = $canAlokasiMak ? route('verifikasi.perencanaan') : null;

    $totalPaguFull = 'Rp ' . number_format((float) ($stats['total_pagu_dipa'] ?? 0), 0, ',', '.');
    $sisaPaguFull = 'Rp ' . number_format((float) ($stats['sisa_pagu_dipa'] ?? 0), 0, ',', '.');
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Menunggu Anggaran"
            :value="$stats['menunggu_mak'] ?? 0"
            subtitle="Perlu pilih pos anggaran"
            variant="action"
            :href="$alokasiHref"
        />

        <x-figma-stat-card
            title="Total Pagu Anggaran"
            :value="$stats['total_pagu_dipa_compact'] ?? $totalPaguFull"
            :fullTitle="$totalPaguFull"
            :compact="true"
            subtitle="Anggaran aktif tahun ini"
            variant="active"
            :href="$perencanaanHref"
        />

        <x-figma-stat-card
            title="Sisa Pagu Anggaran"
            :value="$stats['sisa_pagu_dipa_compact'] ?? $sisaPaguFull"
            :fullTitle="$sisaPaguFull"
            :compact="true"
            subtitle="Dana siap dialokasikan"
            variant="done"
            :href="$perencanaanHref"
        />

        <x-figma-stat-card
            title="Pos Terkunci"
            :value="$stats['mak_locked'] ?? 0"
            subtitle="Tidak dapat diubah lagi"
            variant="bell"
            :href="$perencanaanHref"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$alokasiHref"
        rightTitle="Daftar Pos Anggaran"
        :rightHref="$perencanaanHref"
    >
        <x-slot:left>
            @forelse($roleData['antrean_mak'] ?? [] as $usulanMak)
                @php
                    $unitNama = $usulanMak->unit->nama_unit ?? $usulanMak->kode_unit ?? 'Unit Kerja';
                    $nilaiUsulan = number_format((float) ($usulanMak->estimasi_total_pagu ?? $usulanMak->anggaran_diajukan ?? 0), 0, ',', '.');
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    variant="action"
                    :title="$usulanMak->judul_pengadaan"
                    :meta="$usulanMak->nomor_surat . ' • ' . $unitNama"
                    :submeta="'Estimasi Usulan: Rp ' . $nilaiUsulan"
                    badge="Pilih Anggaran"
                    badgeClass="bg-[#fff5df] text-[#99660d]"
                    :href="$alokasiHref"
                    buttonLabel="Buka"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada usulan menunggu pos anggaran"
                    subtitle=""
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[22%] shrink-0">Kode Pos</div>
                <div class="w-[20%] shrink-0">Sumber</div>
                <div class="flex-1 px-2">Uraian &amp; Sisa Pagu</div>
                <div class="w-[16%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['daftar_mak'] ?? [] as $mak)
                    @php
                        $sumberTahun = ($mak->sumber_dana ?? 'DIPA') . ' • ' . ($mak->tahun_anggaran ?? date('Y'));
                        $sisaMak = number_format((float) ($mak->sisa_pagu_mak ?? 0), 0, ',', '.');
                        $uraianLabel = ($mak->uraian_mak ?? '-') . ' (Sisa: Rp ' . $sisaMak . ')';
                        $statusMak = $mak->is_locked ? 'Terkunci' : 'Tersedia';
                    @endphp
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[22%] shrink-0 font-mono font-semibold truncate" title="{{ $mak->kode_mak }}">
                            {{ $mak->kode_mak }}
                        </div>
                        <div class="w-[20%] shrink-0 font-semibold truncate" title="{{ $sumberTahun }}">
                            {{ $sumberTahun }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $uraianLabel }}">
                            {{ $uraianLabel }}
                        </div>
                        <div class="w-[16%] shrink-0 font-semibold text-right truncate {{ $mak->is_locked ? 'text-slate-500' : 'text-emerald-700' }}">
                            {{ $statusMak }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada daftar pos anggaran.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
