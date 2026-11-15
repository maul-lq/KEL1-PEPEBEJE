/**
 * Modul Tata Letak Utama Aplikasi SIM-PBJ (layout.js)
 *
 * Mengatur perilaku bilah sisi (sidebar) responsif serta menu lonceng
 * notifikasi dan profil pengguna pada bilah atas.
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

        init() {
            this.unreadCount = parseInt(this.$el.dataset.unreadCount || '0', 10) || 0;
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
                const res = await fetch('/api/notifikasi-terbaru');
                const data = await res.json();
                if (data.status === 'success') {
                    this.unreadCount = data.unread_count || (this.unreadCount + 1);
                } else {
                    this.unreadCount++;
                }
            } catch (err) {
                this.unreadCount++;
            }
        },
    };
}
