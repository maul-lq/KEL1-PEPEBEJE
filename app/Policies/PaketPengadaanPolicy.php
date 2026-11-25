<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis policy PaketPengadaanPolicy ini untuk menegakkan otorisasi tingkat objek pada paket pengadaan, terutama isolasi berkas antar 4 akun PPK serta pembatasan akses PP dan unit pemohon.
 */

namespace App\Policies;

use App\Models\PaketPengadaan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kebijakan Otorisasi Paket Pengadaan (PaketPengadaanPolicy)
 *
 * memusatkan aturan akses baca, ubah, dan alih tugas PPK di policy ini agar controller dan Blade
 * menegakkan aturan isolasi berkas RBAC yang konsisten di seluruh modul paket.
 *
 * Parameter konstruktor: Tidak ada.
 * Nilai kembali         : Instance policy yang di-resolve otomatis oleh Gate Laravel untuk model PaketPengadaan.
 *
 * Contoh pemanggilan:
 * `Gate::authorize('view', $paket);`
 */
class PaketPengadaanPolicy
{
    /**
     * mengizinkan seluruh pengguna terautentikasi membuka halaman daftar paket karena penyaringan baris dilakukan oleh scopeForUser.
     *
     * @param  User  $user  menerima pengguna terautentikasi yang membuka daftar paket.
     * @return bool mengembalikan true agar akses daftar tetap diteruskan ke query scope RBAC.
     *
     * Contoh pemanggilan:
     * `$user->can('viewAny', PaketPengadaan::class);`
     */
    public function viewAny(User $user): bool
    {
        // mendelegasikan isolasi baris per peran ke PaketPengadaan::scopeForUser agar menu daftar tetap bisa dibuka oleh setiap role terkait.
        return true;
    }

    /**
     * memvalidasi hak akses lihat detail satu berkas paket dengan menerapkan isolasi ketat PPK, batasan jalur PP, dan kepemilikan pemohon.
     *
     * @param  User  $user  memeriksa peran dan izin RBAC pengguna yang meminta detail paket.
     * @param  PaketPengadaan  $paket  membandingkan ID penugasan (id_user_ppk, id_user_pp) serta pemilik permohonan asal paket ini.
     * @return Response mengembalikan Response::allow() bila berwenang atau Response::deny() dengan alasan penolakan yang jelas.
     *
     * Contoh pemanggilan:
     * `Gate::authorize('view', $paket);`
     */
    public function view(User $user, PaketPengadaan $paket): Response
    {
        if ($user->hasPermission('paket.view_all') || $user->hasPermission('admin.user_manage')) {
            return Response::allow();
        }

        // mendahulukan isolasi paket yang ditugaskan khusus ke PPK (termasuk role kustom yang memegang reviu.ppk/paket.view_assigned) agar berkas antar-PPK tidak bocor.
        if (($user->hasPermission('reviu.ppk') || $user->hasPermission('paket.view_assigned')) && ! $user->hasPermission('reviu.pp')) {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Paket pengadaan ini ditugaskan kepada PPK lain.');
        }

        // membatasi pemeriksa PP pada paket jalur Rp50jt-200jt atau yang ditugaskan langsung padanya agar paket >200jt tidak terekspos.
        if ($user->hasPermission('reviu.pp')) {
            $isAssigned = (int) $paket->id_user_pp === (int) $user->id_user;
            $isPpRouting = in_array($paket->jalur_routing, [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt']);

            return ($isAssigned || $isPpRouting)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Paket pengadaan ini bukan wewenang Pejabat Pengadaan.');
        }

        // memberi akses lihat bagi pemeriksa penerimaan, pencairan pembayaran, atau verifikator lintas unit sesuai izin aktifnya.
        if ($user->hasAnyPermission([
            'penerimaan.view',
            'penerimaan.bast_create',
            'penerimaan.bast_sign',
            'pembayaran.pencairan_sp2d',
            'pembayaran.memo_wadir2',
            'pembayaran.pengajuan',
            'pembayaran.spp_terbitkan',
            'verifikasi.wadir_2_view',
            'verifikasi.wadir_2_decide',
            'verifikasi.bidang_2_view',
            'anggaran.mak_view',
        ])) {
            return Response::allow();
        }

        // mengizinkan unit pemohon melihat progres paket hanya jika berasal dari usulannya sendiri (tanpa izin view_all).
        if ($user->hasPermission('permohonan.view_own') && ! $user->hasPermission('permohonan.view_all')) {
            return (int) $paket->permohonan?->id_user === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan pemohon pengadaan ini.');
        }

        // mengizinkan pemegang izin pemantauan PP melihat paket jalur PP walaupun tidak memegang paket.view_all.
        if ($user->canMonitorPp() && (in_array($paket->jalur_routing, [PaketPengadaan::ROUTING_PP, 'pp_50_sd_200jt']) || ! empty($paket->id_user_pp))) {
            return Response::allow();
        }

        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki hak akses untuk berkas ini.');
    }

    /**
     * menentukan apakah pengguna berwenang memodifikasi data teknis atau status paket pengadaan.
     *
     * @param  User  $user  memeriksa izin pembuatan paket, reviu PPK, atau reviu PP tanpa mengunci pada slug peran tertentu.
     * @param  PaketPengadaan  $paket  mencocokkan penugasan pengguna dengan atribut id_user_ppk / id_user_pp / jalur_routing paket.
     * @return Response mengembalikan Response::allow() jika sesuai wewenang atau Response::deny() bila melanggar isolasi.
     *
     * Contoh pemanggilan:
     * `Gate::authorize('update', $paket);`
     */
    public function update(User $user, PaketPengadaan $paket): Response
    {
        if ($user->hasPermission('paket.create')) {
            return Response::allow();
        }

        if ($user->hasPermission('reviu.ppk')) {
            return (int) $paket->id_user_ppk === (int) $user->id_user
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda hanya berwenang memperbarui paket yang ditugaskan kepada Anda.');
        }

        if ($user->hasPermission('reviu.pp')) {
            return ($paket->jalur_routing === PaketPengadaan::ROUTING_PP || (int) $paket->id_user_pp === (int) $user->id_user)
                ? Response::allow()
                : Response::deny('Akses Ditolak: Anda bukan Pejabat Pengadaan yang berwenang atas paket ini.');
        }

        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki izin untuk memperbarui paket ini.');
    }

    /**
     * membatasi wewenang pengalihan penugasan PPK hanya kepada peran yang memegang izin `paket.reassign_ppk`.
     *
     * @param  User  $user  memeriksa apakah pengguna memegang izin pengalihan penugasan PPK.
     * @param  PaketPengadaan  $paket  menerima paket yang akan dialihkan penugasan PPK-nya.
     * @return Response mengembalikan Response::allow() jika memiliki izin atau Response::deny() bila tidak.
     *
     * Contoh pemanggilan:
     * `Gate::authorize('reassignPpk', $paket);`
     */
    public function reassignPpk(User $user, PaketPengadaan $paket): Response
    {
        if ($user->hasPermission('paket.reassign_ppk')) {
            return Response::allow();
        }

        return Response::deny('Akses Ditolak: Peran Anda tidak memiliki izin untuk mengubah penugasan PPK.');
    }
}
