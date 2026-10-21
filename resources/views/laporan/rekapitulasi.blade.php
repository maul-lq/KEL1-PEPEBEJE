@extends('layouts.app')

@section('title', 'Laporan Rekapitulasi Anggaran - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<article>
    <header>
        <h2>Laporan Rekapitulasi Total Anggaran Pengadaan</h2>
        <p>Ringkasan alokasi pagu DIPA, komitmen nilai kontrak pengadaan, dan realisasi pencairan dana SP2D Politeknik Negeri Jakarta.</p>
    </header>

    {{-- Formulir Filter Rekapitulasi Anggaran --}}
    <section>
        <form method="GET" action="{{ route('laporan.rekapitulasi') }}">
            <fieldset>
                <legend><strong>Parameter Filter Rekapitulasi</strong></legend>
                <p>
                    <label for="tahun"><strong>Tahun Anggaran:</strong></label><br>
                    <input type="number" id="tahun" name="tahun" value="{{ $tahun }}" min="2020" max="2035" required>
                </p>
                <p>
                    <label for="bulan"><strong>Bulan Pengadaan:</strong></label><br>
                    <select id="bulan" name="bulan">
                        <option value="">-- Seluruh Bulan (Tahunan) --</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (string)$bulan === (string)$m ? 'selected' : '' }}>
                                Bulan {{ date('F', mktime(0, 0, 0, $m, 10)) }} ({{ $m }})
                            </option>
                        @endfor
                    </select>
                </p>
                <p>
                    <label for="unit"><strong>Jurusan / Unit Kerja:</strong></label><br>
                    <select id="unit" name="unit">
                        <option value="">-- Seluruh Jurusan & Unit Kerja --</option>
                        @foreach($units as $u)
                            <option value="{{ $u->kode_unit }}" {{ $kodeUnit === $u->kode_unit ? 'selected' : '' }}>
                                {{ $u->nama_unit }} ({{ $u->kode_unit }})
                            </option>
                        @endforeach
                    </select>
                </p>
                <p>
                    <button type="submit">Tampilkan Laporan</button>
                    <a href="{{ route('laporan.rekapitulasi') }}">Reset Filter</a>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    {{-- Ringkasan Anggaran Agregat --}}
    <section>
        <h3>Ringkasan Keuangan Periode {{ $tahun }} @if($bulan)(Bulan {{ $bulan }})@endif</h3>
        <table>
            <thead>
                <tr>
                    <th>Indikator Keuangan</th>
                    <th>Nominal (Rupiah)</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Total Pagu Anggaran DIPA</strong></td>
                    <td>Rp {{ number_format($totalPaguDipa, 0, ',', '.') }}</td>
                    <td>Alokasi master pos anggaran DIPA kampus</td>
                </tr>
                <tr>
                    <td><strong>Total Estimasi Usulan Permohonan</strong></td>
                    <td>Rp {{ number_format($totalUsulan, 0, ',', '.') }}</td>
                    <td>Akumulasi usulan belanja unit kerja</td>
                </tr>
                <tr>
                    <td><strong>Total Nilai Kontrak / SPK</strong></td>
                    <td>Rp {{ number_format($totalKontrak, 0, ',', '.') }}</td>
                    <td>Komitmen perikatan dengan pihak rekanan</td>
                </tr>
                <tr>
                    <td><strong>Total Realisasi Pencairan SP2D</strong></td>
                    <td><strong>Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</strong></td>
                    <td>Dana yang telah ditransfer ke kas penyedia</td>
                </tr>
                <tr>
                    <td><strong>Sisa Anggaran Belum Terserap</strong></td>
                    <td>Rp {{ number_format($sisaAnggaran, 0, ',', '.') }}</td>
                    <td>Sisa pagu DIPA yang belum terealisasi</td>
                </tr>
                <tr>
                    <td><strong>Persentase Serapan Anggaran</strong></td>
                    <td><strong>{{ $persentaseSerapan }}%</strong></td>
                    <td>Rasio realisasi SP2D terhadap total pagu DIPA</td>
                </tr>
            </tbody>
        </table>

        <p>
            <a href="{{ route('dokumen.pdf', ['jenis' => 'rekapitulasi', 'id' => $tahun]) }}?bulan={{ $bulan }}&unit={{ $kodeUnit }}">
                <button type="button">Unduh Salinan Resmi Laporan Rekapitulasi (PDF)</button>
            </a>
        </p>
    </section>

    <hr>

    {{-- Rincian Serapan per Jurusan / Unit Kerja --}}
    <section>
        <h3>Distribusi Pagu Anggaran per Jurusan dan Unit Kerja</h3>
        <table>
            <thead>
                <tr>
                    <th>Kode Unit</th>
                    <th>Nama Jurusan / Unit Kerja</th>
                    <th>Jumlah Usulan</th>
                    <th>Total Nilai Usulan</th>
                    <th>Pangsa Usulan (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($unitBreakdown as $ub)
                    <tr>
                        <td>{{ $ub['kode_unit'] }}</td>
                        <td>{{ $ub['nama_unit'] }}</td>
                        <td>{{ $ub['jumlah_usulan'] }} berkas</td>
                        <td>Rp {{ number_format($ub['total_pagu'], 0, ',', '.') }}</td>
                        <td>{{ $ub['persentase'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><em>Tidak ada data permohonan pada periode ini.</em></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</article>
@endsection
