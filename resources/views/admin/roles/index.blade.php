@extends('layouts.app')

@section('title', 'Manajemen Akun dan Role - SIM-PBJ')

@section('content')
<div x-data="roleManager()" class="space-y-6">

   <!-- HEADER NAVIGATION & JUDUL HALAMAN -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <!-- Tombol Kembali dengan Jarak (mb-4) -->
        <a href="{{ route('profile.index') }}" 
           class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm hover:shadow-md transition-all duration-200 mb-4">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span x-text="viewMode === 'matrix' ? 'Kembali ke Manajemen Profil' : 'Kembali ke Profil'"></span>
        </a>

        <h1 class="text-2xl md:text-3xl font-extrabold text-[#213153] tracking-tight">
            Manajemen Akun dan Role
        </h1>
    </div>

    <!-- Tab Switching Mode -->
    <div class="flex items-center bg-slate-200/70 p-1 rounded-xl shrink-0">
        <button @click="viewMode = 'users'" 
                :class="viewMode === 'users' ? 'bg-white text-[#0969da] shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                class="px-4 py-2 text-xs rounded-lg transition-all">
            Daftar Akun User
        </button>
        <button @click="viewMode = 'roles'" 
                :class="viewMode === 'roles' ? 'bg-white text-[#0969da] shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                class="px-4 py-2 text-xs rounded-lg transition-all">
            Kelola Role
        </button>
        <button @click="viewMode = 'matrix'" 
                :class="viewMode === 'matrix' ? 'bg-white text-[#0969da] shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                class="px-4 py-2 text-xs rounded-lg transition-all">
            Matriks Hak Akses
        </button>
    </div>
