{{-- resources/views/dashboard/roles/perlengkapan.blade.php --}}
{{-- Role: Bagian Perlengkapan & Pengelola BMN (perlengkapan) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Tembusan SPK Masuk"
            :value="$stats['spk_masuk'] ?? 0"
            color="navy"
            icon="document"
            subtitle="Tembusan otomatis dari PPBJ"
            :href="route('penerimaan.index')"
        />

        <x-stat-card
            title="Menunggu Uji Fisik"
            :value="$stats['belum_diperiksa'] ?? 0"
            color="amber"
            icon="bell"
            badge="Prioritas"
            subtitle="Barang tiba belum diinspeksi"
        />

        <x-stat-card
            title="BAST Diterbitkan"
            :value="$stats['total_bast'] ?? 0"
            color="emerald"
            icon="check"
            badge="HMAC SHA-256"
            subtitle="BAST bertoken digital resmi"
        />

        <x-stat-card
            title="Barang vs Modal BMN"
            :value="($stats['belanja_barang'] ?? 0) . ' / ' . ($stats['belanja_modal'] ?? 0)"
            color="teal"
            icon="box"
            subtitle="Persediaan vs Aset BMN"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (6 Cols): Jadwal Pengiriman Barang Vendor (Alarm H-2) --}}
        <div class="lg:col-span-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-blue-600 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Jadwal Pengiriman Rekanan Vendor</h2>
                </div>
                <a href="{{ route('penerimaan.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Cek Semua Jadwal
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['jadwal_pengiriman'] ?? [] as $spkKirim)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-mono font-bold text-slate-400 block">
                                    No. SPK: {{ $spkKirim->nomor_spk }}
                                </span>
                                <h4 class="text-xs font-bold text-[#213153] truncate mt-0.5" title="{{ $spkKirim->paket->nama_paket ?? '-' }}">
                                    {{ $spkKirim->paket->nama_paket ?? 'Paket Pengadaan' }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    Rekanan: {{ $spkKirim->paket->vendor->nama_perusahaan ?? 'Penyedia Resmi' }}
                                </p>
                            </div>
                            <x-status-badge status="pengiriman" text="In-Transit" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-500">
                                Batas Kirim: <span class="font-bold text-[#213153]">{{ $spkKirim->tanggal_selesai ? date('d M Y', strtotime($spkKirim->tanggal_selesai)) : ($spkKirim->tanggal_spk ? date('d M Y', strtotime($spkKirim->tanggal_spk)) : '-') }}</span>
                            </span>
                            <a href="{{ route('penerimaan.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                Input Cek Fisik &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Tidak ada jadwal pengiriman vendor yang sedang berlangsung.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KOLOM KANAN (6 Cols): Antrean Pemeriksaan Fisik & BAST Terkini --}}
        <div class="lg:col-span-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Riwayat BAST Diterbitkan (HMAC)</h2>
                </div>
                <a href="{{ route('penerimaan.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Arsip BAST
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">No. BAST</th>
                                <th class="px-4 py-3">Nama Paket</th>
                                <th class="px-4 py-3">Penyedia</th>
                                <th class="px-4 py-3 text-center">Klasifikasi</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['bast_terbaru'] ?? [] as $bast)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $bast->nomor_bast ?? 'BAST-'.$bast->id_penerimaan }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $bast->paket->nama_paket ?? '-' }}">
                                        {{ $bast->paket->nama_paket ?? 'Paket Pengadaan' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $bast->paket->vendor->nama_perusahaan ?? 'Penyedia' }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $bast->kategori_belanja === 'belanja_modal' ? 'bg-blue-50 text-[#0969da] border border-blue-200' : 'bg-teal-50 text-[#087F79] border border-teal-200' }}">
                                            {{ $bast->kategori_belanja === 'belanja_modal' ? 'Aset BMN' : 'Persediaan' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('penerimaan.index') }}" class="px-2 py-1 rounded-lg text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 transition-colors">
                                            Rincian
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Belum ada naskah BAST yang diterbitkan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
