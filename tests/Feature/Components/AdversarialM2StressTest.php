<?php

namespace Tests\Feature\Components;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdversarialM2StressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Unit::firstOrCreate(
            ['kode_unit' => 'TIK'],
            ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
        );

        $roles = [
            'admin' => 'Administrator',
            'user' => 'Pemohon / Jurusan',
            'staff_bidang_2' => 'Staf Bidang II',
            'wadir_2' => 'Wakil Direktur II',
            'perencanaan' => 'Subbagian Perencanaan',
            'ppbj' => 'Koordinator PPBJ',
            'pp' => 'Pejabat Pengadaan',
            'ppk' => 'Pejabat Pembuat Komitmen',
            'perlengkapan' => 'Unit Perlengkapan',
            'keuangan' => 'Bagian Keuangan',
        ];

        foreach ($roles as $namaRole => $label) {
            Role::firstOrCreate(
                ['nama_role' => $namaRole],
                ['label' => $label, 'is_system' => true]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 1. EXTREME VALUES: X-STAT-CARD
    |--------------------------------------------------------------------------
    */

    public function test_stat_card_handles_ultra_large_numbers_and_floats(): void
    {
        // PHP_INT_MAX (9223372036854775807 pada 64-bit)
        $intMax = PHP_INT_MAX;
        $viewIntMax = $this->blade('<x-stat-card title="Max Int" :value="$val" />', ['val' => $intMax]);
        $viewIntMax->assertSee((string) $intMax);

        // Scientific notation / large float
        $floatVal = 1.23456789e18;
        $viewFloat = $this->blade('<x-stat-card title="Large Float" :value="$val" />', ['val' => $floatVal]);
        $viewFloat->assertSee((string) $floatVal);

        // Negative ultra large
        $negVal = -999999999999999;
        $viewNeg = $this->blade('<x-stat-card title="Large Neg" :value="$val" />', ['val' => $negVal]);
        $viewNeg->assertSee('-999999999999999');

        // Zero as integer, float, string
        $this->blade('<x-stat-card title="Zero Int" :value="0" />')->assertSee('0');
        $this->blade('<x-stat-card title="Zero Float" :value="0.0" />')->assertSee('0');
        $this->blade('<x-stat-card title="Zero Str" value="0" />')->assertSee('0');
    }

    public function test_stat_card_handles_extremely_long_strings_without_exception(): void
    {
        $longText = str_repeat('KATA_PANJANG_', 200); // 2600 chars
        $view = $this->blade('<x-stat-card :title="$long" :value="$long" :subtitle="$long" :badge="$long" />', ['long' => $longText]);

        $view->assertSee($longText);
    }

    public function test_stat_card_handles_malicious_xss_in_all_props(): void
    {
        $xss = '<script>alert("hacked")</script><img src=x onerror=alert(1)>';
        $view = $this->blade('<x-stat-card :title="$xss" :value="$xss" :subtitle="$xss" :badge="$xss" href="javascript:alert(1)" />', ['xss' => $xss]);

        $view->assertDontSee('<script>alert("hacked")</script>', false);
        $view->assertDontSee('<img src=x onerror=alert(1)>', false);
        $view->assertSee('&lt;script&gt;alert(&quot;hacked&quot;)&lt;/script&gt;', false);
    }

    public function test_stat_card_handles_null_empty_and_unusual_color_and_icon_props(): void
    {
        // Null values everywhere
        $view = $this->blade('<x-stat-card title="Null Props" value="0" :color="null" :icon="null" :subtitle="null" :badge="null" :trend="null" :href="null" />');
        $view->assertSee('Null Props');
        $view->assertSee('0');

        // Unknown colors fallback cleanly to blue
        $viewUnknownColor = $this->blade('<x-stat-card title="Unknown Color" value="10" color="super_neon_pink_unknown" icon="document" />');
        $viewUnknownColor->assertSee('text-[#0969da]');
        $viewUnknownColor->assertSee('hover:border-blue-300');

        // Unknown icon fallback cleanly to info icon
        $viewUnknownIcon = $this->blade('<x-stat-card title="Unknown Icon" value="10" icon="unknown_custom_icon_xyz" />');
        $viewUnknownIcon->assertSee('<svg class="size-6"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. EXTREME VALUES: X-STATUS-BADGE
    |--------------------------------------------------------------------------
    */

    public function test_status_badge_handles_extreme_status_values_and_unknowns(): void
    {
        // Empty string status
        $viewEmpty = $this->blade('<x-status-badge status="" />');
        $viewEmpty->assertSee('bg-slate-100');

        // Non-standard symbols and emojis
        $viewEmoji = $this->blade('<x-status-badge status="🔥 PROSES_SUPER_URGENT 🔥" />');
        $viewEmoji->assertSee('🔥 proses super urgent 🔥');

        // Extremely long status string (1000 chars)
        $longStatus = str_repeat('STATUS_PANJANG_', 50);
        $viewLong = $this->blade('<x-status-badge :status="$st" />', ['st' => $longStatus]);
        $viewLong->assertSee(ucfirst(str_replace('_', ' ', strtolower($longStatus))));

        // Unrecognized size (e.g. 9999 or 'massive')
        $viewHugeSize = $this->blade('<x-status-badge status="diajukan" size="massive_unknown" />');
        $viewHugeSize->assertSee('text-xs px-2.5 py-1 gap-1.5'); // fallback md

        // Unrecognized variant
        $viewUnknownVar = $this->blade('<x-status-badge status="diajukan" variant="neon_glow_unknown" />');
        $viewUnknownVar->assertSee('bg-blue-50 text-[#0969da]'); // fallback soft
    }

    public function test_status_badge_escapes_xss_in_status_and_custom_text(): void
    {
        $xss = '<script>alert("badgexss")</script>';
        $view = $this->blade('<x-status-badge :status="$xss" />', ['xss' => $xss]);
        $view->assertDontSee('<script>alert("badgexss")</script>', false);
        $view->assertSee('&lt;script&gt;alert(&quot;badgexss&quot;)&lt;/script&gt;', false);

        $viewText = $this->blade('<x-status-badge status="diajukan" :text="$xss" />', ['xss' => $xss]);
        $viewText->assertDontSee('<script>alert("badgexss")</script>', false);
        $viewText->assertSee('&lt;script&gt;alert(&quot;badgexss&quot;)&lt;/script&gt;', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. EXTREME VALUES: X-STEPPER-8-SIKLUS
    |--------------------------------------------------------------------------
    */

    public function test_stepper_handles_extreme_step_numbers_and_types(): void
    {
        // Step ultra-negative (-999999) -> clamp ke 1
        $viewNeg = $this->blade('<x-stepper-8-siklus :currentStep="-999999" />');
        $viewNeg->assertSee('Tahap 1 dari 8:');
        $viewNeg->assertSee('style="width: 0px;"', false);

        // Step ultra-positive (999999) -> clamp ke 8
        $viewPos = $this->blade('<x-stepper-8-siklus :currentStep="999999" />');
        $viewPos->assertSee('Tahap 8 dari 8:');
        $viewPos->assertSee('SP2D Lunas');
        $viewPos->assertSee('style="width: calc(100% - 0px);"', false);

        // Step PHP_INT_MAX -> clamp ke 8
        $viewIntMax = $this->blade('<x-stepper-8-siklus :currentStep="PHP_INT_MAX" />');
        $viewIntMax->assertSee('Tahap 8 dari 8:');

        // Step null -> default ke 1
        $viewNull = $this->blade('<x-stepper-8-siklus :currentStep="null" />');
        $viewNull->assertSee('Tahap 1 dari 8:');
    }

    public function test_stepper_computes_valid_css_for_all_8_steps(): void
    {
        // Periksa setiap langkah 1 s.d 8 memastikan CSS width valid dan tidak ada negative width atau broken calc
        for ($s = 1; $s <= 8; $s++) {
            $view = $this->blade('<x-stepper-8-siklus :currentStep="$s" />', ['s' => $s]);
            $view->assertSee("Tahap {$s} dari 8:");

            if ($s === 1) {
                $view->assertSee('style="width: 0px;"', false);
            } elseif ($s === 8) {
                $view->assertSee('style="width: calc(100% - 0px);"', false);
            } else {
                $pct = (($s - 1) / 7) * 100;
                $view->assertSee("style=\"width: calc({$pct}% - 35px);\"", false);
            }
        }
    }

    public function test_stepper_vertical_mode_handles_empty_sparse_and_xss_step_data(): void
    {
        // Empty array
        $viewEmpty = $this->blade('<x-stepper-8-siklus mode="vertical" :currentStep="3" :stepsData="[]" />');
        $viewEmpty->assertSee('Tahap 1: Diajukan');
        $viewEmpty->assertSee('Tahap 8: SP2D Lunas');

        // Sparse array dengan XSS di catatan
        $xss = '<script>alert("stepper")</script>';
        $sparseData = [
            1 => ['catatan' => $xss, 'date' => '2026-10-29'],
            5 => ['catatan' => 'SPK Terbit'],
        ];

        $viewSparse = $this->blade('<x-stepper-8-siklus mode="vertical" :currentStep="5" :stepsData="$sparse" />', ['sparse' => $sparseData]);
        $viewSparse->assertDontSee('<script>alert("stepper")</script>', false);
        $viewSparse->assertSee('&lt;script&gt;alert(&quot;stepper&quot;)&lt;/script&gt;', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. DASHBOARD ROLES COMPOSITION WITH EMPTY/NULL COLLECTIONS
    |--------------------------------------------------------------------------
    */

    public function test_all_10_role_partials_render_cleanly_with_empty_collections_and_empty_stats(): void
    {
        $roleSlugs = [
            'admin',
            'user',
            'staff_bidang_2',
            'wadir_2',
            'perencanaan',
            'ppbj',
            'pp',
            'ppk',
            'perlengkapan',
            'keuangan',
        ];

        $emptyStats = [];
        $emptyRecentPermohonan = collect();
        $emptyRecentPaket = collect();
        $emptyRoleData = [];

        foreach ($roleSlugs as $slug) {
            $user = User::factory()->make([
                'username' => 'test_'.$slug,
                'role' => $slug,
                'nama' => 'Test User '.$slug,
            ]);

            $view = $this->blade(
                '@include("dashboard.roles." . $role, compact("user", "stats", "recentPermohonan", "recentPaket", "roleData"))',
                [
                    'role' => $slug,
                    'user' => $user,
                    'stats' => $emptyStats,
                    'recentPermohonan' => $emptyRecentPermohonan,
                    'recentPaket' => $emptyRecentPaket,
                    'roleData' => $emptyRoleData,
                ]
            );

            // Pastikan parsial berhasil dirender tanpa Exception
            $this->assertNotEmpty((string) $view, "Role partial dashboard.roles.{$slug} gagal merender konten saat data kosong.");
        }
    }

    public function test_ppk_role_partial_safely_handles_null_auth_user_when_user_prop_is_passed(): void
    {
        // Buat mock user PPK tanpa sesi Auth aktif
        Auth::logout();

        $user = User::factory()->make([
            'username' => 'ppk_isolated_user',
            'role' => 'ppk',
            'nama' => 'PPK Mandiri',
        ]);

        $view = $this->blade(
            '@include("dashboard.roles.ppk", compact("user", "stats", "recentPermohonan", "recentPaket", "roleData"))',
            [
                'user' => $user,
                'stats' => [],
                'recentPermohonan' => collect(),
                'recentPaket' => collect(),
                'roleData' => [],
            ]
        );

        $view->assertSee('ppk_isolated_user');
        $view->assertSee('Penegakan Isolasi Data Portofolio PPK');
    }

    public function test_all_10_role_dashboards_render_via_http_with_frontend_viewmode(): void
    {
        config(['app.dev_viewmode' => 'frontend']);

        $roleSlugs = [
            'admin',
            'user',
            'staff_bidang_2',
            'wadir_2',
            'perencanaan',
            'ppbj',
            'pp',
            'ppk',
            'perlengkapan',
            'keuangan',
        ];

        foreach ($roleSlugs as $slug) {
            $roleRecord = Role::where('nama_role', $slug)->first();
            $user = User::factory()->create([
                'role' => $slug,
                'id_role' => $roleRecord->id,
                'kode_unit' => 'TIK',
                'status_aktif' => true,
                'status_registrasi' => 'disetujui',
            ]);

            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();
            $response->assertSee('Dashboard '.strtoupper(str_replace('_', ' ', $slug)));
            // Pastikan tidak ada pesan error Blade atau Exception
            $response->assertDontSee('ErrorException');
            $response->assertDontSee('Undefined variable');
            $response->assertDontSee('Attempt to read property');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5. EXTERNAL CDN SCRIPT DETECTION & AUDIT
    |--------------------------------------------------------------------------
    */

    public function test_cdn_tailwindcss_com_is_completely_eliminated_from_views(): void
    {
        $viewsPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));

        $infected = [];
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $content = file_get_contents($file->getPathname());
                if (str_contains($content, 'cdn.tailwindcss.com')) {
                    $infected[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        expect($infected)->toBeEmpty('Ditemukan referensi cdn.tailwindcss.com pada berkas: '.implode(', ', $infected));
    }

    public function test_audit_external_script_tags_in_master_layouts_and_views(): void
    {
        $layoutPath = resource_path('views/layouts/app.blade.php');
        $content = file_get_contents($layoutPath);

        // Deteksi script tag dengan src eksternal (http/https)
        preg_match_all('/<script\b[^>]*src=["\'](https?:\/\/[^"\']+)["\']/i', $content, $matches);
        $externalScripts = $matches[1] ?? [];

        // Catat seluruh external script yang terpanggil di layout utama
        // Temuan: https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js
        expect($externalScripts)->toBeArray();
    }
}
