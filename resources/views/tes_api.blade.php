<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes API Backend - Sistem PBJ</title>
    @vite('resources/js/tes_api.js')
</head>
<body>
    <h1>Halaman Tes API Backend (17 Entitas)</h1>
    <p>
        <a href="{{ route('home') }}">&larr; Kembali ke Tes Beranda (Home)</a>
    </p>

    <hr>

    <h3>Form Pengujian Permintaan API:</h3>
    <form id="apiTestForm" onsubmit="event.preventDefault(); window.sendApiRequest();">
        <p>
            <label for="endpoint">Pilih Entitas Endpoint:</label><br>
            <select id="endpoint">
                <option value="/api/users">1. Users (/api/users)</option>
                <option value="/api/vendors">2. Vendors (/api/vendors)</option>
                <option value="/api/permohonan-pengadaan">3. Permohonan Pengadaan (/api/permohonan-pengadaan)</option>
                <option value="/api/anggaran-mak">4. Anggaran MAK (/api/anggaran-mak)</option>
                <option value="/api/paket-pengadaan">5. Paket Pengadaan (/api/paket-pengadaan)</option>
                <option value="/api/verifikasi-paralel">6. Verifikasi Paralel (/api/verifikasi-paralel)</option>
                <option value="/api/alokasi-mak">7. Alokasi MAK (/api/alokasi-mak)</option>
                <option value="/api/reviu-pengadaan">8. Reviu Pengadaan (/api/reviu-pengadaan)</option>
                <option value="/api/riwayat-penugasan-ppk">9. Riwayat Penugasan PPK (/api/riwayat-penugasan-ppk)</option>
                <option value="/api/kontrak-spk">10. Kontrak SPK (/api/kontrak-spk)</option>
                <option value="/api/penerimaan-barang">11. Penerimaan Barang (/api/penerimaan-barang)</option>
                <option value="/api/dokumentasi-penerimaan-barang">12. Dokumentasi Penerimaan Barang (/api/dokumentasi-penerimaan-barang)</option>
                <option value="/api/pembayaran">13. Pembayaran (/api/pembayaran)</option>
                <option value="/api/memo-bayar">14. Memo Bayar (/api/memo-bayar)</option>
                <option value="/api/transaksi-pencairan">15. Transaksi Pencairan (/api/transaksi-pencairan)</option>
                <option value="/api/reminders">16. Reminders (/api/reminders)</option>
                <option value="/api/pengadaan-logs">17. Pengadaan Logs (/api/pengadaan-logs)</option>
            </select>
        </p>

        <p>
            <label for="method">Metode HTTP:</label><br>
            <select id="method">
                <option value="GET">GET (Ambil Data)</option>
                <option value="POST">POST (Tambah Data)</option>
                <option value="PUT">PUT (Ubah Data)</option>
                <option value="DELETE">DELETE (Hapus Data)</option>
            </select>
        </p>

        <p>
            <label for="paramId">ID / Parameter Tambahan (Opsional):</label><br>
            <input type="text" id="paramId" placeholder="Contoh: 1 atau nomor_surat (untuk show/update/delete)" size="50">
        </p>

        <p>
            <label for="jsonBody">JSON Request Body (Untuk POST / PUT):</label><br>
            <textarea id="jsonBody" rows="8" cols="70" placeholder='{\n  "kunci": "nilai"\n}'></textarea>
        </p>

        <p>
            <button type="submit">Kirim Permintaan (Send Request)</button>
            <button type="button" onclick="window.quickGetAll();">Quick GET (Ambil Semua Data)</button>
            <button type="button" onclick="window.clearOutput();">Bersihkan Hasil</button>
        </p>
    </form>

    <hr>

    <h3>Hasil Respons Backend:</h3>
    <p>
        <strong>URL Tujuan:</strong> <span id="targetUrl">-</span><br>
        <strong>Status HTTP:</strong> <span id="httpStatus">-</span>
    </p>

    <pre id="outputResult">[Hasil respons JSON dari server akan ditampilkan di sini...]</pre>
</body>
</html>
