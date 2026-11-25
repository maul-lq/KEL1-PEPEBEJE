<div align="center">
  <h1>SIM-PBJ Politeknik Negeri Jakarta</h1>
  <p>Sistem Informasi Manajemen Pengadaan Barang dan Jasa berbasis Laravel</p>
  <p>
    <kbd>PHP ^8.3</kbd>
    <kbd>Laravel ^13.17</kbd>
    <kbd>Alpine.js ^3.17</kbd>
    <kbd>Tailwind CSS ^4.0</kbd>
    <kbd>Vite ^8.0</kbd>
    <kbd>GPL-3.0</kbd>
  </p>
  <p>
    <a href="#tujuan-proyek">Tujuan proyek</a> |
    <a href="#tech-stack">Tech stack</a> |
    <a href="#instalasi">Instalasi</a> |
    <a href="#cara-menjalankan">Cara menjalankan</a> |
    <a href="#struktur-folder">Struktur folder</a> |
    <a href="#daftar-modul-dan-fungsi">Daftar modul dan fungsi</a>
  </p>
</div>

<hr>

> Panduan ini disusun untuk pengembang yang baru membuka repositori `KEL1-PEPEBEJE` agar dapat memasang lingkungan lokal, memahami tata letak kode, dan menelusuri alur pengadaan barang dan jasa PNJ tanpa menebak-nebak isi modul.

## Tujuan proyek

SIM-PBJ Politeknik Negeri Jakarta mendigitalkan alur kerja pengadaan barang dan jasa dari tingkat jurusan/unit kerja sampai pencairan dana di Bagian Keuangan. Aplikasi ini menggantikan pencatatan berkas manual dengan alur persetujuan berjenjang, pemeriksaan pagu Mata Anggaran Kegiatan (MAK), pemisahan tugas antar pelaku pengadaan, serta pencetakan dokumen resmi dalam format PDF dan CSV.

Secara ringkas, alur bisnis utama yang dikawal aplikasi berjalan melalui urutan berikut:

```text
[Jurusan / Unit] Ajukan Permohonan
        |
        v
[Staf Bidang II] Cek Kelengkapan Berkas
        |
        v
[Bagian Perencanaan] Validasi & Kunci Kode MAK DIPA
        |
        v
[Wadir II & PPK] Keputusan Persetujuan / Penolakan
        |
        v
[Koordinator PPBJ] Bentuk Paket & Auto-Routing Nominal HPS
   |-- <= Rp50 juta        --> Belanja Langsung (tanpa PPK)
   |-- > Rp50jt - Rp200jt  --> Pejabat Pengadaan (PP) -> Reviu & Kontrak/SPK
   |-- > Rp200 juta        --> PPK Mandiri            -> Reviu & Kontrak/SPK
        |
        v
[Unit Perlengkapan] Pemeriksaan Fisik, Foto, & Penerbitan BAST
        |
        v
[PPBJ -> PPK & Wadir II -> Keuangan] Tagihan, Memo Bayar, SPP, & Pencairan SP2D
```

Beberapa karakter teknis penting pada basis kode ini yang perlu diketahui pengembang sejak awal:

1. Otorisasi tidak ditanam mati pada nama peran saja, melainkan dievaluasi melalui tabel `roles` dan `permissions` lewat middleware `CheckRole` (`role:...`) dan `PaketPengadaanPolicy`.
2. Nomor surat dinas, nomor paket, kode MAK, dan nomor pembayaran memakai garis miring (`/`). Pada `routes/web.php`, rute dengan parameter dinamis tersebut memakai pola wildcard `->where([... => '.*'])` dan ditempatkan setelah rute statis agar tidak bertabrakan.
3. Pembenihan standar (`DatabaseSeeder`) hanya mengisi entitas master (16 unit kerja PNJ, 10 peran sistem, katalog permission, dan 13 akun operasional) supaya basis data pengujian awal bersih dari data transaksi tiruan.

## Tech stack

