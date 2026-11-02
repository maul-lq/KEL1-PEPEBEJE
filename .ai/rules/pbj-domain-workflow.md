---
paths:
  - 'app/**'
  - 'routes/**'
  - 'database/**'
  - 'resources/**'
  - 'tests/**'
---

# Aturan Domain & Alur Bisnis SIM-PBJ (Notulensi PPBJ Terbaru)

1. **Alur Telaah Berkas & Persetujuan Eksekutif Wadir 2 (Staf Bidang 2 Read-Only)**:
   - **Peran Staf Bidang II (Read-Only)**: Staf Bidang II hanya memiliki hak akses melihat/membaca seluruh usulan permohonan masuk (monitoring & preview dokumen) tanpa memiliki hak meneruskan atau mendisposisikan berkas ke Wadir 2.
   - **Pemeriksaan Kelengkapan Dokumen & Keputusan oleh Wadir II**: Wakil Direktur II secara langsung memeriksa kelengkapan administrasi berkas dinas (Surat Usulan Srikandi, KAK, Spesifikasi Teknis) dan menetapkan keputusan eksekutif: **ACC (Disetujui)**, **Tolak**, atau **Minta Revisi**.
   - **Pemeriksaan Anggaran oleh Subbagian Perencanaan**: Bagian Perencanaan memeriksa/mengalokasikan ketersediaan MAK DIPA dan memilih PPK awal, yang kemudian disahkan bersama Wadir II sebelum dilanjutkan ke modul PPBJ.
2. **Otoritas Tunggal Kunci & Buka Kunci MAK di Perencanaan**:
   - Selain membuat MAK, **hanya role `perencanaan`** yang berwenang mengunci (`lock`) dan membuka kunci (`unlock`) MAK. PPK maupun penerbitan SPK tidak mengunci MAK secara sepihak.
3. **Penentuan Metode Pengadaan & 3 Jalur Eksekusi**:
   - PPBJ menentukan metode pengadaan (`belanja_langsung`, `non_tender` / `e_katalog` Inaproc, `tender` / `spse`):
     - **`<= Rp50.000.000` (Belanja Langsung)**: Dieksekusi PPBJ, outputnya **Kwitansi** (**TIDAK ADA Kontrak/SPK/Surat Pesanan**), opsional Reminder -> lanjut ke Perlengkapan -> lanjut ke Pembayaran.
     - **`> Rp50.000.000` s.d. `Rp200.000.000` (Melibatkan PP)**: Baik Tender maupun Non-Tender masuk ke **PP** (1 akun). PP hanya mereview/verifikasi kelengkapan & menginput *Nominal Harga Perkiraan*, *Nominal Harga Akhir*, dan *Vendor/Pemenang* (**PP tidak bisa klik Approval** dan **tidak menerbitkan BAST**). Approval tetap oleh **PPK**, lalu **PP mengeksekusi belanja**, kemudian **PPBJ** mengunggah 1 dokumen perikatan terpilih & set Reminder.
     - **`> Rp200.000.000` (Melibatkan PPK)**: PPBJ menginput data lelang (*Harga Perkiraan, Harga Akhir, Vendor*) -> **PPK** mereview, klik Approval, & mengeksekusi belanja -> **PPBJ** mengunggah 1 dokumen perikatan terpilih & set Reminder.
4. **Satu Dokumen Perikatan Terpilih (`> Rp50 Juta`)**:
   - Setiap paket `> Rp50 juta` hanya mengunggah **salah satu** dokumen: `Surat Pesanan` (`surat_pesanan`) atau `SPK` (`spk`) untuk E-Katalog/Inaproc, atau `Surat Kontrak` (`surat_kontrak`) untuk Non-Tender/Tender pihak ketiga.
5. **BAST Murni di Perlengkapan & Informasi Dokumen Pembayaran**:
   - **BAST** hanya diterbitkan oleh **Perlengkapan** saat serah terima fisik barang (beserta penentuan `belanja_barang` / `belanja_modal`, foto, & TTD digital HMAC), baik untuk paket `> 50jt` maupun `<= 50jt` Belanja Langsung.
   - **Informasi Dokumen Pembayaran** menampilkan referensi dokumen perikatan tunggal (`SPK` / `Surat Pesanan` / `Surat Kontrak` untuk `> 50jt`) **ditambah** `Kwitansi` dan `Invoice`, lalu melewati Memo Bayar berjenjang (`PPK` -> `Wadir 2`) -> Perintah Bayar `PPBJ` -> Pencairan `Keuangan`.
