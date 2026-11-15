{{-- resources/views/dashboard/roles/user.blade.php --}}
{{-- Modul RBAC: Pemohon / Unit Pengusul Pengadaan (permohonan_user - Figma Node 484:3385) --}}
@php
    $permohonanRoute = $user->hasAnyPermission(['permohonan.view_own', 'permohonan.create']) ? route('permohonan.index') : null;
    $revisiRoute = $user->hasAnyPermission(['permohonan.view_own', 'permohonan.revisi']) ? route('permohonan.index', ['status' => 'revisi']) : $permohonanRoute;
@endphp

<div class="space-y-6">
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Pengajuan Aktif"
            :value="$stats['pengajuan_aktif'] ?? 0"
            subtitle="Dalam verifikasi & diproses"
            variant="active"
            :href="$permohonanRoute"
        />

        <x-figma-stat-card
            title="Perlu Tindakan"
            :value="$stats['menunggu_tindakan'] ?? 0"
            subtitle="Revisi atau konfirmasi"
            variant="action"
            :href="$revisiRoute"
        />

        <x-figma-stat-card
            title="Usulan Selesai"
            :value="$stats['usulan_selesai'] ?? 0"
            subtitle="Disetujui atau tuntas"
            variant="done"
            :href="$permohonanRoute"
        />

        <x-figma-stat-card
            title="Notifikasi"
            :value="$stats['notifikasi_belum_dibaca'] ?? 0"
            subtitle="Update pengadaan terbaru"
            variant="bell"
            :href="route('notifikasi.index')"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$permohonanRoute"
        rightTitle="Aktivitas Terbaru"
        :rightHref="$permohonanRoute"
    >
        <x-slot:left>
            @forelse($roleData['perlu_tindakan'] ?? [] as $item)
                @php
                    $isDitolak = $item->status_permohonan === 'ditolak';
                    $waktuPengajuan = $item->tanggal_pengajuan ? date('d M Y, H:i', strtotime($item->tanggal_pengajuan)) : '-';
                    $catatanRevisi = $item->alasan_revisi ?? $item->catatan_revisi ?? 'Dokumen perlu dilengkapi ulang.';
                    $detailHref = $user->hasAnyPermission(['permohonan.view_own', 'permohonan.revisi', 'permohonan.create'])
                        ? route('permohonan.show', $item->nomor_surat)
                        : null;
                @endphp
                <x-figma-action-card
                    :index="$loop->index"
                    variant="active"
                    :title="$item->judul_pengadaan"
                    :meta="$item->nomor_surat . ' • ' . $waktuPengajuan"
                    :submeta="($isDitolak ? 'Pengajuan ditolak' : 'Pengajuan memerlukan revisi') . ' • Catatan: ' . $catatanRevisi"
                    :badge="$isDitolak ? 'Ditolak' : 'Revisi'"
                    :badgeClass="$isDitolak ? 'bg-rose-100 text-rose-700' : 'bg-[#fff5df] text-[#99660d]'"
                    :href="$detailHref"
                    buttonLabel="Buka"
                />
            @empty
                <x-figma-empty
                    title="Tidak ada pengajuan yang memerlukan tindakan Anda"
                    subtitle="Semua usulan permohonan pengadaan unit Anda sedang berjalan dengan lancar."
                />
            @endforelse
        </x-slot:left>

        <x-slot:right>
            <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                <div class="w-[24%] shrink-0">Waktu</div>
                <div class="w-[26%] shrink-0">Aktivitas</div>
                <div class="flex-1 px-2">Nama Pengadaan</div>
                <div class="w-[18%] shrink-0 text-right">Status</div>
            </div>

            <div class="divide-y divide-[#d2d2d2]">
                @forelse($roleData['aktivitas_terbaru'] ?? [] as $akt)
                    <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                        <div class="w-[24%] shrink-0 font-semibold truncate" title="{{ $akt['waktu'] ?? '-' }}">
                            {{ $akt['waktu'] ?? '-' }}
                        </div>
                        <div class="w-[26%] shrink-0 font-semibold truncate" title="{{ $akt['aktivitas'] ?? '-' }}">
                            {{ $akt['aktivitas'] ?? '-' }}
                        </div>
                        <div class="flex-1 px-2 font-medium truncate" title="{{ $akt['nama_pengadaan'] ?? '-' }}">
                            <span>{{ $akt['nama_pengadaan'] ?? '-' }}</span>
                            @if(!empty($akt['nomor_surat']))
                                <span class="sr-only">{{ $akt['nomor_surat'] }}</span>
                            @endif
                        </div>
                        <div class="w-[18%] shrink-0 font-semibold text-right truncate" title="{{ $akt['status'] ?? '-' }}">
                            {{ $akt['status'] ?? '-' }}
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-xs text-slate-400">
                        Belum ada aktivitas terbaru yang tercatat untuk akun Anda.
                    </div>
                @endforelse
            </div>
        </x-slot:right>
    </x-figma-workspace>
</div>