<table>
  <thead>
    <tr>
      <th align="left">Lapisan</th>
      <th align="left">Teknologi &amp; Paket</th>
      <th align="left">Peran dalam Aplikasi</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Backend &amp; Framework</td>
      <td><code>PHP ^8.3</code> (berjalan di 8.5), <code>laravel/framework ^13.17</code></td>
      <td>Routing, middleware RBAC, kebijakan akses, antrean, notifikasi email, dan logika transaksi PBJ.</td>
    </tr>
    <tr>
      <td>Templating &amp; Interaktivitas UI</td>
      <td>Laravel Blade, <code>alpinejs ^3.17.5</code> (lokal via npm/Vite)</td>
      <td>Komponen modal, tab, pelacakan status, formulir dinamis, dan kontrol antarmuka tanpa bergantung pada CDN.</td>
    </tr>
    <tr>
      <td>Styling &amp; Tipografi Lokal</td>
      <td><code>tailwindcss ^4.0.0</code>, <code>@tailwindcss/vite</code>, <code>@fontsource/inter ^5.3.0</code>, <code>@fontsource/iceland ^5.3.0</code></td>
      <td>Desain sistem tema PNJ dan aset huruf lokal (Inter &amp; Iceland) terbundel penuh agar tetap tampil normal secara luring/LAN.</td>
    </tr>
    <tr>
      <td>Bundler Frontend</td>
      <td><code>vite ^8.0.0</code>, <code>laravel-vite-plugin ^3.1</code></td>
      <td>Kompilasi <code>resources/css/app.css</code> (CSS modular di <code>resources/css/modules/</code>) dan <code>resources/js/app.js</code> (14 modul di <code>resources/js/modules/</code>).</td>
    </tr>
    <tr>
      <td>Basis Data</td>
      <td>PostgreSQL, SQLite, atau MySQL</td>
      <td>Penyimpanan data pengadaan, anggaran MAK, pembayaran, notifikasi, dan RBAC (skema referensi di <a href="sql/database.sql">sql/database.sql</a>).</td>
    </tr>
    <tr>
      <td>Integrasi &amp; Dokumen</td>
      <td><code>laravel/socialite ^5.31</code>, <code>barryvdh/laravel-dompdf ^3.1</code>, Service Worker (<code>public/sw.js</code>)</td>
      <td>Masuk melalui Google OAuth, ekspor dokumen resmi PDF/BAST/laporan, dan notifikasi desktop peramban.</td>
    </tr>
    <tr>
      <td>Pengujian &amp; Pemformatan</td>
      <td><code>pestphp/pest ^5.1</code>, <code>laravel/pint ^1.27</code>, <code>laravel/tinker ^3.0</code></td>
      <td>Suite uji fitur/unit terisolasi dan penyeragaman gaya penulisan kode PHP.</td>
    </tr>
  </tbody>
</table>

## Instalasi

### Prasyarat lingkungan

<table>
  <thead>
    <tr>
      <th align="left">Komponen</th>
      <th align="left">Kebutuhan minimum</th>
      <th align="left">Catatan pemakaian</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>PHP</td>
      <td><code>^8.3</code></td>
      <td>Aktifkan ekstensi standar Laravel serta driver PDO (<code>pdo_sqlite</code>, <code>pdo_pgsql</code>, atau <code>pdo_mysql</code>) sesuai basis data pilihan Anda.</td>
    </tr>
    <tr>
      <td>Composer</td>
      <td>Versi 2.x</td>
      <td>Mengelola dependensi PHP (<code>laravel/framework ^13.17</code>, <code>laravel/socialite ^5.31</code>, <code>barryvdh/laravel-dompdf ^3.1</code>).</td>
    </tr>
    <tr>
      <td>Node.js dan npm</td>
      <td>LTS aktif</td>
      <td>Dibutuhkan untuk kompilasi aset lokal dengan Vite (<code>^8.0.0</code>), Tailwind CSS (<code>^4.0.0</code>), Alpine.js (<code>^3.17.5</code>), serta paket font <code>@fontsource/inter</code> dan <code>@fontsource/iceland</code>.</td>
    </tr>
    <tr>
      <td>Basis data</td>
      <td>SQLite, PostgreSQL, atau MySQL</td>
      <td>Berkas <code>.env.example</code> menyetel SQLite sebagai bawaan lokal.</td>
    </tr>
  </tbody>
