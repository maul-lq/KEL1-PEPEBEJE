<?php

/**
 * Pendaftaran Service Provider Aplikasi
 *
 * Mengembalikan daftar class Service Provider utama yang dimuat saat bootstraping sistem Laravel.
 */
use App\Providers\AppServiceProvider;

return [
    // Service provider inti aplikasi untuk registrasi service, event listener, dan policy
    AppServiceProvider::class,
];
