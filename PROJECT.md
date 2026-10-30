# Project: SIM-PBJ Politeknik Negeri Jakarta (Frontend & Backend Integration)

## Architecture
- **Framework & Runtime**: Laravel 13 running on PHP 8.5 with Vite & Tailwind CSS v4.
- **Frontend Architecture**: Blade template engine with Alpine.js 3.x and Tailwind CSS v4 styling matching 190 Figma designs (Navy `#213153`, Blue `#0969da`, Teal `#087F79`, Ice Canvas `#F4F8FA`). Shared UI component library in `resources/views/components/`.
- **Backend Architecture**: RESTful API endpoints and Web routes in `routes/web.php` with RBAC middleware `CheckRole` supporting 9 official roles (10 role slugs). FormRequests, Eloquent models with query scopes, and service classes supporting 8 procurement cycles.
- **Dual-Engine View Mode**: `App\View\DevModeViewFinder` dynamically resolves `DEV_VIEWMODE=frontend` to standard production Blade views with seamless fallback to `tes_*` semantic views.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Shared UI Component Library | Reusable Blade components (`x-stat-card`, `x-stepper-8-siklus`, `x-status-badge`, `x-modal`, `x-filter-bar`) | M1 | Survey (explorer_views_survey_1) |
| 2 | Tailwind CSS v4 & Asset Pipeline | Local compilation of Tailwind CSS v4 via `@tailwindcss/vite` in `package.json` and `vite.config.js` | M1 | Survey (explorer_views_survey_1) |
| 3 | Login Multi-Identitas & Split Banner | Halaman login dengan NIP/Username/Email, toggle password, dan Google OAuth 1-Click | M1 | Figma 276:1883 & PANDUAN_INTEGRASI §1.2 |
| 4 | Pendaftaran Pengguna & Indikator Alur | Form registrasi multi-input tanpa pemilihan role dengan step indicator alur | M1 | Figma 300:1986 & PANDUAN_INTEGRASI §1.1 |
| 5 | Profil Pengguna & Modal Manajemen Akun | Halaman profil dengan modal Ubah Password, modal Hapus Akun, dan Google Account Linking | M1 | Figma 419:430, 411:397, 411:428 |
| 6 | Pusat Notifikasi & Reminder 4 Tab | Halaman notifikasi dengan filter tab (Semua, Belum Dibaca, Perlu Tindakan, Pengingat) dan pill counters | M1 | Figma 419:3232 & PANDUAN_INTEGRASI §6 |
| 7 | Dashboard Personal Admin | Dashboard admin dengan kartu metrik pengguna, antrean approval registrasi, dan quick actions | M2 | Figma 558:3052 |
| 8 | Dashboard Personal User / Pemohon | Dashboard pengusul dengan ringkasan permohonan, status revisi, dan tombol buat usulan | M2 | Figma 484:3385 |
| 9 | Dashboard Personal Staf Bidang 2 & Wadir 2 | Dashboard bidang 2 dengan antrean telaah berkas Srikandi dan antrean disposisi Wadir 2 | M2 | Figma 565:19857 |
| 10 | Dashboard Personal Perencanaan | Dashboard perencanaan dengan total pagu DIPA, MAK teralokasi, dan antrean usulan pending | M2 | Figma 565:17282 |
| 11 | Dashboard Personal PPBJ | Dashboard PPBJ dengan antrean registrasi paket, penetapan SPK, dan alih tugas PPK | M2 | Figma 568:22384 |
| 12 | Dashboard Personal Pejabat Pengadaan (PP) | Dashboard PP dengan paket negosiasi aktif (50jt - 200jt) dan reviu teknis | M2 | Figma 568:27235 |
| 13 | Dashboard Personal PPK (4 Akun Terisolasi) | Dashboard PPK dengan paket penugasan terisolasi dan antrean persetujuan memo bayar level 1 | M2 | Figma 568:30261 |
| 14 | Dashboard Personal Perlengkapan | Dashboard perlengkapan dengan tembusan SPK masuk, jadwal pengiriman vendor, dan antrean BAST | M2 | Figma 568:33331 |
| 15 | Dashboard Personal Keuangan | Dashboard keuangan dengan antrean SPP siap cair, realisasi anggaran, dan transaksi SP2D | M2 | Figma 568:36411 |
| 16 | Formulir Usulan Pengadaan Srikandi | Form pembuatan usulan pengadaan dengan upload KAK & spesifikasi (max 10MB) ber-styling Tailwind v4 | M3 | Figma 498:5218 & PANDUAN_INTEGRASI §2.1 |
| 17 | Modal Interaktif Revisi Permohonan | Modal revisi usulan dengan catatan telaah verifikator dan upload dokumen pengganti | M3 | Figma 509:1651 & PANDUAN_INTEGRASI §2.2 |
| 18 | Modal Interaktif Hapus Pengajuan | Modal konfirmasi pembatalan/penghapusan usulan draft/ditolak | M3 | Figma 498:4950 |
| 19 | Layar Verifikasi & Telaah Staf Bidang 2 | Formulir telaah kelengkapan administrasi dan rekomendasi penerusan ke Wadir 2 | M3 | PANDUAN_INTEGRASI §3.0 & Figma 565:19857 |
| 20 | Layar Disposisi Eksekutif Wadir 2 | Panel radio keputusan mutlak Wadir 2 (ACC / Tolak / Revisi) dengan catatan wajib pada tolak/revisi | M3 | PANDUAN_INTEGRASI §3.1 & Figma 565:20075 |
| 21 | Layar Alokasi MAK & Hold Anggaran Perencanaan | Antrean penetapan kode MAK DIPA, alokasi pagu, dan status hold anggaran jika pagu belum siap | M3 | PANDUAN_INTEGRASI §3.2-3.3 & Figma 565:17450 |
| 22 | Formulir Pemecahan Berkas MAK (Split MAK) | Form interaktif pemecahan usulan pengadaan menjadi beberapa sub-usulan per kode MAK (FR 6.3) | M3 | PANDUAN_INTEGRASI §3.4 & FR 6.3 |
| 23 | Registrasi Paket Pengadaan & Auto-Routing HPS | Form registrasi paket pengadaan PPBJ dengan deteksi otomatis jalur routing HPS (<=50jt, 50-200jt, >=200jt) | M4 | PANDUAN_INTEGRASI §4.1 & Figma 568:22384 |
| 24 | Pemecahan Jenis Pengadaan (Split Jenis) | Form split usulan berdasarkan jenis pengadaan (barang, jasa, konstruksi) oleh PPBJ (FR 7.2) | M4 | PANDUAN_INTEGRASI §3.5 & FR 7.2 |
| 25 | Alih Tugas Penugasan PPK (Reassign) | Form pengalihan penugasan PPK dengan alasan perubahan penugasan (FR 13) | M4 | PANDUAN_INTEGRASI §4.2 & FR 13 |
| 26 | Reviu Teknis & Negosiasi Harga (PP & PPK) | Panel reviu dokumen teknis, negosiasi harga definitif PP, dan penguncian MAK permanen PPK | M4 | PANDUAN_INTEGRASI §5.1 & Figma 568:27235 |
| 27 | Penerbitan Kontrak SPK & Tembusan Otomatis | Form penerbitan Surat Kontrak/SPK dengan distribusi otomatis ke Perlengkapan & Keuangan | M4 | PANDUAN_INTEGRASI §5.2 & FR 7.4 |
| 28 | Inspeksi Fisik, Multi-Foto & BAST HMAC SHA-256 | Form pemeriksaan fisik barang perlengkapan, klasifikasi belanja, upload multi-foto, dan tanda tangan digital BAST | M4 | PANDUAN_INTEGRASI §7.1 & FR 14 |
| 29 | Pengajuan Tagihan & Memo Bayar Bertingkat | Form pengajuan tagihan termin PPBJ, persetujuan Memo Level 1 (PPK), dan Level 2 (Wadir 2) | M4 | PANDUAN_INTEGRASI §8.1-8.2 & FR 16 |
| 30 | Penerbitan SPP & Pencairan SP2D Anti-Duplikasi | Form penerbitan SPP oleh PPBJ dan pencairan SP2D keuangan dengan proteksi anti-pencairan ganda | M4 | PANDUAN_INTEGRASI §8.3, §9.1 & NFR 8 |
| 31 | Approval Registrasi Akun & Manajemen Role | Panel admin untuk persetujuan akun baru (penetapan role) dan matriks hak akses permissions dinamis | M4 | PANDUAN_INTEGRASI §1.3-1.4 & Figma 558:3052 |
| 32 | Pelacakan Monitoring 8 Siklus & Laporan Realisasi DIPA | Halaman monitoring real-time alur 8 siklus dengan isolasi data dan rekapitulasi realisasi DIPA (unduh PDF) | M4 | PANDUAN_INTEGRASI §10.1, §11.2 & Figma 526:3083 |
| 33 | E2E Multi-Role Integration & Verification Suite | Verifikasi menyeluruh 9 role, 8 siklus pengadaan, switch DEV_VIEWMODE=frontend, dan audit integritas forensik | M5 | Acceptance Criteria & Integrity Forensics |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Foundation & Shared Components | Reusable Blade components (`stat-card`, `stepper-8-siklus`, `status-badge`, `modal`, `filter-bar`), layout refinement, local Tailwind v4 Vite compilation | none | DONE |
| M2 | Role-Specific Dashboards | 9 role-tailored dashboard views/partials in `resources/views/dashboard/` with specific KPI stats and action queues | M1 | DONE |
| M3 | Permohonan & Verifikasi Views Enhancement | Tailwind v4 styling of `permohonan/create.blade.php`, interactive modals (Revisi & Hapus) in `permohonan/index.blade.php`, unified MAK allocation in `verifikasi/perencanaan.blade.php` | M1 | DONE |
| M4 | Pengadaan, Kontrak, Fisik & Pembayaran Views Polish | Complete integration and polish for paket, kontrak, penerimaan (BAST), pembayaran (SPP/SP2D), and admin approval modules | M1, M3 | IN_PROGRESS |
| M5 | E2E Multi-Role Integration & Verification | E2E browser/HTTP testing across all 9 roles, 8 procurement cycles, 100% test pass, and Forensic Integrity Audit | M1, M2, M3, M4 | PLANNED |

