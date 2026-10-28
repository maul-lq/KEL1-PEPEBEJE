@extends('layouts.app')

@section('title', 'Notifikasi dan Reminder - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<div x-data="notifikasiManager()" x-init="init()" class="w-full space-y-5 font-sans">

    <!-- 1. HEADER HALAMAN & TOMBOL UJI COBA -->
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-0.5">
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-[#213153]">Notifikasi dan Reminder</h1>
            <p class="text-sm font-normal text-slate-500">Pantau perkembangan pengajuan, permintaan revisi, dan tenggat pengadaan Anda.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="resetData()"
                    class="h-9 px-3 rounded-xl border border-slate-200 bg-white text-xs font-medium text-slate-600 hover:bg-slate-50 transition-all">
                Reset Data
            </button>
            <button type="button" @click="tambahUjiCoba()"
                    class="h-9 px-4 rounded-xl bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 transition-all inline-flex items-center gap-1.5 shadow-sm">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Uji Coba Notifikasi Masuk
            </button>
        </div>
    </div>

    <!-- 2. PENCARIAN & FILTER -->
    <div class="w-full rounded-2xl bg-white p-3.5 shadow-sm border border-slate-100">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

            <div class="md:col-span-5 space-y-1">
                <label for="search-input" class="text-[11px] font-medium text-slate-600">Pencarian</label>
                <div class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" id="search-input" x-model="q" @input="page = 1"
                           placeholder="Cari nomor surat atau judul pengajuan"
                           class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                </div>
            </div>

            <div class="md:col-span-3 space-y-1">
                <label for="type-filter" class="text-[11px] font-medium text-slate-600">Jenis Notifikasi</label>
                <select id="type-filter" x-model="jenis" @change="page = 1"
                        class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <option value="">Semua Jenis</option>
                    <option value="pengajuan">Pengajuan</option>
                    <option value="revisi">Permintaan Revisi</option>
                    <option value="pengadaan">Pengadaan</option>
                    <option value="laporan">Laporan Anggaran</option>
                </select>
            </div>

            <div class="md:col-span-3 space-y-1">
                <label for="period-filter" class="text-[11px] font-medium text-slate-600">Periode</label>
                <select id="period-filter" x-model="periode" @change="page = 1"
                        class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs text-slate-700 focus:border-[#0969da] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0969da]/20 transition-all">
                    <option value="">Semua Periode</option>
                    <option value="today">Hari Ini</option>
                    <option value="week">Minggu Ini</option>
                    <option value="month">Bulan Ini</option>
                </select>
            </div>

            <div class="md:col-span-1 flex justify-end">
                <button type="button" @click="resetFilter()" title="Reset Filter"
                        class="h-9 w-9 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-all">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- 3. DAFTAR NOTIFIKASI (tanpa overflow-hidden agar dropdown tidak terpotong) -->
    <div class="w-full rounded-2xl border border-slate-200/80 bg-white shadow-sm">

        <!-- TAB + TOOLBAR -->
        <div class="px-4 pt-3 pb-0 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/40 rounded-t-2xl">
            <div class="flex items-center gap-1 overflow-x-auto text-xs font-medium">
                <button type="button" @click="onlyStar = !onlyStar; page = 1" title="Tampilkan favorit saja"
                        :class="onlyStar ? 'text-amber-400' : 'text-slate-400 hover:text-amber-400'"
                        class="p-2 transition-colors">
                    <svg class="size-4" :fill="onlyStar ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </button>

                <template x-for="t in tabs" :key="t.key">
                    <button type="button" @click="activeTab = t.key; page = 1"
                            :class="activeTab === t.key ? 'text-[#0969da] border-b-2 border-b-[#0969da] bg-white font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            class="px-3.5 py-2.5 rounded-t-xl flex items-center gap-1.5 transition-all whitespace-nowrap">
                        <span x-text="t.label"></span>
                        <span :class="t.badge" class="rounded-full px-2 py-0.5 text-[10px] font-semibold" x-text="count(t.key)"></span>
                    </button>
                </template>
            </div>

            <div class="flex items-center gap-4 py-2 text-xs">
                <button type="button" @click="tandaiSemua()" class="inline-flex items-center gap-1.5 font-medium text-[#0969da] hover:underline">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Tandai semua dibaca
                </button>

                <button type="button" @click="refresh()" class="inline-flex items-center gap-1.5 font-medium text-[#0969da] hover:underline">
                    <svg class="size-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh
                </button>

                <!-- Pagination -->
                <div class="flex items-center gap-1 pl-2 border-l border-slate-200">
                    <button type="button" @click="page > 1 && page--" :disabled="page <= 1"
                            class="size-7 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 text-xs disabled:opacity-40 disabled:cursor-not-allowed">&lt;</button>
                    <template x-for="n in totalPages" :key="n">
                        <button type="button" @click="page = n"
                                :class="page === n ? 'bg-[#0969da] text-white shadow-sm border-transparent font-medium' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                class="size-7 flex items-center justify-center rounded-lg border text-xs" x-text="n"></button>
                    </template>
                    <button type="button" @click="page < totalPages && page++" :disabled="page >= totalPages"
                            class="size-7 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 text-xs disabled:opacity-40 disabled:cursor-not-allowed">&gt;</button>
                </div>
            </div>
        </div>

        <!-- LIST -->
        <div class="divide-y divide-slate-100">
            <template x-for="n in paged" :key="n.id">
                <div class="p-3.5 sm:p-4 flex items-center justify-between gap-4 transition-all hover:bg-[#e6f0ff]/60"
                     :class="n.read ? 'bg-white' : 'bg-[#f0f6ff]/80'">

                    <div class="flex items-center gap-3.5 min-w-0 flex-1">
                        <button type="button" @click="toggleStar(n)" title="Favorit"
                                :class="n.starred ? 'text-amber-400' : 'text-slate-300 hover:text-amber-400'"
                                class="transition-colors shrink-0">
                            <svg class="size-4" :fill="n.starred ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        </button>

                        <div :class="style(n).icon" class="size-9 rounded-full flex items-center justify-center text-white shrink-0 shadow-sm">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="style(n).path"/>
                            </svg>
                        </div>

                        <span class="size-2 rounded-full shrink-0" :class="n.read ? 'bg-transparent' : 'bg-[#0969da]'"></span>

                        <div class="space-y-0.5 min-w-0 flex-1">
                            <h3 class="text-xs sm:text-sm text-[#213153] leading-snug"
                                :class="n.read ? 'font-medium' : 'font-semibold'" x-text="n.judul"></h3>
                            <p class="text-[11px] text-slate-500 truncate" x-text="n.nomor + ' • ' + n.perihal"></p>
                            <p class="text-[11px] text-slate-400 line-clamp-1" x-text="n.pesan"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="hidden sm:inline-flex rounded-md px-2.5 py-0.5 text-[10px] font-semibold"
                              :class="style(n).badge" x-text="style(n).label"></span>
                        <span class="text-[10px] text-slate-400 font-normal" x-text="formatWaktu(n.waktu)"></span>

                        <a :href="getLinkTarget(n)" @click="n.read = true; simpan()"
                           class="inline-flex items-center gap-1 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-[#0969da] hover:bg-blue-50 transition-colors shadow-sm">
                            <span x-text="n.aksi"></span>
                            <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>

                        <!-- Menu titik tiga -->
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 transition-colors">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition
                                 class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1 shadow-lg text-xs">
                                <button type="button" @click="n.read = !n.read; simpan(); open = false"
                                        class="w-full px-3 py-2 text-left text-slate-600 hover:bg-slate-50"
                                        x-text="n.read ? 'Tandai belum dibaca' : 'Tandai sudah dibaca'"></button>
                                <button type="button" @click="toggleStar(n); open = false"
                                        class="w-full px-3 py-2 text-left text-slate-600 hover:bg-slate-50"
                                        x-text="n.starred ? 'Hapus dari favorit' : 'Tambah ke favorit'"></button>
                                <button type="button" @click="hapus(n); open = false"
                                        class="w-full px-3 py-2 text-left text-rose-600 hover:bg-rose-50">Hapus notifikasi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="filtered.length === 0" x-cloak class="p-10 text-center">
                <svg class="size-9 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <p class="text-xs text-slate-500">Tidak ada notifikasi yang sesuai.</p>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 right-6 z-50 rounded-xl bg-[#213153] px-4 py-2.5 text-xs font-medium text-white shadow-lg" x-text="toast"></div>
</div>

<script>
    function notifikasiManager() {
        const KEY = 'simpbj_notifikasi_v2';
        const sekarang = () => new Date().toISOString();

        // Route aman
        const URL_PERMOHONAN = @json(route('permohonan.index'));
        const URL_LAPORAN = @json(Route::has('laporan.rekapitulasi') ? route('laporan.rekapitulasi') : route('permohonan.index'));

        const seed = () => [
            { id: 1, tipe: 'setuju',   jenis: 'pengajuan', judul: 'Pengajuan disetujui',
              nomor: '032/PL3/TE/2026', perihal: 'Komponen praktikum digital',
              pesan: 'Pengajuan Anda telah disetujui dan dilanjutkan ke tahap berikutnya.',
              aksi: 'Lihat Detail', waktu: '2026-01-29T08:00:00+07:00', read: false, starred: false },
            { id: 2, tipe: 'pengingat', jenis: 'revisi', judul: 'Batas pengiriman revisi 1 hari lagi!',
              nomor: '032/PL3/TE/2026', perihal: 'Komponen praktikum digital',
              pesan: 'Pengingat: revisi yang diminta Bidang 2 belum dikirim. Jadwal tindak lanjut: 30 Januari 2026; lengkapi rincian jumlah komponen.',
              aksi: 'Lihat Pengajuan', waktu: '2026-01-29T08:00:00+07:00', read: false, starred: false },
            { id: 3, tipe: 'tolak',     jenis: 'revisi', judul: 'Pengajuan ditolak',
              nomor: '032/PL3/TE/2026', perihal: 'Komponen praktikum digital',
              pesan: 'Pengajuan ditolak. Mohon lengkapi revisi sesuai catatan Bidang 2.',
              aksi: 'Lengkapi Revisi', waktu: '2026-01-29T08:00:00+07:00', read: false, starred: false },
            { id: 4, tipe: 'lanjut',    jenis: 'pengadaan', judul: 'Bagian Keuangan menyetujui, dilanjutkan ke Bagian PPK',
              nomor: '032/PL3/TE/2026', perihal: 'Komponen praktikum digital',
              pesan: 'Dokumen pengajuan sedang diproses oleh bagian selanjutnya.',
              aksi: 'Lihat Detail', waktu: '2026-01-29T08:00:00+07:00', read: true, starred: false },
            { id: 5, tipe: 'laporan',   jenis: 'laporan', judul: 'Pembaruan Laporan Rekapitulasi Anggaran',
              nomor: 'REKAP/2026/01', perihal: 'Rekapitulasi Anggaran DIPA PNJ',
              pesan: 'Laporan penyerapan anggaran triwulan telah diperbarui. Silakan unduh PDF laporan rekapitulasi.',
              aksi: 'Cek Laporan', waktu: '2026-01-28T14:30:00+07:00', read: false, starred: true },
        ];

        const templates = [
            { tipe: 'setuju', jenis: 'pengajuan', judul: 'Pengajuan disetujui', aksi: 'Lihat Detail',
              pesan: 'Pengajuan Anda telah disetujui oleh Bidang terkait.' },
            { tipe: 'pengingat', jenis: 'revisi', judul: 'Batas pengiriman revisi 1 hari lagi!', aksi: 'Lihat Pengajuan',
              pesan: 'Pengingat: revisi yang diminta belum dikirim. Segera lengkapi rincian dokumen.' },
            { tipe: 'tolak', jenis: 'revisi', judul: 'Pengajuan ditolak', aksi: 'Lengkapi Revisi',
              pesan: 'Pengajuan ditolak. Mohon perbaiki dan kirim ulang sesuai catatan reviewer.' },
            { tipe: 'lanjut', jenis: 'pengadaan', judul: 'Bagian menyetujui, dilanjutkan ke bagian berikutnya', aksi: 'Lihat Detail',
              pesan: 'Dokumen pengadaan diteruskan ke bagian selanjutnya untuk ditinjau.' },
            { tipe: 'laporan', jenis: 'laporan', judul: 'Pembaruan Laporan Rekapitulasi Anggaran', aksi: 'Cek Laporan',
              pesan: 'Sistem telah memperbarui laporan rekapitulasi anggaran pengadaan terbaru.' },
        ];
        const perihalList = ['Komponen praktikum digital', 'Server dan GPU Workstation AI Lab', 'Perangkat praktikum jaringan komputer', 'ATK Semester Genap'];

        const styles = {
            setuju:    { label: 'Informasi',      icon: 'bg-emerald-500 shadow-emerald-500/30', badge: 'bg-emerald-100 text-emerald-700',
                         path: 'M5 13l4 4L19 7' },
            pengingat: { label: 'Pengingat',      icon: 'bg-amber-400 shadow-amber-400/30',     badge: 'bg-amber-100 text-amber-700',
                         path: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
            tolak:     { label: 'Perlu Tindakan', icon: 'bg-rose-600 shadow-rose-600/30',        badge: 'bg-rose-100 text-rose-700',
                         path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
            lanjut:    { label: 'Informasi',      icon: 'bg-[#0969da] shadow-blue-500/30',      badge: 'bg-blue-100 text-[#0969da]',
                         path: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            laporan:   { label: 'Laporan',        icon: 'bg-indigo-600 shadow-indigo-500/30',    badge: 'bg-indigo-100 text-indigo-700',
                         path: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        };

        return {
            items: [],
            q: '', jenis: '', periode: '',
            activeTab: 'semua', onlyStar: false,
            page: 1, perPage: 5,
            loading: false, toast: '', toastTimer: null,

            tabs: [
                { key: 'semua',     label: 'Semua',          badge: 'bg-blue-100 text-[#0969da]' },
                { key: 'unread',    label: 'Belum Dibaca',   badge: 'bg-slate-200/70 text-slate-700' },
                { key: 'tindakan',  label: 'Perlu Tindakan', badge: 'bg-rose-100 text-rose-600' },
                { key: 'pengingat', label: 'Pengingat',      badge: 'bg-amber-100 text-amber-700' },
            ],

            init() {
                try {
                    const saved = localStorage.getItem(KEY);
                    this.items = saved ? JSON.parse(saved) : seed();
                } catch (e) { this.items = seed(); }
                this.simpan();
            },
            simpan() { try { localStorage.setItem(KEY, JSON.stringify(this.items)); } catch (e) {} },
            notify(msg) {
                this.toast = msg; clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => this.toast = '', 2200);
            },
            style(n) { return styles[n.tipe] || styles.lanjut; },

            getLinkTarget(n) {
                return (n.jenis === 'laporan' || n.tipe === 'laporan') ? URL_LAPORAN : URL_PERMOHONAN;
            },

            matchTab(n, key) {
                if (key === 'unread')    return !n.read;
                if (key === 'tindakan')  return n.tipe === 'tolak';
                if (key === 'pengingat') return n.tipe === 'pengingat';
                return true;
            },
            count(key) { return this.items.filter(n => this.matchTab(n, key)).length; },

            inPeriode(n) {
                if (!this.periode) return true;
                const d = new Date(n.waktu), now = new Date();
                if (this.periode === 'today') return d.toDateString() === now.toDateString();
                if (this.periode === 'week')  return (now - d) <= 7 * 864e5 && d <= now;
                if (this.periode === 'month') return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
                return true;
            },

            get filtered() {
                const kw = this.q.trim().toLowerCase();
                return this.items
                    .filter(n => this.matchTab(n, this.activeTab))
                    .filter(n => !this.onlyStar || n.starred)
                    .filter(n => !this.jenis || n.jenis === this.jenis)
                    .filter(n => this.inPeriode(n))
                    .filter(n => !kw || (n.nomor + ' ' + n.perihal + ' ' + n.judul).toLowerCase().includes(kw))
                    .sort((a, b) => new Date(b.waktu) - new Date(a.waktu));
            },
            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
            get paged() {
                if (this.page > this.totalPages) this.page = this.totalPages;
                const s = (this.page - 1) * this.perPage;
                return this.filtered.slice(s, s + this.perPage);
            },

            toggleStar(n) { n.starred = !n.starred; this.simpan(); },
            hapus(n) {
                this.items = this.items.filter(x => x.id !== n.id);
                this.simpan(); this.notify('Notifikasi dihapus');
            },
            tandaiSemua() {
                this.items.forEach(n => n.read = true);
                this.simpan(); this.notify('Semua notifikasi ditandai dibaca');
            },
            refresh() {
                this.loading = true;
                setTimeout(() => { this.init(); this.loading = false; this.notify('Data diperbarui'); }, 600);
            },
            resetFilter() {
                this.q = ''; this.jenis = ''; this.periode = '';
                this.activeTab = 'semua'; this.onlyStar = false; this.page = 1;
            },
            resetData() {
                this.items = seed(); this.resetFilter();
                this.simpan(); this.notify('Data dikembalikan ke awal');
            },

            // =========================================================================
            // FUNGSI TAMBAH UJI COBA YANG SUDAH TERHUBUNG KE BACKEND & LONCENG HEADER
            // =========================================================================
            async tambahUjiCoba() {
                this.loading = true;
                const t = templates[Math.floor(Math.random() * templates.length)];
                const isLaporan = t.tipe === 'laporan';
                const nomor = isLaporan
                    ? 'REKAP/2026/' + String(Math.floor(Math.random() * 90) + 10)
                    : String(Math.floor(Math.random() * 900) + 100) + '/PL3/TE/2026';
                const perihal = isLaporan
                    ? 'Rekapitulasi Anggaran DIPA PNJ'
                    : perihalList[Math.floor(Math.random() * perihalList.length)];

                // 1. Tambah ke list lokal
                this.items.unshift({
                    id: Date.now(), ...t, nomor, perihal,
                    waktu: sekarang(), read: false, starred: false,
                });
                this.page = 1;
                this.simpan();

                // 2. Kirim POST request ke Backend Laravel
                try {
                    await fetch("{{ route('notifikasi.tes-kirim') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            judul: t.judul,
                            pesan: t.pesan,
                            tipe: t.tipe
                        })
                    });

                    // 3. KIRIM SIGNAL EVENT REAL-TIME KE ICON LONCENG HEADER
                    window.dispatchEvent(new CustomEvent('notification-updated'));

                    // 4. Pemicu Notifikasi OS Desktop jika aktif
                    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        new Notification(t.judul, {
                            body: t.pesan + ' (' + nomor + ')',
                            icon: '/favicon.ico'
                        });
                    }

                    this.notify('Notifikasi uji coba berhasil masuk!');
                } catch (e) {
                    // Tetap picu update lonceng jika offline/mode simulasi
                    window.dispatchEvent(new CustomEvent('notification-updated'));
                    this.notify('Notifikasi uji coba ditambahkan (mode lokal)!');
                } finally {
                    this.loading = false;
                }
            },

            formatWaktu(iso) {
                const d = new Date(iso);
                const tgl = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                const jam = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace(':', '.');
                return tgl + ' • ' + jam + ' WIB';
            },
        };
    }
</script>
@endsection