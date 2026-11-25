<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Pengujian fitur ini saya tulis untuk memverifikasi rendering halaman error kustom SIM-PBJ,
 *             keamanan penyembunyian pesan internal, tampilan otorisasi 403, serta pemisahan respons browser dan JSON.
 */

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        [
            'nama_unit' => 'Jurusan Teknik Informatika dan Komputer',
            'jenis_unit' => 'jurusan',
        ]
    );

    $this->seed(RolePermissionSeeder::class);
});

it('renders Indonesian 404 HTML error page without sidebar for unknown web and browser-opened api endpoints', function (): void {
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();

    // Tamu yang membuka alamat tidak dikenal harus menerima halaman 404 kustom dengan tombol kembali.
    $guestNotFound = $this->get('/halaman-tidak-ditemukan-simpbj');
    $guestNotFound->assertStatus(404)
        ->assertSee('SIM-PBJ')
        ->assertSee('Halaman Tidak Ditemukan')
        ->assertSee('Kembali ke Halaman Sebelumnya')
        ->assertSee('Ke Halaman Masuk')
        ->assertDontSee('vertical-navbar');

    // Pengguna login yang membuka URL /api/* lewat browser biasa tetap mendapat halaman error HTML, bukan dump JSON.
    $browserApiNotFound = $this->actingAs($pengusul)->get('/api/endpoint-tidak-ada');
    $browserApiNotFound->assertStatus(404)
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('Halaman Tidak Ditemukan')
        ->assertSee('Kembali ke Halaman Sebelumnya')
        ->assertSee('Ke Dashboard')
        ->assertDontSee('vertical-navbar')
        ->assertDontSee('could not be found');

    // Permintaan ModelNotFound (firstOrFail) tidak boleh membocorkan namespace model Eloquent ke browser maupun JSON API.
    $modelNotFoundHtml = $this->actingAs($pengusul)->get('/permohonan/999-TIDAK-ADA');
    $modelNotFoundHtml->assertStatus(404)
        ->assertSee('Halaman Tidak Ditemukan')
        ->assertDontSee('No query results for model')
        ->assertDontSee('PermohonanPengadaan');

    $this->actingAs($pengusul)
        ->getJson('/api/endpoint-tidak-ada')
        ->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Halaman atau data yang diminta tidak ditemukan.',
        ]);

    $this->actingAs($pengusul)
        ->getJson('/permohonan/999-TIDAK-ADA')
        ->assertStatus(404)
        ->assertJson([
            'status' => 'error',
            'message' => 'Halaman atau data yang diminta tidak ditemukan.',
        ])
        ->assertDontSee('PermohonanPengadaan');
});

it('renders Indonesian 403 error page with required authorization details and protects raw internal messages', function (): void {
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();

    // Izin tunggal pada middleware harus menampilkan label izin Bahasa Indonesia standar.
    $this->actingAs($pengusul)
        ->get(route('paket.create'))
        ->assertStatus(403)
        ->assertSee('Akses Ditolak')
        ->assertSee('Membuat Paket Pengadaan &amp; Menentukan Harga Perkiraan', false)
        ->assertSee('Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi Membuat Paket Pengadaan &amp; Menentukan Harga Perkiraan.', false)
        ->assertSee('Kembali ke Halaman Sebelumnya');

    // Izin administrasi peran pada middleware harus menampilkan label izin RBAC terkait.
    $this->actingAs($pengusul)
        ->get(route('admin.roles.index'))
        ->assertStatus(403)
        ->assertSee('Mengelola Daftar Peran &amp; Hak Akses Fitur', false);

    // Multi-izin OR pada middleware harus menggabungkan daftar otorisasi yang dapat mengakses.
    $this->actingAs($pengusul)
        ->get(route('verifikasi.wadir-2'))
        ->assertStatus(403)
        ->assertSee('Melihat Antrean Verifikasi Usulan (Wadir 2) atau Menyetujui, Menolak, atau Meminta Revisi Usulan (Wadir 2)', false);

    // Izin alih tugas PPK menolak pengusul biasa dengan label izin tergeneralisasi.
    $this->actingAs($pengusul)
        ->get(route('paket.reassign-ppk.create'))
        ->assertStatus(403)
        ->assertSee('Mengalihkan Penugasan PPK pada Paket', false);

    // Akun nonaktif atau belum disetujui admin harus ditolak dengan penjelasan status akun.
    $pendingUser = User::factory()->withRole(User::ROLE_PPBJ)->menungguApproval()->create();
    $this->actingAs($pendingUser)
        ->get(route('paket.create'))
        ->assertStatus(403)
        ->assertSee('akun Anda berstatus nonaktif atau belum disetujui oleh Administrator');

    // Klien JSON tetap memperoleh respons 403 JSON yang rapi.
    $this->actingAs($pengusul)
        ->getJson(route('admin.roles.index'))
        ->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi Mengelola Daftar Peran & Hak Akses Fitur.',
        ]);

    // Pesan abort(403) mentah yang tidak terkurasi tidak boleh dibocorkan ke UI 403.
    Route::get('/__test-error/403-raw', fn () => abort(403, 'Internal Policy Secret Leak'));
    $this->get('/__test-error/403-raw')
        ->assertStatus(403)
        ->assertSee('Akses Ditolak')
        ->assertSee('Halaman ini tidak dapat diakses karena Anda tidak memiliki otorisasi yang dibutuhkan untuk membuka fitur ini.')
        ->assertDontSee('Internal Policy Secret Leak');

    // Pesan domain controller berawalan "Akses Ditolak:" dinormalisasi dengan tetap menyebut alasan pembatasan.
    Route::get('/__test-error/403-domain', fn () => abort(403, 'Akses Ditolak: Anda tidak berhak memproses paket yang ditugaskan ke akun PPK lain.'));
    $this->get('/__test-error/403-domain')
        ->assertStatus(403)
        ->assertSee('Halaman ini tidak dapat diakses. Anda tidak berhak memproses paket yang ditugaskan ke akun PPK lain.');
});

