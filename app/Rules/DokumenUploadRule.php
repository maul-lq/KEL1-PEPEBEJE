<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Aturan Validasi Unggah Berkas Dokumen (DokumenUploadRule)
 *
 * Mengimplementasikan NFR Kompatibilitas Dokumen:
 * - Membatasi format ekstensi file yang diizinkan (PDF, JPG, JPEG, PNG).
 * - Menetapkan batas maksimal ukuran berkas sebesar 10 MB (10.240 KB).
 * - Mencegah upload file yang berpotensi membahayakan integritas sistem kampus.
 */
class DokumenUploadRule implements ValidationRule
{
    /**
     * Konstruktor aturan validasi unggah berkas.
     *
     * @param  array<string>  $allowedExtensions  Daftar ekstensi huruf kecil yang diperbolehkan (misal: ['pdf', 'jpg'])
     * @param  int  $maxKilobytes  Batas ukuran maksimum file dalam satuan kilobita (default 10 MB = 10.240 KB)
     */
    public function __construct(
        protected array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'],
        protected int $maxKilobytes = 10240 // Batas 10 MB sesuai NFR Kompatibilitas Dokumen
    ) {}

    /**
     * Factory method untuk membuat aturan validasi khusus berkas format PDF saja.
     * Cocok digunakan untuk: Surat Usulan Permohonan Srikandi, Dokumen Kontrak/SPK, dan BAST.
     */
    public static function pdfOnly(int $maxKilobytes = 10240): self
    {
        return new self(['pdf'], $maxKilobytes);
    }

    /**
     * Factory method untuk membuat aturan validasi khusus berkas gambar/foto.
     * Cocok digunakan untuk: Bukti Dokumentasi Fisik Penerimaan Barang di Bagian Perlengkapan.
     */
    public static function imageOnly(int $maxKilobytes = 10240): self
    {
        return new self(['jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * Factory method untuk membuat aturan validasi dokumen PDF maupun Gambar.
     * Cocok digunakan untuk: Berkas Legalitas Vendor (NIB/NPWP) dan Slip Bukti Transfer Bank.
     */
    public static function pdfOrImage(int $maxKilobytes = 10240): self
    {
        return new self(['pdf', 'jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * Menjalankan logika validasi pada atribut input yang diberikan.
     *
     * @param  string  $attribute  Nama field atribut input yang divalidasi
     * @param  mixed  $value  Nilai objek file atau string path yang dikirimkan klien
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  Callback pemicu error validasi
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Kasus 1: Nilai input merupakan instance file yang baru diunggah melalui formulir multipart
        if ($value instanceof UploadedFile) {
            // Ambil ekstensi asli berkas dan ubah ke huruf kecil untuk konsistensi pencocokan
            $extension = strtolower($value->getClientOriginalExtension());

            // Periksa apakah ekstensi file termasuk dalam daftar ekstensi yang diizinkan
            if (! in_array($extension, $this->allowedExtensions, true)) {
                $allowed = strtoupper(implode(', ', $this->allowedExtensions));
                $fail("Format berkas {$attribute} tidak diizinkan. Sistem hanya menerima format [{$allowed}] (NFR Kompatibilitas Dokumen).");

                return;
            }

            // Periksa apakah ukuran fisik berkas melebihi batas kilobita yang diizinkan
            if ($value->getSize() > $this->maxKilobytes * 1024) {
                $fail("Ukuran berkas {$attribute} melebihi batas maksimal 10 MB (10.240 KB).");

                return;
            }
        }
        // Kasus 2: Nilai input berupa string (misalnya path berkas yang sudah ada sebelumnya di penyimpanan)
        elseif (is_string($value)) {
            // Batasi panjang path berkas di database maksimal 255 karakter
            if (strlen($value) > 255) {
                $fail("Nama/path berkas {$attribute} maksimal 255 karakter.");
            }
        }
        // Kasus 3: Nilai input bukan file unggahan dan bukan string valid
        else {
            $fail("Berkas {$attribute} harus berupa file unggahan atau path dokumen yang valid.");
        }
    }
}
