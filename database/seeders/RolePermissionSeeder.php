<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder Role & Permission (RolePermissionSeeder)
 *
 * Membenihkan:
 * 1. 9 Role Resmi Institusi (is_system = true).
 * 2. Master Permissions Fitur Granular untuk seluruh siklus pengadaan (FR 1-20 & NFR 1-12).
 * 3. Matriks Relasi Role-Permission Bawaan.
 * 4. Pengaitan id_role pada akun-akun pengguna eksisting.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Menjalankan pembenihan data role dan permissions.
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
                'deskripsi' => 'Pemeriksa kelengkapan berkas administrasi awal dan penerus telaah ke Wadir 2 (read-only/tanpa wewenang ACC).',
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
                'deskripsi' => 'Verifikator MAK DIPA, alokasi pagu, penahanan anggaran, pemecahan usulan MAK (FR 6.3), dan penguncian MAK.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'ppbj',
                'label' => 'Unit Pengadaan Barang & Jasa (PPBJ)',
                'deskripsi' => 'Pengelola paket, pemecah jenis pengadaan (FR 7.2), auto-routing HPS, penerbit Kontrak/SPK, dan pengaju tagihan/SPP.',
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
                'deskripsi' => 'Peninjau dokumen, pengunci MAK, dan pembeli barang/sewa jasa komitmen terisolasi per akun (4 akun mandiri).',
                'is_system' => true,
            ],
            [
                'nama_role' => 'perlengkapan',
                'label' => 'Unit Perlengkapan & BMN',
                'deskripsi' => 'Pemeriksa fisik barang, pengunggah multi-foto, penentu Belanja Barang vs Modal, dan penerbit BAST HMAC.',
                'is_system' => true,
            ],
            [
                'nama_role' => 'keuangan',
                'label' => 'Bagian Keuangan & Bendahara',
                'deskripsi' => 'Verifikator berkas tagihan SPP dan eksekutor transfer pencairan dana SP2D anti-duplicate bayar.',
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
            ['nama_permission' => 'permohonan.create', 'label' => 'Membuat Usulan Permohonan Pengadaan', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.view_own', 'label' => 'Melihat Usulan Milik Sendiri & Catatan Penolakan', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.view_all', 'label' => 'Melihat Seluruh Usulan Permohonan Unit', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.revisi', 'label' => 'Mengajukan Revisi Usulan yang Ditolak', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.split_mak', 'label' => 'Memecah Usulan Berdasarkan Kode MAK (FR 6.3)', 'kategori_modul' => 'Permohonan'],
            ['nama_permission' => 'permohonan.split_jenis', 'label' => 'Memecah Berkas Berdasarkan Jenis Pengadaan (FR 7.2)', 'kategori_modul' => 'Permohonan'],

            // Modul Verifikasi & Anggaran
            ['nama_permission' => 'verifikasi.bidang_2_view', 'label' => 'Melihat Antrean Telaah Berkas Masuk (Staf B2)', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.bidang_2_teruskan', 'label' => 'Meneruskan Hasil Telaah Berkas ke Wadir 2', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.wadir_2_view', 'label' => 'Melihat Antrean Verifikasi Eksekutif Wadir 2', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'verifikasi.wadir_2_decide', 'label' => 'Memberikan Keputusan ACC / Tolak Usulan', 'kategori_modul' => 'Verifikasi'],
            ['nama_permission' => 'anggaran.mak_view', 'label' => 'Melihat Daftar Rekening MAK dan Pagu DIPA', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_manage', 'label' => 'Mengelola Master MAK DIPA', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_alokasi', 'label' => 'Mengalokasikan Pagu MAK atau Menetapkan Hold', 'kategori_modul' => 'Anggaran'],
            ['nama_permission' => 'anggaran.mak_lock', 'label' => 'Mengunci Pagu MAK DIPA Permanen', 'kategori_modul' => 'Anggaran'],

            // Modul Pengadaan & SPK
            ['nama_permission' => 'paket.create', 'label' => 'Registrasi Paket Pengadaan & Penetapan HPS', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.view_all', 'label' => 'Melihat Seluruh Paket Pengadaan', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.view_assigned', 'label' => 'Melihat Paket yang Ditugaskan Khusus (Terisolasi)', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'paket.reassign_ppk', 'label' => 'Mengalihkan Penugasan Paket ke PPK Lain', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'reviu.pp', 'label' => 'Verifikasi Teknis & Negosiasi Harga Rekanan (PP)', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'reviu.ppk', 'label' => 'Tinjauan Dokumen, Kunci MAK & Beli/Sewa (PPK)', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'spk.create', 'label' => 'Menerbitkan Surat Kontrak/SPK & Tembusan Otomatis', 'kategori_modul' => 'Pengadaan'],
            ['nama_permission' => 'reminder.manage', 'label' => 'Mengonfigurasi Jadwal Alarm Pengingat Pengiriman', 'kategori_modul' => 'Pengadaan'],

            // Modul Penerimaan (BMN)
            ['nama_permission' => 'penerimaan.view', 'label' => 'Melihat Daftar Penerimaan Fisik Barang', 'kategori_modul' => 'Penerimaan'],
            ['nama_permission' => 'penerimaan.bast_create', 'label' => 'Inspeksi Fisik, Upload Multi-Foto & Kategori Belanja', 'kategori_modul' => 'Penerimaan'],
            ['nama_permission' => 'penerimaan.bast_sign', 'label' => 'Penerbitan BAST Resmi Bertoken HMAC SHA-256', 'kategori_modul' => 'Penerimaan'],

            // Modul Pembayaran & Pencairan
            ['nama_permission' => 'pembayaran.pengajuan', 'label' => 'Mengajukan Berkas Tagihan Termin / Pelunasan', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.memo_ppk', 'label' => 'Persetujuan Disposisi Memo Bayar Level 1 (PPK)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.memo_wadir2', 'label' => 'Persetujuan Disposisi Memo Bayar Level 2 (Wadir 2)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.spp_terbitkan', 'label' => 'Menerbitkan Surat Perintah Pembayaran (SPP)', 'kategori_modul' => 'Pembayaran'],
            ['nama_permission' => 'pembayaran.pencairan_sp2d', 'label' => 'Eksekusi Transfer SP2D Anti-Duplicate Bayar', 'kategori_modul' => 'Pembayaran'],

            // Modul Monitoring & Laporan
            ['nama_permission' => 'monitoring.view', 'label' => 'Melacak 8 Tahapan Siklus Pengadaan Real-Time', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'laporan.rekapitulasi', 'label' => 'Mengakses Laporan Rekapitulasi Realisasi Anggaran', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'dokumen.unduh_pdf', 'label' => 'Mengunduh Dokumen Resmi Format PDF (< 3 Detik)', 'kategori_modul' => 'Laporan'],
            ['nama_permission' => 'audit_log.view', 'label' => 'Memantau Riwayat Jejak Audit Mutasi Transaksi', 'kategori_modul' => 'Laporan'],

            // Modul Administrasi Sistem
            ['nama_permission' => 'admin.approval_registrasi', 'label' => 'Menyetujui (ACC) atau Menolak Pendaftaran Pengguna', 'kategori_modul' => 'Administrasi'],
            ['nama_permission' => 'admin.user_manage', 'label' => 'Mengelola Data Pengguna & Penugasan Role', 'kategori_modul' => 'Administrasi'],
            ['nama_permission' => 'admin.role_manage', 'label' => 'Manajemen CRUD Role & Matriks Hak Akses Fitur', 'kategori_modul' => 'Administrasi'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['nama_permission']] = Permission::updateOrCreate(
                ['nama_permission' => $perm['nama_permission']],
                $perm
            );
        }

        // =========================================================================
        // 3. Pemetaan Default Permissions ke 9 Role Sistem
        // =========================================================================
        $rolePermissionMap = [
            'admin' => array_keys($permissionModels), // Admin memiliki seluruh permissions
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
                'verifikasi.bidang_2_teruskan',
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
                'spk.create',
                'reminder.manage',
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

        // =========================================================================
        // 4. Sinkronisasi id_role pada Pengguna Eksisting
        // =========================================================================
        foreach ($roleModels as $slug => $role) {
            User::where('role', $slug)->whereNull('id_role')->update([
                'id_role' => $role->id,
                'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            ]);
        }
    }
}
