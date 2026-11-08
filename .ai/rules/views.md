---
paths:
  - 'app/**, resources/views/**'
  - 'app/**, routes/**, resources/views/**'
---

# Views

## Hak Akses Murni Berbasis RBAC Permissions
Semua otorisasi akses menu, tombol, aksi, dan controller WAJIB menggunakan RBAC permissions ($user->hasPermission(...) atau $user->hasAnyPermission(...)). Dilarang keras menggunakan pengecekan string role hardcoded seperti $user->role === '...' atau Auth::user()->role === '...'. Menu dan fitur tampil dinamis berdasarkan wewenang permission yang dicentang pada role di modul RBAC.

## Alur BPMN Inkremental Per Role & Pembersihan Dead Code
Alur proses bisnis PBJ dibangun secara bertahap per role sesuai panduan BPMN pengguna (backend & frontend berfungsi penuh). Agen WAJIB bertanya (dilarang berasumsi) jika ada instruksi, istilah, atau alur yang kurang jelas. Setelah implementasi fungsi tiap role selesai, hapus class, method, atau rute yang sudah tidak relevan (dead code cleanup).

## Kewajiban Unggah File Fisik untuk Semua Jenis Memo
Semua dokumen atau proses berlabel 'Memo' (Memo Cek MAK, Memo Ketersediaan MAK, Memo Disposisi/Permohonan Wadir 2, Memo Persetujuan Pembayaran PPK/Wadir 2) WAJIB menyediakan input nomor memo dan upload file fisik dokumen memo (PDF/gambar). Dilarang hanya mengandalkan generate otomatis tanpa bukti file fisik.

## Urutan Memo Pembayaran (Wadir 2 Lvl 1, PPK Lvl 2) & Visibilitas Dual Memo ke PPBJ dan User
Urutan Memo Pembayaran Berjenjang adalah: Wadir 2 Level 1, PPK Level 2. Saat terjadi penolakan usulan/pembayaran oleh Wadir 2 atau PPK, user pengaju wajib dapat melihat catatan alasan dari kedua peninjau tersebut. Saat usulan/pembayaran disetujui, di antrean PPBJ wajib ditampilkan KEDUA memo lengkap (Nomor memo & file fisik dokumen memo) baik dari Wadir 2 maupun PPK sesuai pengadaannya.
