<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory Data Uji Pengguna (User Factory)
 *
 * Bertanggung jawab menghasilkan dummy data pengguna acak untuk keperluan testing otomatis (Pest/PHPUnit).
 * Mendefinisikan atribut bawaan seperti nama, surel acak aman, kata sandi ter-hash,
 * serta state pembantu seperti email yang belum terverifikasi.
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
            'role' => 'user',
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
     * State mutator untuk pengguna Administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'status_aktif' => true,
            'status_registrasi' => User::STATUS_REGISTRASI_DISETUJUI,
            'tanggal_approval' => now(),
        ]);
    }

    /**
     * State mutator untuk mengkondisikan pengguna dengan email belum terverifikasi.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
