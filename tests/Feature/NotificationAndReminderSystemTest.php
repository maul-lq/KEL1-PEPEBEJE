<?php

use App\Models\KontrakSPK;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Pembayaran;
use App\Models\PenerimaanBarang;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\TransaksiPencairan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\PengingatPengadaanNotification;
use App\Services\PengadaanNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

it('persists profile email reminder toggle and respects opt-out in observer', function (): void {
    Notification::fake();

    $user = User::factory()->withRole(User::ROLE_USER)->create([
        'reminder_email_aktif' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('profile.reminder-email'), ['enabled' => false])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'enabled' => false,
        ]);

    expect($user->fresh()->reminder_email_aktif)->toBeFalse();

    $service = app(PengadaanNotificationService::class);
    $notifOff = $service->kirimKeUser($user->fresh(), 'Pengingat Dijeda', 'Tes saat email dijeda', 'pengingat');

    expect($notifOff)->toBeInstanceOf(NotifikasiUser::class);
    Notification::assertNothingSent();

    $this->actingAs($user->fresh())
        ->postJson(route('profile.reminder-email'), ['enabled' => true])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'enabled' => true,
        ]);

    $service->kirimKeUser($user->fresh(), 'Pengingat Aktif', 'Tes saat email aktif', 'verifikasi');

    Notification::assertSentToTimes($user->fresh(), PengingatPengadaanNotification::class, 1);
});

it('notifies admin via RBAC permissions on registration and resolves action url dynamically for custom roles', function (): void {
    Notification::fake();

    $customRole = Role::create([
        'nama_role' => 'staf_pencairan_khusus',
        'label' => 'Staf Pencairan Khusus',
        'deskripsi' => 'Role khusus dengan izin pencairan SP2D',
    ]);
    $sp2dPermission = Permission::where('nama_permission', 'pembayaran.pencairan_sp2d')->firstOrFail();
    $customRole->permissions()->sync([$sp2dPermission->id]);

    $customFinanceUser = User::factory()->create([
        'id_role' => $customRole->id,
        'status_aktif' => true,
        'reminder_email_aktif' => true,
    ]);

    $service = app(PengadaanNotificationService::class);
    $sent = $service->kirimKePeran('keuangan', 'Tagihan Siap Dicairkan', 'Segera proses pencairan dana SP2D.', 'pembayaran');

    $matchedNotif = $sent->firstWhere('id_user', $customFinanceUser->id_user);
    expect($matchedNotif)->not->toBeNull();
    expect($matchedNotif->action_url)->toBe(route('pembayaran.index'));
    expect($matchedNotif->action_label)->toBe('Pencairan Dana ↗');
    Notification::assertSentTo($customFinanceUser, PengingatPengadaanNotification::class);

    $admin = User::factory()->admin()->create([
        'id_role' => Role::where('nama_role', 'admin')->value('id'),
    ]);

    $this->post(route('register'), [
        'nip' => '199502022026011009',
        'username' => 'pegawai_baru_uji',
        'email' => 'pegawai.baru@pnj.ac.id',
        'nama' => 'Pegawai Baru Uji',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
        'jabatan' => 'Dosen',
        'kode_unit' => 'TIK',
        'no_hp' => '081234567890',
    ])->assertRedirect(route('login'));

    Notification::assertSentTo($admin, PengingatPengadaanNotification::class);
    expect(NotifikasiUser::where('id_user', $admin->id_user)->count())->toBe(1);
});

