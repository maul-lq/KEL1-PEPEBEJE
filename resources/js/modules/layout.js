/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini susun untuk mengontrol tata letak bilah sisi responsif (`appShell`)
 *             serta bilah lonceng notifikasi dan menu profil pengguna pada header (`headerNotificationBar`).
 */

/**
 * Mengelola kondisi buka/tutup bilah navigasi samping pada tampilan desktop dan seluler.
 *
 * Tujuan: membedakan perilaku tombol toggle berdasarkan breakpoint layar (`768px`) agar
 * sidebar di layar seluler menutup drawer sepenuhnya sedangkan desktop hanya menciut/melebar.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state dan method toggle sidebar untuk Alpine.js.
 * Contoh pemanggilan: `<body x-data="appShell()">`
 */
export function appShell() {
    return {
        sidebarExpanded: false,
        mobileSidebarOpen: false,

        /**
         * Mengubah status bilah sisi menyesuaikan ukuran layar seluler atau desktop.
         *
         * Tujuan: menutup overlay drawer pada viewport `< 768px` dan menukar ekspansi lebar
         * bilah sisi pada viewport desktop.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="toggleSidebar()"`
         */
        toggleSidebar() {
            if (window.innerWidth < 768) {
                this.mobileSidebarOpen = false;
            } else {
                this.sidebarExpanded = !this.sidebarExpanded;
            }
        },
    };
}

/**
 * Mengelola menu dropdown lonceng notifikasi, tab belum dibaca, dan dropdown profil header.
 *
 * Tujuan: memastikan menu notifikasi dan menu profil saling menutup satu sama lain serta
 * tersinkron secara real-time setiap ada pembaruan dari event `simpbj-notifications-synced`.
 * Parameter: Tidak menerima argumen (data awal dibaca dari atribut `data-unread-count` & `data-initial-items`).
 * Nilai kembali: Objek state, getter, dan handler AJAX notifikasi header untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="headerNotificationBar()">`
 */
export function headerNotificationBar() {
    return {
        openNotif: false,
        openProfile: false,
        notifTab: 'semua',
        unreadCount: 0,
        recentItems: [],

        /**
         * Menginisialisasi hitungan notifikasi belum dibaca, daftar awal, dan listener sinkronisasi.
         *
         * Tujuan: mengurai JSON notifikasi awal dengan `try/catch` defensif agar gagal parsing
         * tidak memutus eksekusi skrip halaman, lalu mendengarkan broadcast pembaruan polling.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: Dijalankan otomatis oleh Alpine saat elemen header dimuat.
         */
        init() {
            this.unreadCount = parseInt(this.$el.dataset.unreadCount || '0', 10) || 0;
            try {
                const raw = this.$el.dataset.initialItems || '[]';
                const parsed = JSON.parse(raw);
                this.recentItems = Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                // fallback ke array kosong bila payload SSR terpotong atau tidak valid JSON.
                this.recentItems = [];
            }

            window.addEventListener('simpbj-notifications-synced', (event) => {
                this.applyPayload(event.detail);
            });
        },

        /**
         * Menyaring daftar notifikasi header berdasarkan tab aktif (`semua` atau `belum_dibaca`).
         *
         * Tujuan: menyediakan properti komputasi agar daftar dropdown bereaksi seketika saat
         * pengguna berpindah tab tanpa memanggil API ulang.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Array<Object>}` Daftar notifikasi terfilter untuk dirender.
         * Contoh pemanggilan: `<template x-for="item in displayedItems">`
         */
        get displayedItems() {
            if (this.notifTab === 'belum_dibaca') {
                return this.recentItems.filter((item) => !item.is_read);
            }
            return this.recentItems;
        },

        /**
         * Menerapkan payload sinkronisasi notifikasi ke badge angka dan daftar item header.
         *
         * Tujuan: memvalidasi status sukses pada payload sebelum memperbarui state reaktif.
         * Parameter: `{Object} payload` - Respons JSON dari endpoint polling `/api/notifikasi-terbaru`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.applyPayload(data);`
         */
        applyPayload(payload) {
            if (!payload || payload.status !== 'success') {
                return;
            }
            if (typeof payload.unread_count === 'number') {
                this.unreadCount = payload.unread_count;
            }
            if (Array.isArray(payload.recent)) {
                this.recentItems = payload.recent;
            }
        },

        /**
         * Membuka atau menutup panel notifikasi sambil otomatis menutup menu profil.
         *
         * Tujuan: mencegah dua dropdown header terbuka tumpang tindih di sudut kanan atas.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="toggleNotif()"`
         */
        toggleNotif() {
            this.openNotif = !this.openNotif;
            if (this.openNotif) {
                this.openProfile = false;
            }
        },

        /**
         * Membuka atau menutup menu dropdown profil sambil menutup panel notifikasi.
         *
         * Tujuan: menjaga fokus antarmuka hanya satu menu melayang aktif pada satu waktu.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="toggleProfile()"`
         */
        toggleProfile() {
            this.openProfile = !this.openProfile;
            if (this.openProfile) {
                this.openNotif = false;
            }
        },

        /**
         * Menutup paksa menu notifikasi dan menu profil sekaligus.
         *
         * Tujuan: mengaitkan aksi ini ke klik luar (`@click.outside`) maupun tombol Escape.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@keydown.escape.window="closeMenus()"`
         */
        closeMenus() {
            this.openNotif = false;
            this.openProfile = false;
        },

        /**
         * Mengambil daftar notifikasi terbaru secara asinkron dari server menggunakan kredensial sesi.
         *
         * Tujuan: menyertakan `credentials: 'same-origin'` agar cookie sesi Laravel tetap ikut
         * dan menelan error jaringan sementara supaya polling latar tidak memicu pesan kesalahan UI.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{Promise<void>}` Promise asinkron pembaruan payload header.
         * Contoh pemanggilan: `await this.fetchTerbaru();`
         */
        async fetchTerbaru() {
            try {
                const res = await fetch('/api/notifikasi-terbaru', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                this.applyPayload(data);
            } catch (err) {
                // abaikan error jaringan sesaat pada polling latar belakang agar tidak mengganggu pengalaman pengguna.
            }
        },
    };
}
