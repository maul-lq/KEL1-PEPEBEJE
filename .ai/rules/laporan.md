---
paths:
  - 'resources/views/laporan/**'
  - 'app/Http/Controllers/HomeController.php'
  - 'routes/web.php'
---

# Aturan Domain Modul Riwayat & Laporan (/laporan)

1. **Penggantian Permanen Modul Monitoring**:
   - Rute `/monitoring` dan view `monitoring/index.blade.php` telah ditiadakan. Seluruh navigasi dialihkan ke rute `/laporan` (`laporan.index`).
   - Sidebar navigasi menggunakan label 'Riwayat & Laporan' yang mengarah ke `route('laporan.index')`.

2. **Dua Tab Navigasi Utama**:
   - **Tab 1: Riwayat Aktivitas** (Figma 515:863): Menampilkan jejak audit pengadaan (Waktu, Nama Pengadaan, Revisi, Aktivitas, Status). Setiap baris rekaman interaktif dan mengarahkan ke `/permohonan?selected={nomor_surat}`.
   - **Tab 2: Laporan** (Figma 526:3083): Menampilkan 6 Kartu Ringkasan (Pagu, Realisasi, Usulan, Sisa, Kontrak, Persentase Serapan) dan Tabel Rekapitulasi serta tombol Unduh Laporan (PDF) dan Unduh Detail (PDF).

3. **Isolasi Data Laporan Berbasis Peran (Role-Based Scoping)**:
   - **Role `user` (Pengaju)**: Kartu KPI dan tabel menampilkan data khusus unit pengaju (pagu unit, usulan disetujui/proses, dan daftar pengajuan unit).
   - **Role `admin`**: Kartu KPI dan tabel berfokus pada manajemen sistem dan pengguna (total user, user aktif per role, permohonan registrasi, log aktivitas).
   - **Role Eksekutif Institusi (`wadir_2`, `perencanaan`, `ppbj`, `keuangan`)**: Menampilkan distribusi anggaran DIPA kampus per jurusan/unit (Figma 526:3083).
   - **Role Operasional (`ppk`, `pp`, `perlengkapan`, `staff_bidang_2`)**: Menampilkan rekapitulasi sesuai batasan penugasan dan modul masing-masing.

4. **Interaktivitas Reaktif Alpine.js & URL Deep Linking**:
   - Data dimuat secara server-side melalui Blade view ke dalam payload Alpine.js.
   - Filter, pencarian kata kunci, dropdown status/waktu, dan pagination berjalan instan di klien tanpa reload penuh.
   - Status tab dan filter disinkronkan ke URL query string (`?tab=...&q=...`) agar ramah bookmarking.
