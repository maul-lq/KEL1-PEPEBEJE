# Panduan & Aturan Agen Backend (AGENTS-BACKEND.md)

Dokumen ini berisi aturan baku bagi pengembang backend dan agen AI yang bekerja pada sisi backend sistem SIM-PBJ Politeknik Negeri Jakarta (PNJ).

---

## 1. Filosofi Antarmuka: Program Riil Berbasis Peran Berstandar Frontend Produksi
- **Program Riil Berbasis Peran (Role-Based Real Web Application)**:
  - Seluruh antarmuka web pada folder `resources/views/` merupakan **program aplikasi web riil yang utuh dan berfungsi penuh** berstandar produksi menggunakan Tailwind CSS v4 dan Blade.
  - Tampilan setiap peran pengguna (9 peran resmi institusi PNJ) disajikan berbeda secara fungsional sesuai wewenang, batasan data, ERD, dan Sequence Diagram (`1Jorking/Kode Sequence Diagram.txt`):
    1. **Admin (`admin`)**: Pengelolaan konfigurasi sistem, manajemen pengguna 9 peran, persetujuan registrasi, dan pemantauan penuh seluruh siklus pengadaan.
    2. **User (`user` - Pemohon / Jurusan / Unit)**: Pengajuan permohonan Srikandi ke Bagian Perencanaan (upload KAK & spesifikasi), pemantauan riwayat status usulan, transparansi alasan penolakan & verifikator penolak, pengajuan revisi surat, dan unduh PDF resmi.
    3. **Bidang 2 (`staff_bidang_2` & `wadir_2`)**: Pemisahan hak akses tegas — **Staff Bidang 2** (`verifikasi.bidang_2_view`) hanya membaca & memantau berkas surat permohonan masuk tanpa formulir verifikasi maupun aksi penerusan; sedangkan **Wadir 2** (`verifikasi.wadir_2_decide` & `pembayaran.memo_wadir2`) menyetujui kegiatan secara paralel bersama PPK terpilih pasca-penetapan anggaran Perencanaan, serta menyetujui Memo Bayar Wadir II secara paralel dengan PPK.
    4. **Perencanaan (`perencanaan`)**: Menerima berkas langsung dari Pemohon (atau revisi), membuat & mengecek ketersediaan pos anggaran (`Tersedia?` / Hold), **pemegang wewenang eksklusif mengunci (`lock`) dan membuka kunci (`unlock`) pos anggaran**, memilih **PPK awal** (`id_user_ppk_awal`), meneruskan usulan ke tahap persetujuan paralel `Wadir 2` & `PPK` terpilih, serta pemecahan (*split*) berkas permohonan berdasarkan pos anggaran (FR 6.3).
    5. **PPBJ (`ppbj`)**: Menerima berkas setelah disetujui bersama oleh Wadir 2 & PPK, pemecahan (*split*) berkas berdasarkan jenis pengadaan (`barang`, `jasa`, `konstruksi` - FR 7.2), registrasi paket & penentuan **Metode Pengadaan** (`belanja_langsung` untuk $\le$ Rp50.000.000 dengan output **Kwitansi** tanpa Kontrak/SPK; `non_tender` / `e_katalog` Inaproc dan `tender` / `spse` untuk $>$ Rp50.000.000), input data lelang untuk $>$ Rp200.000.000, mengunggah **tepat 1 dokumen perikatan terpilih** (`surat_pesanan`, `spk`, atau `surat_kontrak`) beserta tembusan otomatis ke Perlengkapan & Keuangan, alih penugasan (*reassign*) PPK, serta pengajuan pembayaran (melengkapi Kwitansi & Invoice).
    6. **Pejabat Pengadaan (`pp` - 1 akun aktif)**: Terlibat pada seluruh paket $>$ Rp50.000.000 s.d. Rp200.000.000 (baik Tender maupun Non-Tender) — melakukan review/verifikasi kelengkapan dokumen (**PP tidak bisa klik Approval** dan **tidak menerbitkan BAST**), menginput *Nominal Harga Perkiraan*, *Nominal Harga Akhir*, serta *Vendor/Pemenang Lelang*, berkoordinasi meminta approval PPK, dan **mengeksekusi kewenangan belanja** setelah disetujui PPK.
    7. **Pejabat Pembuat Komitmen (`ppk` - 4 akun terisolasi)**: Menyetujui usulan awal (`verifikasi.ppk_approve`) secara paralel bersama Wadir 2 atas penetapan anggaran Perencanaan, meninjau & menyetujui dokumen paket pengadaan (`reviu.ppk`) baik atas hasil review PP pada paket $>$ Rp50.000.000 s.d. Rp200.000.000 maupun review langsung & eksekusi belanja pada paket $>$ Rp200.000.000 dengan **penegakan isolasi data ketat** antar akun PPK, serta menyetujui Memo Pembayaran PPK secara paralel dengan Wadir 2 (`pembayaran.memo_ppk`, termasuk paket Belanja Langsung dan PPK pengganti hasil alih tugas).
    8. **Perlengkapan (`perlengkapan`)**: Satu-satunya peran yang menerbitkan **BAST** resmi bertoken validasi HMAC SHA-256 (baik dari paket berdokumen Kontrak/SPK/Surat Pesanan maupun Belanja Langsung ber-Kwitansi), menerima tembusan otomatis SPK, memeriksa fisik barang/jasa, upload dokumentasi multi-foto, dan menentukan kategori **Belanja Barang** (`belanja_barang`) vs **Belanja Modal** (`belanja_modal` / SIMAK-BMN).
    9. **Keuangan (`keuangan`)**: Menerima tembusan otomatis SPK, memverifikasi Informasi Dokumen Pembayaran (`SPK`/`Surat Pesanan`/`Surat Kontrak` terpilih + `Kwitansi` + `Invoice`) serta BAST, mengeksekusi pencairan dana SP2D anti-duplikasi bayar, dan upload bukti slip transfer bank.
