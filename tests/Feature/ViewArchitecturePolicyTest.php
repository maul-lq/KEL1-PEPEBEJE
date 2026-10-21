<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Uji Kepatuhan Arsitektur View Backend Testing (ViewArchitecturePolicyTest)
 *
 * Menegakkan kebijakan arsitektur sistem SIM-PBJ Politeknik Negeri Jakarta
 * sesuai dokumen panduan AGENTS-BACKEND.md:
 * 1. Bagian 1: Aturan TANPA CSS TOTAL (Zero CSS Strict Rule) pada berkas tes_*.blade.php.
 * 2. Bagian 3C: Konfigurasi bundler aset vite.config.js wajib mendaftarkan 4 input utama.
 * 3. Bagian 4: Larangan script inline pada berkas Blade view tes_*.blade.php.
 *
 * Seluruh pemindaian hanya menargetkan berkas sumber Blade berawalan 'tes_' (mode backend testing)
 * dan DILARANG memindai atau membatasi berkas non-tes_* milik tim front end.
 */
class ViewArchitecturePolicyTest extends TestCase
{
    /**
     * Daftar nilai class HTML yang diizinkan (allowlist) jika ditemukan nilai class yang jelas bukan styling.
     * Berdasarkan AGENTS-BACKEND.md bagian 1, antarmuka Zero CSS murni tidak menggunakan class styling.
     *
     * @var list<string>
     */
    private const ALLOWED_CLASSES = [];

    /**
     * Daftar 4 input aset wajib pada vite.config.js sesuai AGENTS-BACKEND.md bagian 3C.
     *
     * @var list<string>
     */
    private const REQUIRED_VITE_INPUTS = [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/tes_app.css',
        'resources/js/tes_app.js',
    ];

    /**
     * Memastikan seluruh berkas view tes_* bebas dari tag <style> internal.
     *
     * Berdasarkan AGENTS-BACKEND.md bagian 1, tampilan pengujian backend wajib 100% bebas dari
     * deklarasi CSS internal agar Overseer dapat menguji fungsionalitas dan logika data murni
     * tanpa terpengaruh beban rendering styling.
     */
    public function test_tes_view_bebas_tag_style_internal(): void
    {
        $violations = $this->scanTesViews(function (string $line): ?string {
            if (preg_match('/<\s*style\b/i', $line)) {
                return 'Ditemukan tag internal <style>. Seluruh styling dilarang pada berkas tes_*; jika diperlukan aset tambahan, letakkan pada resources/css/tes_app.css.';
            }

            return null;
        });

        $message = $this->formatFailureMessage(
            'Larangan Tag <style> Internal pada View tes_* (AGENTS-BACKEND.md Bagian 1)',
            $violations,
            'Hapus seluruh tag <style> ... </style> dari berkas Blade tes_*. Gunakan elemen semantik murni HTML.'
        );

        $this->assertEmpty($violations, $message);
    }

    /**
     * Memastikan seluruh berkas view tes_* bebas dari atribut inline style="..." maupun style='...'.
     *
     * Berdasarkan AGENTS-BACKEND.md bagian 1, penggunaan inline styling dilarang keras untuk
     * menjamin kebersihan DOM semantik yang siap diintegrasikan tim front end di kemudian hari.
     */
    public function test_tes_view_bebas_atribut_style_inline(): void
    {
        $violations = $this->scanTesViews(function (string $line): ?string {
            if (preg_match('/\bstyle\s*=\s*["\']/i', $line)) {
                return 'Ditemukan atribut inline style="...". Styling inline dilarang pada berkas tes_*; gunakan elemen HTML semantik standar.';
            }

            return null;
        });

        $message = $this->formatFailureMessage(
            'Larangan Atribut Inline style pada View tes_* (AGENTS-BACKEND.md Bagian 1)',
            $violations,
            'Hapus atribut style="..." atau style=\'...\' dari elemen HTML terkait pada berkas Blade tes_*.'
        );

        $this->assertEmpty($violations, $message);
    }

