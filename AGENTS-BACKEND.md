# Panduan & Aturan Agen Backend (AGENTS-BACKEND.md)

Dokumen ini berisi aturan baku bagi pengembang backend dan agen AI yang bekerja pada sisi backend sistem SIM-PBJ Politeknik Negeri Jakarta (PNJ).

---

## 1. Filosofi Antarmuka: Program Riil Berbasis Peran TANPA CSS (Zero CSS pada Berkas `tes_*`)
- **Program Riil Berbasis Peran (Role-Based Real Web Application)**:
  - Antarmuka web pengujian backend yang dibangun pada folder `resources/views/` (dengan prefiks `tes_*`) **BUKAN** sekadar form uji coba/mockup ad-hoc terisolasi, melainkan **program aplikasi web riil yang utuh dan berfungsi penuh** mencakup sisi backend dan front-end semantik.
  - Tampilan setiap peran pengguna (9 peran resmi institusi PNJ) harus disajikan berbeda secara fungsional sesuai wewenang, batasan data, ERD, dan Sequence Diagram (`1Jorking/Kode Sequence Diagram.txt`):
    1. **Admin (`admin`)**: Pengelolaan konfigurasi sistem, manajemen pengguna 9 peran, dan pemantauan penuh seluruh siklus pengadaan.
    2. **User (`user` - Pemohon / Jurusan / Unit)**: Pengajuan permohonan Srikandi ke Bidang 2 (upload KAK & spesifikasi), pemantauan riwayat status usulan, transparansi alasan penolakan & verifikator penolak, pengajuan revisi surat, dan unduh PDF resmi.
    3. **Bidang 2 (`staff_bidang_2` & `wadir_2`)**: Satu kesatuan organisasi Bidang 2 dengan pemisahan hak akses tegas — **Staff Bidang 2** menerima surat dari User, memeriksa kelengkapan berkas, dan mengirim **Memo Cek MAK** ke Perencanaan; sedangkan **Wadir 2** menyetujui kegiatan (ACC kegiatan atas Memo Ketersediaan MAK dari Perencanaan bersama PPK terpilih; alasan wajib saat menolak) serta menyetujui Memo Bayar Level 2.
    4. **Perencanaan (`perencanaan`)**: Menerima Memo Cek MAK dari Bidang 2, membuat & mengecek ketersediaan kode MAK (`Tersedia?` / Hold), **pemegang wewenang eksklusif mengunci (`lock`) dan membuka kunci (`unlock`) MAK**, memilih **1 dari 4 akun PPK** (`id_user_ppk_awal`), mengirim **Memo Ketersediaan MAK** ke Bidang 2 (`Wadir 2`) & `PPK` terpilih, serta pemecahan (*split*) berkas permohonan berdasarkan kode MAK (FR 6.3).
    5. **PPBJ (`ppbj`)**: Menerima berkas setelah disetujui bersama oleh Wadir 2 & PPK, pemecahan (*split*) berkas berdasarkan jenis pengadaan (`barang`, `jasa`, `konstruksi` - FR 7.2), registrasi paket & penentuan **Metode Pengadaan** (`belanja_langsung` untuk $\le$ Rp50.000.000 dengan output **Kwitansi** tanpa Kontrak/SPK; `non_tender` / `e_katalog` Inaproc dan `tender` / `spse` untuk $>$ Rp50.000.000), input data lelang untuk $>$ Rp200.000.000, mengunggah **tepat 1 dokumen perikatan terpilih** (`surat_pesanan`, `spk`, atau `surat_kontrak`) beserta tembusan otomatis ke Perlengkapan & Keuangan, penentuan jadwal Reminder, alih penugasan (*reassign*) PPK, serta pengajuan pembayaran (melengkapi Kwitansi & Invoice).
    6. **Pejabat Pengadaan (`pp` - 1 akun aktif)**: Terlibat pada seluruh paket $>$ Rp50.000.000 s.d. Rp200.000.000 (baik Tender maupun Non-Tender) — melakukan review/verifikasi kelengkapan dokumen (**PP tidak bisa klik Approval** dan **tidak menerbitkan BAST**), menginput *Nominal Harga Perkiraan*, *Nominal Harga Akhir*, serta *Vendor/Pemenang Lelang*, berkoordinasi meminta approval PPK, dan **mengeksekusi kewenangan belanja** setelah disetujui PPK.
    7. **Pejabat Pembuat Komitmen (`ppk` - 4 akun terisolasi)**: Ikut menyetujui permohonan awal bersama Wadir 2 atas Memo Ketersediaan MAK dari Perencanaan, memberikan **Approval** pengadaan (baik atas hasil review PP pada paket $>$ Rp50.000.000 s.d. Rp200.000.000 maupun review langsung & eksekusi belanja pada paket $>$ Rp200.000.000) dengan **penegakan isolasi data ketat** antar 4 akun PPK, serta menyetujui Memo Pembayaran Level 1.
    8. **Perlengkapan (`perlengkapan`)**: Satu-satunya peran yang menerbitkan **BAST** resmi bertoken validasi HMAC SHA-256 (baik dari paket berdokumen Kontrak/SPK/Surat Pesanan maupun Belanja Langsung ber-Kwitansi), menerima tembusan otomatis SPK, memeriksa fisik barang/jasa, upload dokumentasi multi-foto, dan menentukan kategori **Belanja Barang** (`belanja_barang`) vs **Belanja Modal** (`belanja_modal` / SIMAK-BMN).
    9. **Keuangan (`keuangan`)**: Menerima tembusan otomatis SPK, memverifikasi Informasi Dokumen Pembayaran (`SPK`/`Surat Pesanan`/`Surat Kontrak` terpilih + `Kwitansi` + `Invoice`) serta BAST, mengeksekusi pencairan dana SP2D anti-duplikasi bayar, dan upload bukti slip transfer bank.
