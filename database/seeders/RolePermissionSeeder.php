<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Seeder ini buat untuk menyemai seluruh peran sistem resmi, katalog permission
 *             granular lintas modul SIM-PBJ, pemetaan default role-permission, serta pengaitan
 *             `id_role` bawaan bagi pengguna yang belum memiliki peran.
 */

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Menyemai master peran sistem (`roles`), izin fitur (`permissions`), dan relasi matriks RBAC.
 *
 * Tujuan: merancang seeder ini supaya otorisasi SIM-PBJ bersifat data-driven di database,
 * idempotent saat dijalankan ulang, dan langsung membersihkan permission usang yang sudah dihapus.
 *
 * Contoh pemanggilan:
 * `php artisan db:seed --class=RolePermissionSeeder`
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Mengeksekusi pembenihan role sistem, daftar permission granular, dan sinkronisasi pivot RBAC.
     *
     * Tujuan: menyinkronkan izin standar setiap peran sistem dan menambatkan pengguna yatim
     * tanpa `id_role` ke peran pengusul dasar (`user`) agar akun tetap dapat dievaluasi RBAC.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$this->call(RolePermissionSeeder::class);`
     */
    public function run(): void
    {
        // =========================================================================
        // 1. Pembenihan 9 Role Resmi Sistem Institusi
        // =========================================================================
        $systemRoles = [
            [
                'nama_role' => 'admin',
                'label' => 'Administrator Sistem',
                'deskripsi' => 'Pengelola sistem, manajemen pengguna, approval registrasi, dan pengaturan hak akses role.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'user',
                'label' => 'Pengusul / Jurusan / Unit Kerja',
                'deskripsi' => 'Pengusul permohonan pengadaan barang/jasa dan pemantau riwayat usulan.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'staff_bidang_2',
                'label' => 'Staff Bidang II (Umum & Keuangan)',
                'deskripsi' => 'Pemantau daftar dan dokumen usulan permohonan masuk (hanya baca tanpa wewenang verifikasi atau disposisi).',
                'is_system' => true,
            ],
            [
                'nama_role' => 'wadir_2',
                'label' => 'Wakil Direktur II',
                'deskripsi' => 'Pemegang hak mutlak persetujuan (ACC/Tolak) usulan dan persetujuan Memo Bayar Level 2.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'perencanaan',
                'label' => 'Bagian Perencanaan',
                'deskripsi' => 'Verifikator MAK DIPA, alokasi pagu, penahanan anggaran, pemecahan usulan MAK, dan penguncian MAK.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'ppbj',
                'label' => 'Unit Pengadaan Barang & Jasa (PPBJ)',
                'deskripsi' => 'Pengelola paket, pemecah jenis pengadaan, auto-routing HPS, penerbit Kontrak/SPK, dan pengaju tagihan/SPP.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'pp',
                'label' => 'Pejabat Pengadaan (PP)',
                'deskripsi' => 'Penelaah teknis dan negosiator harga rekanan untuk paket bernilai > 50 Juta s.d < 200 Juta (1 akun aktif).',
                'is_system' => true,
            ],
            [
                'nama_role' => 'ppk',
                'label' => 'Pejabat Pembuat Komitmen (PPK)',
                'deskripsi' => 'Verifikator persetujuan awal usulan, peninjau dan pemberi persetujuan dokumen paket, serta penyetuju memo pembayaran sesuai penugasan.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'perlengkapan',
                'label' => 'Unit Perlengkapan & BMN',
                'deskripsi' => 'Pemeriksa fisik barang, pengunggah foto dokumentasi, penentu jenis belanja, dan penerbit BAST.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'keuangan',
                'label' => 'Bagian Keuangan & Bendahara',
                'deskripsi' => 'Verifikator berkas tagihan SPP dan pelaksana pencairan dana SP2D.',
                'is_system' => true,
            ],
        ];

        $roleModels = [];
        foreach ($systemRoles as $roleData) {
            $roleModels[$roleData['nama_role']] = Role::updateOrCreate(
                ['nama_role' => $roleData['nama_role']],
                $roleData
            );
        }

        // =========================================================================
        // 2. Pembenihan Master Permissions Granular
        // =========================================================================
        $permissions = [
            // Modul Permohonan
            ['nama_permission' => 'permohonan.create', 'label' => 'Mengajukan Usulan Permohonan Pengadaan', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.view_own', 'label' => 'Melihat Daftar Usulan Milik Sendiri', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.view_all', 'label' => 'Melihat Seluruh Usulan Permohonan Unit', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.revisi', 'label' => 'Mengajukan Perbaikan Usulan yang Ditolak', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.delete', 'label' => 'Membatalkan & Menghapus Usulan Permohonan', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.split_mak', 'label' => 'Memecah Usulan Berdasarkan Pos Anggaran', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.split_jenis', 'label' => 'Memecah Usulan Berdasarkan Jenis Pengadaan', 'kategori_modul' => 'Permohonan'],

            // Modul Verifikasi
            ['nama_permission' => 'verifikasi.bidang_2_view', 'label' => 'Melihat Antrean & Berkas Usulan Masuk (Hanya Baca)', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.wadir_2_view', 'label' => 'Melihat Antrean Verifikasi Usulan (Wadir 2)', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.wadir_2_decide', 'label' => 'Menyetujui, Menolak, atau Meminta Revisi Usulan (Wadir 2)', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.ppk_approve', 'label' => 'Menyetujui atau Menolak Usulan Awal (PPK)', 'kategori_modul' => 'Verifikasi'],

            // Modul Anggaran
            ['nama_permission' => 'anggaran.mak_view', 'label' => 'Melihat Daftar Pos Anggaran & Sisa Pagu', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_manage', 'label' => 'Menambah & Mengelola Daftar Pos Anggaran', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_alokasi', 'label' => 'Menentukan Pos Anggaran, Menahan Usulan & Memilih PPK', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_lock', 'label' => 'Mengunci & Membuka Kunci Pos Anggaran', 'kategori_modul' => 'Anggaran'],

            // Modul Pengadaan & SPK
            ['nama_permission' => 'paket.create', 'label' => 'Membuat Paket Pengadaan & Menentukan Harga Perkiraan', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.view_all', 'label' => 'Melihat Seluruh Paket Pengadaan', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.view_assigned', 'label' => 'Melihat Paket Pengadaan yang Ditugaskan', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.reassign_ppk', 'label' => 'Mengalihkan Penugasan PPK pada Paket', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'reviu.pp', 'label' => 'Meninjau Teknis & Negosiasi Harga Penyedia (PP)', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'pp.monitoring_aktivitas', 'label' => 'Memantau Aktivitas & Hasil Negosiasi Pejabat Pengadaan', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'reviu.ppk', 'label' => 'Meninjau & Menyetujui Dokumen Paket Pengadaan (PPK)', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'spk.create', 'label' => 'Mengunggah Dokumen Kontrak, SPK, atau Surat Pesanan', 'kategori_modul' => 'Pengadaan'],

            // Modul Penerimaan
            ['nama_permission' => 'penerimaan.view', 'label' => 'Melihat Daftar Penerimaan Barang', 'kategori_modul' => 'Penerimaan'],
            ['nama_permission' => 'penerimaan.bast_create', 'label' => 'Memeriksa Fisik Barang, Unggah Foto & Jenis Belanja', 'kategori_modul' => 'Penerimaan'],
            ['nama_permission' => 'penerimaan.bast_sign', 'label' => 'Menerbitkan Berita Acara Serah Terima (BAST)', 'kategori_modul' => 'Penerimaan'],

            // Modul Pembayaran & Pencairan
            ['nama_permission' => 'pembayaran.pengajuan', 'label' => 'Mengajukan Berkas Tagihan Pembayaran', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.memo_wadir2', 'label' => 'Menyetujui atau Menolak Memo Pembayaran (Wadir 2)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.memo_ppk', 'label' => 'Menyetujui atau Menolak Memo Pembayaran (PPK)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.spp_terbitkan', 'label' => 'Menerbitkan Surat Perintah Pembayaran (SPP)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.pencairan_sp2d', 'label' => 'Mencairkan Dana Pembayaran (SP2D)', 'kategori_modul' => 'Pembayaran'],

            // Modul Monitoring & Laporan
            ['nama_permission' => 'monitoring.view', 'label' => 'Memantau Progres Tahapan Pengadaan', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'laporan.rekapitulasi', 'label' => 'Melihat & Mengunduh Laporan Rekapitulasi Anggaran', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'dokumen.unduh_pdf', 'label' => 'Mengunduh Dokumen Resmi (PDF)', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'audit_log.view', 'label' => 'Melihat Riwayat Jejak Audit Sistem', 'kategori_modul' => 'Laporan'],

            // Modul Administrasi Sistem
            ['nama_permission' => 'admin.approval_registrasi', 'label' => 'Menyetujui atau Menolak Pendaftaran Akun Baru', 'kategori_modul' => 'Administrasi'],
            ['nama_permission' => 'admin.user_manage', 'label' => 'Mengelola Data Pengguna & Penetapan Peran', 'kategori_modul' => 'Administrasi'],
            ['nama_permission' => 'admin.role_manage', 'label' => 'Mengelola Daftar Peran & Hak Akses Fitur', 'kategori_modul' => 'Administrasi'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['nama_permission']] = Permission::updateOrCreate(
                ['nama_permission' => $perm['nama_permission']],
                $perm
            );
        }

        // pindahkan pemegang izin lama lihat_pp ke pp.monitoring_aktivitas sebelum izin duplikat dihapus agar peran kustom tidak kehilangan akses.
        $oldLihatPp = Permission::where('nama_permission', 'lihat_pp')->first();
        if ($oldLihatPp && isset($permissionModels['pp.monitoring_aktivitas'])) {
            $monitorPermId = $permissionModels['pp.monitoring_aktivitas']->id;
            foreach ($oldLihatPp->roles as $holderRole) {
                $holderRole->permissions()->syncWithoutDetaching([$monitorPermId]);
            }
        }

        // =========================================================================
        // 3. Pemetaan Default Permissions ke 9 Role Sistem
        // =========================================================================
        $rolePermissionMap = [
            'admin' => [
                'admin.approval_registrasi',
                'admin.user_manage',
                'admin.role_manage',
                'permohonan.view_all',
                'permohonan.delete',
                'monitoring.view',
                'audit_log.view',
                'dokumen.unduh_pdf',
            ],
            'user' => [
                'permohonan.create',
                'permohonan.view_own',
                'permohonan.revisi',
                'monitoring.view',
                'dokumen.unduh_pdf',
            ],
            'staff_bidang_2' => [
                'permohonan.view_all',
                'verifikasi.bidang_2_view',
                'monitoring.view',
                'dokumen.unduh_pdf',
            ],
            'wadir_2' => [
                'permohonan.view_all',
                'verifikasi.wadir_2_view',
                'verifikasi.wadir_2_decide',
                'pembayaran.memo_wadir2',
                'monitoring.view',
                'laporan.rekapitulasi',
                'audit_log.view',
                'dokumen.unduh_pdf',
            ],
            'perencanaan' => [
                'permohonan.view_all',
                'permohonan.split_mak',
                'anggaran.mak_view',
                'anggaran.mak_manage',
                'anggaran.mak_alokasi',
                'anggaran.mak_lock',
                'paket.reassign_ppk',
                'monitoring.view',
                'laporan.rekapitulasi',
                'dokumen.unduh_pdf',
            ],
            'ppbj' => [
                'permohonan.view_all',
                'permohonan.split_jenis',
                'paket.create',
                'paket.view_all',
                'paket.reassign_ppk',
                'pp.monitoring_aktivitas',
                'spk.create',
                'pembayaran.pengajuan',
                'pembayaran.spp_terbitkan',
                'monitoring.view',
                'laporan.rekapitulasi',
                'dokumen.unduh_pdf',
            ],
            'pp' => [
                'paket.view_assigned',
                'reviu.pp',
                'monitoring.view',
                'dokumen.unduh_pdf',
            ],
            'ppk' => [
                'permohonan.view_all',
                'verifikasi.ppk_approve',
                'paket.view_assigned',
                'reviu.ppk',
                'pembayaran.memo_ppk',
                'monitoring.view',
                'dokumen.unduh_pdf',
            ],
            'perlengkapan' => [
                'penerimaan.view',
                'penerimaan.bast_create',
                'penerimaan.bast_sign',
                'monitoring.view',
                'dokumen.unduh_pdf',
            ],
            'keuangan' => [
                'pembayaran.pencairan_sp2d',
                'monitoring.view',
                'laporan.rekapitulasi',
                'dokumen.unduh_pdf',
            ],
        ];

        foreach ($rolePermissionMap as $roleSlug => $permSlugs) {
            if (isset($roleModels[$roleSlug])) {
                $ids = [];
                foreach ($permSlugs as $slug) {
                    if (isset($permissionModels[$slug])) {
                        $ids[] = $permissionModels[$slug]->id;
                    }
                }
                $roleModels[$roleSlug]->permissions()->sync($ids);
            }
        }

        // salin izin verifikasi.ppk_approve ke peran kustom mana pun yang sebelumnya memegang reviu.ppk agar tidak putus saat kedua izin dipisah.
        if (isset($permissionModels['reviu.ppk'], $permissionModels['verifikasi.ppk_approve'])) {
            $verifPpkPermId = $permissionModels['verifikasi.ppk_approve']->id;
            foreach ($permissionModels['reviu.ppk']->roles()->where('is_system', false)->get() as $customHolder) {
                $customHolder->permissions()->syncWithoutDetaching([$verifPpkPermId]);
            }
        }

        // bersihkan slug permission usang maupun duplikat agar daftar hak akses di Admin tetap bersih.
        Permission::whereIn('nama_permission', ['reminder.manage', 'lihat_pp'])->each(function (Permission $obsoletePerm): void {
            $obsoletePerm->roles()->detach();
            $obsoletePerm->delete();
        });

        // tautkan pengguna tanpa id_role ke peran user default agar pengecekan permission tidak null.
        if (isset($roleModels[User::ROLE_USER])) {
            User::whereNull('id_role')->update([
                'id_role' => $roleModels[User::ROLE_USER]->id,
            ]);
        }
    }
}
