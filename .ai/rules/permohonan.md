---
paths:
  - 'resources/views/permohonan/**'
---

# Permohonan

## Arsitektur dan Alur 12 Desain Tracking Pengajuan Permohonan
Halaman tracking pengajuan (resources/views/permohonan/index.blade.php) mengintegrasikan 12 desain Figma dalam satu SPA terpadu via Alpine.js (resources/js/modules/tracking.js). Terdiri dari Split View (daftar pengajuan & detail ringkas 8 siklus), Fullscreen Mode (hero 5-kolom, stepper lebar penuh, box perlu revisi, surat & dokumen, riwayat kronologis), serta 4 modal: Pratinjau Dokumen (715:6528 / 744:7446), Buat Permohonan Baru (737:7343 / 737:7339), Revisi Permohonan (744:7706 / 744:7705), dan Hapus Pengajuan dengan password confirmation (744:7604). Tidak boleh ada inline <script> tag di dalam Blade.
