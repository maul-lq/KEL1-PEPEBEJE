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
            'name' => fake()->name(),
            // Alamat surel acak yang unik dan aman untuk testing
            'email' => fake()->unique()->safeEmail(),
            // Waktu verifikasi email diisi saat ini
            'email_verified_at' => now(),
            // Hash kata sandi bawaan 'password'
            'password' => static::$password ??= Hash::make('password'),
            // Token remember token string acak 10 karakter
            'remember_token' => Str::random(10),
        ];
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