it('sends single non-duplicated spk notifications when uploading contract and creates recurring yearly schedule', function (): void {
    Notification::fake();
    Storage::fake('public');

    $ppbj = User::factory()->withRole(User::ROLE_PPBJ)->create();
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create();
    $keuangan = User::factory()->withRole(User::ROLE_KEUANGAN)->create();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '010/PL3/TIK/2026',
        'judul_pengadaan' => 'Lisensi Software Tahunan Lab',
        'tujuan_pengadaan_ringkas' => 'Perpanjangan tahunan lisensi perangkat lunak lab.',
        'file_permohonan' => 'permohonan/test.pdf',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'disetujui',
        'estimasi_total_pagu' => 120000000,
        'kode_unit' => 'TIK',
        'id_user' => $pengusul->id_user,
    ]);

    $vendor = Vendor::create([
        'nama_vendor' => 'PT Teknologi Nusantara',
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-TEST-2026-001',
        'nama_paket' => 'Lisensi Software Tahunan Lab',
        'jenis_pengadaan' => 'jasa_lainnya',
        'platform_pengadaan' => 'e_katalog',
        'mekanisme_pengadaan' => 'e_purchasing',
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'nilai_hps' => 120000000,
        'alokasi_pagu_paket' => 120000000,
        'nominal_harga_akhir' => 120000000,
        'status_paket' => PaketPengadaan::STATUS_SIAP_UPLOAD_DOKUMEN_PPBJ,
        'nomor_surat' => $permohonan->nomor_surat,
        'id_user_ppbj' => $ppbj->id_user,
        'id_vendor' => $vendor->id,
    ]);

    $this->actingAs($ppbj)->post(route('kontrak.store'), [
        'kode_paket' => $paket->nomor_paket,
        'nama_vendor' => $vendor->nama_vendor,
        'tipe_dokumen' => 'surat_kontrak',
        'nomor_kontrak_spk' => 'SPK-2026-001',
        'sifat_kontrak' => 'kontrak_tahunan',
        'nilai_kontrak' => 115000000,
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2027-11-01',
        'jadwal_pengiriman' => '2026-11-10',
        'file_spk' => UploadedFile::fake()->create('kontrak.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('kontrak.index'));

    // Harus tepat 1 notifikasi ke Perlengkapan, 1 ke Keuangan, 1 ke Pengusul (bukan 2-3 duplikat)
    expect(NotifikasiUser::where('id_user', $perlengkapan->id_user)->count())->toBe(1);
    expect(NotifikasiUser::where('id_user', $keuangan->id_user)->count())->toBe(1);
    expect(NotifikasiUser::where('id_user', $pengusul->id_user)->count())->toBe(1);

    Notification::assertSentToTimes($perlengkapan, PengingatPengadaanNotification::class, 1);
    Notification::assertSentToTimes($keuangan, PengingatPengadaanNotification::class, 1);

    $recurringSchedule = Reminder::where('nomor_paket', $paket->nomor_paket)
        ->where('tipe_reminder', Reminder::TIPE_PENGIRIMAN)
        ->whereNull('parent_reminder_id')
        ->first();

    expect($recurringSchedule)->not->toBeNull();
    expect($recurringSchedule->is_recurring)->toBeTrue();
    expect($recurringSchedule->interval_bulan)->toBe(12);
    expect($recurringSchedule->is_active)->toBeTrue();
});

it('processes due recurring and payment reminders via artisan command and stops upon settlement', function (): void {
    Notification::fake();

    $ppbj = User::factory()->withRole(User::ROLE_PPBJ)->create();
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create();
    $keuangan = User::factory()->withRole(User::ROLE_KEUANGAN)->create();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '011/PL3/TIK/2026',
        'judul_pengadaan' => 'Layanan Internet Bulanan',
        'tujuan_pengadaan_ringkas' => 'Layanan bandwidth bulanan kampus.',
        'file_permohonan' => 'permohonan/net.pdf',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'disetujui',
        'estimasi_total_pagu' => 90000000,
        'kode_unit' => 'TIK',
        'id_user' => $pengusul->id_user,
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-RECUR-2026-002',
        'nama_paket' => 'Layanan Internet Bulanan',
        'jenis_pengadaan' => 'jasa_lainnya',
        'platform_pengadaan' => 'e_katalog',
        'mekanisme_pengadaan' => 'e_purchasing',
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'nilai_hps' => 90000000,
        'alokasi_pagu_paket' => 90000000,
        'status_paket' => PaketPengadaan::STATUS_SPK_TERBIT,
        'nomor_surat' => $permohonan->nomor_surat,
        'id_user_ppbj' => $ppbj->id_user,
    ]);

    KontrakSPK::create([
        'nomor_spk' => 'SPK-RECUR-002',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $ppbj->id_user,
        'tipe_dokumen' => 'kontrak',
        'sifat_kontrak' => 'termin_bulanan',
        'status_spk' => 'aktif',
        'tanggal_spk' => now()->subDays(35)->toDateString(),
        'jadwal_pengiriman' => now()->subDay()->toDateString(),
        'nilai_kontrak' => 90000000,
        'file_spk' => 'spk/recur.pdf',
    ]);

    $pembayaran = Pembayaran::create([
        'nomor_pembayaran' => 'PAY-DUE-001',
        'nomor_paket' => $paket->nomor_paket,
        'id_user_pengaju' => $ppbj->id_user,
        'tahap_termin' => 1,
        'nominal_pengajuan' => 15000000,
        'tanggal_pengajuan' => now()->subDays(30)->toDateString(),
        'jatuh_tempo' => now()->toDateString(),
        'status_pembayaran' => Pembayaran::STATUS_PERINTAH_BAYAR,
    ]);

    $this->artisan('reminder:proses')->assertSuccessful();

    $monthlySchedule = Reminder::where('nomor_paket', $paket->nomor_paket)
        ->where('tipe_reminder', Reminder::TIPE_PENGIRIMAN)
        ->whereNull('parent_reminder_id')
        ->firstOrFail();

    expect($monthlySchedule->is_active)->toBeTrue();
    expect($monthlySchedule->last_triggered_at)->not->toBeNull();
    expect($monthlySchedule->waktu_trigger->isFuture())->toBeTrue();

    // Ada notifikasi occurrence untuk keuangan/perlengkapan dari jadwal jatuh tempo & bulanan
    expect(NotifikasiUser::where('id_user', $keuangan->id_user)->count())->toBeGreaterThanOrEqual(2);
    expect(NotifikasiUser::where('id_user', $perlengkapan->id_user)->count())->toBeGreaterThanOrEqual(1);
    Notification::assertSentTo($keuangan, PengingatPengadaanNotification::class);

    // Simulasikan siklus berikutnya saat sudah waktunya lagi -> tidak boleh gagal unique constraint
    $monthlySchedule->waktu_trigger = now()->subMinute();
    $monthlySchedule->save();
    $countBeforeSecondCycle = NotifikasiUser::where('id_user', $perlengkapan->id_user)->count();

    $this->artisan('reminder:proses')->assertSuccessful();
    expect(NotifikasiUser::where('id_user', $perlengkapan->id_user)->count())->toBe($countBeforeSecondCycle + 1);

    // Setelah pelunasan SP2D (TransaksiPencairan), jadwal terkait paket & pembayaran otomatis nonaktif
    TransaksiPencairan::create([
        'nomor_transaksi_bank' => 'SP2D-999',
        'nomor_pembayaran' => $pembayaran->nomor_pembayaran,
        'id_user' => $keuangan->id_user,
        'tanggal_transfer' => now(),
        'nominal_transfer' => 15000000,
        'file_bukti_transfer' => 'transfer/bukti.pdf',
    ]);

    expect(
        Reminder::where('nomor_paket', $paket->nomor_paket)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true)
            ->count()
    )->toBe(0);
});

