/**
 * Modul Halaman Profil Pengguna SIM-PBJ (profile.js)
 *
 * Mengatur modal profil, unggah foto, sakelar pengingat email persisten ke server,
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
        saving: false,
        updateUrl: '',
        feedback: '',
        feedbackError: false,

        init() {
            this.enabled = this.$el.dataset.enabled !== '0';
            this.updateUrl = this.$el.dataset.url || '';
        },

        async toggle() {
            if (this.saving || !this.updateUrl) {
                return;
            }

            const previousState = this.enabled;
            const nextState = !previousState;
            this.enabled = nextState;
            this.saving = true;
            this.feedback = '';
            this.feedbackError = false;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const response = await fetch(this.updateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ enabled: nextState }),
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'Gagal menyimpan preferensi pengingat email.');
                }

                this.enabled = Boolean(payload.enabled);
                this.feedback = payload.message || 'Preferensi pengingat email diperbarui.';
            } catch (error) {
                this.enabled = previousState;
                this.feedbackError = true;
                this.feedback = error.message || 'Gagal memperbarui pengaturan pengingat email.';
            } finally {
                this.saving = false;
            }
        },
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
