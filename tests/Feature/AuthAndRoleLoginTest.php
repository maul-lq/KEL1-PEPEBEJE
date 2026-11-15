<?php

use App\Models\AnggaranMAK;
use App\Models\AuditLog;
use App\Models\NotifikasiUser;
use App\Models\PaketPengadaan;
use App\Models\Permission;
use App\Models\PermohonanPengadaan;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seeds only master units, roles, permissions, and accounts without dummy procurement data', function (): void {
    expect(Unit::count())->toBe(16)
        ->and(Role::count())->toBe(10)
        ->and(Permission::count())->toBe(38)
        ->and(User::count())->toBe(13)
        ->and(PermohonanPengadaan::count())->toBe(0)
        ->and(PaketPengadaan::count())->toBe(0)
        ->and(AnggaranMAK::count())->toBe(0)
        ->and(Vendor::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0)
        ->and(Reminder::count())->toBe(0)
        ->and(NotifikasiUser::count())->toBe(0);

    expect(User::whereNull('id_role')->count())->toBe(0);
});

it('allows every seeded account to log in and open its dashboard', function (): void {
    $usernames = [
        'admin',
        'user_pengaju',
        'staff_b2',
        'wadir2',
        'perencanaan',
        'ppbj',
        'pp_user',
        'ppk_1',
        'ppk_2',
        'ppk_3',
        'ppk_4',
        'perlengkapan',
        'keuangan',
    ];

    foreach ($usernames as $username) {
        $user = User::where('username', $username)->firstOrFail();

        $loginResponse = $this->post(route('login'), [
            'login' => $username,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
});

it('supports login by email and nip and allows admin to open role and user management', function (): void {
    $this->post(route('login'), [
        'login' => 'admin@pnj.ac.id',
        'password' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('admin.roles.index'))->assertOk();
    $this->get(route('admin.users.index'))->assertOk();
    $this->get(route('admin.persetujuan-registrasi.index'))->assertOk();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $pengaju = User::where('username', 'user_pengaju')->firstOrFail();

    $this->post(route('login'), [
        'login' => $pengaju->nip,
        'password' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($pengaju);
});
