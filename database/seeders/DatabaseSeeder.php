<?php

namespace Database\Seeders;

use App\Models\AlokasiMAK;
use App\Models\AnggaranMAK;
use App\Models\PaketPengadaan;
use App\Models\PermohonanPengadaan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorFileLegalitas;
use App\Models\VerifikasiParalel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Basis Data Utama (Database Seeder)
 *
 * Bertanggung jawab membenihkan data awal (seed) demo dan master institusi untuk pengujian
 * komprehensif sistem SIM-PBJ.
 * Menghasilkan:
 * 1. Master Unit Kerja (Jurusan, Bidang II, Unit Perlengkapan, Keuangan).
 * 2. 9 Peran Pengguna Resmi (Pemohon, Staf Bidang 2, Wadir 2, Perencanaan, PPBJ, 1 Akun PP aktif,
 *    serta 4 Akun PPK mandiri untuk validasi isolasi berkas).
 * 3. Mata Anggaran Kegiatan (MAK) dengan pagu dan sumber dana (BOPTN, BLU, RM).
 * 4. Master Penyedia / Vendor terverifikasi beserta lampiran berkas legalitas.
 * 5. Sampel usulan Permohonan Pengadaan, Verifikasi Paralel, dan Alokasi MAK.
 * 6. Sampel Paket Pengadaan bernilai di atas Rp 200 juta (jalur routing PPK) berstatus SPK terbit.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan pembenihan data awal sistem secara terstruktur.
     */
    public function run(): void
    {
        // Standar enkripsi hash kata sandi untuk seluruh akun seeder demo
        $defaultPassword = Hash::make('password123');

        // =========================================================================
        // 1. Pembenihan Data Master Unit Kerja & Jurusan (Units)
        // =========================================================================
        $units = [
            ['kode_unit' => 'TIK', 'nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TM', 'nama_unit' => 'Jurusan Teknik Mesin', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TS', 'nama_unit' => 'Jurusan Teknik Sipil', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TE', 'nama_unit' => 'Jurusan Teknik Elektro', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'TGP', 'nama_unit' => 'Jurusan Teknik Grafika Penerbitan', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'AK', 'nama_unit' => 'Jurusan Akuntansi', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'AN', 'nama_unit' => 'Jurusan Administrasi Niaga', 'jenis_unit' => 'jurusan'],
            ['kode_unit' => 'LKP', 'nama_unit' => 'Unit Perlengkapan & BMN', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'KEU', 'nama_unit' => 'Unit Keuangan', 'jenis_unit' => 'unit'],
            ['kode_unit' => 'B1', 'nama_unit' => 'Bidang I (Akademik)', 'jenis_unit' => 'bidang'],
            ['kode_unit' => 'B2', 'nama_unit' => 'Bidang II (Umum dan Keuangan)', 'jenis_unit' => 'bidang'],
            ['kode_unit' => 'B3', 'nama_unit' => 'Bidang III (Kemahasiswaan)', 'jenis_unit' => 'bidang'],
        ];

        foreach ($units as $u) {
            Unit::updateOrCreate(['kode_unit' => $u['kode_unit']], $u);
        }

        // =========================================================================
        // 2. Pembenihan Pengguna (9 Peran, Batasan 1 Akun PP Aktif, 4 Akun PPK Terisolasi)
        // =========================================================================
        $users = [
            // Peran 1: Pengusul (User) dari unit kerja akademik
            [
                'nip' => '198501012010011001',
                'username' => 'user_pengaju',
                'email' => 'pengaju@pnj.ac.id',
                'nama' => 'Dr. Ir. Maulana Pengaju, M.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Ketua Jurusan TIK',
                'role' => 'user',
                'status_aktif' => true,
                'no_hp' => '081234567890',
                'no_telp_kantor' => '0217270001',
                'kode_unit' => 'TIK',
            ],
            // Peran 2: Staf Bidang II (Pemeriksa administrasi permohonan)
            [
                'nip' => '198602022011011002',
                'username' => 'staff_b2',
                'email' => 'staff_b2@pnj.ac.id',
                'nama' => 'Ahmad Staff Bidang 2, S.E.',
                'password' => $defaultPassword,
                'jabatan' => 'Staff Bidang Umum & Keuangan',
                'role' => 'staff_bidang_2',
                'status_aktif' => true,
                'no_hp' => '081298765431',
                'no_telp_kantor' => '0217270002',
                'kode_unit' => 'B2',
            ],
            // Peran 3: Wakil Direktur II (Pemegang hak veto dan persetujuan mutlak)
            [
                'nip' => '197001011995011001',
                'username' => 'wadir2',
                'email' => 'wadir2@pnj.ac.id',
                'nama' => 'Prof. Dr. Ir. Wadir Dua, M.Sc.',
                'password' => $defaultPassword,
                'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
                'role' => 'wadir_2',
                'status_aktif' => true,
                'no_hp' => '081298765432',
                'no_telp_kantor' => '0217270002',
                'kode_unit' => 'B2',
            ],
            // Peran 4: Perencanaan (Verifikator anggaran MAK)
            [
                'nip' => '198503032007031004',
                'username' => 'perencanaan',
                'email' => 'perencanaan@pnj.ac.id',
                'nama' => 'Budi Perencanaan, S.Kom.',
                'password' => $defaultPassword,
                'jabatan' => 'Koordinator Subbagian Perencanaan & Anggaran',
                'role' => 'perencanaan',
                'status_aktif' => true,
                'no_hp' => '081377889900',
                'no_telp_kantor' => '0217270003',
                'kode_unit' => 'B2',
            ],
            // Peran 5: PPBJ (Pengelola registrasi paket dan disposisi PP/PPK)
            [
                'nip' => '198001012005011002',
                'username' => 'ppbj',
                'email' => 'ppbj@pnj.ac.id',
                'nama' => 'Citra Koordinator PPBJ, M.M.',
                'password' => $defaultPassword,
                'jabatan' => 'Koordinator Pengadaan Barang dan Jasa',
                'role' => 'ppbj',
                'status_aktif' => true,
                'no_hp' => '081512345678',
                'no_telp_kantor' => '0217270004',
                'kode_unit' => 'B2',
            ],
            // Peran 6: Pejabat Pengadaan (PP) - Mematuhi batas tepat 1 akun PP aktif (FR 2.1)
            [
                'nip' => '198102022006021003',
                'username' => 'pp_user',
                'email' => 'pp@pnj.ac.id',
                'nama' => 'Dedi Pejabat Pengadaan, S.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pengadaan (PP)',
                'role' => 'pp',
                'status_aktif' => true,
                'no_hp' => '081623456789',
                'no_telp_kantor' => '0217270005',
                'kode_unit' => 'B2',
            ],
            // Peran 7 (Akun 1/4): Pejabat Pembuat Komitmen I (Sarana)
            [
                'nip' => '198203032007031001',
                'username' => 'ppk_1',
                'email' => 'ppk1@pnj.ac.id',
                'nama' => 'Eko PPK Bidang Sarana, M.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen I (Sarana)',
                'role' => 'ppk',
                'status_aktif' => true,
                'no_hp' => '081734567891',
                'no_telp_kantor' => '0217270006',
                'kode_unit' => 'B2',
            ],
            // Peran 7 (Akun 2/4): Pejabat Pembuat Komitmen II (Laboratorium)
            [
                'nip' => '198203032007031002',
                'username' => 'ppk_2',
                'email' => 'ppk2@pnj.ac.id',
                'nama' => 'Fajar PPK Bidang Laboratorium, M.Kom.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen II (Laboratorium)',
                'role' => 'ppk',
                'status_aktif' => true,
                'no_hp' => '081734567892',
                'no_telp_kantor' => '0217270006',
                'kode_unit' => 'B2',
            ],
            // Peran 7 (Akun 3/4): Pejabat Pembuat Komitmen III (Konstruksi)
            [
                'nip' => '198203032007031003',
                'username' => 'ppk_3',
                'email' => 'ppk3@pnj.ac.id',
                'nama' => 'Gita PPK Bidang Konstruksi, S.T.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen III (Konstruksi)',
                'role' => 'ppk',
                'status_aktif' => true,
                'no_hp' => '081734567893',
                'no_telp_kantor' => '0217270006',
                'kode_unit' => 'B2',
            ],
            // Peran 7 (Akun 4/4): Pejabat Pembuat Komitmen IV (Jasa)
            [
                'nip' => '198203032007031004',
                'username' => 'ppk_4',
                'email' => 'ppk4@pnj.ac.id',
                'nama' => 'Hendra PPK Bidang Jasa Lainnya, S.E.',
                'password' => $defaultPassword,
                'jabatan' => 'Pejabat Pembuat Komitmen IV (Jasa)',
                'role' => 'ppk',
                'status_aktif' => true,
                'no_hp' => '081734567894',
                'no_telp_kantor' => '0217270006',
                'kode_unit' => 'B2',
            ],
            // Peran 8: Perlengkapan (Pemeriksa fisik & penerbit BAST)
            [
                'nip' => '198405052009051006',
                'username' => 'perlengkapan',
                'email' => 'perlengkapan@pnj.ac.id',
                'nama' => 'Indra Staff Perlengkapan, A.Md.',
                'password' => $defaultPassword,
                'jabatan' => 'Staff Bagian Perlengkapan dan BMN',
                'role' => 'perlengkapan',
                'status_aktif' => true,
                'no_hp' => '081845678912',
                'no_telp_kantor' => '0217270007',
                'kode_unit' => 'LKP',
            ],
            // Peran 9: Keuangan (Verifikator memo bayar & eksekutor transfer dana)
            [
                'nip' => '198704042008041005',
                'username' => 'keuangan',
                'email' => 'keuangan@pnj.ac.id',
                'nama' => 'Joko Bendahara Keuangan, S.Ak.',
                'password' => $defaultPassword,
                'jabatan' => 'Bendahara Pengeluaran & Pencairan',
                'role' => 'keuangan',
                'status_aktif' => true,
                'no_hp' => '081956789023',
                'no_telp_kantor' => '0217270008',
                'kode_unit' => 'KEU',
            ],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(['username' => $u['username']], $u);
        }

        // Ambil referensi user untuk foreign key data relasional berikutnya
        $perencanaanUser = User::where('username', 'perencanaan')->first();
        $pengajuUser = User::where('username', 'user_pengaju')->first();
        $ppbjUser = User::where('username', 'ppbj')->first();
        $ppUser = User::where('username', 'pp_user')->first();
        $ppk1User = User::where('username', 'ppk_1')->first();

        // =========================================================================
        // 3. Pembenihan Master Anggaran MAK (Mata Anggaran Kegiatan)
        // =========================================================================
        $maks = [
            [
                'kode_mak' => 'MAK-TIK-2026-001',
                'uraian_mak' => 'Pengadaan Fasilitas Lab Cloud Computing & AI Jurusan TIK',
                'pagu_anggaran' => 250000000.00,
                'sisa_pagu_mak' => 250000000.00,
                'sumber_dana' => 'BOPTN',
                'tahun_anggaran' => 2026,
                'catatan_mak' => 'Prioritas Renstra Kampus 2026',
                'is_locked' => false,
                'id_user' => $perencanaanUser?->id_user,
                'tanggal_penetapan' => now(),
            ],
            [
                'kode_mak' => 'MAK-TM-2026-002',
                'uraian_mak' => 'Pengadaan Alat Ukur Presisi Mesin CNC Jurusan Teknik Mesin',
                'pagu_anggaran' => 120000000.00,
                'sisa_pagu_mak' => 120000000.00,
                'sumber_dana' => 'BLU',
                'tahun_anggaran' => 2026,
                'catatan_mak' => 'Mendukung sertifikasi kompetensi mesin',
                'is_locked' => false,
                'id_user' => $perencanaanUser?->id_user,
                'tanggal_penetapan' => now(),
            ],
            [
                'kode_mak' => 'MAK-UMUM-2026-003',
                'uraian_mak' => 'Pengadaan Kebutuhan ATK & Sarana Kantor Semester Ganjil',
                'pagu_anggaran' => 35000000.00,
                'sisa_pagu_mak' => 35000000.00,
                'sumber_dana' => 'RM',
                'tahun_anggaran' => 2026,
                'catatan_mak' => 'Operasional rutin perkantoran',
                'is_locked' => false,
                'id_user' => $perencanaanUser?->id_user,
                'tanggal_penetapan' => now(),
            ],
        ];

        foreach ($maks as $mak) {
            AnggaranMAK::updateOrCreate(['kode_mak' => $mak['kode_mak']], $mak);
        }

        // =========================================================================
        // 4. Pembenihan Data Rekanan (Vendor) & Berkas Legalitas
        // =========================================================================
        $vendors = [
            [
                'npwp' => '01.234.567.8-012.000',
                'nib' => '9120001234567',
                'nama_perusahaan' => 'PT. Teknologi Nusantara Mandiri',
                'alamat' => 'Jl. TB Simatupang No. 88, Jakarta Selatan',
                'is_active' => true,
                'nama_pic' => 'Bambang Irawan',
                'telepon_pic' => '081122334455',
                'email_pic' => 'bambang@teknus.co.id',
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => '1270001234567',
                'atas_nama' => 'PT Teknologi Nusantara Mandiri',
            ],
            [
                'npwp' => '02.345.678.9-023.000',
                'nib' => '9120002345678',
                'nama_perusahaan' => 'CV. Mitra Mesin Presisi',
                'alamat' => 'Kawasan Industri Pulogadung Blok B No. 12, Jakarta Timur',
                'is_active' => true,
                'nama_pic' => 'Suryanto',
                'telepon_pic' => '081233445566',
                'email_pic' => 'suryanto@mitramesin.com',
                'nama_bank' => 'Bank BNI',
                'nomor_rekening' => '0198882345',
                'atas_nama' => 'CV Mitra Mesin Presisi',
            ],
        ];

        foreach ($vendors as $v) {
            $vendor = Vendor::updateOrCreate(['npwp' => $v['npwp']], $v);
            VendorFileLegalitas::updateOrCreate(
                ['npwp' => $vendor->npwp, 'nama_dokumen' => 'NIB & SIUP Induk'],
                ['file_legalitas' => 'legalitas/'.$vendor->npwp.'_nib.pdf', 'uploaded_at' => now()]
            );
        }

        // =========================================================================
        // 5. Pembenihan Usulan Permohonan Pengadaan & Keputusan Verifikasi Paralel
        // =========================================================================
        $permohonan1 = PermohonanPengadaan::updateOrCreate(
            ['nomor_surat' => '001/PL3.TIK/PG/2026'],
            [
                'judul_pengadaan' => 'Pengadaan Server dan GPU Workstation AI Lab Komputasi',
                'tujuan_pengadaan_ringkas' => 'Mendukung riset GenAI dan pembelajaran machine learning mahasiswa TIK',
                'file_permohonan' => 'srikandi/001_PL3_TIK_2026.pdf',
                'status_permohonan' => 'disetujui',
                'status_anggaran' => 'disetujui',
                'revisi_ke' => 0,
                'estimasi_total_pagu' => 240000000.00,
                'tanggal_pengajuan' => now()->subDays(5),
                'kode_unit' => 'TIK',
                'id_user' => $pengajuUser->id_user,
                'kode_mak' => 'MAK-TIK-2026-001',
            ]
        );

        // Alokasi pagu dana MAK untuk surat usulan permohonan 1
        AlokasiMAK::updateOrCreate(
            ['nomor_surat' => $permohonan1->nomor_surat, 'kode_mak' => 'MAK-TIK-2026-001'],
            ['nominal_alokasi' => 240000000.00, 'tanggal_alokasi' => now()->subDays(4)]
        );

        // Keputusan Verifikasi Paralel: Pihak Perencanaan
        VerifikasiParalel::updateOrCreate(
            ['id_user' => $perencanaanUser->id_user, 'nomor_surat' => $permohonan1->nomor_surat, 'role_verifikator' => 'perencanaan'],
            ['status_keputusan' => 'disetujui', 'catatan_alasan' => 'Anggaran BOPTN 2026 sesuai dan mencukupi', 'tanggal_keputusan' => now()->subDays(4)]
        );

        // Keputusan Verifikasi Paralel: Pihak Pimpinan (Wadir 2)
        VerifikasiParalel::updateOrCreate(
            ['id_user' => User::where('role', 'wadir_2')->first()->id_user, 'nomor_surat' => $permohonan1->nomor_surat, 'role_verifikator' => 'wadir_2'],
            ['status_keputusan' => 'disetujui', 'catatan_alasan' => 'Disetujui untuk diteruskan ke PPBJ', 'tanggal_keputusan' => now()->subDays(4)]
        );

        // =========================================================================
        // 6. Pembenihan Sampel Paket Pengadaan (>200 Juta -> Jalur Routing: PPK)
        // =========================================================================
        PaketPengadaan::updateOrCreate(
            ['nomor_paket' => 'PKT-TIK-2026-001'],
            [
                'nama_paket' => 'Paket Pengadaan Server GPU Lab AI TIK',
                'jenis_pengadaan' => 'barang',
                'platform_pengadaan' => 'spse',
                'mekanisme_pengadaan' => 'tender',
                'jalur_routing' => PaketPengadaan::ROUTING_PPK,
                'nilai_hps' => 235000000.00,
                'alokasi_pagu_paket' => 240000000.00,
                'file_dokumen_pengadaan' => 'dokumen/hps_gpu_server_2026.pdf',
                'status_paket' => 'spk_terbit',
                'id_paket_lkpp' => 'LKPP-2026-998812',
                'nomor_surat' => $permohonan1->nomor_surat,
                'tanggal_registrasi_paket' => now()->subDays(3),
                'nomor_memo_ppbj' => 'MEMO-PPBJ/2026/001',
                'id_user_ppbj' => $ppbjUser->id_user,
                'id_user_ppk' => $ppk1User->id_user,
                'tanggal_penugasan_ppk' => now()->subDays(3),
                'status_penugasan_ppk' => 'ditugaskan',
                'npwp_vendor' => '01.234.567.8-012.000',
                'tanggal_penunjukan_vendor' => now()->subDays(2),
            ]
        );
    }
}
