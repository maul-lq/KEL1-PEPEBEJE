<?php

/**
 * Informasi File
 *
 * Penulis   : Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0)
 * Ringkasan : Migrasi ini rancang untuk memisahkan kolom `file_spp` dari `file_dokumen_penunjang`,
 *             membersihkan path dummy yang tidak berfile fisik, serta menyinkronkan nilai kontrak SPK
 *             terhadap HPS paket dan saldo efisiensi MAK.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas migrasi anonim pemisah berkas SPP dan penyinkron data kontrak-HPS historis.
 *
 * Tujuan: menormalkan dokumen pembayaran lama agar berkas SPP tersimpan di kolom tersendiri
 * dan mengembalikan selisih efisiensi nilai kontrak di bawah HPS ke `sisa_pagu_mak` dengan aman.
 *
 * Contoh pemanggilan:
 * `php artisan migrate --path=database/migrations/2026_11_15_113412_separate_file_spp_and_sync_kontrak_hps.php`
 */
return new class extends Migration
{
    /**
     * Menjalankan migrasi pemisahan `file_spp` dan rekonsiliasi efisiensi kontrak terhadap pagu MAK.
     *
     * Tujuan: membungkus seluruh pembaruan data dalam transaksi dengan `lockForUpdate()` pada
     * baris `anggaran_mak` agar pengembalian saldo efisiensi bebas race condition.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->up();`
     */
    public function up(): void
    {
        if (Schema::hasTable('pembayaran') && ! Schema::hasColumn('pembayaran', 'file_spp')) {
            Schema::table('pembayaran', function (Blueprint $table): void {
                $table->string('file_spp', 255)->nullable()->after('file_dokumen_penunjang');
            });
        }

        DB::transaction(function (): void {
            if (Schema::hasTable('pembayaran')) {
                $pembayaranRows = DB::table('pembayaran')->get();
                foreach ($pembayaranRows as $row) {
                    $updates = [];

                    if (! empty($row->file_dokumen_penunjang) && str_starts_with((string) $row->file_dokumen_penunjang, 'spp/')) {
                        if (empty($row->file_spp)) {
                            $updates['file_spp'] = $row->file_dokumen_penunjang;
                        }
                        $updates['file_dokumen_penunjang'] = null;
                    }

                    $dummyMap = [
                        'file_dokumen_penunjang' => 'penunjang/resume_kontrak.pdf',
                        'file_kwitansi' => 'kwitansi/kwitansi_pembayaran.pdf',
                        'file_invoice' => 'tagihan/invoice_dummy.pdf',
                    ];

                    foreach ($dummyMap as $column => $dummyPath) {
                        $current = array_key_exists($column, $updates) ? $updates[$column] : ($row->{$column} ?? null);
                        if ($current === $dummyPath && ! Storage::disk('public')->exists($dummyPath)) {
                            $updates[$column] = null;
                        }
                    }

                    if (! empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('pembayaran')
                            ->where('nomor_pembayaran', $row->nomor_pembayaran)
                            ->update($updates);
                    }
                }
            }

            if (Schema::hasTable('kontrak_spk') && Schema::hasTable('paket_pengadaan')) {
                $kontrakRows = DB::table('kontrak_spk')->get();
                foreach ($kontrakRows as $spk) {
                    $paket = DB::table('paket_pengadaan')->where('nomor_paket', $spk->nomor_paket)->first();
                    if (! $paket) {
                        continue;
                    }

                    $nilaiKontrak = (float) $spk->nilai_kontrak;
                    if ($nilaiKontrak <= 0) {
                        continue;
                    }

                    $hpsLama = (float) ($paket->nilai_hps ?? 0);
                    $paketUpdates = [
                        'nominal_harga_akhir' => $nilaiKontrak,
                        'nilai_hasil_negosiasi' => $nilaiKontrak,
                    ];

                    if ($hpsLama > $nilaiKontrak) {
                        $selisihEfisiensi = $hpsLama - $nilaiKontrak;
                        $paketUpdates['nilai_hps'] = $nilaiKontrak;
                        $paketUpdates['alokasi_pagu_paket'] = $nilaiKontrak;

                        if (! empty($paket->nomor_surat)) {
                            $permohonan = DB::table('permohonan_pengadaan')
                                ->where('nomor_surat', $paket->nomor_surat)
                                ->first();

                            if ($permohonan) {
                                DB::table('permohonan_pengadaan')
                                    ->where('nomor_surat', $permohonan->nomor_surat)
                                    ->update([
                                        'estimasi_total_pagu' => max(0, (float) $permohonan->estimasi_total_pagu - $selisihEfisiensi),
                                        'updated_at' => now(),
                                    ]);

                                if (! empty($permohonan->kode_mak)) {
                                    $mak = DB::table('anggaran_mak')
                                        ->where('kode_mak', $permohonan->kode_mak)
                                        ->lockForUpdate()
                                        ->first();

                                    if ($mak) {
                                        DB::table('anggaran_mak')
                                            ->where('kode_mak', $mak->kode_mak)
                                            ->update([
                                                'sisa_pagu_mak' => (float) $mak->sisa_pagu_mak + $selisihEfisiensi,
                                                'updated_at' => now(),
                                            ]);
                                    }

                                    $alokasi = DB::table('alokasi_mak')
                                        ->where('nomor_surat', $permohonan->nomor_surat)
                                        ->where('kode_mak', $permohonan->kode_mak)
                                        ->first();

                                    if ($alokasi) {
                                        DB::table('alokasi_mak')
                                            ->where('id', $alokasi->id)
                                            ->update([
                                                'nominal_alokasi' => max(0, (float) $alokasi->nominal_alokasi - $selisihEfisiensi),
                                                'updated_at' => now(),
                                            ]);
                                    }
                                }
                            }
                        }
                    }

                    $paketUpdates['updated_at'] = now();
                    DB::table('paket_pengadaan')
                        ->where('nomor_paket', $paket->nomor_paket)
                        ->update($paketUpdates);
                }
            }
        });
    }

    /**
     * Membalikkan migrasi dengan memindahkan `file_spp` kembali ke `file_dokumen_penunjang` bila kosong.
     *
     * Tujuan: menjaga agar path berkas SPP yang terlanjur tersimpan tidak hilang sebelum
     * kolom `file_spp` dijatuhkan pada proses rollback.
     *
     * @return void
     *
     * Contoh pemanggilan:
     * `$migration->down();`
     */
    public function down(): void
    {
        if (Schema::hasTable('pembayaran') && Schema::hasColumn('pembayaran', 'file_spp')) {
            DB::transaction(function (): void {
                $rows = DB::table('pembayaran')->whereNotNull('file_spp')->get();
                foreach ($rows as $row) {
                    if (empty($row->file_dokumen_penunjang)) {
                        DB::table('pembayaran')
                            ->where('nomor_pembayaran', $row->nomor_pembayaran)
                            ->update([
                                'file_dokumen_penunjang' => $row->file_spp,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

            Schema::table('pembayaran', function (Blueprint $table): void {
                $table->dropColumn('file_spp');
            });
        }
    }
};
