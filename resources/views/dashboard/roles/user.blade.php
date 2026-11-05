{{-- resources/views/dashboard/roles/user.blade.php --}}
{{-- Role: Pemohon Unit / Jurusan (user) - Designed based on Figma node 484:3385 --}}

<div class="space-y-8">

    {{-- ALERT BANNER: REVISI BERKAS DINAS JIKA ADA USULAN DITOLAK / REVISI
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
    @endif --}}

    {{-- 1. 4-COLUMN KPI STAT CARDS (Figma Node 484:3385) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Card 1: Pengajuan Aktif --}}
        <a href="{{ route('permohonan.index') }}" class="block bg-white border border-[#d2d2d2] rounded-[5px] p-5 h-[150px] relative hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="size-[59px] shrink-0">
                        <img src="{{ asset('images/dashboard/icon-card-active.svg') }}" alt="Pengajuan Aktif" class="size-full">
                    </div>
                    <div>
                        <div class="text-[#4a4a4a] text-[16px] font-semibold">Pengajuan Aktif</div>
                        <div class="text-[38px] font-semibold text-black leading-none my-1">
                            {{ $stats['pengajuan_aktif'] ?? $stats['usulan_proses'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="text-[#657a7c] group-hover:text-[#3b82f6] group-hover:translate-x-0.5 transition-all mt-2">
                    <svg class="size-4 -rotate-90" viewBox="0 0 14.314 8.657" fill="none">
                        <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                    </svg>
                </div>
            </div>
            <div class="text-[#969696] text-[13px] font-medium mt-3 pl-[75px] truncate">
                Total pengajuan yang sedang diproses
            </div>
        </a>

        {{-- Card 2: Menunggu Tindakan --}}
        <a href="{{ route('permohonan.index') }}" class="block bg-white border border-[#d2d2d2] rounded-[5px] p-5 h-[150px] relative hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="size-[59px] shrink-0">
                        <img src="{{ asset('images/dashboard/icon-card-action.svg') }}" alt="Menunggu Tindakan" class="size-full">
                    </div>
                    <div>
                        <div class="text-[#4a4a4a] text-[16px] font-semibold">Menunggu Tindakan</div>
                        <div class="text-[38px] font-semibold text-black leading-none my-1">
                            {{ $stats['menunggu_tindakan'] ?? $stats['usulan_revisi'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="text-[#657a7c] group-hover:text-[#3b82f6] group-hover:translate-x-0.5 transition-all mt-2">
                    <svg class="size-4 -rotate-90" viewBox="0 0 14.314 8.657" fill="none">
                        <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                    </svg>
                </div>
            </div>
            <div class="text-[#969696] text-[13px] font-medium mt-3 pl-[75px] truncate">
                Perlu segera ditindaklanjuti
            </div>
        </a>

        {{-- Card 3: Selesai --}}
        <a href="{{ route('permohonan.index') }}" class="block bg-white border border-[#d2d2d2] rounded-[5px] p-5 h-[150px] relative hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="size-[59px] shrink-0">
                        <img src="{{ asset('images/dashboard/icon-card-done.svg') }}" alt="Selesai" class="size-full">
                    </div>
                    <div>
                        <div class="text-[#4a4a4a] text-[16px] font-semibold">Selesai</div>
                        <div class="text-[38px] font-semibold text-black leading-none my-1">
                            {{ $stats['usulan_selesai'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="text-[#657a7c] group-hover:text-[#3b82f6] group-hover:translate-x-0.5 transition-all mt-2">
                    <svg class="size-4 -rotate-90" viewBox="0 0 14.314 8.657" fill="none">
                        <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                    </svg>
                </div>
            </div>
            <div class="text-[#969696] text-[13px] font-medium mt-3 pl-[75px] truncate">
                Pengajuan yang telah selesai
            </div>
        </a>

        {{-- Card 4: Notifikasi Belum Dibaca --}}
        <a href="{{ route('notifikasi.index') }}" class="block bg-white border border-[#d2d2d2] rounded-[5px] p-5 h-[150px] relative hover:shadow-md transition-shadow group">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="size-[59px] shrink-0">
                        <img src="{{ asset('images/dashboard/icon-card-bell.svg') }}" alt="Notifikasi Belum Dibaca" class="size-full">
                    </div>
                    <div>
                        <div class="text-[#4a4a4a] text-[16px] font-semibold">Notifikasi Belum Dibaca</div>
                        <div class="text-[38px] font-semibold text-black leading-none my-1">
                            {{ $stats['notifikasi_belum_dibaca'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="text-[#657a7c] group-hover:text-[#3b82f6] group-hover:translate-x-0.5 transition-all mt-2">
                    <svg class="size-4 -rotate-90" viewBox="0 0 14.314 8.657" fill="none">
                        <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                    </svg>
                </div>
            </div>
            <div class="text-[#969696] text-[13px] font-medium mt-3 pl-[75px] truncate">
                Ada notifikasi yang belum dibaca
            </div>
        </a>
    </div>

    {{-- 2. DUA KOLOM: PERLU TINDAKAN ANDA & AKTIVITAS TERBARU (Figma Node 484:3385) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- KOLOM KIRI: PERLU TINDAKAN ANDA --}}
        <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/dashboard/icon-action-needed.svg') }}" alt="Perlu Tindakan" class="size-[25px]">
                        <h3 class="text-[#4a4a4a] text-[18px] font-semibold">Perlu Tindakan Anda</h3>
                    </div>
                    <a href="{{ route('permohonan.index') }}" class="text-[#3b82f6] text-[16px] md:text-[18px] font-semibold flex items-center gap-1.5 hover:underline group">
                        Lihat Semua
                        <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                            <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                        </svg>
                    </a>
                </div>

                <div class="mt-4 space-y-3">
                    @php
                        $perluTindakan = $roleData['perlu_tindakan'] ?? collect();
                        $accentColors = ['#ffd900', '#5d60ff', '#fc3dff'];
                    @endphp

                    @forelse($perluTindakan as $item)
                        @php
                            $borderColor = $accentColors[$loop->index % count($accentColors)];
                        @endphp
                        <div class="bg-white rounded-[5px] shadow-[0px_0px_5px_0px_rgba(0,0,0,0.20)] p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 border-l-4 transition-all hover:shadow-md" style="border-left-color: {{ $borderColor }};">
                            <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                <div class="shrink-0 size-[50px] md:size-[55px]">
                                    <img src="{{ asset('images/dashboard/icon-card-active.svg') }}" alt="" class="size-full">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-[15px] font-medium text-black truncate" title="{{ $item->judul_pengadaan }}">
                                        {{ $item->judul_pengadaan }}
                                    </h4>
                                    <p class="text-[11px] font-semibold text-[#657a7c] mt-0.5">
                                        {{ $item->nomor_surat }} &bull; {{ $item->tanggal_pengajuan ? date('d M Y, H:i', strtotime($item->tanggal_pengajuan)) : date('d M Y, H:i') }}
                                    </p>
                                    <p class="text-[11px] font-medium text-[#657a7c] mt-0.5 line-clamp-1">
                                        <span>{{ $item->status_permohonan === 'ditolak' ? 'Pengajuan ditolak' : 'Pengajuan memerlukan revisi' }}</span>
                                        <span class="font-bold">&bull;</span>
                                        <span>Catatan: {{ $item->alasan_revisi ?? $item->catatan_revisi ?? 'Dokumen perlu dilengkapi ulang.' }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center gap-3 self-end md:self-center">
                                <span class="bg-[#fff5df] text-[#99660d] text-[11px] font-normal px-2.5 py-1 rounded-[4px] whitespace-nowrap">
                                    Revisi
                                </span>
                                <a href="{{ route('permohonan.show', $item->nomor_surat) }}" class="bg-[#3b82f6] hover:bg-blue-600 border border-[#3b82f6] text-white text-[15px] font-medium px-4 py-1.5 rounded-[5px] whitespace-nowrap transition-colors flex items-center gap-1 shadow-xs">
                                    Buka &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[5px] border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center my-4">
                            <div class="size-10 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-700">Tidak ada pengajuan yang memerlukan tindakan Anda</p>
                            <p class="text-xs text-slate-400 mt-0.5">Semua usulan permohonan pengadaan unit Anda sedang berjalan dengan lancar.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: AKTIVITAS TERBARU --}}
        <div class="lg:col-span-6 bg-white border border-[#d2d2d2] rounded-[5px] p-6 shadow-xs flex flex-col justify-between min-h-[381px]">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/dashboard/icon-recent-activity.svg') }}" alt="Aktivitas Terbaru" class="size-[22px]">
                        <h3 class="text-[#4a4a4a] text-[20px] font-semibold">Aktivitas Terbaru</h3>
                    </div>
                    <a href="{{ route('permohonan.index') }}" class="text-[#3b82f6] text-[16px] md:text-[18px] font-semibold flex items-center gap-1.5 hover:underline group">
                        Lihat Semua
                        <svg class="size-3.5 -rotate-90 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 14.314 8.657" fill="none">
                            <path d="M12.814 1.5L7.157 7.157L1.5 1.5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"/>
                        </svg>
                    </a>
                </div>

                {{-- Tabel / List Aktivitas --}}
                <div class="mt-4">
                    <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[14px] md:text-[15px] font-semibold">
                        <div class="w-[24%] shrink-0">Waktu</div>
                        <div class="w-[26%] shrink-0">Aktivitas</div>
                        <div class="flex-1 px-2">Nama Pengadaan</div>
                        <div class="w-[18%] shrink-0 text-right">Status</div>
                    </div>

                    <div class="divide-y divide-[#d2d2d2]">
                        @php
                            $aktivitasList = $roleData['aktivitas_terbaru'] ?? collect();
                        @endphp

                        @forelse($aktivitasList as $akt)
                            <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                                <div class="w-[24%] shrink-0 font-semibold truncate" title="{{ $akt['waktu'] ?? '-' }}">
                                    {{ $akt['waktu'] ?? '-' }}
                                </div>
                                <div class="w-[26%] shrink-0 font-semibold truncate" title="{{ $akt['aktivitas'] ?? '-' }}">
                                    {{ $akt['aktivitas'] ?? '-' }}
                                </div>
                                <div class="flex-1 px-2 font-medium truncate" title="{{ $akt['nama_pengadaan'] ?? '-' }}">
                                    {{ $akt['nama_pengadaan'] ?? '-' }}
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
                </div>
            </div>
        </div>
    </div>
</div>