it('deactivates one-shot delivery reminders once accepted bast is recorded', function (): void {
    $ppbj = User::factory()->withRole(User::ROLE_PPBJ)->create();
    $pengusul = User::factory()->withRole(User::ROLE_USER)->create();
    $perlengkapan = User::factory()->withRole(User::ROLE_PERLENGKAPAN)->create();

    $permohonan = PermohonanPengadaan::create([
        'nomor_surat' => '012/PL3/TIK/2026',
        'judul_pengadaan' => 'Pengadaan Proyektor Ruang Kelas',
        'tujuan_pengadaan_ringkas' => 'Proyektor HD',
        'file_permohonan' => 'permohonan/proj.pdf',
        'status_permohonan' => 'diproses_ppbj',
        'status_anggaran' => 'disetujui',
        'estimasi_total_pagu' => 75000000,
        'kode_unit' => 'TIK',
        'id_user' => $pengusul->id_user,
    ]);

    $paket = PaketPengadaan::create([
        'nomor_paket' => 'PKT-BAST-2026-003',
        'nama_paket' => 'Pengadaan Proyektor Ruang Kelas',
        'jenis_pengadaan' => 'barang',
        'platform_pengadaan' => 'spse',
        'mekanisme_pengadaan' => 'pengadaan_langsung',
        'jalur_routing' => PaketPengadaan::ROUTING_PP,
        'nilai_hps' => 75000000,
        'alokasi_pagu_paket' => 75000000,
        'status_paket' => PaketPengadaan::STATUS_SPK_TERBIT,
        'nomor_surat' => $permohonan->nomor_surat,
        'id_user_ppbj' => $ppbj->id_user,
    ]);

    KontrakSPK::create([
        'nomor_spk' => 'SPK-BAST-003',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $ppbj->id_user,
        'tipe_dokumen' => 'spk',
        'sifat_kontrak' => 'sekali_selesai',
        'status_spk' => 'aktif',
        'tanggal_spk' => now()->subDays(10)->toDateString(),
        'jadwal_pengiriman' => now()->subDay()->toDateString(),
        'nilai_kontrak' => 74000000,
        'file_spk' => 'spk/proj.pdf',
    ]);

    PenerimaanBarang::create([
        'nomor_bast' => 'BAST-003',
        'nomor_paket' => $paket->nomor_paket,
        'id_user' => $perlengkapan->id_user,
        'tanggal_penerimaan' => now()->toDateString(),
        'status_fisik' => 'terima',
        'kategori_belanja' => PenerimaanBarang::KATEGORI_BELANJA_MODAL,
        'kondisi_barang' => 'terima',
    ]);

    Notification::fake();
    $this->artisan('reminder:proses')->assertSuccessful();

    Notification::assertNothingSent();
    expect(
        Reminder::where('nomor_paket', $paket->nomor_paket)
            ->whereNull('parent_reminder_id')
            ->where('is_active', true)
            ->count()
    )->toBe(0);
});

