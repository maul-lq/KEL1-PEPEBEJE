<?php

namespace App\Policies;

use App\Models\PaketPengadaan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaketPengadaanPolicy
{
    /**
     * Tentukan apakah pengguna dapat melihat daftar paket.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat melihat detail paket (NFR RBAC & Isolasi 4 Akun PPK).
     */
    public function view(User $user, PaketPengadaan $paket): Response
    {
        // PPK: Isolasi berkas 4 akun PPK (hanya dapat melihat jika di-assign ke ID dirinya)
        if ($user->role === 'ppk') {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Berkas paket pengadaan ini ditugaskan kepada PPK lain (Isolasi Berkas PPK).');
        }

        // PP: Pejabat Pengadaan hanya melihat paket 50-200jt atau ditugaskan kepadanya
        if ($user->role === 'pp') {
            $isAssigned = (int) $paket->id_user_pp === (int) $user->id_user;
            $isPpRouting = $paket->jalur_routing === PaketPengadaan::ROUTING_PP;

            return ($isAssigned || $isPpRouting)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Paket pengadaan ini bukan wewenang Pejabat Pengadaan (PP).');
        }

        // Pemohon (User): Hanya melihat permohonan/paket miliknya
        if ($user->role === 'user') {
            return (int) $paket->permohonan?->id_user === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan pemohon pengadaan ini.');
        }

        // Role manajemen & pengawasan (PPBJ, Perencanaan, Wadir 2, Staff Bidang 2, Perlengkapan, Keuangan)
        if (in_array($user->role, ['ppbj', 'perencanaan', 'wadir_2', 'staff_bidang_2', 'perlengkapan', 'keuangan'], true)) {
            return Response::allow();
        }

        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki hak akses untuk berkas ini.');
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data paket.
     */
    public function update(User $user, PaketPengadaan $paket): Response
    {
        // PPBJ memiliki wewenang mengelola paket
        if ($user->role === 'ppbj') {
            return Response::allow();
        }

        // PPK hanya dapat memperbarui jika paket ditugaskan kepadanya
        if ($user->role === 'ppk') {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda hanya berwenang memperbarui paket yang ditugaskan kepada Anda.');
        }

        // PP hanya dapat memperbarui jika dalam wewenangnya
        if ($user->role === 'pp') {
            return ($paket->jalur_routing === PaketPengadaan::ROUTING_PP || (int) $paket->id_user_pp === (int) $user->id_user)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan Pejabat Pengadaan yang berwenang atas paket ini.');
        }

        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki izin untuk memperbarui paket ini.');
    }

    /**
     * Tentukan apakah pengguna dapat mengalihkan penugasan PPK (FR 7.3 & Notulensi 2).
     */
    public function reassignPpk(User $user, PaketPengadaan $paket): Response
    {
        // Hanya PPBJ dan Perencanaan yang boleh mengubah penugasan PPK
        if (in_array($user->role, ['ppbj', 'perencanaan'], true)) {
            return Response::allow();
        }

        return Response::deny('Akses Ditolak: Hanya PPBJ dan Perencanaan yang dapat mengubah penugasan PPK.');
    }
}
