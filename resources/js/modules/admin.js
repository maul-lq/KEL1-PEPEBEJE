/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini susun untuk mengelola komponen Alpine halaman administrasi pengguna,
 *             manajemen peran/hak akses, persetujuan registrasi di dasbor admin, dan tab PPBJ.
 */

/**
 * Mengelola state modal tambah, ubah, dan hapus pengguna pada halaman manajemen user admin.
 *
 * Tujuan: memusatkan kontrol pembukaan modal dan penyalinan data user terpilih agar proses
 * ubah peran atau penonaktifan tidak mengubah objek baris tabel sebelum form dikirim.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state dan handler Alpine.js untuk halaman manajemen user.
 * Contoh pemanggilan: `<div x-data="adminUsers()">`
 */
export function adminUsers() {
    return {
        addUserModal: false,
        editUserModal: false,
        deleteUserModal: false,
        editUser: {},
        deleteUser: {},

        /**
         * Membuka modal ubah user dengan salinan data dan normalisasi `id_role` ke string.
         *
         * Tujuan: menormalkan nilai `id_role` menjadi string karena elemen `<select>` HTML
         * mencocokkan nilai terpilih dengan perbandingan string di Alpine `x-model`.
         * Parameter: `{Object} user` - Objek metadata pengguna dari baris tabel.
         * Nilai kembali: Tidak mengembalikan nilai (memperbarui state modal ubah).
         * Contoh pemanggilan: `@click="openEdit(userRow)"`
         */
        openEdit(user) {
            // kloning objek, ubah id_role ke string untuk <select> x-model, dan normalkan status_aktif menjadi boolean untuk checkbox x-model.
            this.editUser = Object.assign({}, user, {
                id_role: user.id_role != null ? String(user.id_role) : '',
                status_aktif: Boolean(user.status_aktif),
            });
            this.editUserModal = true;
        },

        /**
         * Membuka modal konfirmasi penghapusan pengguna dengan salinan objek terisolasi.
         *
         * Tujuan: menyalin metadata target agar dialog konfirmasi menampilkan identitas akun
         * yang tepat sebelum mengirim permintaan DELETE.
         * Parameter: `{Object} user` - Objek metadata pengguna target.
         * Nilai kembali: Tidak mengembalikan nilai (membuka dialog konfirmasi hapus).
         * Contoh pemanggilan: `@click="openDelete(userRow)"`
         */
        openDelete(user) {
            this.deleteUser = Object.assign({}, user);
            this.deleteUserModal = true;
        },
    };
}

/**
 * Mengelola state pembukaan modal pembuatan peran baru pada halaman admin roles.
 *
 * Tujuan: menjaga agar kontrol modal pembuatan role terisolasi di level halaman.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi flag `addRoleModal`.
 * Contoh pemanggilan: `<div x-data="adminRolesPage()">`
 */
export function adminRolesPage() {
    return {
        addRoleModal: false,
    };
}

/**
 * Mengelola state pembukaan modal ubah hak akses untuk setiap baris peran RBAC.
 *
 * Tujuan: menyediakan state lokal per baris tabel supaya pembukaan dialog edit satu role
 * tidak memengaruhi baris peran lainnya.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi flag `editModal`.
 * Contoh pemanggilan: `<tr x-data="adminRoleRow()">`
 */
export function adminRoleRow() {
    return {
        editModal: false,
    };
}

/**
 * Mengelola modal persetujuan (ACC) dan penolakan registrasi akun di dasbor administrator.
 *
 * Tujuan: menyimpan detail pendaftar serta URL aksi endpoint agar admin dapat memproses
 * verifikasi pendaftaran langsung dari widget antrean dasbor.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state dan method pembuka modal ACC/Tolak untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="adminDashboardApproval()">`
 */
export function adminDashboardApproval() {
    return {
        approveModalOpen: false,
        rejectModalOpen: false,
        selectedUser: { nama: '', nip: '', email: '', unit: '', accUrl: '', tolakUrl: '' },

        /**
         * Membuka modal persetujuan pendaftaran dan memuat identitas akun terpilih.
         *
         * Tujuan: mengisi `selectedUser` dan menyalakan `approveModalOpen` secara atomik
         * sebelum dialog ACC ditampilkan.
         * Parameter: `{Object} userData` - Data ringkas pendaftar beserta URL endpoint ACC/Tolak.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openApprove(item)"`
         */
        openApprove(userData) {
            this.selectedUser = userData;
            this.approveModalOpen = true;
        },

        /**
         * Membuka modal penolakan pendaftaran dan menyiapkan alasan penolakan untuk admin.
         *
         * Tujuan: mengisi `selectedUser` dan membuka dialog input alasan penolakan.
         * Parameter: `{Object} userData` - Data ringkas pendaftar beserta URL endpoint penolakan.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openReject(item)"`
         */
        openReject(userData) {
            this.selectedUser = userData;
            this.rejectModalOpen = true;
        },
    };
}

/**
 * Mengelola perpindahan tab panel kanan pada dasbor Koordinator PPBJ.
 *
 * Tujuan: menetapkan tab awal `'paket'` agar antrean paket siap proses tampil lebih dulu.
 * Parameter: Tidak menerima argumen.
 * Nilai kembali: Objek state Alpine.js berisi properti `rightTab`.
 * Contoh pemanggilan: `<div x-data="ppbjDashboard()">`
 */
export function ppbjDashboard() {
    return {
        rightTab: 'paket',
    };
}
