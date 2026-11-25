{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : View Blade ini susun sebagai template cetak dokumen PDF laporan resmi SIM-PBJ
              yang dirender oleh DomPDF memakai `resources/css/pdf/laporan.css` untuk satu maupun
              banyak seksi modul aktif.
--}}
{{--
   Template Render PDF Laporan Resmi SIM-PBJ (`laporan.pdf`).

  Tujuan: merender kop institusi PNJ, kotak metadata pencetak beserta filter aktif, grid
          kartu KPI 3-kolom, serta tabel rincian data setiap seksi modul yang diizinkan oleh RBAC.
  Parameter (Data dari LaporanController::unduhPdf):
    - `$sections`, `$selectedModule`, `$user`, `$generatedAt`, `$roleLabel`, `$filterSummaryText`, `$activeModuleLabels`.
  Nilai kembali: Dokumen HTML cetak mandiri berorientasi lanskap untuk konversi DomPDF.
  Contoh pemanggilan: `Pdf::loadView('laporan.pdf', $data)->setPaper('a4', 'landscape');`
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan SIM-PBJ Politeknik Negeri Jakarta</title>
    <link rel="stylesheet" href="{{ resource_path('css/pdf/laporan.css') }}">
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
                    <p class="kop-sub">Sistem Informasi Pengadaan Barang dan Jasa</p>
                </td>
                <td class="kop-badge">
                    <span class="badge-scope">
                        {{ $selectedModule === 'semua' ? count($sections).' Modul Aktif' : ($sections[0]['tab_label'] ?? 'Laporan Pengadaan') }}
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
            <td class="meta-label">Peran</td>
            <td class="meta-val">{{ $roleLabel }}</td>
            <td class="meta-label">Filter Aktif</td>
            <td class="meta-val">{{ $filterSummaryText }}</td>
        </tr>
        <tr>
            <td class="meta-label">Hak Akses Modul</td>
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
                                    <td class="kpi-card kpi-spacer"></td>
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
                                $isRight = str_contains($col, 'Pagu') || str_contains($col, 'Nilai') || str_contains($col, 'HPS') || str_contains($col, 'Harga') || str_contains($col, 'Saldo') || str_contains($col, 'Nominal') || str_contains($col, '%');
                                $isCenter = $cIdx === 0 || str_contains($col, 'Status') || str_contains($col, 'Tahun') || str_contains($col, 'Jumlah');
                            @endphp
                            <th class="{{ $cIdx === 0 ? 'text-center col-no' : ($isRight ? 'text-right' : ($isCenter ? 'text-center' : '')) }}">
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
                            } elseif (preg_match('/aktif|disetujui|selesai|lunas|diterima|terima|lengkap|sesuai|spk terbit|kontrak terbit|terbit|cair/', $c5Lower)) {
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
            <p class="section-desc">Akun Anda belum memiliki hak akses modul laporan.</p>
        </div>
    @endforelse

    {{-- CATATAN KAKI & PENGESAHAN RINGKAS --}}
    <table class="sign-box">
        <tr>
            <td class="sign-note">
                <strong>Catatan:</strong> Dokumen ini dihasilkan oleh SIM-PBJ Politeknik Negeri Jakarta
                sesuai hak akses peran aktif dan filter pengguna.
                Untuk olah data lanjutan, gunakan file Data CSV yang diunduh bersamaan.
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
