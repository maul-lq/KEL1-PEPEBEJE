<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Berkas Pengadaan - {{ $pengadaan->nomor_pengadaan }}</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.4; color: #000; padding: 20px; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 13pt; text-transform: uppercase; }
        .header h1 { margin: 4px 0; font-size: 15pt; text-transform: uppercase; }
        .header p { margin: 0; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10.5pt; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 5px 8px; }
        th { background-color: #f2f2f2; }
        .no-border, .no-border td { border: none; }
        .signatures { margin-top: 35px; }
        .signatures td { border: none; text-align: center; vertical-align: top; width: 33%; padding-bottom: 50px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; background: #eee; padding: 8px 12px; border-radius: 4px;">
        <button onclick="window.print()" style="padding: 6px 14px; font-weight: bold; background: #18181b; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
            Cetak / Simpan PDF
        </button>
        <button onclick="window.close()" style="padding: 6px 14px; margin-left: 8px; background: #ddd; border: none; border-radius: 4px; cursor: pointer;">
            Tutup
        </button>
    </div>

    <div class="header">
        <h2>KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI</h2>
        <h1>UNIT LAYANAN PENGADAAN BARANG DAN JASA (PPBJ) KAMPUS</h1>
        <p>Gedung Pusat Administrasi Lantai 2 • Kampus Politeknik</p>
        <p>Telepon: (021) 1234567 • Email: pbj@kampus.ac.id</p>
    </div>

    <div style="text-align: center; margin-bottom: 15px;">
        <h3 style="margin: 0; text-decoration: underline; font-size: 12pt;">LEMBAR KONTROL DAN DISPOSISI PENGADAAN</h3>
        <p style="margin: 3px 0 0 0; font-family: monospace; font-weight: bold;">No. Register: {{ $pengadaan->nomor_pengadaan }}</p>
    </div>

    <table class="no-border">
        <tr><td style="width: 25%;">Nama Kegiatan Pengadaan</td><td style="width: 2%;">:</td><td style="font-weight: bold;">{{ $pengadaan->nama_pengadaan }}</td></tr>
        <tr><td>Unit Pemohon</td><td>:</td><td>{{ $pengadaan->user->name }} ({{ $pengadaan->user->unit_kerja }})</td></tr>
        <tr><td>Surat & Ref Srikandi</td><td>:</td><td>{{ $pengadaan->nomor_surat_user ?? '-' }} / {{ $pengadaan->no_draft_srikandi ?? '-' }}</td></tr>
        <tr><td>Mata Anggaran (MAK)</td><td>:</td><td><strong>{{ $pengadaan->nomor_mak ?? 'Belum ditentukan' }}</strong> (Pagu: {{ $pengadaan->formatted_pagu_anggaran }} • {{ $pengadaan->sumber_dana ?? 'RM' }})</td></tr>
        <tr><td>Jalur & Metode Pengadaan</td><td>:</td><td>{{ $pengadaan->jalur_label }} (Metode: {{ $pengadaan->metode_pengadaan ?? '-' }})</td></tr>
        <tr><td>Penyedia / SPK</td><td>:</td><td>{{ $pengadaan->vendor->nama_perusahaan ?? '-' }} • SPK: {{ $pengadaan->nomor_spk ?? '-' }} (Kontrak: {{ $pengadaan->formatted_nilai_kontrak }})</td></tr>
        <tr><td>Status Terkini Berkas</td><td>:</td><td><strong>{{ $pengadaan->status_label }}</strong></td></tr>
    </table>

    <h4 style="margin: 12px 0 4px 0; font-size: 10.5pt; text-transform: uppercase;">Rincian Barang / Jasa:</h4>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Nama Barang / Uraian</th>
                <th>Spesifikasi</th>
                <th style="width: 8%;">Vol</th>
                <th style="width: 10%;">Satuan</th>
                <th style="width: 18%;">Harga Satuan</th>
                <th style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pengadaan->items as $i => $item)
                <tr>
                    <td style="text-align: center;">{{ $i + 1 }}</td>
                    <td>{{ $item->nama_barang }}</td>
                    <td>{{ $item->spesifikasi ?? '-' }}</td>
                    <td style="text-align: center;">{{ $item->volume }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td style="text-align: right;">{{ $item->formatted_harga_satuan }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ $item->formatted_total_harga }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" style="text-align: right;">TOTAL ESTIMASI:</th>
                <th style="text-align: right;">{{ $pengadaan->formatted_estimasi_anggaran }}</th>
            </tr>
        </tfoot>
    </table>

    <table class="signatures">
        <tr>
            <td>Menyetujui,<br><strong>Wakil Direktur 2</strong><br>Bidang Keuangan & Umum<br><br><br><br>( {{ $pengadaan->wadir2->name ?? 'Prof. Dr. Ahmad Dahlan, M.Sc.' }} )<br>NIP. {{ $pengadaan->wadir2->nip ?? '196803121994031002' }}</td>
            <td>Mengetahui,<br><strong>Pejabat Pembuat Komitmen</strong><br>(PPK)<br><br><br><br>( {{ $pengadaan->ppk->name ?? 'Ir. Hendra Gunawan, M.T.' }} )<br>NIP. {{ $pengadaan->ppk->nip ?? '197009181997021002' }}</td>
            <td>Pengelola Pengadaan,<br><strong>Koordinator PPBJ</strong><br>Unit Layanan Pengadaan<br><br><br><br>( {{ $pengadaan->ppbj->name ?? 'Rahmat Hidayat, S.T., M.Kom.' }} )<br>NIP. {{ $pengadaan->ppbj->nip ?? '198105202008121003' }}</td>
        </tr>
    </table>
</body>
</html>
