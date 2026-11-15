/**
 * Modul Halaman Profil Pengguna SIM-PBJ (profile.js)
 *
 * Mengatur modal profil, unggah foto, sakelar pengingat Gmail,
 * serta reset formulir otomatis saat modal kata sandi/hapus akun ditutup.
 */

export function profilePage() {
    return {
        editModalOpen: false,
        passwordModalOpen: false,
        deleteAccountModalOpen: false,

        init() {
            this.passwordModalOpen = this.$el.dataset.passwordModalOpen === '1';
            this.deleteAccountModalOpen = this.$el.dataset.deleteModalOpen === '1';
        },
    };
}

export function profilePhotoUpload() {
    return {
        photoName: '',
    };
}

export function profileReminderToggle() {
    return {
        enabled: true,
    };
}

export function profilePasswordForm() {
    return {
        showOld: false,
        showNew: false,
        showConfirm: false,

        init() {
            this.$watch('passwordModalOpen', (open) => {
                if (!open) {
                    this.$refs.passwordForm?.reset();
                    this.showOld = false;
                    this.showNew = false;
                    this.showConfirm = false;
                }
            });
        },
    };
}

export function profileDeleteForm() {
    return {
        showPass: false,

        init() {
            this.$watch('deleteAccountModalOpen', (open) => {
                if (!open) {
                    this.$refs.deleteForm?.reset();
                    this.showPass = false;
                }
            });
        },
    };
}
