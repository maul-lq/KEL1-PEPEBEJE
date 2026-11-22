<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : menulis rule validasi DokumenUploadRule ini untuk membatasi ekstensi file aman (PDF/gambar) dan ukuran maksimal 10 MB di seluruh formulir unggah SIM-PBJ.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Aturan Validasi Unggahan Berkas Resmi (DokumenUploadRule)
 *
 * membangun rule kustom ini agar batasan ukuran 10 MB (10.240 KB) dan daftar ekstensi aman
 * tidak perlu diulang-ulang sebagai string rule di setiap controller pengadaan.
 *
 * Parameter konstruktor: `$allowedExtensions` (ekstensi diizinkan) dan `$maxKilobytes` (batas ukuran KB).
 * Nilai kembali         : Instance ValidationRule Laravel yang siap disematkan di array `$request->validate(...)`.
 *
 * Contoh pemanggilan:
 * `['file_surat' => ['required', DokumenUploadRule::pdfOnly()]]`
 */
class DokumenUploadRule implements ValidationRule
{
    /**
     * menginisialisasi konfigurasi ekstensi dan ukuran maksimum berkas dengan fallback NFR 10 MB.
     *
     * @param  array<int, string>  $allowedExtensions  menerima daftar ekstensi huruf kecil yang lolos validasi.
     * @param  int  $maxKilobytes  menetapkan batas maksimal berkas dalam kilobita (default 10.240 KB = 10 MB).
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `new DokumenUploadRule(['pdf'], 5120);`
     */
    public function __construct(
        protected array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'],
        protected int $maxKilobytes = 10240
    ) {}

    /**
     * menyediakan factory ini untuk berkas formal yang wajib berformat PDF (surat usulan, SPK, BAST, SPP).
     *
     * @param  int  $maxKilobytes  menerima batas ukuran opsional dalam kilobita (default 10.240 KB).
     * @return self mengembalikan instance DokumenUploadRule khusus ekstensi ['pdf'].
     *
     * Contoh pemanggilan:
     * `$rules = ['file_spk' => ['required', DokumenUploadRule::pdfOnly()]];`
     */
    public static function pdfOnly(int $maxKilobytes = 10240): self
    {
        return new self(['pdf'], $maxKilobytes);
    }

    /**
     * menyediakan factory ini untuk unggahan foto dokumentasi fisik barang atau avatar pengguna.
     *
     * @param  int  $maxKilobytes  menerima batas ukuran opsional dalam kilobita (default 10.240 KB).
     * @return self mengembalikan instance DokumenUploadRule khusus ekstensi ['jpg', 'jpeg', 'png'].
     *
     * Contoh pemanggilan:
     * `$rules = ['foto_barang.*' => [DokumenUploadRule::imageOnly()]];`
     */
    public static function imageOnly(int $maxKilobytes = 10240): self
    {
        return new self(['jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * menyediakan factory ini untuk dokumen pendukung seperti memo, kwitansi, dan legalitas vendor yang boleh PDF atau foto scan.
     *
     * @param  int  $maxKilobytes  menerima batas ukuran opsional dalam kilobita (default 10.240 KB).
     * @return self mengembalikan instance DokumenUploadRule untuk PDF maupun gambar.
     *
     * Contoh pemanggilan:
     * `$rules = ['file_legalitas' => ['required', DokumenUploadRule::pdfOrImage()]];`
     */
    public static function pdfOrImage(int $maxKilobytes = 10240): self
    {
        return new self(['pdf', 'jpg', 'jpeg', 'png'], $maxKilobytes);
    }

    /**
     * memvalidasi apakah nilai input merupakan file unggahan yang memenuhi format dan ukuran, atau string path tersimpan yang sah.
     *
     * @param  string  $attribute  menerima nama field yang sedang divalidasi untuk menyusun pesan galat yang spesifik.
     * @param  mixed  $value  memeriksa objek UploadedFile baru maupun string path berkas lama saat mode pembaruan.
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  memanggil closure ini untuk menggagalkan validasi beserta alasan NFR-nya.
     * @return void Tidak mengembalikan nilai.
     *
     * Contoh pemanggilan:
     * `$rule->validate('file_permohonan', $request->file('file_permohonan'), $fail);`
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile) {
            // menormalkan ekstensi ke huruf kecil supaya file berakhiran '.PDF' dari scanner/Windows tetap diterima.
            $extension = strtolower($value->getClientOriginalExtension());

            if (! in_array($extension, $this->allowedExtensions, true)) {
                $allowed = strtoupper(implode(', ', $this->allowedExtensions));
                $fail("Format berkas {$attribute} tidak diizinkan. Sistem hanya menerima format [{$allowed}] (NFR Kompatibilitas Dokumen).");

                return;
            }

            // mengalikan kilobita dengan 1024 karena UploadedFile::getSize() mengembalikan ukuran dalam byte.
            if ($value->getSize() > $this->maxKilobytes * 1024) {
                $fail("Ukuran berkas {$attribute} melebihi batas maksimal 10 MB (10.240 KB).");

                return;
            }
        } elseif (is_string($value)) {
            // mengizinkan string path berkas eksisting pada skenario edit/revisi, selama panjangnya tidak melampaui batas kolom VARCHAR(255).
            if (strlen($value) > 255) {
                $fail("Nama/path berkas {$attribute} maksimal 255 karakter.");
            }
        } else {
            $fail("Berkas {$attribute} harus berupa file unggahan atau path dokumen yang valid.");
        }
    }
}
