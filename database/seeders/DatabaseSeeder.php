<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Seeder utama ini susun untuk membenihkan data master awal SIM-PBJ meliputi
 *             unit kerja/jurusan, role dan permission melalui RolePermissionSeeder, serta akun
 *             operasional 9 peran termasuk 4 akun PPK mandiri tanpa data transaksi dummy.
 */

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Menjalankan pembenihan data master inti Politeknik Negeri Jakarta untuk sistem SIM-PBJ.
 *
 * Tujuan: memastikan instalasi baru maupun `migrate:fresh --seed` hanya mengisi entitas master
 * resmi (unit, role, permission, akun operasional) agar data transaksi tetap bersih dan siap uji.
 *
 * Contoh pemanggilan:
 * `php artisan db:seed`
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Membenihkan master unit kerja, matriks RBAC, dan akun pengguna awal semua peran BPMN.
     *
     * Tujuan: menggunakan `updateOrCreate` pada unit dan pengguna supaya eksekusi ulang seeder
     * bersifat idempotent dan tidak menimbulkan duplikasi kunci unik di PostgreSQL.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$this->call(DatabaseSeeder::class);`
     */
    public function run(): void
    {
        // gunakan kata sandi seragam terenkripsi bcrypt untuk seluruh akun seed awal pengujian internal.
        $defaultPassword = Hash::make('password123');

        // =========================================================================
        // 1. Pembenihan Data Master Unit Kerja & Jurusan Sesuai BPMN
        // =========================================================================
        $units = [
            // Jurusan Akademik (Pengusul / User)
            ['kode_unit' => 'TIK', 'nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TM', 'nama_unit' => 'Jurusan Teknik Mesin', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TS', 'nama_unit' => 'Jurusan Teknik Sipil', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TE', 'nama_unit' => 'Jurusan Teknik Elektro', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TGP', 'nama_unit' => 'Jurusan Teknik Grafika Penerbitan', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'AK', 'nama_unit' => 'Jurusan Akuntansi', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'AN', 'nama_unit' => 'Jurusan Administrasi Niaga', 'jenis_unit' => 'jurusan'],

            // Unit & Bagian Operasional Pengadaan (Sesuai Swimlane BPMN)
            ['kode_unit' => 'REN', 'nama_unit' => 'Subbagian Perencanaan & Anggaran', 'jenis_unit' => 'bagian'],
            ['kode_unit' => 'PPBJ', 'nama_unit' => 'Unit Pengadaan Barang dan Jasa', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'PPK', 'nama_unit' => 'Pejabat Pembuat Komitmen', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'LKP', 'nama_unit' => 'Unit Perlengkapan & BMN', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'KEU', 'nama_unit' => 'Unit Keuangan', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'PST', 'nama_unit' => 'Pusat Komputer & TI (Administrator)', 'jenis_unit' => 'unit'],

            // Bidang Pimpinan
            ['kode_unit' => 'B1', 'nama_unit' => 'Bidang I (Akademik)', 'jenis_unit' => 'bidang'],
            ['kode_unit' => 'B2', 'nama_unit' => 'Bidang II (Umum dan Keuangan)', 'jenis_unit' => 'bidang'],
            ['kode_unit' => 'B3', 'nama_unit' => 'Bidang III (Kemahasiswaan)', 'jenis_unit' => 'bidang'],
        ];

        foreach ($units as $u) {
            Unit::updateOrCreate(['kode_unit' => $u['kode_unit']], $u);
        }

        // =========================================================================
        // 2. Pembenihan Master Role dan Permissions Dinamis
        // =========================================================================
        $this->call(RolePermissionSeeder::class);

        $rolesMap = Role::pluck('id', 'nama_role');

        // =========================================================================
        // 3. Pembenihan Akun Pengguna Awal (9 Peran BPMN, 4 Akun PPK Terisolasi, 1 Admin)
        // =========================================================================
        $users = [
            // Peran 0: Administrator Sistem
            [
                'nip' => '198000002005011000',
                'username' => 'admin',
                'email' => 'admin@pnj.ac.id',
                'nama' => 'Administrator Sistem SIM-PBJ',
                'password' => $defaultPassword,
                'jabatan' => 'Administrator Sistem Informasi',
                'id_role' => $rolesMap['admin'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081100000001',
                'kode_unit' => 'PST',
            ],

            // Peran 1: Pengusul (User / Jurusan) - Swimlane BPMN: Jurusan/Unit/Bidang
            [
                'nip' => '198501012010011001',
                'username' => 'user_pengaju',
                'email' => 'pengaju@pnj.ac.id',
                'nama' => 'Dr. Ir. Maulana Pengaju, M.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Ketua Jurusan TIK',
                'id_role' => $rolesMap['user'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081234567890',
                'kode_unit' => 'TIK',
            ],

            // Peran 2: Staf Bidang II - Swimlane BPMN: Bidang 2
            [
                'nip' => '198602022011011002',
                'username' => 'staff_b2',
                'email' => 'staff_b2@pnj.ac.id',
                'nama' => 'Ahmad Staff Bidang 2, S.E.',
                'password' => $defaultPassword,
                'jabatan' => 'Staff Bidang Umum & Keuangan',
                'id_role' => $rolesMap['staff_bidang_2'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081298765431',
                'kode_unit' => 'B2',
            ],

            // Peran 3: Wakil Direktur II - Swimlane BPMN: Bidang 2
            [
                'nip' => '197001011995011001',
                'username' => 'wadir2',
                'email' => 'wadir2@pnj.ac.id',
                'nama' => 'Prof. Dr. Ir. Wadir Dua, M.Sc.',
                'password' => $defaultPassword,
                'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
                'id_role' => $rolesMap['wadir_2'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081298765432',
                'kode_unit' => 'B2',
            ],

            // Peran 4: Perencanaan (Verifikator MAK) - Swimlane BPMN: Perencanaan
            [
                'nip' => '198503032007031004',
                'username' => 'perencanaan',
                'email' => 'perencanaan@pnj.ac.id',
                'nama' => 'Budi Perencanaan, S.Kom.',
                'password' => $defaultPassword,
                'jabatan' => 'Koordinator Subbagian Perencanaan & Anggaran',
                'id_role' => $rolesMap['perencanaan'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081377889900',
                'kode_unit' => 'REN',
            ],

            // Peran 5: PPBJ (Unit Pengadaan) - Swimlane BPMN: PPBJ
            [
                'nip' => '198001012005011002',
                'username' => 'ppbj',
                'email' => 'ppbj@pnj.ac.id',
                'nama' => 'Citra Koordinator PPBJ, M.M.',
                'password' => $defaultPassword,
                'jabatan' => 'Koordinator Pengadaan Barang dan Jasa',
                'id_role' => $rolesMap['ppbj'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081512345678',
                'kode_unit' => 'PPBJ',
            ],

            // Peran 6: Pejabat Pengadaan (PP) - Swimlane BPMN: PP (1 Akun Aktif)
            [
                'nip' => '198102022006021003',
                'username' => 'pp_user',
                'email' => 'pp@pnj.ac.id',
                'nama' => 'Dedi Pejabat Pengadaan, S.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pengadaan (PP)',
                'id_role' => $rolesMap['pp'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081623456789',
                'kode_unit' => 'PPBJ',
            ],

            // Peran 7 (Akun 1/4): PPK I (Sarana) - Swimlane BPMN: PPK
            [
                'nip' => '198203032007031001',
                'username' => 'ppk_1',
                'email' => 'ppk1@pnj.ac.id',
                'nama' => 'Eko PPK Bidang Sarana, M.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen I (Sarana)',
                'id_role' => $rolesMap['ppk'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081734567891',
                'kode_unit' => 'PPK',
            ],

            // Peran 7 (Akun 2/4): PPK II (Laboratorium) - Swimlane BPMN: PPK
            [
                'nip' => '198203032007031002',
                'username' => 'ppk_2',
                'email' => 'ppk2@pnj.ac.id',
                'nama' => 'Fajar PPK Bidang Laboratorium, M.Kom.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen II (Laboratorium)',
                'id_role' => $rolesMap['ppk'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081734567892',
                'kode_unit' => 'PPK',
            ],

            // Peran 7 (Akun 3/4): PPK III (Konstruksi) - Swimlane BPMN: PPK
            [
                'nip' => '198203032007031003',
                'username' => 'ppk_3',
                'email' => 'ppk3@pnj.ac.id',
                'nama' => 'Gita PPK Bidang Konstruksi, S.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen III (Konstruksi)',
                'id_role' => $rolesMap['ppk'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081734567893',
                'kode_unit' => 'PPK',
            ],

            // Peran 7 (Akun 4/4): PPK IV (Jasa) - Swimlane BPMN: PPK
            [
                'nip' => '198203032007031004',
                'username' => 'ppk_4',
                'email' => 'ppk4@pnj.ac.id',
                'nama' => 'Hendra PPK Bidang Jasa Lainnya, S.E.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen IV (Jasa)',
                'id_role' => $rolesMap['ppk'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081734567894',
                'kode_unit' => 'PPK',
            ],

            // Peran 8: Perlengkapan (Pemeriksa fisik & penerbit BAST) - Swimlane BPMN: Perlengkapan
            [
                'nip' => '198405052009051006',
                'username' => 'perlengkapan',
                'email' => 'perlengkapan@pnj.ac.id',
                'nama' => 'Indra Staff Perlengkapan, A.Md.',
                'password' => $defaultPassword,
                'jabatan' => 'Staff Bagian Perlengkapan dan BMN',
                'id_role' => $rolesMap['perlengkapan'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081845678912',
                'kode_unit' => 'LKP',
            ],

            // Peran 9: Keuangan (Verifikator SPP & eksekutor SP2D) - Swimlane BPMN: Keuangan
            [
                'nip' => '198704042008041005',
                'username' => 'keuangan',
                'email' => 'keuangan@pnj.ac.id',
                'nama' => 'Joko Bendahara Keuangan, S.Ak.',
                'password' => $defaultPassword,
                'jabatan' => 'Bendahara Pengeluaran & Pencairan',
                'id_role' => $rolesMap['keuangan'] ?? null,
                'status_aktif' => true,
                'no_hp' => '081956789023',
                'kode_unit' => 'KEU',
            ],
        ];

        foreach ($users as $u) {
            $u['status_registrasi'] = User::STATUS_REGISTRASI_DISETUJUI;
            $u['tanggal_approval'] = now();
            User::updateOrCreate(['username' => $u['username']], $u);
        }
    }
}
