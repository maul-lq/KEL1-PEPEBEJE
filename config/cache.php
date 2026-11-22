<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi cache bawaan Laravel ini agar penyimpanan lock penjadwal reminder dan cache aplikasi terdokumentasi jelas.
 */

use Illuminate\Support\Str;

/**
 * Konfigurasi Penyimpanan Cache & Lock (config/cache.php)
 *
 * mendefinisikan store cache bawaan ('database') yang juga dipakai oleh Cache::lock()
 * pada ReminderSchedulerService agar pemrosesan pengingat berkala tidak berjalan ganda.
 *
 * Parameter    : Tidak ada (membaca fallback dari env()).
 * Nilai kembali: array<string, mixed> — daftar driver cache, prefix kunci, dan kebijakan deserialisasi.
 * Contoh pemanggilan:
 * `Cache::lock('simpbj_reminder_scheduler_lock', 30)` atau `config('cache.default')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | mempertahankan default 'database' agar lock dan throttle scheduler
    | tetap bekerja persisten antar proses PHP tanpa ketergantungan Redis wajib.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | menyediakan pilihan store lengkap bawaan Laravel (database, file,
    | redis, failover, array) untuk kebutuhan produksi maupun pengujian unit.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | memberi prefix berbasis APP_NAME agar kunci cache dan lock aplikasi
    | tidak bertabrakan jika beberapa proyek berbagi server database/Redis.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | membiarkan opsi ini false sebagai pengaman agar objek PHP arbitrer
    | tidak dapat dideserialisasi dari cache apabila APP_KEY suatu saat bocor.
    |
    */

    'serializable_classes' => false,

];