</table>

### Pemasangan cepat dengan Composer

Repositori menyediakan skrip `composer setup` yang menginstal paket PHP, membuat `.env`, membangkitkan `APP_KEY`, menjalankan migrasi, menginstal dependensi Node, dan membangun aset frontend.

```bash
git clone https://github.com/maul-lq/KEL1-PEPEBEJE.git
cd KEL1-PEPEBEJE
composer setup
php artisan db:seed
php artisan storage:link
```

Perintah `php artisan db:seed` wajib dijalankan setelah setup pertama agar 16 unit PNJ, role/permission RBAC, dan akun uji awal tersedia. Tautan simbolik `php artisan storage:link` diperlukan supaya lampiran surat, foto penerimaan barang, dan berkas kontrak di `storage/app/public` dapat diakses dari `public/storage`.

### Pemasangan manual dan penyesuaian basis data

Jika ingin memakai PostgreSQL atau MySQL sebelum migrasi dijalankan, ikuti langkah berikut:

1. Pasang dependensi backend dan frontend:

   ```bash
   composer install
   npm install
   ```

2. Salin berkas lingkungan lalu buat kunci enkripsi aplikasi:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Buka berkas `.env` dan sesuaikan blok basis data. Secara bawaan `.env.example` memakai `DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, dan `MAIL_MAILER=log`. Untuk PostgreSQL atau MySQL, ubah `DB_CONNECTION` lalu aktifkan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`. Skema referensi SQL juga tersedia di [sql/database.sql](sql/database.sql).

4. Jalankan migrasi beserta seeder master, buat tautan penyimpanan, lalu bangun aset Vite:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm run build
   ```

<details>
  <summary>Konfigurasi opsional (.env): Google OAuth, Email SMTP, dan Web Push VAPID</summary>

<br>

<table>
  <thead>
    <tr>
      <th align="left">Fitur</th>
      <th align="left">Variabel .env</th>
      <th align="left">Keterangan</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Google OAuth (Socialite)</td>
      <td><code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code>, <code>GOOGLE_REDIRECT_URI</code></td>
      <td>Callback bawaan mengarah ke <code>http://127.0.0.1:8000/auth/google/callback</code>. Dipakai untuk masuk dan menautkan akun Google di halaman profil.</td>
    </tr>
    <tr>
      <td>Web Push dan Notifikasi Desktop</td>
      <td><code>VAPID_PUBLIC_KEY</code>, <code>VAPID_PRIVATE_KEY</code>, <code>VAPID_SUBJECT</code></td>
      <td>Berkas <code>.env.example</code> sudah menyertakan nilai awal pengembangan; ganti pasangan kunci VAPID sebelum rilis produksi.</td>
    </tr>
    <tr>
      <td>Pengiriman Email Reminder</td>
      <td><code>MAIL_MAILER</code>, <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>, <code>MAIL_ENCRYPTION</code>, <code>MAIL_FROM_ADDRESS</code>, <code>MAIL_FROM_NAME</code></td>
      <td>Mode bawaan <code>log</code> menulis isi email ke log Laravel agar aman saat pengujian lokal. Ubah ke <code>smtp</code> bila ingin mengirim email sungguhan.</td>
    </tr>
  </tbody>
</table>

</details>

## Cara menjalankan

### Menjalankan server pengembangan

Anda dapat menjalankan proses pengembangan bawaan Laravel (`php artisan serve`, `php artisan queue:listen`, dan `npm run dev`) sekaligus dengan perintah:

```bash
composer dev
```

Atau jalankan server web dan kompiler aset di dua terminal terpisah:

```bash
php artisan serve
npm run dev
```

Setelah berjalan, buka `http://127.0.0.1:8000` (sesuai nilai `APP_URL` pada `.env`).

### Menjalankan penjadwal pengingat