it('returns complete api polling payload, renders read-only schedule tab without test button, and has no duplicate flash alerts', function (): void {
    Notification::fake();

    // Halaman register (standalone, status guest) hanya menampilkan 1 daftar error validasi
    $registerHtml = $this->from(route('register'))
        ->followingRedirects()
        ->post(route('register'), ['email' => 'bukan-email'])
        ->assertOk()
        ->getContent();

    expect(substr_count($registerHtml, 'Terjadi kesalahan input:'))->toBe(1);
    expect(substr_count($registerHtml, 'Pendaftaran gagal'))->toBe(0);

    $ppbj = User::factory()->withRole(User::ROLE_PPBJ)->create();

    $service = app(PengadaanNotificationService::class);
    $first = $service->kirimKeUser($ppbj, 'Notif Pertama', 'Isi notifikasi pertama', 'verifikasi');
    $second = $service->kirimKeUser($ppbj, 'Notif Kedua', 'Isi notifikasi kedua', 'pengingat');

    $this->actingAs($ppbj)
        ->getJson(route('notifikasi.terbaru', ['since_id' => $first->id]))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('latest_id', $second->id)
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.judul', 'Notif Kedua')
        ->assertJsonStructure([
            'data' => [
                ['id', 'judul', 'pesan', 'badge', 'referensi', 'action_label', 'action_url', 'link', 'created_at', 'is_recurring'],
            ],
            'recent',
        ]);

    // Rute uji coba harus sudah tidak tersedia
    $this->actingAs($ppbj)->post('/notifikasi/tes-kirim')->assertStatus(404);

    // Halaman /notifikasi?tab=jadwal dapat diakses tanpa tombol uji coba
    $notifResponse = $this->actingAs($ppbj)->get(route('notifikasi.index', ['tab' => 'jadwal']));
    $notifResponse->assertOk();
    $notifResponse->assertSee('Jadwal Pengingat');
    $notifResponse->assertDontSee('Uji Coba Notifikasi Masuk');

    // Pesan flash sukses/error tidak boleh muncul lebih dari 1 kali di halaman yang mewarisi layouts.app
    $pagesToCheck = [
        route('permohonan.index'),
        route('profile.index'),
        route('notifikasi.index'),
    ];

    foreach ($pagesToCheck as $url) {
        $html = $this->actingAs($ppbj)
            ->withSession(['success' => 'Pesan Sukses Unik Sistem'])
            ->get($url)
            ->assertOk()
            ->getContent();

        expect(substr_count($html, 'Pesan Sukses Unik Sistem'))->toBe(1);
    }
});

