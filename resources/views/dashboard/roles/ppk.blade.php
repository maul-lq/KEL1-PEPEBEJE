{{-- resources/views/dashboard/roles/ppk.blade.php --}}
{{-- Role: Pejabat Pembuat Komitmen (ppk - 4 Akun Terisolasi Mandiri) --}}

<div class="space-y-8">

    {{-- BANNER INFORMASI ISOLASI DATA KETAT (NFR 6) --}}
    <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-200/80 shadow-sm flex items-start gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-[#0969da] mt-0.5">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <h4 class="text-xs font-bold text-[#213153]">
                Penegakan Isolasi Data Portofolio PPK (Akun: {{ $user->username ?? Auth::user()->username }})
            </h4>
            <p class="text-[11px] text-slate-500 mt-0.5">
                Sesuai kebijakan NFR 6 SIM-PBJ, Anda hanya dapat mengakses paket pengadaan dan memo bayar yang secara spesifik ditugaskan kepada akun Anda. Paket PPK lain tidak ditampilkan.
            </p>
        </div>
    </div>

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Paket Komitmen Saya"
            :value="$stats['total_paket_ppk'] ?? 0"
            color="navy"
            icon="package"
            badge="Terisolasi"
            subtitle="Paket ditugaskan ke akun Anda"
            :href="route('kontrak.index')"
        />

        <x-stat-card
            title="Tender Aktif (>200jt)"
            :value="$stats['tender_aktif'] ?? 0"
            color="amber"
            icon="bell"
            subtitle="Paket komitmen strategis"
        />

        <x-stat-card
            title="Kontrak / SPK Diterbitkan"
            :value="$stats['spk_aktif'] ?? 0"
            color="blue"
            icon="document"
            subtitle="Kontrak rekanan terbit & kunci MAK"
        />

        <x-stat-card
            title="Memo Bayar Lvl 1 Pending"
            :value="$stats['memo_bayar_pending'] ?? 0"
            color="rose"
            icon="wallet"
            badge="Level 1"
            subtitle="Persetujuan tagihan termin siap ACC"
            :href="route('pembayaran.index')"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (7 Cols): Tabel Paket Komitmen PPK Terisolasi --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-[#213153]"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Daftar Paket Komitmen Resmi (Terisolasi)</h2>
                </div>
                <a href="{{ route('kontrak.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Kelola Kontrak
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Kode Paket</th>
                                <th class="px-4 py-3">Nama Paket Komitmen</th>
                                <th class="px-4 py-3">Nilai HPS</th>
                                <th class="px-4 py-3">Penyedia Rekanan</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['paket_komitmen'] ?? [] as $komitmen)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#213153]">
                                        {{ $komitmen->kode_paket ?? $komitmen->nomor_paket }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate" title="{{ $komitmen->nama_paket }}">
                                        {{ $komitmen->nama_paket }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-[#213153]">
                                        Rp {{ number_format((float) ($komitmen->nilai_hps ?? 0), 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $komitmen->vendor->nama_perusahaan ?? 'Penyedia Terpilih' }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <x-status-badge :status="$komitmen->status_paket" size="xs" />
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('kontrak.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                            Reviu & Kontrak
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                        Belum ada paket komitmen yang ditugaskan khusus kepada akun PPK Anda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN (5 Cols): Antrean Persetujuan Memo Bayar Level 1 --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Memo Bayar Level 1</h2>
                </div>
                <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Buka Pembayaran
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['antrean_memo'] ?? [] as $memoLvl1)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-blue-200 transition-colors space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-[#213153] truncate">
                                    {{ $memoLvl1->pembayaran->paket->nama_paket ?? $memoLvl1->nomor_pembayaran }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    Rekanan: {{ $memoLvl1->pembayaran->paket->vendor->nama_perusahaan ?? 'Penyedia Resmi' }}
                                </p>
                            </div>
                            <x-status-badge status="spp_terbit" text="Menunggu ACC Lvl 1" size="xs" />
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                            <span class="font-bold text-[#0969da]">
                                Rp {{ number_format((float) ($memoLvl1->pembayaran->nominal_pengajuan ?? 0), 0, ',', '.') }}
                            </span>
                            <a href="{{ route('pembayaran.index') }}" class="text-xs font-bold text-[#0969da] hover:underline">
                                Setujui Memo &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Tidak ada Memo Bayar Level 1 yang memerlukan persetujuan Anda.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
