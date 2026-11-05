---
paths:
  - 'resources/**'
---

# Resources

## Figma to Blade Frontend Conversion Workflow
Konversi desain Figma ke kode di resources/ mengacu pada Tailwind CSS v4, struktur models, controllers, routes, dan dokumen sumber truth (1DokumenPendukungMahPBL & 1Jorking serta keputusan sesi 332cc91d-5da5-4603-8764-5674852a0c76 Q1-Q8 K1-K3). Sebelum modifikasi antarmuka frontend, selalu periksa 1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md dan pastikan keselarasan payload serta endpoint.

## Tata Letak Standar Master Layout (Vertical Navbar & Top Banner)
- **Hierarki Layout**: Seluruh halaman yang meng-extend `layouts.app` menggunakan struktur dua kolom utama:
  1. **Kolom Kiri**: Vertical Navbar (`aside`) memanjang penuh dari puncak paling atas layar (`top: 0`, `h-screen`, `sticky`) berisi tombol hamburger toggle, navigasi ikon, dan gelombang SVG biru (Ellipse 4 & 5 / Frame 29).
  2. **Kolom Kanan**: Kolom konten yang memuat Header Banner horizontal (`header`, `h-[73px]`) di bagian atas, dan Konten Utama (`main`) di bawahnya.
- **Header Banner & Komponen Mini Profil/Notifikasi (Figma 751:7822)**:
  - Header memuat Logo SIM-PBJ di sebelah kiri dan modul `Unit - Profil dan Notif` di sebelah kanan.
  - Modul kanan menyediakan 2 dropdown popover presisi Figma:
    - Popover Mini Notifikasi (Figma 751:7854): Judul notifikasi, filter chip, daftar notifikasi terbaru, dan link ke halaman notifikasi penuh.
    - Popover Profil Kecil (Figma 751:7823): Rincian nama, unit, role badge, avatar foto profil besar, menu Profil Saya, dan tombol Logout.
