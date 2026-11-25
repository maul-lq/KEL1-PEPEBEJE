---
paths:
  - 'resources/views/laporan/**'
  - 'app/Http/Controllers/LaporanController.php'
  - 'resources/js/modules/laporan.js'
  - 'app/Http/Controllers/HomeController.php'
  - 'routes/web.php'
---

# Aturan Domain Modul Riwayat & Laporan (/laporan)

1. **Penggantian Permanen Modul Monitoring**:
   - Rute `/monitoring` dan view `monitoring/index.blade.php` telah ditiadakan. Seluruh navigasi dialihkan ke rute `/laporan` (`laporan.index`).
   - Sidebar navigasi menggunakan label 'Riwayat & Laporan' yang mengarah ke `route('laporan.index')`.

2. **Dua Tab Navigasi Utama & Tab Seksi Modul di Tab Laporan**:
   - **Tab 1: Riwayat Aktivitas**: Menampilkan jejak audit (`AuditLog`) menggunakan gabungan query (*union scope*) dari seluruh permission RBAC aktif pengguna (tanpa `switch ($userRole)`). Setiap baris mengarahkan ke entitas terkait.
   - **Tab 2: Laporan**:
     - Judul bagian Ringkasan KPI (`<h2>`) dan header Tabel Rincian (`<h3>`) menampilkan tab modul dinamis sesuai daftar modul RBAC aktif pengguna. Jika role memiliki >= 2 modul aktif (Role Gabungan X + Y), otomatis tersedia tab **`Semua Modul`** serta tab per masing-masing modul aktif.
     - **Filter Bar Adaptif**: Input filter pada Tab Laporan menyesuaikan modul yang sedang aktif (misal `Tahun`, `Bulan`, `Unit Kerja` hanya aktif bila modul memiliki dimensi unit lintas jurusan dan pengguna tidak dibatasi ke unit sendiri, `Status`, dsb.) dan perubahan filter menyinkronkan ulang data KPI cards, tabel, serta parameter unduhan.
     - **Satu Tombol `'Unduh Laporan'`**: Tombol `'Unduh Detail (PDF)'` ditiadakan agar hanya tersisa 1 tombol `'Unduh Laporan'` di Filter Bar (dikontrol izin `dokumen.unduh_pdf`). Sekali klik langsung memicu **2 unduhan sekaligus** sesuai tab modul & filter yang aktif:
       1. **PDF Rapi** (`barryvdh/laravel-dompdf` + Blade template `resources/views/laporan/pdf.blade.php`): kop institusi PNJ, metadata filter & profil permission, kartu ringkasan KPI, serta tabel terformat rapi (jika `Semua Modul`, seksi modul aktif X + Y disusun berurutan per bagian).
       2. **CSV (RAW)** (UTF-8 BOM, delimiter koma RFC-4180, kolom angka nominal murni numerik agar siap diolah di Excel): kolom sesuai tab modul aktif, atau disusun berseksi rapi per modul saat `Semua Modul` dipilih.

3. **Komposisi 10 Modul Laporan Independen Berbasis Permission RBAC**:
   Dilarang memeriksa string role hardcoded. Seksi laporan diaktifkan secara kumulatif berdasarkan izin aktif pengguna:
   1. `rekapitulasi_dipa`: `laporan.rekapitulasi`
   2. `permohonan_sendiri`: `permohonan.create` / `permohonan.view_own` (bila tanpa `permohonan.view_all`)
   3. `verifikasi_usulan`: `verifikasi.bidang_2_view`, `verifikasi.wadir_2_view`, `verifikasi.wadir_2_decide`, `permohonan.view_all`
   4. `anggaran_mak`: `anggaran.mak_view`, `anggaran.mak_manage`, `anggaran.mak_alokasi`, `anggaran.mak_lock`, `permohonan.split_mak`
   5. `pengadaan_ppbj`: `paket.create`, `paket.view_all`, `paket.reassign_ppk`, `spk.create`, `pp.monitoring_aktivitas`
   6. `penugasan_pp`: `reviu.pp`
   7. `komitmen_ppk`: `reviu.ppk`
   8. `penerimaan_bmn`: `penerimaan.view`, `penerimaan.bast_create`, `penerimaan.bast_sign`
   9. `pembayaran_sp2d`: `pembayaran.pengajuan`, `pembayaran.memo_ppk`, `pembayaran.memo_wadir2`, `pembayaran.spp_terbitkan`, `pembayaran.pencairan_sp2d`
   10. `administrasi_rbac`: `admin.approval_registrasi`, `admin.user_manage`, `admin.role_manage`

4. **Interaktivitas Reaktif Alpine.js & Sinkronisasi Filter ke Server**:
   - Pencarian lokal dan pagination tabel berjalan instan di klien.
   - Perubahan filter berdimensi query (`tahun`, `bulan`, `unit`, filter spesifik modul, `module`) mengambil payload JSON terbaru dari `/laporan` agar KPI cards dan tabel selalu sinkron dengan parameter unduhan maupun URL deep linking.
