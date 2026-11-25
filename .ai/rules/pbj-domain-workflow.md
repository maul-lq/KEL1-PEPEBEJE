---
paths:
  - 'app/**'
  - 'routes/**'
  - 'database/**'
  - 'resources/**'
  - 'tests/**'
---

# Aturan Domain & Alur Bisnis SIM-PBJ (Notulensi PPBJ Terbaru)

1. **Alur Usulan Awal, Pemantauan Staf Bidang II, & Persetujuan Paralel Wadir II dan PPK**:
   - **Urutan Alur Awal**: **`Pemohon -> Bagian Perencanaan (Tentukan Pos Anggaran & Pilih PPK) -> Verifikasi Paralel Wakil Direktur II & PPK Terpilih -> Unit Pengadaan (PPBJ)`**.
   - **Peran Staf Bidang II (Baca-Saja)**: Staf Bidang II (`verifikasi.bidang_2_view`) hanya memiliki hak melihat/memantau usulan permohonan masuk dan pratinjau dokumennya tanpa formulir verifikasi, tanpa upload memo, dan tanpa tombol penerusan.
   - **Penentuan Pos Anggaran oleh Bagian Perencanaan**: Bagian Perencanaan menerima usulan baru maupun revisi secara langsung, menentukan/mengalokasikan ketersediaan anggaran (`anggaran.mak_alokasi`), dan memilih PPK penanggung jawab awal (`id_user_ppk_awal`), lalu mendorong status ke `menunggu_acc_wadir_ppk`.
   - **Persetujuan Paralel Wadir II & PPK Terpilih**: Setelah Bagian Perencanaan menetapkan pos anggaran dan PPK, Wakil Direktur II (`verifikasi.wadir_2_decide`) dan PPK terpilih (`verifikasi.ppk_approve`) memeriksa dan memutuskan persetujuan secara paralel sebelum diteruskan ke Unit Pengadaan. Wewenang persetujuan dokumen paket pengadaan PPK dipisahkan pada izin `reviu.ppk` agar tidak tercampur dengan persetujuan usulan awal.
2. **Otoritas Tunggal Kunci & Buka Kunci MAK di Perencanaan**:
   - Selain membuat MAK, **hanya role `perencanaan`** yang berwenang mengunci (`lock`) dan membuka kunci (`unlock`) MAK. PPK maupun penerbitan SPK tidak mengunci MAK secara sepihak.
3. **Penentuan Metode Pengadaan & 3 Jalur Eksekusi**:
   - PPBJ menentukan metode pengadaan (`belanja_langsung`, `non_tender` / `e_katalog` Inaproc, `tender` / `spse`):
     - **`<= Rp50.000.000` (Belanja Langsung)**: Dieksekusi PPBJ, outputnya **Kwitansi** (**TIDAK ADA Kontrak/SPK/Surat Pesanan**), opsional Reminder -> lanjut ke Perlengkapan -> lanjut ke Pembayaran.
     - **`> Rp50.000.000` s.d. `Rp200.000.000` (Melibatkan PP)**: Baik Tender maupun Non-Tender masuk ke **PP** (1 akun). PP hanya mereview/verifikasi kelengkapan & menginput *Nominal Harga Perkiraan*, *Nominal Harga Akhir*, dan *Vendor/Pemenang* (**PP tidak bisa klik Approval** dan **tidak menerbitkan BAST**). Approval tetap oleh **PPK**, lalu **PP mengeksekusi belanja**, kemudian **PPBJ** mengunggah 1 dokumen perikatan terpilih & set Reminder.
     - **`> Rp200.000.000` (Melibatkan PPK)**: PPBJ menginput data lelang (*Harga Perkiraan, Harga Akhir, Vendor*) -> **PPK** mereview, klik Approval, & mengeksekusi belanja -> **PPBJ** mengunggah 1 dokumen perikatan terpilih & set Reminder.
4. **Satu Dokumen Perikatan Terpilih (`> Rp50 Juta`)**:
   - Setiap paket `> Rp50 juta` hanya mengunggah **salah satu** dokumen: `Surat Pesanan` (`surat_pesanan`) atau `SPK` (`spk`) untuk E-Katalog/Inaproc, atau `Surat Kontrak` (`surat_kontrak`) untuk Non-Tender/Tender pihak ketiga.
5. **BAST Murni di Perlengkapan & Informasi Dokumen Pembayaran**:
   - **BAST** hanya diterbitkan oleh **Perlengkapan** saat serah terima fisik barang (beserta penentuan `belanja_barang` / `belanja_modal`, foto, dan tanda tangan digital), baik untuk paket `> 50jt` maupun `<= 50jt` Belanja Langsung.
   - **Informasi Dokumen Pembayaran** menampilkan referensi dokumen perikatan tunggal (`SPK` / `Surat Pesanan` / `Surat Kontrak` untuk `> 50jt`) **ditambah** `Kwitansi` dan `Invoice`, lalu melewati Memo Bayar paralel (`Wadir II` via `pembayaran.memo_wadir2` dan `PPK` penanggung jawab via `pembayaran.memo_ppk`, termasuk paket Belanja Langsung serta PPK pengganti hasil alih tugas) -> Perintah Bayar `PPBJ` -> Pencairan `Keuangan`.
