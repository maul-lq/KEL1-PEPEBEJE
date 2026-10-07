<?php

namespace Database\Seeders;

use App\Models\AnggaranMAK;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('tes_password123');

        $demoUsers = [
            [
                'nip' => 'tes_197508152002121001',
                'email' => 'tes_pengaju@kampus.ac.id',
                'nama' => 'tes_User Pengaju Jurusan',
                'jabatan' => 'Ketua Jurusan TIK',
                'unit_kerja' => 'Jurusan TIK',
                'role' => User::ROLE_USER_PENGAJU,
                'status_aktif' => true,
                'no_hp' => '081234567890',
                'no_telp_kantor' => '0217270001',
            ],
            [
                'nip' => 'tes_197001011995011001',
                'email' => 'tes_wadir2@kampus.ac.id',
                'nama' => 'tes_Wakil Direktur II',
                'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
                'unit_kerja' => 'Pimpinan Politeknik',
                'role' => User::ROLE_WADIR_2,
                'status_aktif' => true,
                'no_hp' => '081298765432',
                'no_telp_kantor' => '0217270002',
            ],
            [
                'nip' => 'tes_198503032007031004',
                'email' => 'tes_perencanaan@kampus.ac.id',
                'nama' => 'tes_Tim Perencanaan',
                'jabatan' => 'Koordinator Subbagian Perencanaan & Anggaran',
                'unit_kerja' => 'Bagian Perencanaan',
                'role' => User::ROLE_PERENCANAAN,
                'status_aktif' => true,
                'no_hp' => '081377889900',
                'no_telp_kantor' => '0217270003',
            ],
            [
                'nip' => 'tes_198001012005011002',
                'email' => 'tes_ppbj@kampus.ac.id',
                'nama' => 'tes_Koordinator PPBJ',
                'jabatan' => 'Koordinator Pengadaan Barang dan Jasa',
                'unit_kerja' => 'Unit Layanan Pengadaan',
                'role' => User::ROLE_PPBJ,
                'status_aktif' => true,
                'no_hp' => '081512345678',
                'no_telp_kantor' => '0217270004',
            ],
            [
                'nip' => 'tes_198102022006021003',
                'email' => 'tes_pp@kampus.ac.id',
                'nama' => 'tes_Pejabat Pengadaan',
                'jabatan' => 'Pejabat Pengadaan (PP)',
                'unit_kerja' => 'ULP / PP',
                'role' => User::ROLE_PP,
                'status_aktif' => true,
                'no_hp' => '081623456789',
                'no_telp_kantor' => '0217270005',
            ],
            [
                'nip' => 'tes_198203032007031004',
                'email' => 'tes_ppk@kampus.ac.id',
                'nama' => 'tes_Pejabat Pembuat Komitmen',
                'jabatan' => 'Pejabat Pembuat Komitmen (PPK)',
                'unit_kerja' => 'PPK & Pengadaan',
                'role' => User::ROLE_PPK,
                'status_aktif' => true,
                'no_hp' => '081734567891',
                'no_telp_kantor' => '0217270006',
            ],
            [
                'nip' => 'tes_198405052009051006',
                'email' => 'tes_perlengkapan@kampus.ac.id',
                'nama' => 'tes_Staff Perlengkapan',
                'jabatan' => 'Staff Bagian Perlengkapan dan BMN',
                'unit_kerja' => 'Bagian Umum & Perlengkapan',
                'role' => User::ROLE_PERLENGKAPAN,
                'status_aktif' => true,
                'no_hp' => '081845678912',
                'no_telp_kantor' => '0217270007',
            ],
            [
                'nip' => 'tes_198704042008041005',
                'email' => 'tes_keuangan@kampus.ac.id',
                'nama' => 'tes_Staff Keuangan',
                'jabatan' => 'Staff Bagian Keuangan & Verifikasi',
                'unit_kerja' => 'Bagian Keuangan',
                'role' => User::ROLE_KEUANGAN,
                'status_aktif' => true,
                'no_hp' => '081956789012',
                'no_telp_kantor' => '0217270008',
            ],
            [
                'nip' => 'tes_199001012015011001',
                'email' => 'tes_admin@kampus.ac.id',
                'nama' => 'tes_Staff Bidang 2 Admin',
                'jabatan' => 'Staff Administrasi Bidang 2',
                'unit_kerja' => 'Sekretariat Bidang 2',
                'role' => User::ROLE_STAFF_BIDANG_2,
                'status_aktif' => true,
                'no_hp' => '081122334455',
                'no_telp_kantor' => '0217270009',
            ],
        ];

        $createdUsers = [];
        foreach ($demoUsers as $userData) {
            $createdUsers[$userData['role']] = User::updateOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, ['password' => $defaultPassword])
            );
        }

        // Demo Vendor
        Vendor::updateOrCreate(
            ['npwp' => 'tes_01.234.567.8-012.000'],
            [
                'nib' => 'tes_9120001234567',
                'nama_perusahaan' => 'tes_PT Dharma Mitra Jaya',
                'alamat' => 'Jl. Kampus Raya No. 1, Jakarta Timur',
                'file_legalitas' => 'tes_legalitas_mitra.pdf',
                'is_active' => true,
                'nama_pic' => 'tes_Budi Santoso',
                'telepon_pic' => '081298765432',
                'email_pic' => 'tes_budi@dharmamitra.co.id',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1230009876543',
                'atas_nama' => 'PT Dharma Mitra Jaya',
            ]
        );

        // Demo Anggaran MAK
        if (isset($createdUsers[User::ROLE_PERENCANAAN])) {
            AnggaranMAK::updateOrCreate(
                ['kode_mak' => 'tes_MAK-2026-TIK-001'],
                [
                    'uraian_mak' => 'tes_Pengadaan Komputer dan Lab IoT TIK TA 2026',
                    'pagu_anggaran' => 250000000.00,
                    'sumber_dana' => 'RM',
                    'tahun_anggaran' => 2026,
                    'is_locked' => false,
                    'id_user' => $createdUsers[User::ROLE_PERENCANAAN]->id_user,
                ]
            );
        }
    }
}
