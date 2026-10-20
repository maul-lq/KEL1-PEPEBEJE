# Panduan & Aturan Agen Backend (AGENTS-BACKEND.md)

Dokumen ini berisi aturan baku bagi pengembang backend dan agen AI yang bekerja pada sisi backend sistem SIM-PBJ Politeknik Negeri Jakarta (PNJ).

---

## 1. Filosofi Antarmuka: Program Riil Berbasis Peran TANPA CSS (Zero CSS pada Berkas `tes_*`)
- **Program Riil Berbasis Peran (Role-Based Real Web Application)**:
  - Antarmuka web pengujian backend yang dibangun pada folder `resources/views/` (dengan prefiks `tes_*`) **BUKAN** sekadar form uji coba/mockup ad-hoc terisolasi, melainkan **program aplikasi web riil yang utuh dan berfungsi penuh** mencakup sisi backend dan front-end semantik.
  - Tampilan setiap peran pengguna (9 peran resmi institusi PNJ) harus disajikan berbeda secara fungsional sesuai wewenang, batasan data, ERD, dan Sequence Diagram (`1Jorking/Kode Sequence Diagram.txt`):
    1. **User (Pemohon / Jurusan / Unit)**: Pengajuan permohonan Srikandi (upload KAK & spesifikasi), pemantauan riwayat status usulan, transparansi alasan penolakan & verifikator penolak, pengajuan revisi surat, dan unduh PDF resmi.
    2. **Staff Bidang 2**: Antrean permohonan masuk, telaah kelengkapan berkas administrasi (checklist berkas), dan meneruskan telaah ke Wadir 2.
    3. **Wadir 2 (Wakil Direktur II)**: Antrean verifikasi eksekutif, hak mutlak persetujuan (ACC [catatan opsional] atau Tolak/Revisi [catatan alasan wajib]), dan penerusan ke PPBJ saat disetujui bersama Perencanaan.
    4. **Perencanaan**: Verifikasi anggaran MAK, penetapan kode MAK & alokasi pagu DIPA, serta penguncian MAK (*lock*).
    5. **PPBJ**: Registrasi paket pengadaan, penentuan nilai HPS dengan auto-routing akurasi 100% ($\le$50jt Pengadaan Langsung, 50-200jt PP, >200jt PPK), penugasan ke salah satu dari 4 akun PPK, dan alih penugasan (*reassign*) PPK disertai pencatatan riwayat mutasi.
    6. **Pejabat Pengadaan (PP)**: Paket pengadaan bernilai s.d Rp 200 juta, penetapan vendor, dan penerbitan SPK.
    7. **Pejabat Pembuat Komitmen (PPK)**: Pengelolaan paket bernilai > Rp 200 juta dengan **penegakan isolasi data ketat** (setiap akun dari 4 PPK hanya dapat mengakses paket yang ditugaskan kepadanya), tinjauan dokumen, dan penerbitan SPK komitmen.
    8. **Perlengkapan**: Penerimaan fisik barang dari rekanan, upload dokumentasi/foto barang (minimal 1 foto item terlihat), dan penerbitan BAST resmi bertoken validasi HMAC.
    9. **Keuangan**: Verifikasi berkas tagihan/BAST, eksekusi pencairan dana SP2D dengan proteksi anti-duplikasi bayar, dan upload bukti slip transfer pemindahbukuan bank.
- **Aturan TANPA CSS TOTAL (Zero CSS Strict Rule pada Berkas `tes_*`)**:
  - Seluruh tampilan web pengujian backend di `resources/views/**/tes_*.blade.php` **WAJIB 100% BEBAS DARI CSS**.
  - **DILARANG** menyertakan tag `<style> ... </style>` di dalam berkas Blade view `tes_*`.
  - **DILARANG** menggunakan inline styling seperti atribut `style="..."` pada berkas `tes_*`.
  - **DILARANG** menyertakan class-class CSS dari framework (seperti Tailwind CSS, Bootstrap) maupun class CSS kustom pada berkas `tes_*`.
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

## 4. Aturan JavaScript (Folder `resources/js`)
- **Pemisahan Berkas JavaScript**:
  - `resources/js/tes_app.js`: Diperuntukkan khusus bagi antarmuka Zero CSS backend testing (`tes_*`), memuat logika autofill kredensial demo dan indikator jalur routing HPS.
  - `resources/js/app.js`: Diperuntukkan bagi antarmuka front end utama, memuat interaktivitas klien modern (kompatibel dengan Tailwind & Alpine.js).
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
*Dokumen ini merupakan aturan wajib bagi seluruh aktivitas pengembangan dan pengujian backend SIM-PBJ Politeknik Negeri Jakarta.*
