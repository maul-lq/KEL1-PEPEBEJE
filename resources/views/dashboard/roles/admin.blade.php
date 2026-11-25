{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Sub-view dasbor ini susun untuk modul Administrator Sistem yang memuat 4 KPI
              registrasi/pengguna/peran/permohonan, antrean persetujuan cepat ACC/Tolak, log audit
              atau ringkasan peran, serta modal aksi persetujuan registrasi.
--}}
{{--
   Panel Workspace Dasbor Administrator (`dashboard.roles.admin`).

  Tujuan: memeriksa permission `admin.approval_registrasi` dan `audit_log.view` secara mandiri
          sehingga peran kombinasi tetap hanya melihat widget sesuai izin aktifnya.
  Parameter: `$user` (User), `$stats` (array), `$roleData` (array).
  Nilai kembali: Fragmen HTML dasbor Administrator beserta modal ACC/Tolak pendaftar baru.
  Contoh pemanggilan: `@includeIf('dashboard.roles.admin', [...])`
--}}
@php
    $canApproveRegistrasi = $user->hasPermission('admin.approval_registrasi');
    $canViewAudit = $user->hasPermission('audit_log.view');
    $rightHref = $canViewAudit
        ? ($user->hasAnyPermission(['laporan.view_all', 'laporan.rekapitulasi']) ? route('laporan.index') : ($user->hasPermission('admin.user_manage') ? route('admin.users.index') : null))
        : ($user->hasPermission('admin.role_manage') ? route('admin.roles.index') : null);
@endphp

<div
    class="space-y-6"
    x-data="adminDashboardApproval"