Middleware `TriggerDueReminders` pada grup rute `web` sudah memicu `ReminderSchedulerService` (berjeda cache 2 menit) secara otomatis setiap kali ada pengguna login yang mengakses halaman. Pada pengembangan lokal, Anda umumnya tidak perlu menyalakan proses scheduler terpisah.

Jika perlu menjalankan jadwal 5 menitan dari [routes/console.php](routes/console.php) (`reminder:proses`) tanpa menunggu request web, atau saat menyiapkannya pada server produksi, gunakan perintah berikut:

```bash
# Menjalankan worker penjadwalan otomatis setiap 5 menit di lingkungan lokal
php artisan schedule:work

# Memicu satu siklus pemrosesan pengingat secara langsung
php artisan reminder:proses
```

Pengiriman email pada `PengingatPengadaanNotification` saat ini dieksekusi secara sinkron di dalam proses request/command (belum mengimplementasikan `ShouldQueue`), sehingga worker antrean tambahan tidak diperlukan kecuali Anda mengubah notifikasi menjadi asinkron.

### Pengujian dan pemformatan kode

Suite pengujian memakai Pest PHP (`tests/Feature` dan `tests/Unit`), sedangkan pemformatan kode mengikuti standar Laravel Pint:

```bash
# Membersihkan cache konfigurasi dan menjalankan seluruh suite test
composer test

# Menjalankan test dengan output ringkas
php artisan test --compact

# Memeriksa dan merapikan gaya penulisan berkas PHP yang berubah
vendor/bin/pint --dirty
```

Untuk menyiapkan bundel aset produksi sebelum deploy atau saat tampilan Vite tidak terbaca, jalankan `npm run build`.

<details>
  <summary>Daftar akun uji awal dari DatabaseSeeder (kata sandi semua akun: password123)</summary>

<br>

Pengguna yang mendaftar mandiri melalui halaman `/register` berstatus menunggu persetujuan dan tidak dapat login sebelum disetujui oleh `admin` pada menu persetujuan registrasi. Gunakan daftar akun bawaan hasil `php artisan db:seed` di bawah ini untuk menguji setiap peran BPMN:

<table>
  <thead>
    <tr>
      <th align="left">Username</th>
      <th align="left">Email</th>
      <th align="left">Peran (Role)</th>
      <th align="left">Unit</th>
      <th align="left">Cakupan Pengujian</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>admin</code></td>
      <td><code>admin@pnj.ac.id</code></td>
      <td><code>admin</code></td>
      <td><code>PST</code></td>
      <td>Persetujuan registrasi, CRUD pengguna, pengaturan matriks role/permission.</td>
    </tr>
    <tr>
      <td><code>user_pengaju</code></td>
      <td><code>pengaju@pnj.ac.id</code></td>
      <td><code>user</code></td>
      <td><code>TIK</code></td>
      <td>Membuat usulan permohonan, revisi berkas ditolak, memantau status usulan unit.</td>
    </tr>
    <tr>
      <td><code>staff_b2</code></td>
      <td><code>staff_b2@pnj.ac.id</code></td>
      <td><code>staff_bidang_2</code></td>
      <td><code>B2</code></td>
      <td>Pemeriksaan kelengkapan berkas awal dan penerusan ke Wadir II.</td>
    </tr>
    <tr>
      <td><code>wadir2</code></td>
      <td><code>wadir2@pnj.ac.id</code></td>
      <td><code>wadir_2</code></td>
      <td><code>B2</code></td>
      <td>Keputusan ACC/Tolak usulan awal dan persetujuan Memo Bayar Level 2.</td>
    </tr>
    <tr>
      <td><code>perencanaan</code></td>
      <td><code>perencanaan@pnj.ac.id</code></td>
      <td><code>perencanaan</code></td>
      <td><code>REN</code></td>
      <td>Verifikasi kode MAK, alokasi pagu, penguncian MAK, dan pemecahan usulan MAK.</td>
    </tr>
    <tr>
      <td><code>ppbj</code></td>
      <td><code>ppbj@pnj.ac.id</code></td>
      <td><code>ppbj</code></td>
      <td><code>PPBJ</code></td>
      <td>Pembuatan paket, split jenis pengadaan, Belanja Langsung, dan pengajuan tagihan/SPP.</td>
    </tr>
    <tr>
      <td><code>pp_user</code></td>
      <td><code>pp@pnj.ac.id</code></td>
      <td><code>pp</code></td>
      <td><code>PPBJ</code></td>
      <td>Reviu dokumen dan negosiasi paket jalur di atas Rp50 juta s.d. Rp200 juta.</td>
    </tr>
    <tr>
      <td><code>ppk_1</code> s.d. <code>ppk_4</code></td>
      <td><code>ppk1@pnj.ac.id</code> s.d. <code>ppk4@pnj.ac.id</code></td>
      <td><code>ppk</code></td>
      <td><code>PPK</code></td>
      <td>4 akun PPK mandiri (Sarana, Laboratorium, Konstruksi, Jasa) terisolasi per penugasan paket.</td>
    </tr>
    <tr>
      <td><code>perlengkapan</code></td>
      <td><code>perlengkapan@pnj.ac.id</code></td>
      <td><code>perlengkapan</code></td>
      <td><code>LKP</code></td>
      <td>Pemeriksaan fisik barang, unggah multi-foto dokumentasi, dan penerbitan BAST.</td>
    </tr>
    <tr>
      <td><code>keuangan</code></td>
      <td><code>keuangan@pnj.ac.id</code></td>
      <td><code>keuangan</code></td>
      <td><code>KEU</code></td>
      <td>Verifikasi tagihan SPP dan eksekusi pencairan dana SP2D.</td>
    </tr>
  </tbody>
