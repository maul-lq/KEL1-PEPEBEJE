/**
 * Modul Manajemen Admin & Dashboard Peran SIM-PBJ (admin.js)
 *
 * Mengelola modal tambah/ubah/hapus pengguna, modal peran & izin,
 * persetujuan registrasi di dashboard admin, dan tab dashboard Unit Pengadaan.
 */

export function adminUsers() {
    return {
        addUserModal: false,
        editUserModal: false,
        deleteUserModal: false,
        editUser: {},
        deleteUser: {},

        openEdit(user) {
            this.editUser = Object.assign({}, user, {
                id_role: user.id_role != null ? String(user.id_role) : '',
            });
            this.editUserModal = true;
        },

        openDelete(user) {
            this.deleteUser = Object.assign({}, user);
            this.deleteUserModal = true;
        },
    };
}

export function adminRolesPage() {
    return {
        addRoleModal: false,
    };
}

export function adminRoleRow() {
    return {
        editModal: false,
    };
}

export function adminDashboardApproval() {
    return {
        approveModalOpen: false,
        rejectModalOpen: false,
        selectedUser: { nama: '', nip: '', email: '', unit: '', accUrl: '', tolakUrl: '' },

        openApprove(userData) {
            this.selectedUser = userData;
            this.approveModalOpen = true;
        },

        openReject(userData) {
            this.selectedUser = userData;
            this.rejectModalOpen = true;
        },
    };
}

export function ppbjDashboard() {
    return {
        rightTab: 'paket',
    };
}
