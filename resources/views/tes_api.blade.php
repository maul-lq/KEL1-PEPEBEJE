<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes API Backend - Sistem PBJ</title>
</head>
<body>
    <h1>Halaman Tes API Backend (17 Entitas)</h1>
    <p>
        <a href="{{ route('home') }}">&larr; Kembali ke Tes Beranda (Home)</a>
    </p>

    <hr>

    <h3>Form Pengujian Permintaan API:</h3>
    <form id="apiTestForm" onsubmit="event.preventDefault(); sendApiRequest();">
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
            <input type="text" id="paramId" placeholder="Contoh: 1 atau nomor_surat (untuk show/update/delete)" style="width: 400px;">
        </p>

        <p>
            <label for="jsonBody">JSON Request Body (Untuk POST / PUT):</label><br>
            <textarea id="jsonBody" rows="8" cols="70" placeholder='{\n  "kunci": "nilai"\n}'></textarea>
        </p>

        <p>
            <button type="submit">Kirim Permintaan (Send Request)</button>
            <button type="button" onclick="quickGetAll();">Quick GET (Ambil Semua Data)</button>
            <button type="button" onclick="clearOutput();">Bersihkan Hasil</button>
        </p>
    </form>

    <hr>

    <h3>Hasil Respons Backend:</h3>
    <p>
        <strong>URL Tujuan:</strong> <span id="targetUrl">-</span><br>
        <strong>Status HTTP:</strong> <span id="httpStatus">-</span>
    </p>

    <pre id="outputResult" style="border: 1px solid black; padding: 10px; background-color: #f4f4f4; white-space: pre-wrap; word-break: break-all;">[Hasil respons JSON dari server akan ditampilkan di sini...]</pre>

    <script>
        async function sendApiRequest() {
            const endpoint = document.getElementById('endpoint').value;
            const method = document.getElementById('method').value;
            const paramId = document.getElementById('paramId').value.trim();
            const jsonBody = document.getElementById('jsonBody').value.trim();

            let url = endpoint;
            if (paramId) {
                url += '/' + paramId;
            }

            document.getElementById('targetUrl').textContent = `${method} ${url}`;
            document.getElementById('httpStatus').textContent = 'Memuat (Loading)...';
            document.getElementById('outputResult').textContent = 'Sedang mengirim permintaan ke server...';

            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            const options = {
                method: method,
                headers: headers
            };

            if (method === 'POST' || method === 'PUT') {
                headers['Content-Type'] = 'application/json';
                if (jsonBody) {
                    try {
                        JSON.parse(jsonBody); // validasi sintaks JSON
                        options.body = jsonBody;
                    } catch (e) {
                        alert('Format JSON pada Request Body tidak valid: ' + e.message);
                        document.getElementById('httpStatus').textContent = 'Error JSON Client';
                        return;
                    }
                }
            }

            try {
                const response = await fetch(url, options);
                document.getElementById('httpStatus').textContent = `${response.status} ${response.statusText}`;

                const data = await response.json().catch(() => null);
                if (data !== null) {
                    document.getElementById('outputResult').textContent = JSON.stringify(data, null, 2);
                } else {
                    const text = await response.text();
                    document.getElementById('outputResult').textContent = text;
                }
            } catch (err) {
                document.getElementById('httpStatus').textContent = 'Error Jaringan / Server';
                document.getElementById('outputResult').textContent = err.toString();
            }
        }

        function quickGetAll() {
            document.getElementById('method').value = 'GET';
            document.getElementById('paramId').value = '';
            sendApiRequest();
        }

        function clearOutput() {
            document.getElementById('targetUrl').textContent = '-';
            document.getElementById('httpStatus').textContent = '-';
            document.getElementById('outputResult').textContent = '[Hasil respons dibersihkan]';
        }
    </script>
</body>
</html>
