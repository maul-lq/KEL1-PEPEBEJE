<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Factory ini rancang untuk membangkitkan data uji entitas User SIM-PBJ lengkap
 *             dengan peran default, variasi status persetujuan registrasi, serta penetapan role
 *             spesifik untuk skenario pengujian Pest.
 */

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory pembangkit instans model `User` untuk kebutuhan pengujian otomatis.
 *
 * Tujuan: menyederhanakan pembuatan akun uji beserta pembuatan peran otomatis bila belum
 * tersedia di database tes, termasuk state mutator registrasi menunggu, ditolak, dan admin.
 *
 * @extends Factory<User>
 *
 * Contoh pemanggilan:
 * `$user = User::factory()->withRole(User::ROLE_PPBJ)->create();`
 */
class UserFactory extends Factory
{
    /**
     * Cache hash kata sandi bawaan agar eksekusi massal factory tidak membebani CPU hashing berulang.
     */
    protected static ?string $password;

    /**
     * Mendefinisikan atribut default untuk setiap instans model `User`.
     *
     * Tujuan: menjamin setiap user uji langsung berstatus aktif, terverifikasi unit `TIK`,
     * serta otomatis terkait ke peran `user` agar siap dipakai dalam feature test otorisasi.
     *
     * @return array<string, mixed> Peta kolom dan nilai awal model User.
     *
     * Contoh pemanggilan:
     * `$attrs = User::factory()->definition();`
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'nip' => fake()->unique()->numerify('19900101########'),
            'email' => fake()->unique()->safeEmail(),
            'jabatan' => 'Staff Pengajar / Pengusul',
            'kode_unit' => 'TIK',
            'no_hp' => fake()->phoneNumber(),
            // gunakan firstOrCreate di closure agar feature test tanpa seeder penuh tetap memiliki id_role valid.
            'id_role' => fn (): int => Role::firstOrCreate(
                ['nama_role' => User::ROLE_USER],
                ['label' => 'Pengusul / Unit', 'is_system' => true]
            )->id,
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'tanggal_approval' => now(),
            // cache hash password statis supaya puluhan pembuatan user dalam satu test suite berjalan instan.
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Mengubah state factory menjadi pendaftar baru yang menunggu persetujuan Admin.
     *
     * Tujuan: mensimulasikan akun pendaftar mandiri yang belum diaktivasi agar dapat menguji
     * alur antrean approval dan penolakan login sebelum diverifikasi.
     *
     * @return static Instance factory dengan atribut registrasi menunggu.
     *
     * Contoh pemanggilan:
     * `$pending = User::factory()->menungguApproval()->create();`
     */
    public function menungguApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_aktif' => false,
            'status_registrasi' => User::STATUS_REGISTRASI_MENUNGGU,
            'tanggal_approval' => null,
            'id_admin_approval' => null,
        ]);
    }

    /**
     * Mengubah state factory menjadi pendaftar yang ditolak oleh Administrator.
     *
     * Tujuan: memfasilitasi pengujian kasus akun berstatus ditolak lengkap dengan alasan
     * penolakan default maupun kustom.
     *
     * @param  string  $alasan  Pesan alasan penolakan pendaftaran pengguna.
     * @return static Instance factory dengan status registrasi ditolak.
     *
     * Contoh pemanggilan:
     * `$rejected = User::factory()->ditolak('NIP tidak terdaftar')->create();`
     */
    public function ditolak(string $alasan = 'Data persyaratan belum lengkap'): static
    {
        return $this->state(fn (array $attributes) => [
            'status_aktif' => false,
            'status_registrasi' => User::STATUS_REGISTRASI_DITOLAK,
            'alasan_penolakan' => $alasan,
            'tanggal_approval' => now(),
        ]);
    }

    /**
     * Menetapkan peran RBAC tertentu ke user uji berdasarkan slug `nama_role`.
     *
     * Tujuan: menghindari ketergantungan urutan ID role dengan memetakan slug ke `id_role`
     * secara dinamis lewat `Role::firstOrCreate`.
     *
     * @param  string  $roleSlug  Slug peran sistem atau kustom (misalnya `ppk`, `wadir_2`).
     * @param  string|null  $label  Label tampilan peran bila record role baru perlu dibuat.
     * @return static Instance factory dengan `id_role` terikat pada peran target.
     *
     * Contoh pemanggilan:
     * `$wadir = User::factory()->withRole(User::ROLE_WADIR_2, 'Wakil Direktur II')->create();`
     */
    public function withRole(string $roleSlug, ?string $label = null): static
    {
        return $this->state(function () use ($roleSlug, $label): array {
            $role = Role::firstOrCreate(
                ['nama_role' => $roleSlug],
                [
                    'label' => $label ?? ucwords(str_replace('_', ' ', $roleSlug)),
                    'is_system' => true,
                ]
            );

            return ['id_role' => $role->id];
        });
    }

    /**
     * Mengubah state factory menjadi pengguna Administrator aktif dan terverifikasi.
     *
     * Tujuan: menyediakan pintasan ekspresif untuk test manajemen pengguna, role, dan approval.
     *
     * @return static Instance factory pengguna berstatus admin aktif.
     *
     * Contoh pemanggilan:
     * `$admin = User::factory()->admin()->create();`
     */
    public function admin(): static
    {
        return $this->withRole(User::ROLE_ADMIN, 'Administrator')->state(fn (array $attributes) => [
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'tanggal_approval' => now(),
        ]);
    }
}