- **Aturan TANPA CSS TOTAL (Zero CSS Strict Rule pada Berkas `tes_*`)**:
  - Seluruh tampilan web pengujian backend di `resources/views/**/tes_*.blade.php` **WAJIB 100% BEBAS DARI CSS**.
  - **DILARANG** menyertakan tag `<style> ... </style>` di dalam berkas Blade view `tes_*`.
  - **DILARANG** menggunakan inline styling seperti atribut `style="..."` pada berkas `tes_*`.
  - **DILARANG** menyertakan class-class CSS dari framework (seperti Tailwind CSS, Bootstrap) maupun class CSS kustom pada berkas `tes_*`.
  - **Aturan Selektor JavaScript pada `tes_*`**: Jika elemen HTML pada `tes_*` membutuhkan penanda/selektor untuk JavaScript di `tes_app.js`, **DILARANG** menggunakan atribut `class="..."`. Wajib menggunakan data attribute semantik murni (contoh: `data-username="..."`, `data-password="..."`, `data-action="..."`).
  - **Wajib Murni Struktur HTML Semantik**:
    - Heading: `<h1>`, `<h2>`, `<h3>`, `<h4>`
    - Struktur Layout: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<aside>`, `<footer>`
    - Elemen Teks & Data: `<p>`, `<hr>`, `<strong>`, `<small>`, `<em>`, `<pre>`, `<code>`, `<details>`, `<summary>`
    - Formulir & Input: `<form>`, `<fieldset>`, `<legend>`, `<label>`, `<input>`, `<select>`, `<option>`, `<textarea>`, `<button>`
    - Tabel & List: `<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th>`, `<td>`, `<ul>`, `<ol>`, `<li>`
- **Tujuan**: Memastikan Overseer dapat menguji seluruh logika alur data dan interaksi pengguna secara nyata tanpa beban rendering styling, sekaligus menyajikan struktur DOM semantik bersih yang siap didekorasi oleh tim Front End di masa depan.

---

## 2. Prinsip Pengujian Fungsional Manual oleh Overseer & Sinergi dengan PHPUnit
- **Hak & Wewenang Pengujian Manual oleh Overseer**:
  - Pengujian fungsional dan penerimaan sistem (*acceptance testing*) dilakukan secara **langsung / manual oleh Overseer** melalui antarmuka web semantik di browser (pada berkas dan rute `tes_*`).
  - Agen AI berkewajiban memberikan ruang kerja (*workspace/UI space*) yang setara dan utuh pada tampilan web Zero CSS agar Overseer dapat menguji setiap tahap dari awal hingga akhir (*end-to-end*).
- **Kewajiban Pengujian Otomatis PHPUnit oleh Agen AI**:
  - Di saat yang bersamaan, Agen AI **WAJIB** membuat pengujian otomatis berbasis kelas PHPUnit (`Tests\TestCase`) untuk setiap fitur, endpoint, dan aturan bisnis backend.
  - PHPUnit test berfungsi sebagai jaring pengaman otomatis (*automated regression guard*) untuk memastikan tidak ada logika atau validasi yang rusak selama pengembangan.
  - **Uji Kepatuhan Arsitektur View (`ViewArchitecturePolicyTest`)**: Sistem kini dilengkapi test guardrail otomatis pada `tests/Feature/ViewArchitecturePolicyTest.php` yang memindai seluruh berkas `tes_*.blade.php` untuk menjamin ketiadaan tag `<style>`, atribut `style="..."`, tag `<script>`, inline event handler (`on*="..."`), dan atribut `class="..."`, serta memastikan pendaftaran 4 input aset wajib pada `vite.config.js`.
- **Kemudahan Akses Testing (Quick-Login Switcher)**:
  - Pada halaman `/login` dan `/tes-login`, sistem wajib menyediakan panel tabel daftar 9 akun demo seeder institusi PNJ lengkap dengan tombol bantu 1-klik untuk autofill kredensial.
  - Ini memungkinkan Overseer berganti peran secara instan (misal: Pemohon $\rightarrow$ Staf B2 $\rightarrow$ Wadir 2 $\rightarrow$ Perencanaan $\rightarrow$ PPBJ $\rightarrow$ PPK $\rightarrow$ Perlengkapan $\rightarrow$ Keuangan) saat memverifikasi alur pengadaan.

---

## 3. Aturan Kerja Berkas di Folder `resources/`: Mode Backend Testing (`tes_*`) vs Mode Implementasi Frontend (Non-`tes_*`)

Folder `resources/` memiliki dua kelompok berkas yang berdampingan dengan peruntukan yang berbeda secara tegas:

### A. Mode Standar / Backend Testing (Default - Berkas `tes_*`)
- **Ruang Lingkup Berkas**:
  - Views: `resources/views/**/tes_*.blade.php` (contoh: `auth/tes_login.blade.php`, `auth/tes_register.blade.php`, `dashboard/tes_index.blade.php`, `layouts/tes_app.blade.php`, `permohonan/tes_index.blade.php`, dst.)
  - CSS: `resources/css/tes_app.css` (murni Zero CSS tanpa framework)
  - JavaScript: `resources/js/tes_app.js` (skrip bantu pengujian semantik, autofill, dan kalkulasi hint)
- **Aturan Eksekusi Agen**:
  - Secara default, agen backend dan pengembang backend **HANYA BEKERJA** pada berkas-berkas dengan prefiks `tes_*`.
  - Berkas `tes_*` digunakan untuk simulasi pengujian backend, verifikasi fungsi oleh Overseer, dan eksekusi rute pengujian tanpa beban frontend CSS.
  - **DILARANG KERAS** menyentuh, mengubah, atau menimpa berkas non-`tes_*` (seperti `login.blade.php`, `register.blade.php`, `app.css`, dsb.) selama dalam mode backend testing ini agar tidak merusak hasil pekerjaan tim front end.

### B. Mode Implementasi Frontend (Non-`tes_*`)
- **Kondisi Aktivasi**:
  - Mode ini **HANYA AKTIF** jika Overseer/pengguna memberikan instruksi eksplisit:
    > *"implementasikan fitur backend ke frontend"* (atau frasa setara seperti *"implement the backend feature to frontend"*).
- **Ruang Lingkup Berkas**:
  - Views: `resources/views/**/<nama>.blade.php` (contoh: `auth/login.blade.php`, `auth/register.blade.php`, `dashboard/index.blade.php`, `layouts/app.blade.php`, dst.)
  - CSS: `resources/css/app.css` (menggunakan `@import "tailwindcss";` dan konfigurasi desain front end)
  - JavaScript: `resources/js/app.js` (skrip interaktivitas front end utama)
- **Kewajiban Mutlak: Pemeriksaan Validitas `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md` Terlebih Dahulu**:
  1. Sebelum membuat, mengedit, atau menyentuh berkas frontend non-`tes_*`, agen **WAJIB** membuka dan memeriksa berkas `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md`.
  2. Agen **WAJIB mengecek validitas dan keselarasan** dokumen tersebut terhadap kondisi aktual implementasi backend:
     - Apakah nama endpoint dan HTTP method di panduan sesuai dengan `routes/web.php` dan `routes/api.php`?
     - Apakah parameter request (nama field input, tipe data, batas file upload, keharusan/opsional) sesuai dengan validasi di Controller atau FormRequest?
     - Apakah struktur JSON response (field sukses, pesan error, kode status HTTP 200, 201, 403, 422) sesuai dengan apa yang dikembalikan controller backend?
  3. **Jika ditemukan ketidaksesuaian atau terdapat fitur/endpoint baru yang belum tercantum**:
     - Agen **WAJIB memperbarui dan menyelaraskan `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md` terlebih dahulu**.
     - Setelah dokumen panduan tersebut tervalidasi 100% akurat, barulah agen mengeksekusi integrasi fitur backend ke dalam berkas antarmuka frontend non-`tes_*`.
  4. Seluruh form action, AJAX/fetch request, payload binding, dan navigasi pada berkas frontend non-`tes_*` wajib mengacu penuh pada dokumen `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md` yang telah tervalidasi.

### C. Konfigurasi Bundler Aset (`vite.config.js`)
- Agar build Vite tidak gagal (*missing manifest entry*), kedua pasang file CSS dan JS wajib selalu didaftarkan pada konfigurasi `input` di `vite.config.js`:
  ```javascript
  input: [
      'resources/css/app.css',
      'resources/js/app.js',
      'resources/css/tes_app.css',
      'resources/js/tes_app.js',
  ],
  ```

---

## 4. Aturan JavaScript & Pola Modularitas (Folder `resources/js`)
- **Pemisahan Berkas JavaScript & Prinsip Modularitas (ES Modules)**:
  - `resources/js/tes_app.js` dan `resources/js/app.js` berfungsi murni sebagai **entrypoint orkestrasi global**.
  - **DILARANG** menumpuk seluruh logika JavaScript berbagai halaman atau fitur di dalam satu berkas `*app.js` raksasa karena akan membengkak (*bloated*) dan sulit dirawat.
  - **Pola Pemecahan Modular**:
    - **Backend Testing (`tes_*`)**: Logika fitur/topik wajib dipecah ke dalam modul-modul mandiri di subdirektori `resources/js/tes/` (contoh: `resources/js/tes/autofill_login.js`, `resources/js/tes/routing_hint.js`) lalu diimpor ke dalam `resources/js/tes_app.js`.
    - **Frontend Utama (`non-tes_*`)**: Logika fitur/topik dipecah ke dalam modul-modul mandiri di subdirektori `resources/js/modules/` (contoh: `resources/js/modules/tracking.js`, `resources/js/modules/charting.js`) lalu diimpor ke dalam `resources/js/app.js`.
  - **Aturan Terkait Berkas CSS**:
    - `resources/css/tes_app.css`: Tetap tunduk pada aturan Zero CSS total (kosong; dilarang membuat file CSS per halaman untuk `tes_*`).
    - `resources/css/app.css`: Memuat `@import "tailwindcss";`. Jika tim front end memerlukan kustomisasi styling modular, dapat memecah ke subdirektori `resources/css/components/` lalu mengimpornya via `@import` di `app.css`.
- **Larangan Script Inline**:
  - **DILARANG** menuliskan kode JavaScript secara inline di dalam tag `<script> ... </script>` pada file Blade view.
- **Pemuatan Berkas Skrip**:
  - Pada view backend testing (`tes_*`):
    ```blade
    @vite(['resources/css/tes_app.css', 'resources/js/tes_app.js'])
    ```
  - Pada view frontend utama (non-`tes_*`):
    ```blade
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    ```
- Seluruh file JavaScript yang aktif diverifikasi kelancaran kompilasinya melalui `npm run build` atau proses Vite.

---

## 5. Wajib Memperbarui & Memvalidasi Dokumentasi Integrasi Front End
- Setiap kali pengembang atau agen backend mengimplementasikan atau merevisi Functional Requirements (FR) maupun Non-Functional Requirements (NFR) berikutnya:
  - **WAJIB** segera memperbarui file dokumentasi di `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md`.
  - Pastikan seluruh endpoint baru, metode HTTP, format payload request (beserta tipe data & validasi), struktur payload response (sukses & error HTTP 422/403), serta kode status terdokumentasi secara lengkap dan akurat bagi tim Front End.
- Setiap kali beralih ke tugas implementasi front end (mode non-`tes_*`), validitas dokumen ini harus diperiksa ulang sebelum kode front end ditulis atau diperbarui.

---

## 6. Aturan Komentar Kode Backend (Bahasa Natural & Jelas)
- Seluruh file kode backend (Controllers, Models, Middleware, Services, Policies, Migrations, Seeders, dan Routes) **WAJIB** dilengkapi komentar penjelasan:
  - **Di setiap blok method/fungsi**: Menjelaskan apa tujuan fungsi tersebut, peran pengguna yang berhak memanggilnya, dan referensi FR/NFR terkait.
  - **Di setiap blok kode penting**: Menjelaskan validasi input yang dilakukan, logika bisnis/ambang batas (threshold), transaksi database, event/cascade status, dan struktur respons yang dihasilkan.
  - **Di baris kode kunci**: Menjelaskan maksud dan alasan teknis di balik penulisan baris kode tersebut menggunakan bahasa Indonesia yang natural, lugas, dan mudah dimengerti manusia maupun agen AI.
- Pengecualian: Folder `resources/` (views Blade dan JS) dikecualikan untuk penambahan komentar ini karena nantinya akan dikerjakan bersama tim Front End jika diminta.

---

## 7. Aturan Pengujian Backend: Wajib PHPUnit (Bukan Pest)
- **Standar Testing Proyek**: Seluruh pengujian backend otomatis di proyek SIM-PBJ ini **WAJIB menggunakan PHPUnit** dan **DILARANG menggunakan framework Pest**.
- **Pembuatan File Test**: Gunakan `php artisan make:test {name}` (atau dengan opsi `--unit`). **DILARANG** menambahkan flag `--pest`. Seluruh test harus berupa kelas PHP standar yang meng-extend `Tests\TestCase`.
- **Sintaks Pengujian**: Gunakan method pengujian standar PHPUnit seperti `public function test_alur_pengadaan_berhasil(): void` atau atribut PHP `#[Test]`. Gunakan assertion standar PHPUnit / Laravel seperti `$this->assertDatabaseHas(...)`, `$this->assertEquals(...)`, `$response->assertStatus(200)`. Dilarang menuliskan fungsi closure Pest seperti `test('...', function () { ... })` atau `it('...', function () { ... })`.
- **Eksekusi Pengujian**: Jalankan pengujian menggunakan `php artisan test` atau `vendor/bin/phpunit` dengan parameter filter (contoh: `--filter=NamaTestMethod`). **DILARANG** menjalankan perintah runner Pest (`vendor/bin/pest`).