## Interface Contracts
### Blade Components ↔ Views
- `x-stat-card`: props: `title` (string), `value` (string|int), `icon` (string/slot), `color` (string: blue|teal|amber|emerald|indigo), `subtitle` (string, optional)
- `x-stepper-8-siklus`: props: `currentStep` (int 1-8), `status` (string: draft|diajukan|verifikasi|paket|reviu|spk|pengiriman|bast|lunas|ditolak|revisi)
- `x-status-badge`: props: `status` (string), `size` (string: sm|md)
- `x-modal`: props: `id` (string), `title` (string), `show` (boolean expression), `maxWidth` (string: sm|md|lg|xl|2xl)
- `x-filter-bar`: props: `action` (string), `searchPlaceholder` (string), `filters` (array/slot)

### Controllers ↔ Views
- All controllers pass variables via `compact(...)` as documented in `backend_readiness_report.md § 4`.
- Views access data seamlessly: `$user`, `$stats`, `$recentPermohonan`, `$recentPaket`, `$notifications`, `$units`, `$permohonanList`, `$paketList`, `$kontrakList`, `$bastList`, etc.
- In `DEV_VIEWMODE=frontend`, `HomeController` and related controllers render the non-`tes_*` Blade views directly.

## Code Layout
- `resources/views/layouts/app.blade.php`: Master production layout.
- `resources/views/components/`: Reusable UI Blade components.
- `resources/views/dashboard/`: Role-specific dashboard views (`index.blade.php`, `roles/*.blade.php`).
- `resources/views/auth/`: Login and Register views.
- `resources/views/permohonan/`: Permohonan index, create, show, and modals.
- `resources/views/verifikasi/`: Bidang 2, Wadir 2, and Perencanaan verification views.
- `resources/views/paket/`: Paket pengadaan index and create views.
- `resources/views/kontrak/`: Kontrak SPK and review views.
- `resources/views/penerimaan/`: BAST physical inspection and signature views.
- `resources/views/pembayaran/`: Pembayaran memo, SPP, and SP2D views.
- `resources/views/monitoring/`: 8-cycle real-time tracking views.
- `resources/views/laporan/`: DIPA budget realization and audit history views.
- `resources/views/admin/`: User approval and role/permissions management views.
- `resources/views/profile/`: User profile and security settings views.
- `resources/views/notifikasi/`: Notification feed views.
