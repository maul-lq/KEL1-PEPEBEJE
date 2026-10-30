{{-- resources/views/dashboard/roles/perencanaan.blade.php --}}
{{-- Role: Subbagian Perencanaan & Anggaran MAK (perencanaan) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Total Pagu DIPA"
            :value="'Rp ' . number_format((float) ($stats['total_pagu_dipa'] ?? 0), 0, ',', '.')"
            color="navy"
            icon="wallet"
            subtitle="Pagu alokasi DIPA kampus"
        />

        <x-stat-card
            title="Pagu Teralokasi"
            :value="'Rp ' . number_format((float) ($stats['pagu_teralokasi'] ?? 0), 0, ',', '.')"
            color="blue"
            icon="box"
            subtitle="Anggaran terikat pada usulan"
        />

        <x-stat-card
            title="Sisa Pagu Bebas"
            :value="'Rp ' . number_format((float) ($stats['sisa_pagu_dipa'] ?? 0), 0, ',', '.')"
            color="emerald"
            icon="check"
            subtitle="Dana bebas siap dialokasikan"
        />

        <x-stat-card
            title="Usulan Menunggu MAK"
            :value="$stats['menunggu_mak'] ?? 0"
            color="amber"
            icon="document"
            badge="Alokasi"
            subtitle="Perlu penetapan pos MAK DIPA"
            :href="route('verifikasi.perencanaan')"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (7 Cols): Antrean Usulan Menunggu Alokasi MAK --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Usulan Menunggu Penetapan MAK</h2>
                </div>
                <a href="{{ route('verifikasi.perencanaan') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Buka Panel Alokasi
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">No. Surat</th>
                                <th class="px-4 py-3">Unit Pengusul</th>
                                <th class="px-4 py-3">Perihal Pengadaan</th>
                                <th class="px-4 py-3">Nilai Usulan</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['antrean_mak'] ?? [] as $usulanMak)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $usulanMak->nomor_surat }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $usulanMak->unit->nama_unit ?? $usulanMak->kode_unit ?? 'Unit' }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $usulanMak->judul_pengadaan }}">
                                        {{ $usulanMak->judul_pengadaan }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                        Rp {{ number_format((float) ($usulanMak->estimasi_total_pagu ?? $usulanMak->anggaran_diajukan ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('verifikasi.perencanaan') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                            Alokasi MAK
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Seluruh usulan telah diverifikasi ketersediaan anggarannya.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN (5 Cols): Daftar Rekening MAK Aktif & Status Kunci --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Rekening MAK DIPA Aktif</h2>
                </div>
                <a href="{{ route('verifikasi.perencanaan') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Tambah MAK
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['daftar_mak'] ?? [] as $mak)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="font-mono text-xs font-bold text-[#0969da] block">
                                    {{ $mak->kode_mak }}
                                </span>
                                <h4 class="text-xs font-bold text-[#213153] truncate mt-0.5" title="{{ $mak->uraian_mak }}">
                                    {{ $mak->uraian_mak }}
                                </h4>
                            </div>
                            @if($mak->is_locked)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Terkunci
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Tersedia
                                </span>
                            @endif
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span>Sisa Pagu:</span>
                            <span class="font-bold text-[#213153]">
                                Rp {{ number_format((float) ($mak->sisa_pagu_mak ?? 0), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <p class="text-xs text-slate-500 font-medium">Belum ada data rekening MAK DIPA terdaftar.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