>
    {{-- 1. 4-COLUMN FIGMA KPI STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        <x-figma-stat-card
            title="Persetujuan Akun"
            :value="$stats['pending_registrations'] ?? 0"
            subtitle="Pendaftar menunggu persetujuan"
            variant="action"
            :href="$canApproveRegistrasi ? route('admin.persetujuan-registrasi.index') : null"
        />

        <x-figma-stat-card
            title="Pengguna Aktif"
            :value="$stats['active_users'] ?? 0"
            subtitle="Pegawai & unit terverifikasi"
            variant="active"
            :href="$user->hasPermission('admin.user_manage') ? route('admin.users.index') : null"
        />

        <x-figma-stat-card
            title="Daftar Peran"
            :value="$stats['total_roles'] ?? 0"
            subtitle="Pengaturan hak akses"
            variant="done"
            :href="$user->hasPermission('admin.role_manage') ? route('admin.roles.index') : null"
        />

        <x-figma-stat-card
            title="Total Permohonan"
            :value="$stats['total_permohonan_all'] ?? 0"
            subtitle="Seluruh usulan dalam sistem"
            variant="bell"
            :href="$user->hasAnyPermission(['permohonan.view_all', 'permohonan.delete']) ? route('permohonan.index') : null"
        />
    </div>

    {{-- 2. 2-COLUMN FIGMA OPERATION GRID --}}
    <x-figma-workspace
        leftTitle="Perlu Tindakan Anda"
        :leftHref="$canApproveRegistrasi ? route('admin.persetujuan-registrasi.index') : null"
        :rightTitle="$canViewAudit ? 'Aktivitas Terbaru' : 'Sebaran Peran'"
        :rightHref="$rightHref"
    >
        <x-slot:left>
            @if($canApproveRegistrasi)
                @forelse($roleData['pending_users'] ?? [] as $pendingUser)
                    @php
                        $unitNama = $pendingUser->unit->nama_unit ?? $pendingUser->kode_unit ?? 'Belum ada unit';
                        $payload = [
                            'nama' => $pendingUser->nama,
                            'nip' => $pendingUser->nip ?? '-',
                            'email' => $pendingUser->email ?? '-',
                            'unit' => $unitNama,
                            'accUrl' => route('admin.persetujuan-registrasi.acc', $pendingUser),
                            'tolakUrl' => route('admin.persetujuan-registrasi.tolak', $pendingUser),
                        ];
                    @endphp
                    <x-figma-action-card
                        :index="$loop->index"
                        variant="action"
                        :title="$pendingUser->nama"
                        :meta="'NIP: ' . ($pendingUser->nip ?? '-') . ' • ' . ($pendingUser->email ?? '-')"
                        :submeta="'Unit: ' . $unitNama"
                        badge="Pendaftar Baru"
                        badgeClass="bg-[#fff5df] text-[#99660d]"
                    >
                        <x-slot:actions>
                            <button
                                type="button"
                                @click='openReject(@json($payload, JSON_HEX_APOS))'
                                class="bg-rose-600 hover:bg-rose-700 text-white text-[13px] md:text-[14px] font-medium px-3.5 py-1.5 rounded-[5px] whitespace-nowrap transition-colors shadow-xs cursor-pointer"
                            >
                                Tolak
                            </button>
                            <button
                                type="button"
                                @click='openApprove(@json($payload, JSON_HEX_APOS))'
                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] md:text-[14px] font-medium px-3.5 py-1.5 rounded-[5px] whitespace-nowrap transition-colors shadow-xs cursor-pointer"
                            >
                                Terima
                            </button>
                        </x-slot:actions>
                    </x-figma-action-card>
                @empty
                    <x-figma-empty
                        title="Tidak ada pendaftaran akun yang menunggu persetujuan"
                        subtitle="Seluruh permohonan registrasi pengguna baru telah diproses oleh Administrator."
                    />
                @endforelse
            @else
                <x-figma-empty
                    title="Antrean registrasi tidak ditampilkan"
                    subtitle="Izin persetujuan registrasi tidak diaktifkan untuk peran akun Anda."
                />
            @endif
        </x-slot:left>

        <x-slot:right>
            @if($canViewAudit)
                <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                    <div class="w-[22%] shrink-0">Waktu</div>
                    <div class="w-[24%] shrink-0">Aksi</div>
                    <div class="flex-1 px-2">Aktivitas / Entitas</div>
                    <div class="w-[20%] shrink-0 text-right">Pengguna</div>
                </div>

                <div class="divide-y divide-[#d2d2d2]">
                    @forelse($roleData['admin_audit_logs'] ?? [] as $log)
                        @php
                            $waktuLog = $log->waktu_log ? \Carbon\Carbon::parse($log->waktu_log)->format('d M Y, H:i') : '-';
                            $aksiLabel = ucwords(strtolower(str_replace('_', ' ', $log->jenis_aksi ?? 'Aktivitas')));
                            $detailLog = $log->alasan ?: trim(($log->nama_entitas ?? 'Sistem') . ' #' . ($log->id_entitas ?? '-'));
                            $actorName = $log->user->nama ?? 'Sistem';
                        @endphp
                        <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                            <div class="w-[22%] shrink-0 font-semibold truncate" title="{{ $waktuLog }}">
                                {{ $waktuLog }}
                            </div>
                            <div class="w-[24%] shrink-0 font-semibold truncate" title="{{ $aksiLabel }}">
                                {{ $aksiLabel }}
                            </div>
                            <div class="flex-1 px-2 font-medium truncate" title="{{ $detailLog }}">
                                {{ $detailLog }}
                            </div>
                            <div class="w-[20%] shrink-0 font-semibold text-right truncate" title="{{ $actorName }}">
                                {{ $actorName }}
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-xs text-slate-400">
                            Belum ada log aktivitas sistem yang terekam.
                        </div>
                    @endforelse
                </div>
            @else
                <div class="bg-[#deeeff] rounded-[5px] h-[35px] px-3 flex items-center text-[#4a4a4a] text-[13px] md:text-[14px] font-semibold">
                    <div class="w-[24%] shrink-0">Kode Role</div>
                    <div class="flex-1 px-2">Label Resmi</div>
                    <div class="w-[20%] shrink-0 text-right">Pengguna</div>
                </div>

                <div class="divide-y divide-[#d2d2d2]">
                    @forelse($roleData['roles_summary'] ?? [] as $roleItem)
                        <div class="py-2.5 px-3 flex items-center text-[#4a4a4a] text-[12px] hover:bg-slate-50/80 transition-colors">
                            <div class="w-[24%] shrink-0 font-mono font-semibold truncate" title="{{ $roleItem->nama_role }}">
                                {{ $roleItem->nama_role }}
                            </div>
                            <div class="flex-1 px-2 font-medium truncate" title="{{ $roleItem->label ?? $roleItem->nama_role }}">
                                {{ $roleItem->label ?? ucfirst(str_replace('_', ' ', $roleItem->nama_role)) }}
                            </div>
                            <div class="w-[20%] shrink-0 font-semibold text-right">
                                {{ $roleItem->users_count ?? 0 }} Akun
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-xs text-slate-400">
                            Data role sistem belum terdaftar.
                        </div>
                    @endforelse
                </div>
            @endif
        </x-slot:right>
    </x-figma-workspace>

    {{-- MODAL TERIMA (ACC) REGISTRASI & PENETAPAN ROLE --}}
    <div
        x-show="approveModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @keydown.escape.window="approveModalOpen = false"
    >
        <div class="w-full max-w-md rounded-[8px] border border-[#d2d2d2] bg-white p-6 shadow-xl" @click.away="approveModalOpen = false">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-semibold text-[#14213d]">Setujui Pendaftaran Akun</h3>
                </div>
                <button type="button" @click="approveModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">&times;</button>
            </div>

            <form method="POST" :action="selectedUser.accUrl" class="mt-4 space-y-4">
                @csrf
                <div class="rounded-[5px] bg-slate-50 border border-slate-200 p-3 text-xs space-y-1">
                    <div class="font-semibold text-[#14213d]" x-text="selectedUser.nama"></div>
                    <div class="text-[#657a7c]">NIP: <span x-text="selectedUser.nip"></span> &bull; <span x-text="selectedUser.email"></span></div>
                    <div class="text-[#657a7c]">Unit: <span x-text="selectedUser.unit"></span></div>
                </div>

                <div>
                    <label for="admin-acc-role" class="block text-xs font-semibold text-[#4a4a4a] mb-1.5">
                        Pilih Peran <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="admin-acc-role"
                        name="id_role"
                        required
                        class="w-full rounded-[5px] border border-[#d2d2d2] px-3 py-2 text-sm text-[#14213d] focus:border-[#3b82f6] focus:outline-none"
                    >
                        <option value="">-- Pilih Role Pengguna --</option>
                        @foreach($roleData['roles_list'] ?? [] as $roleOption)
                            <option value="{{ $roleOption->id }}">
                                {{ $roleOption->label ?? ucfirst(str_replace('_', ' ', $roleOption->nama_role)) }} ({{ $roleOption->nama_role }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="approveModalOpen = false" class="px-4 py-2 rounded-[5px] border border-[#d2d2d2] text-xs font-medium text-[#4a4a4a] hover:bg-slate-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-[5px] bg-emerald-600 hover:bg-emerald-700 text-xs font-medium text-white cursor-pointer">
                        Konfirmasi Terima
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL TOLAK REGISTRASI --}}
    <div
        x-show="rejectModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @keydown.escape.window="rejectModalOpen = false"
    >
        <div class="w-full max-w-md rounded-[8px] border border-[#d2d2d2] bg-white p-6 shadow-xl" @click.away="rejectModalOpen = false">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-base font-semibold text-[#14213d]">Tolak Pendaftaran Akun</h3>
                </div>
                <button type="button" @click="rejectModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">&times;</button>
            </div>

            <form method="POST" :action="selectedUser.tolakUrl" class="mt-4 space-y-4">
                @csrf
                <div class="rounded-[5px] bg-rose-50/70 border border-rose-200 p-3 text-xs space-y-1">
                    <div class="font-semibold text-rose-900" x-text="selectedUser.nama"></div>
                    <div class="text-rose-700">NIP: <span x-text="selectedUser.nip"></span> &bull; <span x-text="selectedUser.email"></span></div>
                </div>

                <div>
                    <label for="admin-reject-reason" class="block text-xs font-semibold text-[#4a4a4a] mb-1.5">
                        Alasan Penolakan <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        id="admin-reject-reason"
                        name="alasan_penolakan"
                        rows="3"
                        required
                        maxlength="500"
                        placeholder="Tuliskan alasan penolakan pendaftaran..."
                        class="w-full rounded-[5px] border border-[#d2d2d2] px-3 py-2 text-sm text-[#14213d] focus:border-rose-500 focus:outline-none"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="rejectModalOpen = false" class="px-4 py-2 rounded-[5px] border border-[#d2d2d2] text-xs font-medium text-[#4a4a4a] hover:bg-slate-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-[5px] bg-rose-600 hover:bg-rose-700 text-xs font-medium text-white cursor-pointer">
                        Konfirmasi Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