it('renders custom Indonesian pages for 400, 401, 405, 419, 429, 503, and safe 500 under APP_DEBUG=false', function (): void {
    // Polling API tanpa login menggunakan Accept: application/json tetap mengembalikan 401 JSON.
    $this->getJson(route('notifikasi.terbaru'))
        ->assertStatus(401);

    // Akses browser langsung tanpa login ke endpoint /api/* tetap diarahkan ke halaman login web.
    $this->get(route('notifikasi.terbaru'))
        ->assertRedirect(route('login'));

    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();

    // Uji 405 dari rute nyata yang hanya mengizinkan POST saat diakses langsung lewat GET browser.
    $this->actingAs($pengusul)
        ->get('/notifikasi/mark-all-read')
        ->assertStatus(405)
        ->assertSee('Metode Permintaan Tidak Diizinkan')
        ->assertSee('Kembali ke Halaman Sebelumnya');

    Route::get('/__test-error/400', fn () => abort(400));
    Route::get('/__test-error/401', fn () => abort(401));
    Route::get('/__test-error/408', fn () => abort(408));
    Route::get('/__test-error/419', fn () => throw new HttpException(419));
    Route::get('/__test-error/429', fn () => abort(429));
    Route::get('/__test-error/502', fn () => abort(502));
    Route::get('/__test-error/503', fn () => abort(503));
    Route::get('/__test-error/500', function (): void {
        throw new RuntimeException('SQLSTATE[42P01] relation secret_internal does not exist');
    });

    $this->get('/__test-error/400')
        ->assertStatus(400)
        ->assertSee('Permintaan Tidak Valid')
        ->assertSee('Kembali ke Halaman Sebelumnya');

    $this->get('/__test-error/401')
        ->assertStatus(401)
        ->assertSee('Autentikasi Diperlukan')
        ->assertSee('Masuk ke Akun')
        ->assertSee('Kembali ke Halaman Sebelumnya');

    $this->get('/__test-error/408')
        ->assertStatus(408)
        ->assertSee('Kode HTTP 408')
        ->assertSee('Permintaan Tidak Dapat Diproses');

    $this->get('/__test-error/419')
        ->assertStatus(419)
        ->assertSee('Sesi Halaman Kedaluwarsa')
        ->assertSee('Muat Ulang Halaman')
        ->assertSee('Kembali ke Halaman Sebelumnya');

    $this->get('/__test-error/429')
        ->assertStatus(429)
        ->assertSee('Terlalu Banyak Permintaan');

    $this->get('/__test-error/502')
        ->assertStatus(502)
        ->assertSee('Kode HTTP 502')
        ->assertSee('Terjadi Gangguan Layanan Server');

    $this->get('/__test-error/503')
        ->assertStatus(503)
        ->assertSee('Layanan Sedang Tidak Tersedia');

    // Pada APP_DEBUG=false, error 500 wajib memakai UI aman SIM-PBJ tanpa membocorkan exception SQL/stack trace.
    config(['app.debug' => false]);
    $this->get('/__test-error/500')
        ->assertStatus(500)
        ->assertSee('Terjadi Gangguan pada Server')
        ->assertSee('Kembali ke Halaman Sebelumnya')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('secret_internal');

    // Pada APP_DEBUG=true, perilaku developer debug mode Laravel tetap dipertahankan.
    config(['app.debug' => true]);
    $this->get('/__test-error/500')
        ->assertStatus(500)
        ->assertSee('secret_internal');
});
