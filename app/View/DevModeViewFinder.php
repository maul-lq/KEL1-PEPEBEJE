<?php

namespace App\View;

use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/**
 * DevModeViewFinder
 *
 * Finder kustom untuk menunjang flag konfigurasi DEV_VIEWMODE.
 * Memungkinkan peralihan dinamis antara tampilan mode backend (berkas berawalan tes_*)
 * dan tampilan mode frontend (berkas standar tanpa tes_*) secara transparan.
 */
class DevModeViewFinder extends FileViewFinder
{
    /**
     * Menemukan path absolut file view berdasarkan nama view dan mode DEV_VIEWMODE aktif.
     *
     * @param  string  $view  Nama view dot-notation (misal: 'permohonan.index')
     * @return string Path absolut ke file blade template
     */
    public function find($view)
    {
        $view = trim($view);
        $mode = config('app.dev_viewmode', env('DEV_VIEWMODE', 'backend'));

        $candidates = $this->resolveCandidates($view, (string) $mode);

        foreach ($candidates as $candidate) {
            try {
                return parent::find($candidate);
            } catch (InvalidArgumentException) {
                // Lanjutkan pencarian ke kandidat alternatif berikutnya
            }
        }

        // Jika seluruh kandidat tidak ditemukan, serahkan ke implementasi bawaan untuk melempar exception standar
        return parent::find($view);
    }

    /**
     * Menyusun urutan kandidat nama view yang akan dicari berdasarkan mode DEV_VIEWMODE.
     *
     * @param  string  $view  Nama view awal
     * @param  string  $mode  Nilai mode aktif ('backend' atau lainnya)
     * @return array<int, string> Daftar nama view kandidat terurut
     */
    protected function resolveCandidates(string $view, string $mode): array
    {
        // Pisahkan namespace jika ada (contoh: 'errors::404')
        if ($this->hasHintInformation($view)) {
            return [$view];
        }

        $segments = explode('.', $view);
        $basename = array_pop($segments);
        $prefix = count($segments) > 0 ? implode('.', $segments).'.' : '';

        $isTes = str_starts_with($basename, 'tes_');
        $nonTesBasename = $isTes ? substr($basename, 4) : $basename;
        $tesBasename = $isTes ? $basename : 'tes_'.$basename;

        $tesView = $prefix.$tesBasename;
        $nonTesView = $prefix.$nonTesBasename;

        if ($mode === 'backend') {
            // Mode backend: utamakan file tes_*, kemudian fallback ke non-tes jika tidak ada
            return array_values(array_unique([$tesView, $nonTesView, $view]));
        }

        // Mode frontend: utamakan file non-tes, kemudian fallback ke tes_* jika frontend belum tersedia
        return array_values(array_unique([$nonTesView, $tesView, $view]));
    }
}
