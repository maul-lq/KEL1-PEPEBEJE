<?php

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public/build/manifest.json exists and maps all 4 required assets', function () {
    $manifestPath = public_path('build/manifest.json');
    expect(file_exists($manifestPath))->toBeTrue("Manifest build tidak ditemukan di: {$manifestPath}");

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray('Manifest build tidak dapat di-parse sebagai JSON array');

    $requiredAssets = [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/tes_app.css',
        'resources/js/tes_app.js',
    ];

    foreach ($requiredAssets as $assetKey) {
        expect($manifest)->toHaveKey($assetKey);
        expect($manifest[$assetKey])->toHaveKey('file');

        $builtFile = public_path('build/'.$manifest[$assetKey]['file']);
        expect(file_exists($builtFile))->toBeTrue("Berkas hasil kompilasi tidak ada di disk: {$builtFile}");
    }
});

test('compiled app.css contains Tailwind v4 design tokens and layout utility classes', function () {
    $manifestPath = public_path('build/manifest.json');
    $manifest = json_decode(file_get_contents($manifestPath), true);
    $cssFile = public_path('build/'.$manifest['resources/css/app.css']['file']);

    $cssContent = file_get_contents($cssFile);
    expect(strlen($cssContent))->toBeGreaterThan(50000, 'Ukuran app.css terlalu kecil untuk bundle Tailwind v4');

    // Palet warna institusional SIM-PBJ
    expect($cssContent)->toContain('#213153'); // Navy Primary
    expect($cssContent)->toContain('#0969da'); // Brand Blue
    expect($cssContent)->toContain('#087F79'); // Brand Teal
    expect($cssContent)->toContain('#F4F8FA'); // Ice Canvas

    // Utility classes dan aturan penting
    expect($cssContent)->toContain('x-cloak');
    expect($cssContent)->toContain('font-iceland');
    expect($cssContent)->toContain('sticky');
    expect($cssContent)->toContain('min-h-screen');
    expect($cssContent)->toContain('rounded-xl');
});

test('layouts/app.blade.php is free of cdn.tailwindcss.com and inline style/script tags', function () {
    $layoutPath = resource_path('views/layouts/app.blade.php');
    expect(file_exists($layoutPath))->toBeTrue();

    $layoutContent = file_get_contents($layoutPath);

    // Pastikan CDN Tailwind tidak lagi dimuat
    expect($layoutContent)->not->toContain('cdn.tailwindcss.com');

    // Pastikan Vite assets terdaftar
    expect($layoutContent)->toContain("@vite(['resources/css/app.css', 'resources/js/app.js'])");

    // Pastikan tag style inline tidak ada
    expect($layoutContent)->not->toMatch('/<\s*style\b/i');

    // Pastikan inline script desktopNotificationManager tidak ada (sudah diekstrak ke JS module)
    expect($layoutContent)->not->toContain('function desktopNotificationManager()');

    // Pastikan Alpine binding tetap ada untuk template UI
    expect($layoutContent)->toContain('x-data="desktopNotificationManager()"');
});

test('desktop notification manager is properly modularized and bundled in app.js', function () {
    $modulePath = resource_path('js/modules/desktop_notification.js');
    expect(file_exists($modulePath))->toBeTrue('Berkas modul desktop_notification.js tidak ditemukan');

    $moduleContent = file_get_contents($modulePath);
    expect($moduleContent)->toContain('export function desktopNotificationManager()');
    expect($moduleContent)->toContain('/api/notifikasi-terbaru');

    // Periksa entrypoint app.js
    $appJsPath = resource_path('js/app.js');
    $appJsContent = file_get_contents($appJsPath);
    expect($appJsContent)->toContain("import { desktopNotificationManager } from './modules/desktop_notification.js'");
    expect($appJsContent)->toContain('window.desktopNotificationManager = desktopNotificationManager');
    expect($appJsContent)->toContain("window.Alpine.data('desktopNotificationManager', desktopNotificationManager)");

    // Periksa bundle app.js yang terkompilasi
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $compiledJs = file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['file']));
    expect($compiledJs)->toContain('desktopNotificationManager');
    expect($compiledJs)->toContain('/api/notifikasi-terbaru');
});

test('dual mode switching behaves correctly for backend and frontend view resolution', function () {
    $finder = app('view.finder');

    // 1. Mode Backend
    config(['app.dev_viewmode' => 'backend']);
    $dashboardBackend = $finder->find('dashboard.index');
    expect(str_replace('\\', '/', $dashboardBackend))->toContain('tes_index.blade.php');

    $notifBackend = $finder->find('notifikasi.index');
    expect(str_replace('\\', '/', $notifBackend))->toContain('tes_index.blade.php');

    // 2. Mode Frontend
    config(['app.dev_viewmode' => 'frontend']);
    $dashboardFrontend = $finder->find('dashboard.index');
    expect(str_replace('\\', '/', $dashboardFrontend))->toEndWith('dashboard/index.blade.php');

    $notifFrontend = $finder->find('notifikasi.index');
    expect(str_replace('\\', '/', $notifFrontend))->toEndWith('notifikasi/index.blade.php');
});

