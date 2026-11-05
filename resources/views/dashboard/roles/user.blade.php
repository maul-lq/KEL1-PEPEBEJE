{{-- resources/views/dashboard/roles/user.blade.php --}}
{{-- Role: Pemohon Unit / Jurusan (user) --}}

<div class="space-y-8">

    {{-- ALERT BANNER: REVISI BERKAS DINAS JIKA ADA USULAN DITOLAK --}}
    @if(!empty($roleData['revisi_alerts']) && $roleData['revisi_alerts']->count() > 0)
        <div class="p-5 rounded-2xl bg-rose-50 border border-rose-200/80 shadow-sm space-y-3">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600 mt-0.5">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-rose-900">
                        Perhatian: Terdapat {{ $roleData['revisi_alerts']->count() }} Usulan Pengadaan Memerlukan Revisi
                    </h3>
                    <p class="text-xs text-rose-700 mt-0.5">
                        Harap perbaiki dokumen KAK/Spesifikasi teknis sesuai catatan verifikator dan ajukan kembali surat dinas revisi.
                    </p>
                </div>
            </div>

            <div class="divide-y divide-rose-200/60 bg-white/70 rounded-xl border border-rose-200/60 px-4">
                @foreach($roleData['revisi_alerts'] as $alert)
                    <div class="py-3 flex flex-col md:flex-row md:items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-[#213153]">{{ $alert->nomor_surat }}</span>
                            <span class="text-xs text-slate-500">&bull; {{ $alert->judul_pengadaan }}</span>
                            <p class="text-xs text-rose-800 mt-1 font-medium bg-rose-50/80 p-2 rounded-lg border border-rose-200/60">
                                <span class="font-bold">Catatan Verifikator:</span> {{ $alert->alasan_revisi ?? 'Dokumen KAK atau spesifikasi teknis perlu dilengkapi ulang.' }}
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <a href="{{ route('permohonan.show', $alert->nomor_surat) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 shadow-sm transition-colors">
                                Perbaiki Berkas
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Total Usulan Pengadaan"
            :value="$stats['total_usulan'] ?? $recentPermohonan->count()"
            color="navy"
            icon="document"
            subtitle="Total usulan terdaftar dari unit Anda"
            :href="route('permohonan.index')"
        />

        <x-stat-card
            title="Sedang Diproses"
            :value="$stats['usulan_proses'] ?? 0"
            color="amber"
            icon="bell"
            badge="Verifikasi"
            subtitle="Dalam telaah Bidang II & MAK"
        />

        <x-stat-card
            title="Usulan Disetujui"
            :value="$stats['usulan_disetujui'] ?? 0"
            color="teal"
            icon="check"
            badge="Disetujui"
            subtitle="Siap diproses paket oleh PPBJ"
        />

        <x-stat-card
            title="Perlu Revisi / Ditolak"
            :value="$stats['usulan_revisi'] ?? 0"
            color="rose"
            icon="document"
            badge="Tindak Lanjut"
            subtitle="Perlu perbaikan naskah dinas"
            :href="route('permohonan.index')"
        />
    </div>

    {{-- 2. MAIN OPERATION: TABEL USULAN TERKINI PEMOHON --}}
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-[#0969da]"></span>
                <h2 class="text-lg font-bold text-[#213153]">Daftar Usulan Permohonan Pengadaan Unit Anda</h2>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('permohonan.create') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-all">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Ajukan Permohonan Baru
                </a>
                <a href="{{ route('permohonan.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Lihat Semua
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                            <th class="px-4 py-3">Nomor Surat Srikandi</th>
                            <th class="px-4 py-3">Perihal Pengadaan</th>
                            <th class="px-4 py-3">Tanggal Diajukan</th>
                            <th class="px-4 py-3">Estimasi Pagu</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Siklus</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                        @forelse($recentPermohonan as $p)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-bold text-[#213153]">{{ $p->nomor_surat }}</div>
                                    @if($p->revisi_ke > 0)
                                        <span class="inline-flex items-center text-[10px] text-amber-700 font-bold bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200">
                                            Revisi ke-{{ $p->revisi_ke }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $p->judul_pengadaan }}">
                                    {{ $p->judul_pengadaan }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                    {{ $p->tanggal_pengajuan ? date('d M Y', strtotime($p->tanggal_pengajuan)) : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                    Rp {{ number_format((float) ($p->estimasi_total_pagu ?? $p->anggaran_diajukan ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-status-badge :status="$p->status_permohonan ?? $p->status" size="sm" />
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @php
                                        $step = match($p->status_permohonan ?? $p->status) {
                                            'diajukan' => 1,
                                            'reviu_paralel', 'pending_anggaran' => 2,
                                            'disetujui' => 3,
                                            'selesai' => 8,
                                            default => 1,
                                        };
                                    @endphp
                                    <x-stepper-8-siklus :currentStep="$step" :status="$p->status_permohonan ?? $p->status" mode="compact" />
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('permohonan.show', $p->nomor_surat) }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 transition-colors">
                                            Detail
                                        </a>
                                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank" class="px-2 py-1 rounded-lg text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors" title="Unduh Berkas PDF">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Belum ada usulan permohonan pengadaan yang diajukan oleh unit Anda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