    /**
     * Memastikan seluruh berkas view tes_* bebas dari tag <script>, inline event handler, dan javascript: URI.
     *
     * Berdasarkan AGENTS-BACKEND.md bagian 4, penulisan JavaScript dilarang berada di dalam Blade.
     * Seluruh logika interaktivitas semantik wajib berada di berkas terpisah (resources/js/tes_app.js)
     * dan dimuat melalui directive @vite.
     */
    public function test_tes_view_bebas_script_inline(): void
    {
        $violations = $this->scanTesViews(function (string $line): ?string {
            if (preg_match('/<\s*script\b/i', $line)) {
                return 'Ditemukan tag <script>. Seluruh kode JavaScript wajib dipindahkan ke resources/js/tes_app.js.';
            }

            if (preg_match('/\bon[a-zA-Z]+\s*=\s*["\']/i', $line, $matches)) {
                $handlerName = trim($matches[0]);

                return "Ditemukan inline event handler ({$handlerName}). Pindahkan penanganan event ke resources/js/tes_app.js menggunakan addEventListener.";
            }

            if (preg_match('/javascript\s*:/i', $line)) {
                return 'Ditemukan skema URI javascript:. Pindahkan logika pemanggilan fungsi ke event listener di resources/js/tes_app.js.';
            }

            return null;
        });

        $message = $this->formatFailureMessage(
            'Larangan JavaScript Inline pada View tes_* (AGENTS-BACKEND.md Bagian 4)',
            $violations,
            'Pindahkan seluruh logika JavaScript ke berkas resources/js/tes_app.js, lalu muat via @vite([\'resources/css/tes_app.css\', \'resources/js/tes_app.js\']).'
        );

        $this->assertEmpty($violations, $message);
    }

    /**
     * Memastikan seluruh berkas view tes_* bebas dari atribut class CSS framework maupun class kustom.
     *
     * Berdasarkan AGENTS-BACKEND.md bagian 1, tampilan semantik pengujian backend tidak boleh
     * memuat class styling (Tailwind, Bootstrap, atau CSS kustom) agar murni menguji struktur HTML semantik.
     */
    public function test_tes_view_bebas_class_css(): void
    {
        $violations = $this->scanTesViews(function (string $line): ?string {
            if (preg_match_all('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $line, $matches)) {
                foreach ($matches[1] as $classAttributeValue) {
                    $tokens = array_filter(preg_split('/\s+/', trim($classAttributeValue)) ?: []);
                    foreach ($tokens as $token) {
                        if (! in_array($token, self::ALLOWED_CLASSES, true)) {
                            return "Ditemukan class CSS '{$token}'. Seluruh view tes_* wajib bebas class styling. Gunakan elemen semantik murni atau atribut data-* untuk kebutuhan selektor.";
                        }
                    }
                }
            }

            return null;
        });

        $message = $this->formatFailureMessage(
            'Larangan Atribut Class CSS pada View tes_* (AGENTS-BACKEND.md Bagian 1)',
            $violations,
            'Hapus atribut class="..." dari elemen terkait. Jika elemen memerlukan penanda untuk JavaScript, gunakan data attribute (misal: data-action="...") yang tidak berkonotasi styling.'
        );

        $this->assertEmpty($violations, $message);
    }

    /**
     * Memastikan konfigurasi vite.config.js mendaftarkan 4 entri input aset utama secara lengkap.
     *
     * Berdasarkan AGENTS-BACKEND.md bagian 3C, bundler Vite wajib mendaftarkan berkas aset mode frontend
     * (app.css, app.js) dan aset mode backend testing (tes_app.css, tes_app.js) agar build Vite
     * tidak mengalami kegagalan missing manifest entry.
     */
    public function test_vite_config_mendaftarkan_empat_input_aset(): void
    {
        $viteConfigPath = base_path('vite.config.js');
        $this->assertFileExists(
            $viteConfigPath,
            'Berkas konfigurasi vite.config.js tidak ditemukan pada direktori dasar proyek.'
        );

        $content = file_get_contents($viteConfigPath);
        $this->assertIsString($content, 'Gagal membaca isi berkas vite.config.js.');

        $missingEntries = [];
        foreach (self::REQUIRED_VITE_INPUTS as $requiredInput) {
            $singleQuotePattern = "'".preg_quote($requiredInput, '/')."'";
            $doubleQuotePattern = '"'.preg_quote($requiredInput, '/').'"';
            $regex = '/('.$singleQuotePattern.'|'.$doubleQuotePattern.')/';

            if (! preg_match($regex, $content)) {
                $missingEntries[] = $requiredInput;
            }
        }

        $failureMessage = "Konfigurasi vite.config.js belum mendaftarkan seluruh input aset wajib (AGENTS-BACKEND.md Bagian 3C).\n";
        if (! empty($missingEntries)) {
            $failureMessage .= "Entri input berikut belum ditemukan dalam array 'input':\n";
            foreach ($missingEntries as $missing) {
                $failureMessage .= "  - {$missing}\n";
            }
            $failureMessage .= "\nCara Memperbaiki:\nBuka vite.config.js dan pastikan opsi input laravel memuat:\n"
                ."input: [\n"
                ."    'resources/css/app.css',\n"
                ."    'resources/js/app.js',\n"
                ."    'resources/css/tes_app.css',\n"
                ."    'resources/js/tes_app.js',\n"
                .']';
        }

        $this->assertEmpty($missingEntries, $failureMessage);
    }

    /**
     * Memindai seluruh berkas Blade pengujian backend (tes_*.blade.php) pada folder resources/views
     * terhadap aturan arsitektur tertentu menggunakan callback pemeriksa.
     *
     * @param  callable(string, int, string): ?string  $lineInspector  Callback penguji: fn(string $baris, int $nomorBaris, string $relativePath): ?string
     * @return array<string, list<string>> Peta berkas relatif ke daftar pesan pelanggaran berformat '<nomor_baris>: <pesan>'
     */
    private function scanTesViews(callable $lineInspector): array
    {
        $baseViewsDir = resource_path('views');
        $violations = [];

        if (! is_dir($baseViewsDir)) {
            return $violations;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseViewsDir, FilesystemIterator::SKIP_DOTS)
        );

        $scannedCount = 0;

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || ! str_ends_with($fileInfo->getFilename(), '.blade.php')) {
                continue;
            }