- **Pembersihan Berkas Transisi `tes_*`**:
  - Seluruh berkas transisi pengujian awal Zero-CSS (`tes_*`) dan utilitas `DEV_VIEWMODE` telah **dihapus secara permanen** (*safe delete*) demi kesiapan produksi penuh.
  - Aplikasi berjalan 100% menggunakan arsitektur Blade standar dengan Tailwind CSS dan Alpine.js.

---

## 2. Prinsip Pengujian Fungsional & Sinergi dengan Pest
- **Hak & Wewenang Pengujian Manual oleh Overseer**:
  - Pengujian fungsional dan penerimaan sistem (*acceptance testing*) dilakukan secara **langsung / manual oleh Overseer** melalui antarmuka web produksi di browser.
- **Kewajiban Pengujian Otomatis Pest oleh Agen AI**:
  - Agen AI **WAJIB** membuat dan memelihara pengujian otomatis berbasis Pest (`tests/Feature` dan `tests/Unit`) untuk setiap fitur, endpoint, dan aturan bisnis backend.
  - Suite uji Pest berfungsi sebagai jaring pengaman otomatis (*automated regression guard*) untuk memastikan tidak ada logika atau validasi yang rusak selama pengembangan.
- **Kemudahan Akses Testing (Quick-Login Switcher)**:
  - Pada halaman `/login`, sistem menyediakan panel tabel daftar 9 akun demo seeder institusi PNJ lengkap dengan tombol bantu 1-klik untuk autofill kredensial.
  - Ini memungkinkan Overseer berganti peran secara instan (misal: Pemohon $\rightarrow$ Staf B2 $\rightarrow$ Wadir 2 $\rightarrow$ Perencanaan $\rightarrow$ PPBJ $\rightarrow$ PPK $\rightarrow$ Perlengkapan $\rightarrow$ Keuangan) saat memverifikasi alur pengadaan.

---

## 3. Aturan Kerja Berkas di Folder `resources/`
- **Arsitektur Produksi Tunggal**:
  - Views: `resources/views/**/<nama>.blade.php` (contoh: `auth/login.blade.php`, `auth/register.blade.php`, `dashboard/index.blade.php`, `layouts/app.blade.php`, `errors/layout.blade.php`, dst.)
  - CSS: `resources/css/app.css` (memuat `@fontsource/inter`, `@fontsource/iceland`, `@import "tailwindcss";`, dan modul CSS pada `resources/css/modules/` seperti `layout.css`, `dashboard.css`, `components.css`, `profile.css`, `errors.css`)
  - JavaScript: `resources/js/app.js` (memuat dan menjalankan `alpinejs` lokal dari npm serta mendaftarkan seluruh modul dari `resources/js/modules/`)
