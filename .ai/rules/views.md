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
