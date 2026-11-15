/**
 * Modul Tata Letak Utama Aplikasi SIM-PBJ (layout.js)
 *
 * Mengatur perilaku bilah sisi (sidebar) responsif serta menu lonceng
 * notifikasi dan profil pengguna pada bilah atas secara real-time.
 */

export function appShell() {
    return {
        sidebarExpanded: false,
        mobileSidebarOpen: false,

        toggleSidebar() {
            if (window.innerWidth < 768) {
                this.mobileSidebarOpen = false;
            } else {
                this.sidebarExpanded = !this.sidebarExpanded;
            }
        },
    };
}

export function headerNotificationBar() {
    return {
        openNotif: false,
        openProfile: false,
        notifTab: 'semua',
        unreadCount: 0,
        recentItems: [],

        init() {
            this.unreadCount = parseInt(this.$el.dataset.unreadCount || '0', 10) || 0;
            try {
                const raw = this.$el.dataset.initialItems || '[]';
                const parsed = JSON.parse(raw);
                this.recentItems = Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                this.recentItems = [];
            }

            window.addEventListener('simpbj-notifications-synced', (event) => {
                this.applyPayload(event.detail);
            });
        },

        get displayedItems() {
            if (this.notifTab === 'belum_dibaca') {
                return this.recentItems.filter((item) => !item.is_read);
            }
            return this.recentItems;
        },

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

        toggleNotif() {
            this.openNotif = !this.openNotif;
            if (this.openNotif) {
                this.openProfile = false;
            }
        },

        toggleProfile() {
            this.openProfile = !this.openProfile;
            if (this.openProfile) {
                this.openNotif = false;
            }
        },

        closeMenus() {
            this.openNotif = false;
            this.openProfile = false;
        },

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
                // Abaikan gangguan jaringan sementara pada polling latar belakang
            }
        },
    };
}
