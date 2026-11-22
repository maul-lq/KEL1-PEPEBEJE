<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : memperbarui berkas konfigurasi antrean (queue) bawaan Laravel ini untuk mengatur backend antrean tugas asinkron, job batching, dan pencatatan job gagal.
 */

/**
 * Konfigurasi Antrean Tugas / Queue (config/queue.php)
 *
 * mendefinisikan driver queue default ('database') dan parameter retry_after
 * agar pemrosesan job latar belakang serta pelacakan failed_jobs terkelola di database aplikasi.
 *
 * Parameter    : Tidak ada (membaca variabel env()).
 * Nilai kembali: array<string, mixed> — daftar koneksi queue, konfigurasi batching, dan failed jobs.
 * Contoh pemanggilan:
 * `php artisan queue:work` atau `config('queue.default')`
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | memakai driver 'database' secara default agar antrean pekerjaan dapat
    | langsung berjalan menggunakan tabel 'jobs' tanpa setup broker tambahan.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | mempertahankan konfigurasi seluruh backend antrean Laravel (sync,
    | database, redis, sqs, failover) untuk kebutuhan pengembangan hingga produksi.
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            // menyetel retry_after 90 detik agar job yang macet dapat diambil kembali oleh worker lain tanpa menunggu terlalu lama.
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | mengarahkan tabel job_batches ke DB_CONNECTION aktif agar batch job
    | selalu mengikuti koneksi database utama aplikasi.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | menggunakan driver 'database-uuids' agar setiap kegagalan eksekusi
    | job tercatat permanen dengan identitas unik di tabel failed_jobs.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
