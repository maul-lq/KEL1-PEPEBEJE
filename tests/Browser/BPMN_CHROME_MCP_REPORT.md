# LAPORAN PENGUJIAN INTERAKTIF VISUAL SIKLUS PENGADAAN (BPMN bepemen.drawio)
Tanggal Eksekusi : 2026-10-15 09:43:00
Mode Tampilan    : Jendela Chrome Nyata di Layar (Non-Headless)
Server Sasaran   : http://127.0.0.1:8000

## 1. Swimlane User: Registrasi Akun Pegawai Baru & Login
 - Mengakses Formulir Pendaftaran: http://127.0.0.1:8000/register
 - Tangkapan Layar Form Registrasi: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\01_form_registrasi.png`
 - Eksekusi Pendaftaran: registrasi_submitted
 - Tangkapan Layar Halaman Login: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\02_sukses_registrasi_login.png`

 - Melakukan Login Menggunakan Akun Baru: `pemohon_32180`
 - URL Dasbor Pemohon: http://127.0.0.1:8000/dashboard
 - Tangkapan Layar Dasbor: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\03_dasbor_pemohon_baru.png`

## 2. Swimlane User: Mengisi Form Permohonan Pengadaan (TOR/RAB & KAK)
 - Tangkapan Layar Form Permohonan: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\04_form_permohonan_baru.png`
 - Submit Usulan Permohonan: permohonan_submitted (`B/1792032180/PL3.TIK/PG/2026`)
 - Tangkapan Layar Antrean Usulan: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\05_daftar_permohonan_terkirim.png`

## 3. Swimlane Reviu Paralel: Telaah Staf Bidang II, Alokasi MAK, & Persetujuan Wadir 2
 - 3.a. Staf Bidang II (`staff_b2`) Menelaah Dokumen Administrasi & KAK
   * Status Telaah Staf Bidang II: telaah_submitted
 - 3.b. Subbagian Perencanaan (`perencanaan`) Menentukan MAK & Alokasi DIPA
   * Status Penetapan MAK: mak_allocated
 - 3.c. Wakil Direktur II (`wadir2`) Memberikan Persetujuan Mutlak (ACC)
   * Keputusan Mutlak Wadir 2: wadir_acc_submitted

## 4. Swimlane PPBJ: Menerima Surat, Pendaftaran Paket, & Routing Otomatis
 - Pembuatan Dokumen Paket Pengadaan: paket_created_routing_pengadaan_langsung (`PKT-TIK-BPMN-32180`)
 - Logika Routing Otomatis: HPS Rp 47.500.000 (<= 200 Juta) otomatis diarahkan ke Pengadaan Langsung (PP) & PPK 1
 - Tangkapan Layar Daftar Paket: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\13_daftar_paket_ppbj.png`

## 5. Swimlane PPBJ: Penerbitan Kontrak SPK & Penguncian Otomatis MAK DIPA
 - Penerbitan Kontrak SPK: spk_created_and_mak_locked (`SPK/PNJ/2026/32180`)
 - Penguncian Kode MAK: MAK DIPA berstatus Terkunci Permanen (is_locked = true)
 - Tangkapan Layar SPK Terbit: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\15_kontrak_spk_terbit.png`

## 6. Swimlane Perlengkapan: Menerima & Memeriksa Barang, Upload Foto, TTD BAST
 - Pengesahan Berita Acara Serah Terima (BAST): bast_issued_with_hmac (`BAST/PNJ/2026/32180`)
 - Tanda Tangan Digital: Token HMAC-SHA256 berhasil di-generate
 - Tangkapan Layar BAST Terbit: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\17_bast_disahkan.png`

## 7. Alur Pembayaran: Pengajuan Bayar, Disposisi Memo Internal, & Penerbitan SPP
 - 7.a. PPBJ Mengajukan Permohonan Pembayaran (Tagihan Vendor)
   * Status Pengajuan Pembayaran: pengajuan_bayar_submitted
 - 7.b. PPK 1 (`ppk_1`) Menyetujui Disposisi Memo Tingkat 1
   * Status Memo PPK 1: memo_ppk_approved
 - 7.c. Wakil Direktur II (`wadir2`) Menyetujui Disposisi Memo Tingkat 2
   * Status Memo Wadir 2: memo_wadir_approved
 - 7.d. PPBJ Menerbitkan Surat Perintah Pembayaran (SPP) Resmi ke Keuangan
   * Penerbitan SPP ke Keuangan: spp_issued_to_keuangan
   * Tangkapan Layar SPP Terbit: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\19_spp_diterbitkan.png`

## 8. Swimlane Keuangan: Menerima SPP, Pencairan SP2D, & Upload Bukti
 - Realisasi Pencairan SP2D: sp2d_disbursed_closed
 - Tangkapan Layar Pencairan Sukses: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\21_sp2d_cair_lunas.png`

## 9. Monitoring Siklus Hidup Penuh & Rekapitulasi Anggaran Realtime
 - Halaman Monitoring Realtime: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\22_monitoring_realtime.png`
 - Halaman Laporan Rekapitulasi: `C:\Users\Rizlrad Fz\Koding\Laravel\KEL1-PEPEBEJE\tests\Browser\screenshots_bpmn\23_laporan_rekapitulasi.png`

================================================================================
 [SUKSES TOTAL] SELURUH SIKLUS BPMN (bepemen.drawio) TELAH SELESAI DIUJI SECARA VISUAL!
================================================================================