</table>

</details>

## Struktur folder

Susunan direktori inti aplikasi pada repositori ini meliputi backend Laravel, aset Vite/Tailwind, skema basis data, berkas publik, dan pengujian:

```text
KEL1-PEPEBEJE/
├── app/
│   ├── Console/Commands/       # Perintah Artisan (ProsesReminderBerkalaCommand: reminder:proses)
│   ├── Http/
│   │   ├── Controllers/        # 13 controller alur PBJ serta subdirektori Admin/ (3 controller RBAC & user)
│   │   └── Middleware/         # CheckRole (evaluasi role & permission), SessionTimeout, TriggerDueReminders
│   ├── Models/                 # 20 model Eloquent (PermohonanPengadaan, PaketPengadaan, AnggaranMAK, KontrakSPK, dll.)
│   ├── Notifications/          # Kelas notifikasi Laravel (PengingatPengadaanNotification, ResetPasswordNotification, AccountStatusNotification)
│   ├── Observers/              # Model observer (NotifikasiUserObserver)
│   ├── Policies/               # Otorisasi tingkat objek paket (PaketPengadaanPolicy)
│   ├── Providers/              # Registrasi service aplikasi (AppServiceProvider)
│   ├── Rules/                  # Aturan validasi unggahan dokumen (DokumenUploadRule)
│   └── Services/               # Logika bisnis bersama (routing HPS, notifikasi, scheduler reminder, laporan)
├── bootstrap/                  # Inisialisasi framework Laravel dan berkas cache bootstrap
├── config/                     # Konfigurasi aplikasi, auth, basis data, filesystem, mail, queue, services, session
├── database/
│   ├── factories/              # Factory model pengujian (UserFactory)
│   ├── migrations/             # 44 berkas migrasi skema tabel pengadaan, MAK, pembayaran, RBAC, dan audit
│   └── seeders/                # DatabaseSeeder (16 unit PNJ & 13 akun) dan RolePermissionSeeder (10 role & permission)
├── public/                     # Document root HTTP (index.php, sw.js untuk notifikasi desktop, images/, storage/, build/)
├── resources/
│   ├── css/                    # app.css (Tailwind CSS v4 + @fontsource lokal), subfolder modules/ (layout, dashboard, components, profile, errors), dan pdf/
│   ├── js/                     # app.js (inisialisasi lokal Alpine.js) serta 14 modul interaksi halaman pada subfolder modules/
│   └── views/                  # Template Blade terbagi per modul (auth, dashboard, permohonan, verifikasi, paket, pp, ppk, kontrak, penerimaan, pembayaran, laporan, notifikasi, admin, profile, errors)
├── routes/
│   ├── web.php                 # Definisi endpoint web per modul, admin RBAC, cetak PDF, health check, dan API notifikasi
│   └── console.php             # Perintah Artisan dan penjadwalan cron 5 menitan untuk reminder:proses
├── sql/                        # Berkas database.sql sebagai referensi struktur skema SQL
├── storage/                    # Penyimpanan berkas unggahan (storage/app/public), log aplikasi, dan cache Blade
└── tests/
    ├── Feature/                # Pengujian autentikasi, RBAC, lampiran/revisi permohonan, tracking, dan reminder
    ├── Unit/                   # Pengujian unit dasar (ExampleTest)
    └── Browser/                # Berkas dan tangkapan layar pengujian antarmuka peramban
```