test('notifikasi page renders successfully under DEV_VIEWMODE=backend and DEV_VIEWMODE=frontend', function () {
    $unit = Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
    );

    $roleUser = Role::firstOrCreate(
        ['nama_role' => 'user'],
        ['label' => 'Pemohon / Jurusan', 'is_system' => true]
    );

    $user = User::factory()->create([
        'role' => 'user',
        'id_role' => $roleUser->id,
        'kode_unit' => $unit->kode_unit,
        'status_aktif' => true,
        'status_registrasi' => 'disetujui',
    ]);

    // Uji Mode Backend
    config(['app.dev_viewmode' => 'backend']);
    $backendResponse = $this->actingAs($user)->get(route('notifikasi.index'));
    $backendResponse->assertOk();
    $backendResponse->assertSee('Pusat Notifikasi, Pengingat Jadwal');
    $backendResponse->assertSee('Mode Tampilan Aktif:');

    // Uji Mode Frontend
    config(['app.dev_viewmode' => 'frontend']);
    $frontendResponse = $this->actingAs($user)->get(route('notifikasi.index'));
    $frontendResponse->assertOk();
    $frontendResponse->assertSee('Notifikasi dan Reminder');
    $frontendResponse->assertSee('Aktifkan Notifikasi Desktop');
    $frontendResponse->assertSee('build/assets/app-');
    $frontendResponse->assertDontSee('cdn.tailwindcss.com');
});

test('critical utility classes in layouts/app.blade.php are compiled into app.css', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    // Extract all class attributes, removing Blade echo statements
    preg_match_all('/class=["\']([^"\']+)["\']/', $layout, $matches);
    $classes = [];
    foreach ($matches[1] as $attr) {
        // Also extract classes inside Blade ternary strings
        if (preg_match_all('/\'([^\']+)\'|"([^"]+)"/', $attr, $stringMatches)) {
            $allStrings = array_merge($stringMatches[1], $stringMatches[2]);
            foreach ($allStrings as $s) {
                foreach (preg_split('/\s+/', $s) as $sub) {
                    $sub = trim($sub);
                    if ($sub !== '' && preg_match('/^[a-zA-Z0-9_\-\[\#\:\/\.\%]+$/', $sub)) {
                        $classes[$sub] = true;
                    }
                }
            }
        }

        $cleanAttr = preg_replace('/\{\{.*?\}\}/s', ' ', $attr);
        $cleanAttr = preg_replace('/\{!!.*?!!\}/s', ' ', $cleanAttr);
        foreach (preg_split('/\s+/', $cleanAttr) as $cls) {
            $cls = trim($cls);
            if ($cls !== '' && preg_match('/^[a-zA-Z0-9_\-\[\#\:\/\.\%]+$/', $cls)) {
                $classes[$cls] = true;
            }
        }
    }

    expect(count($classes))->toBeGreaterThan(50, 'Jumlah class pada layouts/app.blade.php kurang dari yang diharapkan');

    $missing = [];
    foreach (array_keys($classes) as $cls) {
        $escaped = preg_replace('/([\[\]\#\:\/\.\%])/', '\\\\$1', $cls);
        if (! str_contains($css, $escaped) && ! str_contains($css, $cls)) {
            $missing[] = $cls;
        }
    }

    expect($missing)->toBeEmpty('Ditemukan class pada layouts/app.blade.php yang tidak terkompilasi dalam app.css: '.implode(', ', $missing));
});

test('dashboard and permohonan pages render without cdn.tailwindcss.com under frontend mode', function () {
    $unit = Unit::firstOrCreate(
        ['kode_unit' => 'TIK'],
        ['nama_unit' => 'Jurusan Teknik Informatika dan Komputer', 'jenis_unit' => 'jurusan']
    );

    $roleUser = Role::firstOrCreate(
        ['nama_role' => 'user'],
        ['label' => 'Pemohon / Jurusan', 'is_system' => true]
    );

    $user = User::factory()->create([
        'role' => 'user',
        'id_role' => $roleUser->id,
        'kode_unit' => $unit->kode_unit,
        'status_aktif' => true,
        'status_registrasi' => 'disetujui',
    ]);

    config(['app.dev_viewmode' => 'frontend']);

    // 1. Dashboard
    $dashRes = $this->actingAs($user)->get(route('dashboard'));
    $dashRes->assertOk();
    $dashRes->assertDontSee('cdn.tailwindcss.com');
    $dashRes->assertSee('build/assets/app-');

    // 2. Permohonan Index
    $permRes = $this->actingAs($user)->get(route('permohonan.index'));
    $permRes->assertOk();
    $permRes->assertDontSee('cdn.tailwindcss.com');
    $permRes->assertSee('build/assets/app-');
});
