---
paths:
  - 'app/**, resources/views/**'
  - 'app/**, bootstrap/app.php, routes/**, resources/views/**, tests/Feature/**'
---

# Views

## Hak Akses Murni Berbasis RBAC Permissions
Semua otorisasi akses rute (`routes/web.php`), menu sidebar, tombol, aksi, policy, dan controller WAJIB menggunakan RBAC permissions (`$user->hasPermission(...)`, `$user->hasAnyPermission(...)`, atau middleware `role:<permission_slug>`). Dilarang menggunakan pengecekan string role hardcoded seperti `$user->role === '...'`, `$user->hasRole('admin')`, atau middleware `role:<nama_role>` pada fitur operasional. Menu dan fitur tampil dinamis berdasarkan wewenang permission yang dicentang pada role di modul RBAC.

### Komposisi Multi-Modul Dinamis untuk Role Gabungan
Untuk halaman agregasi, pelaporan, maupun rekapitulasi yang menyentuh lebih dari satu kelompok fitur (misal Role A mengaktifkan fitur Modul X dan Modul Y sekaligus):
- Dilarang memakai pola pencabangan tunggal eksklusif (`switch ($role)` atau `if/elseif` fallback satu profil) yang membuang modul lainnya.
- Bangun daftar modul aktif secara kumulatif berbasis `hasAnyPermission(...)` (array/union query scope) sehingga seluruh seksi yang diizinkan tetap tampil (sebagai tab modul independen atau gabungan berurutan), lengkap dengan filter adaptif dan ekspor dokumen yang mengikuti cakupan modul aktif tersebut.

## Alur BPMN Inkremental Per Role & Pembersihan Dead Code
Alur proses bisnis PBJ dibangun secara bertahap per role sesuai panduan BPMN pengguna (backend & frontend berfungsi penuh). Agen WAJIB bertanya (dilarang berasumsi) jika ada instruksi, istilah, atau alur yang kurang jelas. Setelah implementasi fungsi tiap role selesai, hapus class, method, atau rute yang sudah tidak relevan (dead code cleanup).

## Kewajiban Unggah File Fisik untuk Semua Jenis Memo
Semua dokumen atau proses berlabel 'Memo' yang memiliki wewenang keputusan (Memo Ketersediaan Anggaran Perencanaan, Memo Disposisi/Persetujuan Wadir 2, Memo Persetujuan Awal PPK, dan Memo Persetujuan Pembayaran Wadir 2 & PPK) WAJIB menyediakan input nomor memo dan upload file fisik dokumen memo (PDF/gambar). Pemegang izin baca-saja (`verifikasi.bidang_2_view`) tidak memiliki form verifikasi maupun unggah memo.

## Persetujuan Paralel Memo Pembayaran (Wadir II & PPK) & Visibilitas Dual Memo ke PPBJ dan User
Persetujuan Memo Pembayaran berjalan paralel antara Wakil Direktur II (`pembayaran.memo_wadir2`) dan PPK penanggung jawab (`pembayaran.memo_ppk`, mencakup jalur Belanja Langsung serta PPK pengganti hasil alih tugas tanpa menuntut urutan Level 1/Level 2). Saat terjadi penolakan usulan/pembayaran oleh Wadir II atau PPK, user pengaju wajib dapat melihat catatan alasan dari kedua peninjau tersebut. Saat pembayaran disetujui kedua pihak, di antrean PPBJ wajib ditampilkan kedua memo lengkap (nomor memo dan file fisik dokumen memo) dari Wadir II maupun PPK sesuai pengadaannya.

## Standar Penanganan Error HTTP, API/JSON, dan Otorisasi 403 pada Modul Baru
Setiap pembuatan atau perubahan fitur, rute web, middleware, maupun endpoint `/api/*` wajib mematuhi ketentuan berikut:
1. **Pemisahan Browser HTML vs Klien JSON**: Gunakan `$request->expectsJson()` (dilarang menggunakan `$request->is('api/*')`) pada exception handler (`bootstrap/app.php`), middleware (`CheckRole`, `SessionTimeout`), maupun controller saat menentukan respons JSON vs HTML/redirect. Dengan demikian, permintaan Fetch/AJAX (`Accept: application/json`) tetap menerima JSON aman, sedangkan kunjungan langsung via browser tetap menampilkan antarmuka web/halaman error HTML.
2. **Keamanan & Sanitasi Pesan Error**:
   - Jangan membocorkan stack trace, kueri SQL, path berkas server, maupun nama kelas model (`ModelNotFoundException` pada `404`) ke respons HTML maupun JSON API ketika `APP_DEBUG=false`. Pada mode `APP_DEBUG=true`, pertahankan perilaku debug Laravel khusus untuk error `500`.
   - Rute terproteksi wajib menggunakan middleware `role:...` (`CheckRole`) agar penolakan `403` otomatis menampilkan label peran/izin RBAC yang dibutuhkan dalam Bahasa Indonesia standar tanpa membocorkan detail internal.
   - Bila controller memanggil `abort(403, ...)`, awali pesan terkurasi dengan `"Akses Ditolak: ..."` atau `"Halaman ini tidak dapat diakses ..."` supaya diteruskan oleh `resources/views/errors/403.blade.php` sementara pesan mentah lain tersaring ke pesan baku.
3. **Konsistensi Antarmuka Error Mandiri (`resources/views/errors/`)**: Pastikan kode status baru atau cabang error baru tetap mengacu ke kerangka `errors.layout` (header SIM-PBJ tanpa sidebar, tanpa kueri basis data, pesan Bahasa Indonesia bebas jargon, serta tombol `Kembali ke Halaman Sebelumnya` dengan fallback Dashboard/Masuk).
4. **Cakupan Pengujian Pest**: Fitur atau endpoint baru wajib dilengkapi pengujian di `tests/Feature/` untuk memverifikasi respons HTML browser (`403`/`404`/`405`), respons JSON (`401`/`403`/`404`) saat memakai header `Accept: application/json`, serta memastikan tidak ada bocoran pesan pengecualian internal.