Untuk penelusuran alur bisnis lintas controller, empat berkas di `app/Services/` menangani logika bersama: `PengadaanRoutingService` (menentukan jalur HPS dan membuat jadwal reminder), `PengadaanNotificationService` (mengirim notifikasi ke peran atau unit sasaran), `ReminderSchedulerService` (mengeksekusi jadwal pengingat jatuh tempo dengan cache lock agar tidak ganda), dan `LaporanModularService` (menyusun data rekapitulasi anggaran dan pengadaan).

## Daftar modul dan fungsi

Seluruh fungsionalitas aplikasi terpetakan ke 11 area modul utama berikut berdasarkan definisi pada [routes/web.php](routes/web.php) dan `app/Http/Controllers/`:

<table>
  <thead>
    <tr>
      <th align="left">No</th>
      <th align="left">Modul</th>
      <th align="left">Controller / Layanan</th>
      <th align="left">Endpoint Utama</th>
      <th align="left">Akses / Role</th>
      <th align="left">Fungsi Utama</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>1</td>
      <td>Autentikasi dan Profil</td>
      <td><code>AuthController</code>, <code>GoogleAuthController</code>, <code>ProfileController</code>, <code>HomeController</code></td>
      <td><code>/login</code>, <code>/register</code>, <code>/forgot-password</code>, <code>/auth/google</code>, <code>/profil</code>, <code>/profile/google/link</code></td>
      <td><code>guest</code> / <code>auth</code></td>
      <td>Login kredensial atau Google OAuth (Socialite), registrasi akun baru menunggu persetujuan admin, reset kata sandi, tautan/putus akun Google, ubah email pengingat, dan batas waktu sesi (<code>SessionTimeout</code>).</td>
    </tr>
    <tr>
      <td>2</td>
      <td>Dasbor dan Utilitas PDF</td>
      <td><code>HomeController</code></td>
      <td><code>/dashboard</code>, <code>/dokumen-pdf/{jenis}/{id}</code>, <code>/health</code>, <code>/health-check</code></td>
      <td><code>auth</code> (<code>/health</code> publik)</td>
      <td>Menampilkan ringkasan status pengadaan sesuai peran aktif, melayani unduhan dokumen resmi PDF terpusat, dan memeriksa kesehatan koneksi basis data.</td>
    </tr>
    <tr>
      <td>3</td>
      <td>Permohonan Pengadaan</td>
      <td><code>PermohonanPengadaanController</code></td>
      <td><code>/permohonan</code>, <code>/permohonan/create</code>, <code>POST /permohonan</code>, <code>POST /permohonan/revisi</code>, <code>/permohonan/{id}</code></td>
      <td><code>auth</code> + izin <code>permohonan.*</code></td>
      <td>Pengajuan usulan barang/jasa beserta unggah lampiran, pelacakan riwayat usulan unit, revisi usulan yang ditolak/dikembalikan, serta penghapusan draf usulan.</td>
    </tr>
    <tr>
      <td>4</td>
      <td>Verifikasi Berantai dan MAK</td>
      <td><code>VerifikasiParalelController</code></td>
      <td><code>/verifikasi/bidang-2</code>, <code>/verifikasi/perencanaan</code>, <code>/verifikasi/wadir-2</code>, <code>/verifikasi/ppk</code></td>
      <td><code>staff_bidang_2</code>, <code>perencanaan</code>, <code>wadir_2</code>, <code>ppk</code></td>
      <td>Pemeriksaan berkas administrasi (Staf Bidang II), validasi dan alokasi pagu kode MAK DIPA, pemecahan usulan per MAK, kunci/buka MAK (Perencanaan), serta keputusan ACC/tolak dari Wadir II dan PPK.</td>
    </tr>
    <tr>
      <td>5</td>
      <td>Paket Pengadaan dan Belanja Langsung</td>
      <td><code>PaketPengadaanController</code>, <code>PengadaanRoutingService</code></td>
      <td><code>/paket-pengadaan</code>, <code>/paket-pengadaan/create</code>, <code>/paket-pengadaan/belanja-langsung</code>, <code>/paket-pengadaan/split-jenis</code>, <code>/paket-pengadaan/reassign-ppk</code></td>
      <td><code>ppbj</code> (lihat reassign: <code>ppbj</code>, <code>perencanaan</code>, <code>wadir_2</code>)</td>
      <td>Pembentukan paket pengadaan, pemecahan berkas berdasarkan jenis pengadaan, auto-routing HPS (s.d. Rp50 juta ke Belanja Langsung, di atas Rp50 juta s.d. Rp200 juta ke PP, di atas Rp200 juta ke PPK), pengalihan PPK, dan pencatatan Belanja Langsung.</td>
    </tr>
    <tr>
      <td>6</td>
      <td>Reviu PP/PPK dan Kontrak SPK</td>
      <td><code>KontrakSPKController</code></td>
      <td><code>/monitor-pp</code>, <code>/kontrak-spk</code>, <code>POST /kontrak-spk/reviu</code>, <code>/reviu-pengadaan/ppk</code></td>
      <td><code>ppk</code> + izin <code>reviu.*</code>, <code>spk.create</code>, <code>pp.monitoring_aktivitas</code>, <code>lihat_pp</code></td>
      <td>Pemantauan progres Pejabat Pengadaan, reviu teknis dokumen oleh PP dan PPK, persetujuan dokumen pengadaan di sisi PPK, serta penerbitan dan unggah dokumen Kontrak/SPK berikut data vendor.</td>
    </tr>
    <tr>
      <td>7</td>
      <td>Penerimaan Barang dan BAST</td>
      <td><code>PenerimaanBarangController</code></td>
      <td><code>/penerimaan-barang</code> (<code>GET</code>, <code>POST</code>)</td>
      <td><code>perlengkapan</code></td>
      <td>Pemeriksaan fisik barang oleh Unit Perlengkapan dan BMN, unggah multi-foto dokumentasi barang, penentuan klasifikasi Belanja Barang atau Modal, dan penerbitan BAST.</td>
    </tr>
    <tr>
      <td>8</td>
      <td>Tagihan SPP dan Pencairan SP2D</td>
      <td><code>PembayaranController</code></td>
      <td><code>/pembayaran</code>, <code>/pembayaran/pengajuan</code>, <code>PUT /pembayaran/{nomor_pembayaran}</code>, <code>/pembayaran/memo/approve</code>, <code>/pembayaran/terbitkan-spp</code></td>
      <td><code>ppbj</code>, <code>ppk</code>, <code>wadir_2</code>, <code>keuangan</code></td>
      <td>Pengajuan dan perbaikan berkas tagihan (PPBJ), persetujuan Memo Bayar oleh PPK dan Wadir II, penerbitan SPP, serta pencatatan pencairan dana SP2D oleh Bagian Keuangan.</td>
    </tr>
    <tr>
      <td>9</td>
      <td>Riwayat dan Laporan Terpadu</td>
      <td><code>LaporanController</code>, <code>LaporanModularService</code></td>
      <td><code>/laporan</code>, <code>/laporan/unduh-pdf</code>, <code>/laporan/unduh-csv</code>, <code>/laporan/unduh-laporan-pdf</code>, <code>/laporan/unduh-detail-pdf</code>, <code>/laporan/rekapitulasi-anggaran</code></td>
      <td><code>auth</code> + izin <code>dokumen.unduh_pdf</code> untuk unduhan</td>
      <td>Rekapitulasi riwayat pengadaan dan realisasi anggaran lintas unit serta ekspor berkas laporan ringkasan dan detail dalam format PDF dan CSV.</td>
    </tr>
    <tr>
      <td>10</td>
      <td>Notifikasi dan Reminder Berkala</td>
      <td><code>NotifikasiUserController</code>, <code>ProsesReminderBerkalaCommand</code>, <code>ReminderSchedulerService</code></td>
      <td><code>/notifikasi</code>, <code>/api/notifikasi-terbaru</code>, perintah <code>php artisan reminder:proses</code></td>
      <td><code>auth</code> + middleware <code>TriggerDueReminders</code></td>
      <td>Pusat notifikasi dalam aplikasi, polling JSON latar belakang, push desktop via Service Worker (<code>public/sw.js</code>), serta pemrosesan reminder jatuh tempo H-2 dan termin kontrak.</td>
    </tr>
    <tr>
      <td>11</td>
      <td>Admin RBAC dan Persetujuan Akun</td>
      <td><code>Admin\ApprovalRegistrasiController</code>, <code>Admin\UserController</code>, <code>Admin\RoleController</code></td>
      <td><code>/admin/persetujuan-registrasi</code>, <code>/admin/users</code>, <code>/admin/roles</code></td>
      <td>Izin <code>admin.approval_registrasi</code>, <code>admin.user_manage</code>, <code>admin.role_manage</code></td>
      <td>Persetujuan atau penolakan akun pendaftar baru, manajemen data akun pengguna dan unit kerja, serta pengaturan master role dan matriks permission dinamis.</td>
    </tr>
  </tbody>
