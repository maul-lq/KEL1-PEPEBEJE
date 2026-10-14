# Panduan & Aturan Agen Backend (AGENTS-BACKEND.md)

Dokumen ini berisi aturan baku bagi pengembang backend dan agen AI yang bekerja pada sisi backend sistem SIM-PBJ Politeknik Negeri Jakarta (PNJ).

---

## 1. Aturan Tampilan Simulasi Backend: TANPA CSS TOTAL
- **Tanpa CSS Total**: Jika pengembang atau agen backend ingin membuat atau menguji antarmuka front end untuk keperluan simulasi/prototipe verifikasi backend, antarmuka tersebut **WAJIB TANPA CSS TOTAL**.
  - **DILARANG** menyertakan tag `<style> ... </style>` di dalam file Blade view.
  - **DILARANG** menggunakan inline styling seperti atribut `style="..."`.
  - **DILARANG** menyertakan class-class CSS dari framework (seperti Tailwind CSS, Bootstrap) maupun class CSS kustom.
- **Fokus Murni Struktur HTML Semantik**: Tampilan pengujian hanya boleh menggunakan elemen semantik HTML standar:
  - Heading: `<h1>`, `<h2>`, `<h3>`, `<h4>`
  - Struktur Konten: `<p>`, `<hr>`, `<strong>`, `<small>`, `<em>`, `<pre>`, `<code>`, `<details>`, `<summary>`
  - Formulir & Input: `<form>`, `<fieldset>`, `<legend>`, `<label>`, `<input>`, `<select>`, `<option>`, `<textarea>`, `<button>`
  - Tabel & List: `<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th>`, `<td>`, `<ul>`, `<ol>`, `<li>`
- **Tujuan**: Memastikan pengujian oleh **Overseer Backend** berfokus 100% pada kelengkapan elemen input data, binding request payload, validasi data, dan alur respon API tanpa terdistraksi atau terbebani oleh styling tampilan.

---

## 2. Enforce Aturan Awalan `tes_`
- Seluruh artefak kode yang dibuat khusus untuk keperluan simulasi/pengujian backend **WAJIB SELALU diawali dengan kata `tes_`**:
  1. **Blade Views**: `resources/views/tes_*.blade.php` (contoh: `resources/views/tes_permohonan.blade.php`, `resources/views/tes_monitoring.blade.php`, `resources/views/tes_api.blade.php`).
  2. **Web Routes**: Rute simulasi pada `routes/web.php` harus menggunakan prefix `/tes-*` (contoh: `/tes-permohonan`, `/tes-monitoring`, `/tes-api`).
  3. **JavaScript**: File script pengujian harus berawalan `tes_` pada folder `resources/js/` (contoh: `resources/js/tes_*.js`).
- Aturan ini memisahkan secara tegas berkas simulasi sementara milik backend dari antarmuka final produksi yang akan dibangun oleh tim Front End.

---

## 3. Aturan JavaScript untuk Testing (Folder `resources/js`)
- Jika fitur pengujian atau simulasi backend memerlukan JavaScript (misalnya untuk fetch API asynchronous, simulasi klik tombol, render JSON response, atau reset form):
  - **DILARANG** menuliskan kode JavaScript secara inline di dalam tag `<script> ... </script>` pada file Blade view.
  - **WAJIB** menempatkan kode JavaScript di dalam folder `resources/js/`.
  - **WAJIB** menamai file tersebut dengan awalan `tes_` (misalnya: `resources/js/tes_api.js`, `resources/js/tes_permohonan.js`).
  - File JavaScript tersebut dimuat ke dalam view Blade menggunakan directive:
    ```blade
    @vite('resources/js/tes_nama_file.js')
    ```
  - Pastikan file `tes_*.js` yang baru didaftarkan pada array `input` di `vite.config.js` agar terintegrasi penuh saat dijalankan dengan Vite / `npm run build`.

---

## 4. Wajib Memperbarui Dokumentasi Integrasi Front End
- Setiap kali pengembang atau agen backend mengimplementasikan atau merevisi Functional Requirements (FR) maupun Non-Functional Requirements (NFR) berikutnya:
  - **WAJIB** segera memperbarui file dokumentasi di `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md`.
  - Pastikan seluruh endpoint baru, metode HTTP, format payload request (beserta tipe data & validasi), struktur payload response (sukses & error HTTP 422/403), serta kode status terdokumentasi secara lengkap dan akurat bagi tim Front End.

---

## 5. Aturan Komentar Kode Backend (Bahasa Natural & Jelas)
- Seluruh file kode backend (Controllers, Models, Middleware, Services, Policies, Migrations, Seeders, dan Routes) **WAJIB** dilengkapi komentar penjelasan:
  - **Di setiap blok method/fungsi**: Menjelaskan apa tujuan fungsi tersebut, peran pengguna yang berhak memanggilnya, dan referensi FR/NFR terkait.
  - **Di setiap blok kode penting**: Menjelaskan validasi input yang dilakukan, logika bisnis/ambang batas (threshold), transaksi database, event/cascade status, dan struktur respons yang dihasilkan.
  - **Di baris kode kunci**: Menjelaskan maksud dan alasan teknis di balik penulisan baris kode tersebut menggunakan bahasa Indonesia yang natural, lugas, dan mudah dimengerti manusia maupun agen AI.
- Pengecualian: Folder `resources/` (views Blade dan JS) dikecualikan untuk penambahan komentar ini karena nantinya akan dikerjakan bersama tim Front End jika diminta.

---
*Dokumen ini merupakan aturan wajib bagi seluruh aktivitas pengembangan dan pengujian backend SIM-PBJ Politeknik Negeri Jakarta.*
