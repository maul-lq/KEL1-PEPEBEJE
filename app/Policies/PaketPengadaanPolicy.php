<?php

namespace App\Policies;

use App\Models\PaketPengadaan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kebijakan Otorisasi Paket Pengadaan (PaketPengadaanPolicy)
 *
 * Mengatur hak akses pengguna terhadap berkas paket pengadaan barang/jasa.
 * Menegakkan aturan NFR RBAC, khususnya isolasi ketat 4 akun PPK (PPK 1, 2, 3, 4)
 * agar tidak saling mengintip atau memodifikasi paket yang bukan wewenangnya.
 */
class PaketPengadaanPolicy
{
    /**
     * Menentukan apakah pengguna yang login dapat melihat daftar paket pengadaan.
     * Semua pengguna terotentikasi diizinkan mengakses daftar paket (penyaringan item dilakukan di level query/controller).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Menentukan apakah pengguna dapat melihat detail paket tertentu.
     * Menerapkan aturan isolasi berkas PPK, pembatasan PP, dan kepemilikan pemohon.
     */
    public function view(User $user, PaketPengadaan $paket): Response
    {
        // 1. Pemeriksaan Khusus Peran PPK (Pejabat Pembuat Komitmen)
        // Menegakkan isolasi berkas 4 akun PPK: PPK hanya boleh melihat paket yang secara eksplisit ditugaskan kepada dirinya.
        if ($user->role === 'ppk') {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Berkas paket pengadaan ini ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        // 2. Pemeriksaan Khusus Peran PP (Pejabat Pengadaan Tunggal)
        // Pejabat Pengadaan hanya boleh melihat paket dengan jalur pengadaan langsung bernilai Rp 50jt - Rp 200jt
        // atau paket yang ditugaskan ke ID user miliknya.
        if ($user->role === 'pp') {
            $isAssigned = (int) $paket->id_user_pp === (int) $user->id_user;
            $isPpRouting = $paket->jalur_routing === PaketPengadaan::ROUTING_PP;

            return ($isAssigned || $isPpRouting)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Paket pengadaan ini bukan wewenang Pejabat Pengadaan (PP).');
        }

        // 3. Pemeriksaan Khusus Peran User (Unit Pemohon / Jurusan)
        // Pemohon hanya boleh melihat paket yang berasal dari surat permohonan yang diajukannya sendiri.
        if ($user->role === 'user') {
            return (int) $paket->permohonan?->id_user === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan pemohon pengadaan ini.');
        }

        // 4. Peran Manajemen, Pimpinan, dan Pengawasan Kampus
        // PPBJ, Perencanaan, Wadir 2, Staff Bidang 2, Perlengkapan, dan Keuangan memiliki wewenang lintas paket.
        if (in_array($user->role, ['ppbj', 'perencanaan', 'wadir_2', 'staff_bidang_2', 'perlengkapan', 'keuangan'], true)) {
            return Response::allow();
        }

        // 5. Peran lain yang tidak dikenali ditolak secara default
        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki hak akses untuk berkas ini.');
    }

    /**
     * Menentukan apakah pengguna dapat memperbarui data paket pengadaan.
     * Mengatur hak ubah data teknis paket berdasarkan peran dan tanggung jawab penugasan.
     */
    public function update(User $user, PaketPengadaan $paket): Response
    {
        // PPBJ memiliki hak penuh sebagai koordinator pengadaan kampus untuk memperbarui data paket
        if ($user->role === 'ppbj') {
            return Response::allow();
        }

        // PPK hanya diizinkan memperbarui data paket jika paket tersebut memang ditugaskan kepada ID dirinya
        if ($user->role === 'ppk') {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda hanya berwenang memperbarui paket yang ditugaskan kepada Anda.');
        }

        // Pejabat Pengadaan (PP) hanya boleh mengubah data jika paket berada dalam wewenang pengadaan langsungnya
        if ($user->role === 'pp') {
            return ($paket->jalur_routing === PaketPengadaan::ROUTING_PP || (int) $paket->id_user_pp === (int) $user->id_user)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan Pejabat Pengadaan yang berwenang atas paket ini.');
        }

        // Selain peran di atas, akses pembaruan ditolak
        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki izin untuk memperbarui paket ini.');
    }

    /**
     * Menentukan apakah pengguna dapat mengalihkan penugasan paket ke PPK lain (Reassign PPK).
     * Berdasarkan Notulensi 2 & FR 7.3: Hanya PPBJ dan Bagian Perencanaan yang berwenang memutasi penugasan PPK.
     */
    public function reassignPpk(User $user, PaketPengadaan $paket): Response
    {
        // Validasi apakah user memiliki peran koordinator PPBJ atau Bagian Perencanaan
        if (in_array($user->role, ['ppbj', 'perencanaan'], true)) {
            return Response::allow();
        }

        // Tolak permintaan mutasi penugasan jika diajukan oleh peran selain PPBJ / Perencanaan
        return Response::deny('Akses Ditolak: Hanya PPBJ dan Perencanaan yang dapat mengubah penugasan PPK.');
    }
}