6. **Kelengkapan Navigasi & Entrypoint Antarmuka (End-to-End Navigation)**:
   - Setiap peran yang memiliki tanggung jawab pada alur persetujuan berantai (`staff_bidang_2`, `perencanaan`, `wadir_2`, `ppk`) **wajib** memiliki 2 pintu masuk antarmuka:
     a. **Ikon Menu Sidebar**: Terpasang di `resources/views/layouts/app.blade.php` dengan pengecekan role yang sesuai, title deskriptif, dan indikator badge antrean aktif.
     b. **Daftar Antrean & Kartu KPI Dashboard**: Tampil di `resources/views/dashboard/roles/{role}.blade.php` lengkap dengan tombol aksi langsung menuju halaman verifikasi, disuplai datanya melalui `HomeController::getRoleDashboardData()`.
7. **Integritas Sinkronisasi Skema Database**:
   - Setelah setiap penggabungan kode (merge git) atau penambahan migrasi baru, wajib memverifikasi `php artisan migrate:status` dan menjalankan `php artisan migrate --no-interaction` sebelum memulai pengujian alur bisnis.
8. **Penyederhanaan Entitas Vendor & Input Rekanan Secara Inline**:
   - **Struktur Minimalis Vendor**: Rekanan vendor hanya memerlukan nama vendor (`nama_vendor`), dokumen legalitas digital (`file_dokumen_legalitas`), dan status keaktifan (`is_active`). Atribut NPWP, NIB, rekening bank, PIC, dsb., tidak lagi wajib disimpan di entitas vendor.
   - **Peniadaan Modul Master `/vendors`**: Rute, controller, dan antarmuka master CRUD rekanan ditiadakan dari sidebar navigasi dan sistem.
   - **Penetapan Rekanan di Tahap Perikatan/Belanja (Bukan di Registrasi Paket)**: Pada saat pendaftaran awal paket pengadaan oleh PPBJ, vendor belum ditentukan. Rekanan baru diinput dan dokumen legalitasnya diunggah secara *inline* pada tahap penerbitan dokumen perikatan (Kwitansi Belanja Langsung pada `<= 50jt`, SPK/Surat Pesanan pada `50-200jt`, atau Kontrak pada `> 200jt`).
9. **Auto-Routing HPS, Filter Metode Pengadaan, & Sinkronisasi Saldo MAK**:
   - **Reaktivitas Registrasi Paket**: Saat permohonan disetujui dipilih pada `paket/create.blade.php`, sistem otomatis mengisi nominal HPS dari pagu permohonan dan menyusun nama paket default.
   - **Auto-Locking & Filtering Dropdown Metode**:
     - **`<= Rp50.000.000` (Belanja Langsung)**: Dropdown metode otomatis terkunci ke `Belanja Langsung` (`disabled` di UI dan dikirim via hidden input). Penugasan PP dan PPK dikosongkan.
     - **`> Rp50.000.000` s.d. `Rp200.000.000` (Jalur PP)**: Dropdown metode memfilter opsi perikatan: `SPK`, `Surat Pesanan (E-Purchasing / E-Katalog)`, atau `Surat Kontrak`.
     - **`> Rp200.000.000` (Jalur PPK)**: Dropdown metode memfilter opsi: `Tender Terbuka (SPSE)`, `Non-Tender (E-Purchasing / E-Katalog)`, atau `Seleksi Konsultansi`. Nomor memo PPBJ otomatis dikosongkan.
   - **Sinkronisasi Saldo MAK saat Penyesuaian Nilai HPS**:
     - Jika PPBJ mengubah nilai HPS berbeda dari estimasi pagu awal permohonan:
       - **Kenaikan Nilai HPS (`HPS > Pagu Awal`)**: Sistem memverifikasi ketersediaan saldo MAK (`sisa_pagu_mak >= selisih`). Jika mencukupi, `sisa_pagu_mak` dikurangi sebesar selisih. Jika tidak mencukupi, form registrasi ditolak dengan pesan kesalahan saldo tidak mencukupi.
       - **Efisiensi / Penurunan Nilai HPS (`HPS < Pagu Awal`)**: Selisih efisiensi anggaran otomatis dikembalikan ke `sisa_pagu_mak` pada rekening MAK terkait (`sisa_pagu_mak += abs(selisih)`).
       - Nilai pagu permohonan (`estimasi_total_pagu`) dan nominal pada `AlokasiMAK` disinkronkan ke nilai HPS baru.