6. **Kelengkapan Navigasi & Entrypoint Antarmuka (End-to-End Navigation)**:
   - Setiap modul yang memiliki alur pemantauan atau persetujuan (`verifikasi.bidang_2_view`, `anggaran.*`, `verifikasi.wadir_2_*`, `verifikasi.ppk_approve`, `reviu.ppk`) **wajib** memiliki 2 pintu masuk antarmuka berbasis RBAC permission:
     a. **Ikon Menu Sidebar**: Terpasang di `resources/views/layouts/app.blade.php` dengan pemeriksaan izin RBAC yang sesuai, title deskriptif, dan indikator badge antrean aktif.
     b. **Daftar Antrean & Kartu Statistik Dashboard**: Tampil di `resources/views/dashboard/roles/{role}.blade.php` menggunakan cek izin murni dan data dari `HomeController::getRoleDashboardData()`.
7. **Integritas Sinkronisasi Skema Database**:
   - Setelah setiap penggabungan kode (merge git) atau penambahan migrasi baru, wajib memverifikasi `php artisan migrate:status` dan menjalankan `php artisan migrate --no-interaction` sebelum memulai pengujian alur bisnis.
8. **Penyederhanaan Entitas Vendor & Input Rekanan Secara Inline**:
   - **Struktur Minimalis Vendor**: Rekanan vendor hanya menyimpan nama vendor (`nama_vendor`) dan dokumen legalitas digital (`file_dokumen_legalitas`). Kolom `npwp`, `npwp_vendor`, `is_active`, NIB, rekening bank, PIC, dan `nama_perusahaan` telah dihapus seluruhnya dari database dan UI.
   - **Sumber Peran Tunggal & Otorisasi Berbasis Permission**: Tabel `users` hanya menyimpan `id_role` (FK ke `roles.id`), tanpa kolom teks `users.role`. Otorisasi fitur di rute, controller, policy, dan Blade wajib memakai permission (`$user->hasPermission(...)`, `$user->hasAnyPermission(...)`) serta seleksi kandidat PPK memakai `User::eligibleForPpk()` / `User::eligibleForPermissions(...)`. Gunakan label izin yang jelas, tergeneralisasi dalam Bahasa Indonesia, dan bebas jargon teknis internal.
   - **Peniadaan Modul Master `/vendors`**: Rute, controller, dan antarmuka master CRUD rekanan ditiadakan dari sidebar navigasi dan sistem.
   - **Penetapan Rekanan di Tahap Perikatan/Belanja (Bukan di Registrasi Paket)**: Pada saat pendaftaran awal paket pengadaan oleh PPBJ, vendor belum ditentukan. Rekanan baru diinput dan dokumen legalitasnya diunggah secara *inline* pada tahap penerbitan dokumen perikatan (Kwitansi Belanja Langsung pada `<= 50jt`, SPK/Surat Pesanan pada `50-200jt`, atau Kontrak pada `> 200jt`).
9. **Auto-Routing HPS, Filter Metode Pengadaan, & Sinkronisasi Saldo MAK**:
   - **Reaktivitas Registrasi Paket**: Saat permohonan disetujui dipilih pada `paket/create.blade.php`, sistem otomatis mengisi nominal HPS dari pagu permohonan dan menyusun nama paket default.
   - **Auto-Locking & Filtering Dropdown Metode**:
     - **`<= Rp50.000.000` (Belanja Langsung)**: Dropdown metode otomatis terkunci ke `Belanja Langsung` (`disabled` di UI dan dikirim via hidden input). Penugasan PP dan PPK dikosongkan.
     - **`> Rp50.000.000` s.d. `Rp200.000.000` (Jalur PP)**: Dropdown metode memfilter opsi perikatan: `SPK`, `Surat Pesanan (E-Purchasing / E-Katalog)`, atau `Surat Kontrak`.
     - **`> Rp200.000.000` (Jalur PPK)**: Dropdown metode memfilter opsi: `Tender Terbuka (SPSE)`, `Non-Tender (E-Purchasing / E-Katalog)`, atau `Seleksi Konsultansi`. Nomor memo PPBJ otomatis dikosongkan.
   - **Sinkronisasi Saldo MAK saat Penyesuaian Nilai HPS**:
     - Jika PPBJ mengubah nilai HPS berbeda dari estimasi pagu awal permohonan:
       - **Kenaikan Nilai HPS (`HPS > Pagu Awal`)**: Sistem memverifikasi ketersediaan saldo MAK (`sisa_pagu_mak >= selisih`). Jika mencukupi, `sisa_pagu_mak` dikurangi sebesar selisih. Jika tidak mencukupi, form registrasi ditolak dengan pesan kesalahan saldo tidak mencukupi.
       - **Efisiensi / Penurunan Nilai HPS (`HPS < Pagu Awal`)**: Selisih efisiensi anggaran otomatis dikembalikan ke `sisa_pagu_mak` pada rekening MAK terkait (`sisa_pagu_mak += abs(selisih)`).
       - Nilai pagu permohonan (`estimasi_total_pagu`) dan nominal pada `AlokasiMAK` disinkronkan ke nilai HPS baru.
    - **Sinkronisasi Saldo MAK saat Realisasi Akhir (Kontrak/SPK & Belanja Langsung)**:
      - Nominal realisasi akhir (`nilai_kontrak` untuk `> 50jt` maupun `nominal_harga_akhir` untuk Belanja Langsung `<= 50jt`) **tidak boleh melebihi** `nilai_hps` paket yang telah disetujui PPK (dan Belanja Langsung tetap dibatasi `<= Rp50.000.000`).
      - Jika realisasi akhir lebih rendah dari `nilai_hps`, selisih efisiensi otomatis dikembalikan ke `sisa_pagu_mak`, sementara `nilai_hps`, `alokasi_pagu_paket`, `estimasi_total_pagu`, dan `AlokasiMAK.nominal_alokasi` diselaraskan ke nominal realisasi akhir.


