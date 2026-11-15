<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan SIM-PBJ Politeknik Negeri Jakarta</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 12mm 16mm 12mm;
            @bottom-left {
                content: "SIM-PBJ Politeknik Negeri Jakarta • Dokumen Laporan RBAC Terverifikasi";
                font-family: "DejaVu Sans", sans-serif;
                font-size: 7.5pt;
                color: #64748b;
            }
            @bottom-right {
                content: "Halaman " counter(page) " dari " counter(pages);
                font-family: "DejaVu Sans", sans-serif;
                font-size: 7.5pt;
                font-weight: bold;
                color: #334155;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .kop-banner {
            width: 100%;
            border-bottom: 3px solid #0969da;
            background-color: #213153;
            color: #ffffff;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kop-instansi {
            font-size: 7.5pt;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #cbd5e1;
            margin: 0 0 2px 0;
        }

        .kop-title {
            font-size: 13pt;
            font-weight: bold;
            color: #ffffff;
            margin: 0 0 3px 0;
        }

        .kop-sub {
            font-size: 8.5pt;
            color: #e2e8f0;
            margin: 0;
        }

        .kop-badge {
            text-align: right;
            vertical-align: top;
        }

        .badge-scope {
            display: inline-block;
            background-color: #0969da;
            color: #ffffff;
            padding: 4px 10px;
            font-size: 7.5pt;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .meta-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            margin-bottom: 14px;
        }

        .meta-box td {
            padding: 5px 10px;
            font-size: 8pt;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .meta-box tr:last-child td {
            border-bottom: none;
        }

        .meta-label {
            width: 15%;
            font-weight: bold;
            color: #475569;
        }

        .meta-val {
            width: 35%;
            color: #0f172a;
        }

        .section-block {
            margin-bottom: 18px;
        }

        .section-head-wrap {
            page-break-inside: avoid;
        }

        .section-header {
            background-color: #eff6ff;
            border-left: 4px solid #0969da;
            border-top: 1px solid #bfdbfe;
            border-right: 1px solid #bfdbfe;
            border-bottom: 1px solid #bfdbfe;
            padding: 7px 12px;
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #213153;
            margin: 0;
        }

        .section-desc {
            font-size: 7.5pt;
            color: #475569;
            margin: 2px 0 0 0;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 0 -6px 8px -6px;
        }

        .kpi-card {
            width: 33.33%;
            border: 1px solid #cbd5e1;
            border-top: 2.5px solid #0969da;
            background-color: #ffffff;
            padding: 7px 10px;
            vertical-align: top;
        }

        .kpi-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 3px 0;
        }

        .kpi-val {
            font-size: 11pt;
            font-weight: bold;
            color: #213153;
            margin: 0 0 2px 0;
        }

        .kpi-sub {
            font-size: 7pt;
            color: #64748b;
            margin: 0;
        }

        .table-caption {
            font-size: 8.5pt;
            font-weight: bold;
            color: #213153;
            margin: 6px 0 4px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-bottom: 6px;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            background-color: #213153;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            border: 1px solid #1e293b;
            text-align: left;
        }

        .data-table td {
            padding: 5.5px 8px;
            font-size: 8pt;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table tbody tr {
            page-break-inside: avoid;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .font-mono {
            font-family: "DejaVu Sans Mono", monospace;
        }

        .font-bold {
            font-weight: bold;
        }

        .pill {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 3px;
        }

        .pill-emerald {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .pill-rose {
            background-color: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        .pill-amber {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .pill-blue {
            background-color: #eff6ff;
            color: #0969da;
            border: 1px solid #bfdbfe;
        }

        .sign-box {
            margin-top: 12px;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .sign-note {
            width: 65%;
            font-size: 7.5pt;
            color: #64748b;
            vertical-align: bottom;
            padding-right: 16px;
        }

        .sign-stamp {
            width: 35%;
            border: 1px dashed #94a3b8;
            background-color: #f8fafc;
            padding: 8px 12px;
            text-align: right;
            font-size: 8pt;
        }

        .empty-row {
            text-align: center;
            color: #64748b;
            padding: 14px !important;
            font-style: italic;
        }
    </style>
</head>
<body>

    {{-- KOP LAPORAN INSTITUSI --}}
    <div class="kop-banner">
        <table class="kop-table">
            <tr>
                <td>
                    <p class="kop-instansi">Kementerian Pendidikan Tinggi, Sains, dan Teknologi • Politeknik Negeri Jakarta</p>
                    <h1 class="kop-title">
                        @if(count($sections) === 1)
                            {{ $sections[0]['ringkasan_title'] }}
                        @else
                            LAPORAN TERPADU PENGADAAN BARANG &amp; JASA (SIM-PBJ)
                        @endif
                    </h1>
                    <p class="kop-sub">Sistem Informasi Pengadaan Barang dan Jasa • Ringkasan Kinerja &amp; Rincian Data Operasional</p>
                </td>
                <td class="kop-badge">
                    <span class="badge-scope">
                        {{ $selectedModule === 'semua' ? count($sections).' Modul RBAC Aktif' : ($sections[0]['tab_label'] ?? 'Laporan PBJ') }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    {{-- METADATA RBAC & PARAMETER FILTER --}}
    <table class="meta-box">
        <tr>
            <td class="meta-label">Dicetak Oleh</td>
            <td class="meta-val">
                <strong>{{ $user->nama }}</strong>
                @if(! empty($user->nip))
                    (NIP: {{ $user->nip }})
                @endif
            </td>
            <td class="meta-label">Waktu Cetak</td>
            <td class="meta-val">{{ $generatedAt }}</td>
        </tr>
        <tr>
            <td class="meta-label">Peran (Role)</td>
            <td class="meta-val">{{ $roleLabel }}</td>
            <td class="meta-label">Filter Aktif</td>
            <td class="meta-val">{{ $filterSummaryText }}</td>
        </tr>
        <tr>
            <td class="meta-label">Modul RBAC</td>
            <td class="meta-val" colspan="3">
                {{ ! empty($activeModuleLabels) ? implode('  •  ', $activeModuleLabels) : 'Tidak ada modul laporan aktif' }}
            </td>
        </tr>
    </table>

    {{-- SEKSI LAPORAN MODULAR --}}
    @forelse($sections as $sIdx => $section)
        <div class="section-block">
            <div class="section-head-wrap">
                <div class="section-header">
                    <p class="section-title">
                        @if(count($sections) > 1)
                            {{ $sIdx + 1 }}. {{ $section['tab_label'] }} — {{ $section['ringkasan_title'] }}
                        @else
                            {{ $section['ringkasan_title'] }}
                        @endif
                    </p>
                    @if(! empty($section['description']))
                        <p class="section-desc">{{ $section['description'] }}</p>
                    @endif
                </div>

                @if(! empty($section['cards']))
                    <table class="kpi-grid">
                        @foreach(array_chunk($section['cards'], 3) as $cardRow)
                            <tr>
                                @foreach($cardRow as $card)
                                    <td class="kpi-card">
                                        <p class="kpi-label">{{ $card['label'] }}</p>
                                        <p class="kpi-val">{{ $card['val'] }}</p>
                                        <p class="kpi-sub">{{ $card['sub'] }}</p>
                                    </td>
                                @endforeach
                                @for($pad = count($cardRow); $pad < 3; $pad++)
                                    <td class="kpi-card" style="border: none; background: transparent;"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>

            <p class="table-caption">{{ $section['table_title'] }} ({{ count($section['rows']) }} baris)</p>

            <table class="data-table">
                <thead>
                    <tr>
                        @foreach($section['columns'] as $cIdx => $col)
                            @php
                                $isRight = str_contains($col, 'Pagu') || str_contains($col, 'Nilai') || str_contains($col, 'HPS') || str_contains($col, 'Saldo') || str_contains($col, 'Nominal') || str_contains($col, '%');
                                $isCenter = $cIdx === 0 || str_contains($col, 'Status') || str_contains($col, 'Tahun') || str_contains($col, 'Jumlah');
                            @endphp
                            <th class="{{ $cIdx === 0 ? 'text-center' : ($isRight ? 'text-right' : ($isCenter ? 'text-center' : '')) }}" style="{{ $cIdx === 0 ? 'width: 34px;' : '' }}">
                                {{ $col }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        @php
                            $c3Str = (string) ($row['col3'] ?? '');
                            $c4Str = (string) ($row['col4'] ?? '');
                            $c5Str = (string) ($row['col5'] ?? '');
                            $c5Lower = mb_strtolower($c5Str);
                            $isC5MoneyOrPct = str_starts_with($c5Str, 'Rp ') || str_ends_with($c5Str, '%');

                            $pillClass = 'pill-blue';
                            if (preg_match('/ditolak|batal|nonaktif|tidak sesuai|terkunci|gagal/', $c5Lower)) {
                                $pillClass = 'pill-rose';
                            } elseif (preg_match('/revisi|perlu perbaikan|kurang lengkap|menunggu|pemeriksaan|pending/', $c5Lower)) {
                                $pillClass = 'pill-amber';
                            } elseif (preg_match('/aktif|disetujui|selesai|lunas|diterima|terima|lengkap|sesuai|spk terbit|terbit|cair/', $c5Lower)) {
                                $pillClass = 'pill-emerald';
                            }
                        @endphp
                        <tr>
                            <td class="text-center font-bold">{{ $row['no'] }}</td>
                            <td class="font-mono font-bold">{{ $row['col1'] }}</td>
                            <td>{{ $row['col2'] }}</td>
                            <td class="{{ str_starts_with($c3Str, 'Rp ') || str_ends_with($c3Str, '%') ? 'text-right' : ((str_contains($section['columns'][3] ?? '', 'Tahun') || str_contains($section['columns'][3] ?? '', 'Jumlah')) ? 'text-center' : '') }}">
                                {{ $c3Str }}
                            </td>
                            <td class="{{ str_starts_with($c4Str, 'Rp ') || str_ends_with($c4Str, '%') ? 'text-right font-bold font-mono' : '' }}">
                                {{ $c4Str }}
                            </td>
                            <td class="{{ $isC5MoneyOrPct ? 'text-right font-bold font-mono' : 'text-center' }}">
                                @if($isC5MoneyOrPct)
                                    {{ $c5Str }}
                                @else
                                    <span class="pill {{ $pillClass }}">{{ $c5Str }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['columns']) }}" class="empty-row">
                                Tidak ada rekaman data pada seksi modul ini untuk filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="section-header">
            <p class="section-title">Tidak Ada Modul Laporan Aktif</p>
            <p class="section-desc">Akun peran Anda belum memiliki izin RBAC untuk menampilkan seksi laporan mana pun.</p>
        </div>
    @endforelse

    {{-- CATATAN KAKI & PENGESAHAN RINGKAS --}}
    <table class="sign-box">
        <tr>
            <td class="sign-note">
                <strong>Catatan Integritas Data:</strong> Dokumen ini dihasilkan langsung oleh SIM-PBJ Politeknik Negeri Jakarta
                mengikuti matriks hak akses RBAC peran aktif serta filter periode/unit yang ditetapkan pengguna.
                Untuk pengolahan lanjutan (pivot/penjumlahan angka), gunakan file CSV RAW yang diunduh bersamaan.
            </td>
            <td class="sign-stamp">
                <strong>Depok, {{ now()->translatedFormat('d F Y') }}</strong><br>
                Pengunduh: {{ $user->nama }}<br>
                <em>{{ $roleLabel }}</em>
            </td>
        </tr>
    </table>

</body>
</html>
