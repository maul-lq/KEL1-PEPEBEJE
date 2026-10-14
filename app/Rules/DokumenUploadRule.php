<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class DokumenUploadRule implements ValidationRule
{
    /**
     * @param  array<string>  $allowedExtensions
     */
    public function __construct(
        protected array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'],
        protected int $maxKilobytes = 10240 // 10 MB (NFR Kompatibilitas Dokumen)
    ) {}

    /**
     * Khusus berkas dokumen PDF saja (misal: Surat Srikandi, SPK).
     */
    public static function pdfOnly(int $maxKilobytes = 10240): self
    {
        return new self(['pdf'], $maxKilobytes);
    }

    /**
     * Khusus berkas foto/gambar (misal: Dokumentasi Fisik Barang BAST).
     */
    public static function imageOnly(int $maxKilobytes = 10240): self
    {
        return new self(['jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * Berkas dokumen PDF atau Gambar (misal: Bukti Transfer, Legalitas Vendor).
     */
    public static function pdfOrImage(int $maxKilobytes = 10240): self
    {
        return new self(['pdf', 'jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * Jalankan validasi berkas.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile) {
            $extension = strtolower($value->getClientOriginalExtension());
            if (! in_array($extension, $this->allowedExtensions, true)) {
                $allowed = strtoupper(implode(', ', $this->allowedExtensions));
                $fail("Format berkas {$attribute} tidak diizinkan. Sistem hanya menerima format [{$allowed}] (NFR Kompatibilitas Dokumen).");

                return;
            }

            if ($value->getSize() > $this->maxKilobytes * 1024) {
                $fail("Ukuran berkas {$attribute} melebihi batas maksimal 10 MB (10.240 KB).");

                return;
            }
        } elseif (is_string($value)) {
            if (strlen($value) > 255) {
                $fail("Nama/path berkas {$attribute} maksimal 255 karakter.");
            }
        } else {
            $fail("Berkas {$attribute} harus berupa file unggahan atau path dokumen yang valid.");
        }
    }
}
