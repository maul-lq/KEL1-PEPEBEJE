---
paths:
  - '1Jorking/**'
  - '1DokumenPendukungMahPBL/**'
  - 'sql/**'
---

# Aturan Revisi Dokumen & Diagram Sumber Truth

1. **Dilarang Mengedit Langsung Artefak Desain/Dokumen Tim Tanpa Instruksi Eksplisit**:
   - Jangan memodifikasi langsung berkas `.xlsx`, `.docx`, `.drawio`, `.png`, atau `Notulensi*.txt` di `1Jorking/` maupun `1DokumenPendukungMahPBL/` kecuali pengguna meminta secara eksplisit file tersebut diedit oleh agen.
2. **Dokumentasikan Seluruh Perubahan ke `1Jorking/REVISI dokumen dan diagramnya.md`**:
   - Setiap kali ada pembaruan alur/notulensi yang mengubah Sumber Truth, tuliskan/perbarui daftar perubahannya di `1Jorking/REVISI dokumen dan diagramnya.md` dengan format siap pakai:
     - Nama File -> Bagian/Sheet/Sel/Tab/Swimlane -> Teks/Elemen Saat Ini -> Teks/Elemen Pengganti.
3. **Pengecualian Dokumen Kontrak Teknis Kode**:
   - `1Jorking/PANDUAN_INTEGRASI_FRONTEND_FR_NFR.md` dan `AGENTS-BACKEND.md` WAJIB langsung diperbarui oleh agen setiap kali endpoint/aturan backend berubah.