it('uses id_role as single role source without users.role or vendors.npwp columns', function (): void {
    expect(Schema::hasColumn('users', 'role'))->toBeFalse();
    expect(Schema::hasColumn('vendors', 'npwp'))->toBeFalse();
    expect(Permission::where('nama_permission', 'reminder.manage')->exists())->toBeFalse();

    $admin = User::factory()->admin()->create();
    $ppRole = Role::where('nama_role', User::ROLE_PP)->firstOrFail();
    $userRole = Role::where('nama_role', User::ROLE_USER)->firstOrFail();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'nip' => '199101012020011001',
        'username' => 'pejabat_pp_tunggal',
        'email' => 'pp.tunggal@pnj.ac.id',
        'nama' => 'Pejabat Pengadaan Uji',
        'password' => 'rahasia123',
        'jabatan' => 'Pejabat Pengadaan',
        'id_role' => $ppRole->id,
        'kode_unit' => 'TIK',
        'no_hp' => '081234567891',
        'status_aktif' => '1',
    ])->assertRedirect(route('admin.users.index'));

    $createdPp = User::where('username', 'pejabat_pp_tunggal')->firstOrFail();
    expect($createdPp->id_role)->toBe($ppRole->id);
    expect($createdPp->hasRole(User::ROLE_PP))->toBeTrue();

    // Duplikasi PP aktif kedua harus ditolak oleh aturan FR 2.1 berbasis id_role
    $this->actingAs($admin)->post(route('admin.users.store'), [
        'nip' => '199101012020011002',
        'username' => 'pejabat_pp_kedua',
        'email' => 'pp.kedua@pnj.ac.id',
        'nama' => 'Calon PP Kedua',
        'password' => 'rahasia123',
        'jabatan' => 'Pejabat Pengadaan',
        'id_role' => $ppRole->id,
        'kode_unit' => 'TIK',
        'no_hp' => '081234567892',
        'status_aktif' => '1',
    ])->assertSessionHasErrors('id_role');

    // Alihkan pengguna ke peran Pengusul dan pastikan relasi roleEntity ikut terbarui
    $this->actingAs($admin)->put(route('admin.users.update', $createdPp), [
        'nip' => $createdPp->nip,
        'username' => $createdPp->username,
        'email' => $createdPp->email,
        'nama' => $createdPp->nama,
        'jabatan' => $createdPp->jabatan,
        'id_role' => $userRole->id,
        'kode_unit' => 'TIK',
        'no_hp' => $createdPp->no_hp,
        'status_aktif' => '1',
    ])->assertRedirect(route('admin.users.index'));

    expect($createdPp->fresh()->hasRole(User::ROLE_USER))->toBeTrue();
});
