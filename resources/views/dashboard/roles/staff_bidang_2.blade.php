{{-- resources/views/dashboard/roles/staff_bidang_2.blade.php --}}
{{-- Role: Staf Bidang II - Penelaah Administrasi (staff_bidang_2) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Menunggu Telaah Berkas"
            :value="$stats['menunggu_telaah'] ?? 0"
            color="amber"
            icon="document"
            badge="Prioritas"
            subtitle="Berkas dinas masuk belum ditelaah"
            :href="route('verifikasi.bidang-2')"
        />

        <x-stat-card
            title="Dalam Reviu Paralel"
            :value="$stats['reviu_paralel'] ?? 0"
            color="blue"
            icon="bell"
            subtitle="Telaah bersama Perencanaan & Wadir"
        />

        <x-stat-card
            title="Selesai Diteruskan"
            :value="$stats['selesai_telaah'] ?? 0"
            color="emerald"
            icon="check"
            subtitle="Lolos telaah kelengkapan ke Wadir 2"
        />

        <x-stat-card
            title="Total Usulan Masuk"
            :value="$stats['total_berkas'] ?? 0"
            color="navy"
            icon="package"
            subtitle="Akumulasi usulan pengadaan"
        />
    </div>

    {{-- 2. MAIN OPERATION: ANTREAN TELAAH KELENGKAPAN ADMINISTRASI --}}
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                <h2 class="text-lg font-bold text-[#213153]">Antrean Telaah Kelengkapan Berkas Srikandi</h2>
            </div>
            <a href="{{ route('verifikasi.bidang-2') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                Buka Halaman Telaah Lengkap
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                            <th class="px-4 py-3">Tanggal Surat</th>
                            <th class="px-4 py-3">No. Surat Srikandi</th>
                            <th class="px-4 py-3">Unit / Jurusan Pengusul</th>
                            <th class="px-4 py-3">Perihal Pengadaan</th>
                            <th class="px-4 py-3">Estimasi Anggaran</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                        @forelse($roleData['antrean_telaah'] ?? [] as $usulan)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                    {{ $usulan->tanggal_pengajuan ? date('d M Y', strtotime($usulan->tanggal_pengajuan)) : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                    {{ $usulan->nomor_surat }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $usulan->unit->nama_unit ?? $usulan->kode_unit ?? 'Unit Kerja' }}
                                </td>
                                <td class="px-4 py-3 max-w-xs truncate" title="{{ $usulan->judul_pengadaan }}">
                                    {{ $usulan->judul_pengadaan }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                    Rp {{ number_format((float) ($usulan->estimasi_total_pagu ?? $usulan->anggaran_diajukan ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <x-status-badge :status="$usulan->status_permohonan ?? $usulan->status" size="sm" />
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('verifikasi.bidang-2') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                        Periksa Berkas
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                    <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Tidak ada berkas usulan permohonan baru yang menunggu telaah saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
