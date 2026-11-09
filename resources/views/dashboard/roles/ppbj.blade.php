{{-- resources/views/dashboard/roles/ppbj.blade.php --}}
{{-- Role: Koordinator Pengadaan Barang & Jasa (ppbj) --}}

<div class="space-y-8">

    {{-- 1. 5-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <x-stat-card
            title="Total Paket Terdaftar"
            :value="$stats['total_paket'] ?? 0"
            color="navy"
            icon="package"
            subtitle="Paket aktif dalam proses"
            :href="route('paket.index')"
        />

        <x-stat-card
            title="Pengadaan Langsung (<=50jt)"
            :value="$stats['routing_langsung'] ?? 0"
            color="teal"
            icon="box"
            subtitle="Eksekusi Kwitansi (Tanpa SPK)"
        />

        <x-stat-card
            title="Jalur PP (50jt - 200jt)"
            :value="$stats['routing_pp'] ?? 0"
            color="blue"
            icon="users"
            subtitle="Reviu & negosiasi harga PP"
        />

        <x-stat-card
            title="Jalur PPK (>200jt)"
            :value="$stats['routing_ppk'] ?? 0"
            color="amber"
            icon="users"
            badge="Tender"
            subtitle="Ditugaskan ke 4 akun PPK"
        />

        <x-stat-card
            title="Direktori Rekanan"
            :value="$stats['total_vendor'] ?? 0"
            color="emerald"
            icon="document"
            badge="Inline Vendor"
            subtitle="Penyedia & toko terdaftar"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (5 Cols): Usulan Disetujui Siap Registrasi Paket --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-blue-600 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Siap Registrasi Paket</h2>
                </div>
                <a href="{{ route('paket.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0969da] text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-all">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    + Paket Baru
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['siap_paket'] ?? [] as $siap)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-[#213153] truncate">
                                    {{ $siap->nomor_surat }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2" title="{{ $siap->judul_pengadaan }}">
                                    {{ $siap->judul_pengadaan }}
                                </p>
                                <span class="text-[10px] text-slate-400 mt-1 block">
                                    Unit: {{ $siap->unit->nama_unit ?? $siap->kode_unit ?? 'Unit' }}
                                </span>
                            </div>
                            <x-status-badge status="disetujui" text="Siap Paket" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="font-bold text-[#0969da]">
                                Rp {{ number_format((float) ($siap->estimasi_total_pagu ?? $siap->anggaran_diajukan ?? 0), 0, ',', '.') }}
                            </span>
                            <a href="{{ route('paket.create') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                Buat Paket &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Seluruh usulan yang disetujui telah memiliki paket pengadaan.</p>
                    </div>
                @endforelse
            </div>

            {{-- Antrean BAST Masuk Siap Dokumen Pembayaran (BPMN N51 & N53) --}}
            @if(!empty($roleData['bast_siap_bayar']) && $roleData['bast_siap_bayar']->isNotEmpty())
                <div class="space-y-3 pt-4 border-t border-slate-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-emerald-600 animate-pulse"></span>
                            <h3 class="text-sm font-bold text-[#213153]">BAST Masuk Siap Dokumen Bayar</h3>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono">
                            {{ $roleData['bast_siap_bayar']->count() }} Paket
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        @foreach($roleData['bast_siap_bayar'] as $bast)
                            <div class="p-3.5 rounded-xl bg-emerald-50/40 border border-emerald-200 shadow-xs hover:border-emerald-300 transition-colors space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-[11px] font-bold text-[#0969da]">{{ $bast->nomor_bast }}</span>
                                            <span class="text-[10px] text-slate-400 font-mono">&bull; Paket: {{ $bast->nomor_paket }}</span>
                                        </div>
                                        <p class="text-[11px] font-medium text-slate-700 mt-0.5 line-clamp-1" title="{{ $bast->paket?->nama_paket }}">
                                            {{ $bast->paket?->nama_paket ?? '-' }}
                                        </p>
                                    </div>
                                    <x-status-badge status="bast_terbit" text="BAST Sah" size="xs" />
                                </div>
                                <div class="pt-2 border-t border-emerald-100 flex items-center justify-between text-xs">
                                    <span class="font-bold text-emerald-800">
                                        Rp {{ number_format((float) ($bast->paket?->nominal_harga_akhir ?: ($bast->paket?->nilai_hps ?: 0)), 0, ',', '.') }}
                                    </span>
                                    <a href="{{ route('pembayaran.index', ['nomor_paket' => $bast->nomor_paket]) }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-emerald-700 hover:bg-emerald-800 shadow-xs transition-colors">
                                        Dokumen Bayar &rarr;
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- KOLOM KANAN (7 Cols): Tabel Monitoring Paket & Jalur Routing --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[#087F79]"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Monitoring Paket & Penugasan PPK</h2>
                </div>
                <a href="{{ route('paket.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Kelola Seluruh Paket
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Kode Paket</th>
                                <th class="px-4 py-3">Nama Paket</th>
                                <th class="px-4 py-3">Nilai HPS</th>
                                <th class="px-4 py-3">Jalur Routing</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($recentPaket as $pkt)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $pkt->kode_paket ?? $pkt->nomor_paket }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $pkt->nama_paket }}">
                                        {{ $pkt->nama_paket }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                        Rp {{ number_format((float) ($pkt->nilai_hps ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-status-badge :status="$pkt->jalur_routing ?? 'langsung_sd_50jt'" size="xs" />
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('paket.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 transition-colors">
                                            Kelola / Reassign
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                        Belum ada paket pengadaan terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- 3. MONITORING AKTIVITAS PEJABAT PENGADAAN (PP) - RBAC 'lihat_pp' / 'pp.monitoring_aktivitas' --}}
    @if(auth()->user()?->canMonitorPp())
        <div class="space-y-4 pt-4 border-t border-slate-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <div>
                        <h2 class="text-lg font-bold text-[#213153] flex items-center gap-2">
                            Monitoring Aktivitas Pejabat Pengadaan (PP)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                RBAC: Lihat PP Aktif
                            </span>
                        </h2>
                        <p class="text-xs text-slate-500">Pantau progres telaah dokumen, negosiasi harga (50jt - 200jt), dan penunjukan rekanan oleh PP secara real-time.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('monitor-pp.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-[#0969da] bg-blue-50 hover:bg-blue-100 transition-colors">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Buka Halaman Monitor PP &rarr;
                    </a>
                </div>
            </div>

            {{-- 4 Mini KPI Cards PP --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Total Paket di PP</div>
                        <div class="text-lg font-black text-[#213153]">{{ $roleData['stats']['pp_total'] ?? 0 }}</div>
                    </div>
                    <span class="p-2 rounded-lg bg-blue-50 text-[#0969da]">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Sedang Reviu / Negosiasi</div>
                        <div class="text-lg font-black text-amber-600">{{ $roleData['stats']['pp_dalam_reviu'] ?? 0 }}</div>
                    </div>
                    <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Menunggu Approval PPK</div>
                        <div class="text-lg font-black text-blue-600">{{ $roleData['stats']['pp_menunggu_ppk'] ?? 0 }}</div>
                    </div>
                    <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                </div>

                <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Siap Belanja / SPK PP</div>
                        <div class="text-lg font-black text-emerald-600">{{ $roleData['stats']['pp_siap_belanja'] ?? 0 }}</div>
                    </div>
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </span>
                </div>
            </div>

            {{-- 2 Kolom: Kiri Tabel Paket PP, Kanan Log Aktivitas PP --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Kolom Kiri: Tabel Paket Pengadaan di Jalur PP (8 Cols) --}}
                <div class="lg:col-span-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-[#213153] flex items-center gap-1.5">
                            <svg class="size-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            Daftar Paket Dalam Wewenang Pejabat Pengadaan (PP)
                        </h3>
                        <span class="text-[11px] text-slate-400">Jalur Pengadaan Langsung (50jt - 200jt)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-[#f0f6ff] text-[#213153] font-bold border-b border-slate-200">
                                    <th class="px-3.5 py-2.5">Paket</th>
                                    <th class="px-3.5 py-2.5">Nilai HPS</th>
                                    <th class="px-3.5 py-2.5">Harga Negosiasi PP</th>
                                    <th class="px-3.5 py-2.5">Vendor Dipilih</th>
                                    <th class="px-3.5 py-2.5">Status Progres</th>
                                    <th class="px-3.5 py-2.5 text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                                @forelse($roleData['pp_monitoring_list'] ?? [] as $ppPkt)
                                    <tr class="hover:bg-blue-50/40 transition-colors">
                                        <td class="px-3.5 py-2.5">
                                            <div class="font-bold text-[#213153]">{{ $ppPkt->kode_paket ?? $ppPkt->nomor_paket }}</div>
                                            <div class="text-[11px] text-slate-500 line-clamp-1" title="{{ $ppPkt->nama_paket }}">{{ $ppPkt->nama_paket }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap font-medium text-slate-700">
                                            Rp {{ number_format((float) ($ppPkt->nilai_hps ?? 0), 0, ',', '.') }}
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            @if($ppPkt->nominal_harga_akhir || $ppPkt->nilai_hasil_negosiasi)
                                                <span class="font-bold text-emerald-700">
                                                    Rp {{ number_format((float) ($ppPkt->nominal_harga_akhir ?: $ppPkt->nilai_hasil_negosiasi), 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="italic text-slate-400 text-[11px]">Belum diinput PP</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            @if($ppPkt->vendor)
                                                <div class="font-semibold text-[#213153] flex items-center gap-1">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    {{ $ppPkt->vendor->nama_vendor }}
                                                </div>
                                            @else
                                                <span class="text-slate-400 text-[11px] italic">Belum ditentukan</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            <x-status-badge :status="$ppPkt->status_paket" size="xs" />
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                            <a href="{{ route('kontrak.index') }}" class="px-2 py-1 rounded bg-blue-50 hover:bg-blue-100 text-[#0969da] font-bold text-[11px] transition-colors">
                                                Lihat &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                            Tidak ada paket yang sedang dalam jalur penanganan Pejabat Pengadaan (PP).
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Kolom Kanan: Feed Log Audit Aktivitas PP (4 Cols) --}}
                <div class="lg:col-span-4 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-[#213153] flex items-center gap-1.5">
                            <svg class="size-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Log Tindakan Pejabat Pengadaan
                        </h3>
                        <a href="{{ route('laporan.index') }}" class="text-[11px] font-bold text-[#0969da] hover:underline">Semua &rarr;</a>
                    </div>

                    <div class="p-3 divide-y divide-slate-100 max-h-[320px] overflow-y-auto space-y-2">
                        @forelse($roleData['pp_audit_logs'] ?? [] as $audit)
                            <div class="pt-2 first:pt-0 space-y-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-[#213153]">{{ $audit->user->nama ?? $audit->user->name ?? 'Pejabat Pengadaan' }}</span>
                                    <span class="text-slate-400 text-[10px]">{{ $audit->waktu_log?->diffForHumans() ?? 'Baru saja' }}</span>
                                </div>
                                <p class="text-xs text-slate-600 line-clamp-2">
                                    {{ $audit->alasan ?: ($audit->jenis_aksi . ' pada ' . $audit->nama_entitas . ' ' . $audit->id_entitas) }}
                                </p>
                                <div class="flex items-center gap-1 text-[10px] text-blue-600 font-medium">
                                    <span>Paket/Entitas:</span>
                                    <span class="font-bold">{{ $audit->id_entitas }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-slate-400 text-xs">
                                Belum ada riwayat aktivitas tindakan PP yang terekam.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