---

## 8. Aturan Eksekusi Skrip Python (.venv Lokal)
- **Virtual Environment Wajib**: Seluruh eksekusi skrip Python untuk analisis data dokumen sumber truth (`1Jorking/`, `1DokumenPendukungMahPBL/`), pengolahan file Excel (`openpyxl`, `pandas`), Word (`python-docx`), atau PDF (`pypdf`) **WAJIB** menggunakan Python dari virtual environment lokal:
  - Path Windows: `.venv\Scripts\python.exe`
  - Package manager: `.venv\Scripts\pip.exe`
- **DILARANG** menggunakan Python atau menjalankan `pip install` secara global.
- Seluruh dependensi Python dipelihara pada berkas `requirements.txt`.

---

## 9. Arsitektur Persetujuan Registrasi & Manajemen Role Dinamis (RBAC & Permissions Matrix)
- **Alur Registrasi Pengguna Baru**:
  - Pendaftar tidak memilih role pada saat registrasi. Formulir registrasi hanya mengumpulkan biodata, NIP, email institusi, unit kerja, nomor kontak, dan password.
  - Setiap pendaftaran baru otomatis tersimpan dengan `status_registrasi = 'menunggu_persetujuan'` dan `status_aktif = false`.
  - Pengguna dengan status menunggu persetujuan atau ditolak dilarang melakukan login ke dalam sistem dan akan menerima pesan status informatif.