            // Batasi pemeriksaan HANYA pada berkas yang namanya berawalan 'tes_' (mode backend testing)
            // Berkas non-tes_* TIDAK BOLEH dipindai karena merupakan wilayah kerja tim front end
            if (! str_starts_with($fileInfo->getFilename(), 'tes_')) {
                continue;
            }

            $scannedCount++;
            $realPath = $fileInfo->getPathname();
            $relativePath = str_replace('\\', '/', substr($realPath, strlen(base_path()) + 1));
            $lines = file($realPath, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            $fileViolations = [];
            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;
                $violationMessage = $lineInspector($line, $lineNumber, $relativePath);

                if ($violationMessage !== null) {
                    $fileViolations[] = "Baris {$lineNumber}: {$violationMessage}";
                }
            }

            if (! empty($fileViolations)) {
                $violations[$relativePath] = $fileViolations;
            }
        }

        $this->assertGreaterThan(
            0,
            $scannedCount,
            'Tidak ditemukan berkas tes_*.blade.php pada direktori resources/views untuk diverifikasi.'
        );

        return $violations;
    }

    /**
     * Memformat daftar pelanggaran menjadi pesan kegagalan assertion yang terstruktur dan actionable.
     *
     * @param  string  $judulAturan  Nama atau ringkasan aturan yang dilanggar
     * @param  array<string, list<string>>  $violations  Daftar pelanggaran per berkas
     * @param  string  $solusi  Instruksi perbaikan bagi pengembang
     */
    private function formatFailureMessage(string $judulAturan, array $violations, string $solusi): string
    {
        $output = "Pelanggaran Aturan Arsitektur: {$judulAturan}\n";
        $output .= "Ditemukan pelanggaran pada berkas berikut:\n";

        foreach ($violations as $file => $messages) {
            $output .= "\n- Berkas: {$file}\n";
            foreach ($messages as $msg) {
                $output .= "  * {$msg}\n";
            }
        }

        $output .= "\nCara Memperbaiki:\n{$solusi}\n";

        return $output;
    }
}
