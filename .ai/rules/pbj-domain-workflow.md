---
paths:
  - 'app/**'
  - 'routes/**'
  - 'database/**'
  - 'resources/**'
  - 'tests/**'
---

# Aturan Domain & Alur Bisnis SIM-PBJ (Notulensi PPBJ Terbaru)

1. **Alur Persetujuan Awal Berantai (Bukan Paralel Langsung)**:
   - `User` mengajukan permohonan ke **Bidang 2** -> `Staff Bidang 2` memeriksa kelengkapan & mengirim **Memo Cek MAK** ke **Perencanaan** -> `Perencanaan` membuat/mengecek ketersediaan MAK (`Tersedia?`), memilih **1 dari 4 akun PPK** (`id_user_ppk_awal`), dan mengirim **Memo Ketersediaan MAK** ke `Wadir 2` & `PPK` terpilih -> `Wadir 2` (ACC kegiatan) **dan** `PPK` sama-sama menyetujui -> lanjut ke **PPBJ** (PPBJ tetap dapat *reassign* PPK bila ada penyesuaian).
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
   - **BAST** hanya diterbitkan oleh **Perlengkapan** saat serah terima fisik barang (beserta penentuan `belanja_barang` / `belanja_modal`, foto, & TTD digital HMAC), baik untuk paket `> 50jt` maupun `<= 50jt` Belanja Langsung.
   - **Informasi Dokumen Pembayaran** menampilkan referensi dokumen perikatan tunggal (`SPK` / `Surat Pesanan` / `Surat Kontrak` untuk `> 50jt`) **ditambah** `Kwitansi` dan `Invoice`, lalu melewati Memo Bayar berjenjang (`PPK` -> `Wadir 2`) -> Perintah Bayar `PPBJ` -> Pencairan `Keuangan`.
