{{-- resources/views/dashboard/roles/admin.blade.php --}}
{{-- Role: Administrator Sistem (admin) --}}

<div class="space-y-8">

    {{-- 1. 4-COLUMN KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <x-stat-card
            title="Menunggu Persetujuan Akun"
            :value="$stats['pending_registrations'] ?? 0"
            color="amber"
            icon="bell"
            badge="Perlu ACC"
            subtitle="Pendaftar akun baru dinas"
            :href="route('admin.persetujuan-registrasi.index')"
        />

        <x-stat-card
            title="Total Pengguna Aktif"
            :value="$stats['active_users'] ?? 0"
            color="emerald"
            icon="users"
            subtitle="Pegawai terverifikasi"
            :href="route('admin.roles.index')"
        />

        <x-stat-card
            title="Master Role Sistem"
            :value="$stats['total_roles'] ?? 9"
            color="navy"
            icon="document"
            subtitle="9 Peran resmi SIM-PBJ"
            :href="route('admin.roles.index')"
        />

        <x-stat-card
            title="Unit Kerja & Jurusan"
            :value="$stats['total_units'] ?? 0"
            color="blue"
            icon="package"
            subtitle="Jurusan, bidang & unit kerja"
        />
    </div>

    {{-- 2. MAIN OPERATION GRID --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- KOLOM KIRI (5 Cols): Antrean Persetujuan Registrasi --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <h2 class="text-lg font-bold text-[#213153]">Antrean Approval Registrasi</h2>
                </div>
                <a href="{{ route('admin.persetujuan-registrasi.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                    Kelola Semua
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="space-y-3">
                @forelse($roleData['pending_users'] ?? [] as $pendingUser)
                    <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-amber-200 transition-colors space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-[#213153] truncate">
                                    {{ $pendingUser->nama }}
                                </h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    NIP: {{ $pendingUser->nip ?? '-' }} &bull; {{ $pendingUser->email }}
                                </p>
                                <span class="text-[10px] text-slate-400 mt-1 block">
                                    Unit: {{ $pendingUser->unit->nama_unit ?? $pendingUser->kode_unit ?? 'Belum ada unit' }}
                                </span>
                            </div>
                            <x-status-badge status="menunggu_persetujuan" text="Pendaftar Baru" size="xs" />
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-end gap-2">
                            <a href="{{ route('admin.persetujuan-registrasi.index') }}" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-colors">
                                Tolak
                            </a>
                            <a href="{{ route('admin.persetujuan-registrasi.index') }}" class="px-3 py-1 rounded-lg text-xs font-bold text-white bg-[#0969da] hover:bg-blue-700 shadow-sm transition-colors">
                                Review & ACC
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center rounded-xl bg-white border border-slate-200/80 shadow-sm">
                        <svg class="size-9 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500 font-medium">Tidak ada permohonan registrasi yang menunggu persetujuan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KOLOM KANAN (7 Cols): Ringkasan Distribusi Role & Pemantauan Sistem --}}
        <div class="lg:col-span-7 space-y-6">

            {{-- Ringkasan Distribusi Role --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-[#0969da]"></span>
                        <h2 class="text-lg font-bold text-[#213153]">Distribusi Pengguna Berdasarkan Role</h2>
                    </div>
                    <a href="{{ route('admin.roles.index') }}" class="text-xs font-bold text-[#0969da] hover:underline flex items-center gap-1">
                        Konfigurasi Hak Akses
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-[#ddeeff] text-[#213153] font-bold">
                                <th class="px-4 py-3">Nama Role</th>
                                <th class="px-4 py-3">Label Resmi</th>
                                <th class="px-4 py-3 text-center">Jumlah Akun</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                            @forelse($roleData['roles_summary'] ?? [] as $roleItem)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-[#213153] font-mono">
                                        {{ $roleItem->nama_role }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $roleItem->label ?? ucfirst(str_replace('_', ' ', $roleItem->nama_role)) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-[#0969da] border border-blue-200">
                                            {{ $roleItem->users_count ?? 0 }} Pengguna
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.roles.index') }}" class="text-[11px] font-bold text-[#0969da] hover:underline">
                                            Detail &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                        Data role sistem belum terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pintasan Cepat Pengelolaan Sistem --}}
            <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-50 to-blue-50/40 border border-slate-200/80 space-y-3">
                <h3 class="text-xs font-bold text-[#213153] uppercase tracking-wider">Aksi Cepat Administrator</h3>
                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('admin.persetujuan-registrasi.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-[#213153] hover:border-blue-300 hover:text-[#0969da] shadow-sm transition-all">
                        <svg class="size-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Approval Akun Baru
                    </a>
                    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-[#213153] hover:border-blue-300 hover:text-[#0969da] shadow-sm transition-all">
                        <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Matriks Hak Akses & Role
                    </a>
                    <a href="{{ route('monitoring.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-xs font-bold text-[#213153] hover:border-blue-300 hover:text-[#0969da] shadow-sm transition-all">
                        <svg class="size-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Monitoring & Jejak Audit
                    </a>
                </div>
            </div>

        </div>

    </div>

</div>