</div>


    <!-- ========================================================================= -->
    <!-- VIEW 1: MANAJEMEN AKUN & ROLE (USER LIST & RIGHT SIDEBAR PERMISSION)     -->
    <!-- ========================================================================= -->
    <template x-if="viewMode === 'users'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- PANEL KIRI (8/12): FILTER & TABEL AKUN USER -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Filter Baris Atas -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Pencarian</label>
                        <input type="text" x-model="searchUser" placeholder="Cari Nama atau ID" class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Unit/Bidang/Jurusan</label>
                        <select x-model="filterUnit" class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                            <option value="Semua">Semua Unit</option>
                            <option value="TIK">TIK / Teknik Informatika</option>
                            <option value="Keuangan">Keuangan</option>
                            <option value="Pengadaan">Pengadaan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                        <select x-model="filterStatus" class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                            <option value="Semua">Semua Status</option>
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="searchUser = ''; filterUnit = 'Semua'; filterStatus = 'Semua';" type="button" class="w-full h-9 bg-blue-500 hover:bg-blue-600 text-white font-medium text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Tabel User -->
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#deeeff] text-[#213153] font-bold">
                                <tr>
                                    <th class="p-3">Profil</th>
                                    <th class="p-3">ID</th>
                                    <th class="p-3">Nama</th>
                                    <th class="p-3">Unit/Bidang/Jurusan</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="u in filteredUsers" :key="u.id">
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="p-3">
                                            <div class="size-8 rounded-full bg-[#0969da] text-white flex items-center justify-center font-bold text-xs" x-text="u.nama.substring(0, 1)"></div>
                                        </td>
                                        <td class="p-3 font-semibold text-slate-700" x-text="u.nip"></td>
                                        <td class="p-3 font-bold text-[#213153]" x-text="u.nama"></td>
                                        <td class="p-3 text-slate-600" x-text="u.unit"></td>
                                        <td class="p-3">
                                            <span :class="u.status === 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'" class="px-2.5 py-0.5 rounded-full font-bold text-[10px]" x-text="u.status"></span>
                                        </td>
                                        <td class="p-3 text-center">
                                            <button @click="toggleUserStatus(u)" type="button" 
                                                    :class="u.status === 'Aktif' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                                                    class="px-3 py-1 text-white font-bold text-[11px] rounded-md transition-colors shadow-sm">
                                                <span x-text="u.status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan'"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PANEL KANAN (4/12): SIDEBAR PRIVILEGES RINGKAS & NAVIGASI CHANGER -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                    <h3 class="font-bold text-base text-[#213153] border-b border-slate-100 pb-2">Manajemen Role</h3>
                    
                    <template x-for="group in permissionGroups" :key="group.title">
                        <div class="space-y-2">
                            <h4 class="font-bold text-xs text-[#0969da]" x-text="group.title"></h4>
                            <div class="space-y-1.5 pl-1">
                                <template x-for="item in group.items" :key="item">
                                    <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700">
                                        <input type="checkbox" checked class="size-3.5 rounded border-slate-300 text-[#0969da] focus:ring-0">
                                        <span x-text="item"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="pt-2 text-center">
                        <button @click="viewMode = 'matrix'" type="button" class="w-full py-2 bg-[#0086e5] hover:bg-blue-600 text-white font-bold text-xs rounded-lg transition-colors shadow-sm">
                            Lihat lebih detail
                        </button>
                    </div>
                </div>

                <!-- Banner Tombol Akses Ke Matriks -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm text-center">
                    <button @click="viewMode = 'matrix'" type="button" class="w-full py-3 bg-[#0969da] hover:bg-blue-700 text-white font-extrabold text-sm rounded-lg transition-colors shadow-sm">
                        Manajemen Role & Hak Akses →
                    </button>
                </div>
            </div>

        </div>
    </template>


    <!-- ========================================================================= -->
    <!-- VIEW 2: MANAJEMEN TAMBAH ROLE (FORM CREATE & ROLE LIST TABLE)            -->
    <!-- ========================================================================= -->
    <template x-if="viewMode === 'roles'">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- PANEL KIRI (8/12): FORM TAMBAH ROLE & DAFTAR ROLE TERHUBUNG -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Form Tambah Role Baru -->
                <form @submit.prevent="addRole()" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex flex-col md:flex-row items-end gap-3">
                    <div class="w-full">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kode Role</label>
                        <input type="text" x-model="newRole.code" placeholder="Masukkan Kode Role" required class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                    </div>
                    <div class="w-full">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Tampilan Role</label>
                        <input type="text" x-model="newRole.name" placeholder="Masukkan Tampilan Role" required class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                    </div>
                    <div class="w-full">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Deskripsi Peran</label>
                        <input type="text" x-model="newRole.description" placeholder="Masukkan Deskripsi Singkat" class="w-full h-9 px-3 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-[#0969da] outline-none">
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="submit" class="h-9 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition-colors shadow-sm">
                            Tambah
                        </button>
                        <button @click="newRole = {code:'', name:'', description:''}" type="button" class="h-9 px-4 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-lg transition-colors shadow-sm">
                            Reset
                        </button>
                    </div>
                </form>

                <!-- Tabel Daftar Role System -->
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-[#deeeff] text-[#213153] font-bold">
                                <tr>
                                    <th class="p-3 w-10">No.</th>
                                    <th class="p-3">Kode & Nama Role</th>
                                    <th class="p-3">Tipe Peran</th>
                                    <th class="p-3">Jumlah Pengguna</th>
                                    <th class="p-3">Hak Akses Aktif</th>
                                    <th class="p-3 text-center">Edit</th>
                                    <th class="p-3 text-center">Aksi Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(role, index) in rolesList" :key="role.id">
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="p-3 font-semibold text-slate-600" x-text="index + 1"></td>
                                        <td class="p-3">
                                            <div class="font-bold text-[#213153]" x-text="role.code"></div>
                                            <div class="text-[11px] text-slate-500" x-text="role.name"></div>
                                            <div class="text-[10px] text-slate-400 italic" x-text="role.description"></div>
                                        </td>
                                        <td class="p-3 font-semibold text-slate-700" x-text="role.type"></td>
                                        <td class="p-3 font-semibold text-[#0969da]" x-text="role.users"></td>
                                        <td class="p-3">
                                            <ul class="list-disc list-inside text-[11px] text-slate-600 space-y-0.5">
                                                <template x-for="p in role.permissions" :key="p">
                                                    <li x-text="p"></li>
                                                </template>
                                            </ul>
                                        </td>
                                        <td class="p-3 text-center">
                                            <button type="button" class="px-3 py-1 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-[11px] rounded-md transition-colors shadow-sm">
                                                Edit
                                            </button>
                                        </td>
                                        <td class="p-3 text-center">
                                            <button @click="deleteRole(role.id)" type="button" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[11px] rounded-md transition-colors shadow-sm">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PANEL KANAN (4/12): CHECKLIST PERMISSON MATRIX SIDEBAR -->
            <div class="lg:col-span-4 bg-white rounded-xl border border-slate-200/80 p-5 shadow-sm space-y-4">
                <h3 class="font-bold text-base text-[#213153] border-b border-slate-100 pb-2">Manajemen Role</h3>
                
                <template x-for="group in permissionGroups" :key="group.title">
                    <div class="space-y-2">
                        <h4 class="font-bold text-xs text-[#0969da]" x-text="group.title"></h4>
                        <div class="space-y-1.5 pl-1">
                            <template x-for="item in group.items" :key="item">
                                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700">
                                    <input type="checkbox" checked class="size-3.5 rounded border-slate-300 text-[#0969da] focus:ring-0">
                                    <span x-text="item"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </template>


    <!-- ========================================================================= -->
    <!-- VIEW 3: MATRIKS HAK AKSES GRID 3-KOLOM (MATRIX PERMISSIONS FRAME 3)       -->
    <!-- ========================================================================= -->
    <template x-if="viewMode === 'matrix'">
        <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#213153]">Matriks Pengaturan Hak Akses Seluruh Role</h2>
                    <p class="text-xs text-slate-500 mt-1">Centang atau hilangkan hak akses fitur untuk seluruh modul sistem PBJ.</p>
                </div>
                <button @click="viewMode = 'users'" type="button" class="px-4 py-2 bg-[#0969da] text-white font-bold text-xs rounded-lg hover:bg-blue-700 transition">
                    Simpan Perubahan Matriks
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Repeat 3 Kolom Sesuai Desain Figma Frame 3 -->
                <template x-for="col in [1, 2, 3]" :key="col">
                    <div class="space-y-6 border-r border-slate-100 last:border-r-0 pr-4">
                        <template x-for="group in permissionGroups" :key="group.title">
                            <div class="space-y-3">
                                <h3 class="font-bold text-sm text-[#0969da] flex items-center gap-2">
                                    <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <span x-text="group.title"></span>
                                </h3>
                                <div class="space-y-2 pl-2">
                                    <template x-for="item in group.items" :key="item">
                                        <label class="flex items-start gap-2.5 cursor-pointer text-xs text-slate-700 hover:text-black transition-colors">
                                            <input type="checkbox" checked class="mt-0.5 size-4 rounded border-slate-300 text-[#0969da] focus:ring-0">
                                            <span class="leading-tight" x-text="item"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>

</div>

<!-- ALPINE.JS MANAGEMENT STATE ENGINE -->
<script>
    function roleManager() {
        return {
            viewMode: 'users', // Mode Tampilan: 'users', 'roles', atau 'matrix'
            searchUser: '',
            filterUnit: 'Semua',
            filterStatus: 'Semua',

            newRole: {
                code: '',
                name: '',
                description: ''
            },

            permissionGroups: [
                {
                    title: 'Modul Permohonan',
                    items: [
                        'Membuat usulan permohonan pengadaan',
                        'Melihat usulan milik sendiri',
                        'Melihat seluruh usulan permohonan unit',
                        'Mengajukan revisi usulan yang ditolak',
                        'Memecah usulan berdasarkan kode MAK'
                    ]
                },
                {
                    title: 'Modul Pengadaan',
                    items: [
                        'Registrasi paket pengadaan & penetapan HPS',
                        'Melihat seluruh paket pengadaan',
                        'Melihat paket yang ditugaskan khusus',
                        'Verifikasi teknis dan Negosiasi harga rekanan',
                        'Tinjauan dokumen, kunci MAK dan beli/sewa',
                        'Menerbitkan surat kontrak/SPK dan tembusan otomatis',
                        'Mengonfigurasi jadwal alarm pengingat pengiriman'
                    ]
                }
            ],

            usersList: [
                { id: 1, nip: '19800002005011000', nama: 'Riyan Sohibul Anam', unit: 'TIK', status: 'Aktif' },
                { id: 2, nip: '19820002005011001', nama: 'Budi Perencanaan', unit: 'Keuangan', status: 'Aktif' },
                { id: 3, nip: '19850002005011002', nama: 'Ahmad Staff Bidang 2', unit: 'TIK', status: 'Nonaktif' },
                { id: 4, nip: '19880002005011003', nama: 'Prof. Dr. Ir. Wadir Dua', unit: 'TIK', status: 'Aktif' },
            ],

            rolesList: [
                {
                    id: 1,
                    code: 'Administrator Sistem',
                    name: 'Admin',
                    description: 'Pengelola sistem, manajemen pengguna, dll',
                    type: 'Sistem Terkunci',
                    users: '1 Pengguna',
                    permissions: [
                        'Membuat usulan permohonan pengadaan',
                        'Melihat usulan milik sendiri',
                        'Melihat seluruh usulan permohonan unit',
                        'Mengajukan revisi usulan yang ditolak',
                        'Memecah usulan berdasarkan kode MAK'
                    ]
                }
            ],

            get filteredUsers() {
                return this.usersList.filter(u => {
                    const matchSearch = u.nama.toLowerCase().includes(this.searchUser.toLowerCase()) || 
                                        u.nip.includes(this.searchUser);
                    const matchUnit = this.filterUnit === 'Semua' || u.unit === this.filterUnit;
                    const matchStatus = this.filterStatus === 'Semua' || u.status === this.filterStatus;
                    return matchSearch && matchUnit && matchStatus;
                });
            },

            toggleUserStatus(user) {
                user.status = user.status === 'Aktif' ? 'Nonaktif' : 'Aktif';
            },

            addRole() {
                if (!this.newRole.code || !this.newRole.name) return;

                this.rolesList.push({
                    id: Date.now(),
                    code: this.newRole.code,
                    name: this.newRole.name,
                    description: this.newRole.description || 'Tidak ada deskripsi',
                    type: 'Sistem',
                    users: '0 Pengguna',
                    permissions: [
                        'Membuat usulan permohonan pengadaan',
                        'Melihat seluruh paket pengadaan'
                    ]
                });

                this.newRole = { code: '', name: '', description: '' };
                alert('Role baru berhasil ditambahkan!');
            },

            deleteRole(id) {
                if (confirm('Apakah Anda yakin ingin menghapus role ini?')) {
                    this.rolesList = this.rolesList.filter(r => r.id !== id);
                }
            }
        };
    }
</script>
@endsection