- **Wewenang Administrator Sistem (`role: admin`)**:
  - Administrator meninjau antrean pendaftar pada modul `/admin/persetujuan-registrasi` (view `resources/views/admin/tes_approval_registrasi.blade.php`).
  - **Persetujuan (ACC)**: Administrator memilihkan dan menetapkan Role definitif bagi pengguna (`id_role` dan `role`). Status pengguna berubah menjadi `disetujui`, `status_aktif = true`, serta tercatat identitas admin verifikator dan waktu persetujuan.
  - **Penolakan (Tolak)**: Administrator wajib mencantumkan alasan penolakan (`alasan_penolakan`). Status berubah menjadi `ditolak` dan akun tetap nonaktif.
  - **Penegakan FR 2.1**: Saat menetapkan peran `pp` (Pejabat Pengadaan), sistem memeriksa ketat agar hanya ada maksimal 1 akun PP aktif. Jika sudah ada akun PP aktif, penetapan role `pp` akan digagalkan oleh validasi sistem.
- **Manajemen Role & Hak Akses Fitur (CRUD Role & Permissions Matrix)**:
  - Modul `/admin/roles` (view `resources/views/admin/tes_roles.blade.php`) dan REST API `/api/roles`.
  - **9 Role Bawaan Sistem Institusi**: Ditandai dengan `is_system = true` (`admin`, `user`, `staff_bidang_2`, `wadir_2`, `perencanaan`, `ppbj`, `pp`, `ppk`, `perlengkapan`, `keuangan`). Diproteksi agar tidak dapat dihapus melalui antarmuka website demi integritas alur PBJ. Untuk menghapusnya, flag `is_system` pada database harus diubah terlebih dahulu menjadi `false`.
  - **Role Kustom (`is_system = false`)**: Administrator dapat membuat peran baru (misal: `auditor`, `pengawas_lapangan`), menentukan hak akses fitur secara granular berbasis matriks permissions, memperbarui informasi, dan menghapusnya jika sudah tidak memiliki relasi pengguna aktif.
  - **Helper Model `User`**:
    - `$user->hasRole($role)`: Memeriksa kesesuaian string role maupun entitas dynamic role.
    - `$user->hasPermission($permissionSlug)`: Memeriksa izin fitur tertentu. Role `admin` otomatis memiliki akses `true` tanpa batas ke seluruh permissions.
    - `$user->hasAnyPermission(array $permissions)`: Memeriksa apakah memiliki salah satu dari sekumpulan izin fitur.

---

## 10. Aturan Penanganan Revisi Dokumen & Diagram Sumber Truth
- **Larangan Mengedit Langsung Berkas Desain/Biner Tim**:
  - Agen **DILARANG** memodifikasi langsung berkas `.xlsx`, `.docx`, `.drawio`, `.png`, atau `Notulensi*.txt` pada folder `1Jorking/` dan `1DokumenPendukungMahPBL/` kecuali ada perintah eksplisit dari pengguna untuk mengedit file tersebut.
- **Kewajiban Mencatat Panduan Perubahan di `1Jorking/REVISI dokumen dan diagramnya.md`**:
  - Setiap kali ada keputusan/notulensi baru yang mengubah Sumber Truth, agen wajib menyusun/memperbarui rincian perubahannya di `1Jorking/REVISI dokumen dan diagramnya.md` dengan format siap pakai (*Nama File $\rightarrow$ Bagian/Sel/Tab/Swimlane $\rightarrow$ Teks/Elemen Saat Ini $\rightarrow$ Teks/Elemen Pengganti*).

---
*Dokumen ini merupakan aturan wajib bagi seluruh aktivitas pengembangan dan pengujian backend SIM-PBJ Politeknik Negeri Jakarta.*