</table>

### Catatan implementasi lintas modul

<details>
  <summary>Mekanisme teknis yang berlaku di hampir seluruh modul (routing HPS, PDF, dan RBAC)</summary>

<br>

<h4>Penentuan jalur otomatis</h4>
<p><code>PaketPengadaan::tentukanRouting</code> dan <code>PengadaanRoutingService</code> mengklasifikasikan nominal pagu paket atau HPS ke tiga konstanta jalur pada model <code>PaketPengadaan</code>, yaitu <code>langsung_sd_50jt</code> (s.d. <code>50.000.000</code>), <code>pp_50jt_sd_200jt</code> (lebih dari <code>50.000.000</code> s.d. <code>200.000.000</code>), dan <code>ppk_diatas_200jt</code> (di atas <code>200.000.000</code>). Paket pada jalur <code>ppk_diatas_200jt</code> otomatis mengosongkan <code>nomor_memo_ppbj</code> dan mengisolasi akses berdasarkan akun <code>id_user_ppk</code>.</p>

<h4>Evaluasi hak akses dinamis</h4>
<p>Middleware <code>CheckRole</code> (<code>role:...</code>) pada <a href="routes/web.php">routes/web.php</a> menerima campuran nama role (seperti <code>role:ppbj</code> atau <code>role:ppk,wadir_2</code>) maupun nama permission granular (seperti <code>role:permohonan.create</code> atau <code>role:admin.role_manage</code>). Pemetaan role ke permission dikelola melalui antarmuka admin maupun <a href="database/seeders/RolePermissionSeeder.php">database/seeders/RolePermissionSeeder.php</a>.</p>

<h4>Pencetakan dokumen terpusat</h4>
<p>Untuk menambah jenis cetakan PDF baru, tambahkan handler pada <code>HomeController::unduhDokumenPdf</code> (<code>/dokumen-pdf/{jenis}/{id}</code>) atau gunakan <code>barryvdh/laravel-dompdf</code> bersama stylesheet cetak di <code>resources/css/pdf/</code>.</p>

</details>

## Lisensi

Proyek ini dirilis di bawah lisensi [GNU General Public License v3.0 (GPL-3.0)](LICENSE).
