{{-- resources/views/dashboard/roles/keuangan.blade.php --}}
{{-- Role: Bagian Keuangan / Bendahara Pengeluaran (keuangan) --}}
{{-- BPMN Lane Keuangan: N44 (Reminder), N59 (Terima Paket Pembayaran), N62 (Bayar), N66 (Bukti), N70 (Notifikasi Seluruh Role), N72 (End) --}}

<div class="space-y-8">

    {{-- 1. BPMN WORKFLOW BANNER KEUANGAN --}}
    <div class="rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-50/60 via-white to-blue-50/40 p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center size-7 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-sm">
                    K
                </span>
                <div>
                    <h3 class="font-bold text-[#213153] text-sm">Alur Operasional: Bagian Keuangan (Pencairan Kas &amp; Pembayaran)</h3>
                    <p class="text-[11px] text-slate-500">Menerima reminder operasional dan mengeksekusi pencairan kas final pengadaan barang dan jasa.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. 4-COLUMN KPI STAT CARDS (SESUAI BPMN KEUANGAN) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Pengingat Masuk"
            :value="$stats['reminder_masuk'] ?? 0"
            color="amber"
            icon="bell"
            badge="Operasional"
            badgeColor="bg-amber-50 text-amber-800 border-amber-200"
            subtitle="Pengingat batas termin dari PPBJ"
        />

        <x-stat-card
            title="Paket Masuk dari PPBJ"
            :value="$stats['paket_masuk_ppbj'] ?? ($stats['sp2d_siap_cair'] ?? 0)"
            color="blue"
            icon="document"
            badge="Siap Bayar"
            badgeColor="bg-blue-50 text-[#0969da] border-blue-200"
            subtitle="Dokumen Pembayaran, Vendor & Memo"
            :href="route('pembayaran.index')"
        />

        <x-stat-card
            title="Pencairan Kas Selesai"
            :value="$stats['transaksi_lunas'] ?? 0"
            color="emerald"
            icon="check"
            badge="Lunas SP2D"
            badgeColor="bg-emerald-50 text-emerald-800 border-emerald-200"
            subtitle="Siklus pengadaan selesai 100%"
            :href="route('pembayaran.index')"
        />

        <x-stat-card
            title="Akumulasi Dana Dicairkan"
            :value="'Rp ' . number_format((float) ($stats['total_dana_cair'] ?? 0), 0, ',', '.')"
            color="navy"
            icon="wallet"
            subtitle="Realisasi kas negara ke rekanan"
        />
    </div>

    {{-- 3. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (6 Cols): Antrean Paket Pembayaran Masuk dari PPBJ (BPMN N59) --}}
        <div class="lg:col-span-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-blue-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Paket Masuk dari PPBJ Siap Cair</h2>
                </div>
                <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Buka Kasir SP2D
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['antrean_tagihan'] ?? [] as $tagihan)
                    @php
                        $vendorObj = $tagihan->paket->kontrakSpk->vendor ?? ($tagihan->paket->kontrak->vendor ?? ($tagihan->paket->vendor ?? null));
                    @endphp
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-mono font-bold text-[#0969da]">
                                        {{ $tagihan->nomor_pembayaran }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ $tagihan->nomor_paket }}
                                    </span>
                                </div>
                                <h4 class="text-xs font-bold text-[#213153] truncate mt-0.5" title="{{ $tagihan->paket->nama_paket ?? '-' }}">
                                    {{ $tagihan->paket->nama_paket ?? 'Pembayaran Pengadaan' }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    Penyedia: <span class="font-semibold text-slate-700">{{ $vendorObj->nama_vendor ?? 'Penyedia Resmi' }}</span>
                                </p>
                                <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-1">
                                    <span>Termin: Ke-{{ $tagihan->tahap_termin }}</span>
                                    <span>&bull;</span>
                                    <span>SPP Terbit: {{ $tagihan->tanggal_perintah_bayar ? \Carbon\Carbon::parse($tagihan->tanggal_perintah_bayar)->format('d/m/Y') : '-' }}</span>
                                </div>
                            </div>
                            <x-status-badge :status="$tagihan->status_pembayaran" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                            <div>
                                <span class="text-[10px] text-slate-400 block">Nominal Pencairan:</span>
                                <span class="font-mono font-bold text-emerald-600">
                                    Rp {{ number_format((float) ($tagihan->nominal_pengajuan ?? 0), 0, ',', '.') }}
                                </span>
                            </div>
                            <a href="{{ route('pembayaran.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-colors inline-flex items-center gap-1">
                                Cairkan SP2D &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Seluruh berkas tagihan SPP dari PPBJ telah diproses dan dicairkan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KOLOM KANAN (6 Cols): Reminder Masuk (N44) & Riwayat Transaksi SP2D (N66 / N72 End) --}}
        <div class="lg:col-span-6 space-y-6">

            {{-- SUB-CARD 1: Reminder Masuk dari PPBJ (BPMN N44) --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-amber-500"></span>
                        <h2 class="text-base font-bold text-[#213153]">Pengingat Masuk dari PPBJ</h2>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Antrean Aktif</span>
                </div>

                <div class="space-y-2">
                    @forelse($roleData['reminders'] ?? [] as $rem)
                        @php
                            $judulRem = $rem instanceof \App\Models\NotifikasiUser ? $rem->judul : ($rem->judul_alert ?? 'Pemberitahuan Sistem');
                            $pesanRem = $rem instanceof \App\Models\NotifikasiUser ? $rem->pesan : ($rem->pesan_alert ?? '-');
                            $waktuRem = $rem->created_at ?? ($rem->waktu_trigger ?? now());
                            $waktuTrigger = $rem instanceof \App\Models\NotifikasiUser ? $rem->reminder?->waktu_trigger : $rem->waktu_trigger;
                            $intervalBulan = $rem instanceof \App\Models\NotifikasiUser ? $rem->reminder?->interval_bulan : $rem->interval_bulan;
                        @endphp
                        <div class="p-3 rounded-xl bg-white border border-slate-200/80 shadow-sm text-xs space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-[#213153] truncate">{{ $judulRem }}</span>
                                <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($waktuRem)->diffForHumans() }}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 line-clamp-2">{{ $pesanRem }}</p>
                            @if($waktuTrigger || $intervalBulan)
                                <div class="flex items-center gap-2 pt-1 text-[10px] text-slate-500">
                                    @if($waktuTrigger)
                                        <span>Trigger: <strong class="font-mono text-slate-700">{{ \Carbon\Carbon::parse($waktuTrigger)->format('d/m/Y') }}</strong></span>
                                    @endif
                                    @if($intervalBulan)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-[#0969da] border border-blue-200">
                                            Berkala ({{ $intervalBulan }} Bulan)
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm text-xs text-slate-400">
                            Belum ada reminder operasional dari PPBJ.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- SUB-CARD 2: Riwayat Pencairan SP2D Selesai (BPMN N66 & N72 End) --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        <h2 class="text-base font-bold text-[#213153]">Riwayat Pencairan SP2D Selesai</h2>
                    </div>
                    <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                        Lihat Pembukuan
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 text-[11px]">
                                    <th class="px-4 py-3">Tanggal Cair</th>
                                    <th class="px-4 py-3">No. Transaksi Bank</th>
                                    <th class="px-4 py-3">Penerima Rekanan</th>
                                    <th class="px-4 py-3">Nominal Cair</th>
                                    <th class="px-4 py-3 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                                @forelse($roleData['transaksi_cair'] ?? [] as $tx)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-400">
                                            {{ $tx->tanggal_transfer ? date('d M Y', strtotime($tx->tanggal_transfer)) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-[#0969da]">
                                            {{ $tx->nomor_transaksi_bank }}
                                        </td>
                                        <td class="px-4 py-3 max-w-xs truncate">
                                            {{ $tx->pembayaran->paket->vendor->nama_vendor ?? 'Penyedia Rekanan' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-emerald-600">
                                            Rp {{ number_format((float) ($tx->nominal_transfer ?? 0), 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <x-status-badge status="sp2d_lunas" size="xs" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                            Belum ada rekaman pencairan dana SP2D yang tercatat.
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

</div>
