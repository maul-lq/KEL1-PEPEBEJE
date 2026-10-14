/**
 * Script Pengujian API Backend SIM-PBJ (AGENTS-BACKEND.md)
 * Digunakan untuk mendukung interaksi asynchronous pada resources/views/tes_api.blade.php
 */

window.sendApiRequest = async function () {
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
};

window.quickGetAll = function () {
    document.getElementById('method').value = 'GET';
    document.getElementById('paramId').value = '';
    window.sendApiRequest();
};

window.clearOutput = function () {
    document.getElementById('targetUrl').textContent = '-';
    document.getElementById('httpStatus').textContent = '-';
    document.getElementById('outputResult').textContent = '[Hasil respons dibersihkan]';
};
