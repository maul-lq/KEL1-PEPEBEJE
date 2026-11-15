<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory Data Uji Pengguna (User Factory)
 *
 * Bertanggung jawab menghasilkan data uji pengguna secara otomatis untuk keperluan pengujian (Pest/PHPUnit).
 * Mendefinisikan atribut bawaan seperti nama, alamat surel acak yang aman, kata sandi ter-hash,
 * serta relasi peran tunggal melalui `id_role`.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Cache kata sandi terenkripsi statis agar pengujian lebih cepat.
     */
    protected static ?string $password;

    /**
     * Definisi atribut default untuk setiap instans pembuatan pengguna uji.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Nama acak representasi pengguna
            'nama' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'nip' => fake()->unique()->numerify('19900101########'),
            // Alamat surel acak yang unik dan aman untuk testing
            'email' => fake()->unique()->safeEmail(),
            'jabatan' => 'Staff Pengajar / Pengusul',
            'kode_unit' => 'TIK',
            'no_hp' => fake()->phoneNumber(),
            'id_role' => fn (): int => Role::firstOrCreate(
                ['nama_role' => User::ROLE_USER],
                ['label' => 'Pengusul / Unit', 'is_system' => true]
            )->id,
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'tanggal_approval' => now(),
            // Hash kata sandi bawaan 'password'
            'password' => static::$password ??= Hash::make('password'),
            // Token remember token string acak 10 karakter
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * State mutator untuk pendaftar baru yang menunggu persetujuan Admin.
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
     * State mutator untuk pendaftar yang ditolak oleh Admin.
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
     * State mutator untuk menetapkan peran tertentu berdasarkan slug `nama_role`.
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
     * State mutator untuk pengguna Administrator.
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