- **Kewajiban Mutlak: Keselarasan Dokumentasi Integrasi**:
  1. Seluruh form action, AJAX/fetch request, payload binding, dan navigasi pada berkas frontend mengacu penuh pada dokumen `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md` yang tervalidasi.
  2. Jika ditemukan penambahan endpoint atau penyesuaian validasi, perbarui dokumentasi tersebut terlebih dahulu.
- **Konfigurasi Bundler Aset (`vite.config.js`)**:
  - Build Vite memuat dua entrypoint produksi resmi:
    ```javascript
    input: [
        'resources/css/app.css',
        'resources/js/app.js',
    ],
    ```

---

## 4. Aturan JavaScript & Pola Modularitas (Folder `resources/js`)
- **Pemisahan Berkas JavaScript & Prinsip Modularitas (ES Modules)**:
  - `resources/js/app.js` berfungsi murni sebagai **entrypoint orkestrasi global**.
  - **DILARANG** menumpuk seluruh logika JavaScript berbagai halaman atau fitur di dalam satu berkas raksasa.
  - **Pola Pemecahan Modular**:
    - Logika fitur/topik dipecah ke dalam modul-modul mandiri di subdirektori `resources/js/modules/` (contoh: `resources/js/modules/desktop_notification.js`, `resources/js/modules/tracking.js`, `resources/js/modules/split_mak.js`) lalu diimpor ke dalam `resources/js/app.js`.
- **Larangan Script Inline**:
  - **DILARANG** menuliskan kode JavaScript berulang secara inline di dalam tag `<script> ... </script>` pada file Blade view jika dapat dimodularisasikan ke `resources/js/modules/`.
- **Pemuatan Berkas Skrip**:
  - Pada layout utama (`layouts/app.blade.php`):
    ```blade
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    ```
- Seluruh file JavaScript yang aktif diverifikasi kelancaran kompilasinya melalui `npm run build`.

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

## 7. Aturan Pengujian Backend: Wajib Pest PHP
- **Standar Testing Proyek**: Seluruh pengujian backend otomatis di proyek SIM-PBJ ini **WAJIB menggunakan Pest PHP** (sesuai `composer.json` dan `AGENTS.md`).
- **Pembuatan File Test**: Gunakan `php artisan make:test --pest {name}` (atau dengan opsi `--unit` untuk `tests/Unit`). Jangan memasukkan prefix `Feature/` atau `Unit/` pada `{name}`.
- **Sintaks Pengujian**: Gunakan fungsi uji standar Pest seperti `it('...', function () { ... })` atau `test('...', function () { ... })` dengan assertion Laravel/Pest (`$this->assertDatabaseHas(...)`, `expect(...)`, `$response->assertStatus(200)`).
- **Eksekusi Pengujian**: Jalankan pengujian menggunakan `php artisan test --compact` atau `vendor/bin/pest` dengan path berkas atau `--filter=namaTest` agar lingkup uji tetap terarah.

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
  - Administrator meninjau antrean pendaftar pada modul `/admin/persetujuan-registrasi` (view `resources/views/admin/approval_registrasi.blade.php`).
  - **Persetujuan (ACC)**: Administrator memilihkan dan menetapkan Role definitif bagi pengguna (`id_role` dan `role`). Status pengguna berubah menjadi `disetujui`, `status_aktif = true`, serta tercatat identitas admin verifikator dan waktu persetujuan.
  - **Penolakan (Tolak)**: Administrator wajib mencantumkan alasan penolakan (`alasan_penolakan`). Status berubah menjadi `ditolak` dan akun tetap nonaktif.
  - **Penegakan FR 2.1**: Saat menetapkan peran `pp` (Pejabat Pengadaan), sistem memeriksa ketat agar hanya ada maksimal 1 akun PP aktif. Jika sudah ada akun PP aktif, penetapan role `pp` akan digagalkan oleh validasi sistem.
- **Manajemen Role & Hak Akses Fitur (CRUD Role & Permissions Matrix)**:
  - Modul `/admin/roles` (view `resources/views/admin/roles.blade.php`) dan REST API `/api/roles`.
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
