<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Simulasi Monitoring Real-Time Status Berkas (NFR 7)</title>
</head>
<body>

    <header>
        <h1>Simulasi Monitoring Real-Time Status Berkas (NFR 7)</h1>
        <p>SIM-PBJ Politeknik Negeri Jakarta &bull; Pelacakan 8 Tahapan Berkas & SLA Respon &lt; 5 Detik</p>
        <p>
            Login: <strong>{{ $user ? $user->nama : 'Guest' }}</strong> ({{ $user ? strtoupper($user->role) : '-' }}) |
            <a href="{{ route('home') }}">&larr; Beranda</a> |
            <a href="{{ route('permohonan.web') }}">Simulasi Permohonan (FR 3) &rarr;</a>
        </p>
    </header>

    <hr>

    <!-- Form Pilih Berkas / Nomor Surat -->
    <section>
        <h2>Pilih Berkas Permohonan untuk Dilacak</h2>
        <form action="{{ route('monitoring.web') }}" method="GET">
            <fieldset>
                <legend>Pencarian Dokumen Pengadaan</legend>
                <p>
                    <label for="nomor_surat">Pilih atau Masukkan Nomor Surat Permohonan (*):</label><br>
                    <input type="text" id="nomor_surat" name="nomor_surat" list="permohonan-list" value="{{ $selectedNomor }}" placeholder="Ketik nomor surat (contoh: 001/PL3.TIK/PG/2026)" required size="50">
                    <datalist id="permohonan-list">
                        @foreach ($allPermohonan as $p)
                            <option value="{{ $p->nomor_surat }}">{{ $p->nomor_surat }} - {{ $p->judul_pengadaan }}</option>
                        @endforeach
                    </datalist>
                    <button type="submit">Lacak Posisi Berkas</button>
                    @if ($selectedNomor)
                        <a href="{{ route('monitoring.web') }}">[Reset Pilihan]</a>
                    @endif
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    @if ($trackingData)
        @php
            $tracking = $trackingData['data'] ?? [];
            $responseTime = $trackingData['response_time_ms'] ?? 0;
            $timeline = $tracking['timeline'] ?? [];
        @endphp

        <!-- Hasil Pelacakan & SLA Performance NFR 7 -->
        <section>
            <h2>Hasil Pelacakan Berkas: {{ $tracking['nomor_surat'] }}</h2>
            <p>
                <strong>Judul Pengadaan:</strong> {{ $tracking['judul_pengadaan'] }}<br>
                <strong>Status Keseluruhan:</strong> [{{ strtoupper($tracking['overall_status']) }}]<br>
                <strong>Kemajuan:</strong> Tahap {{ $tracking['current_stage'] }} dari {{ $tracking['total_stages'] }} Selesai<br>
                <strong>Waktu Respon Server API:</strong> {{ $responseTime }} ms (SLA &lt; 5000 ms: {{ $responseTime < 5000 ? 'MEMENUHI NFR 7' : 'LAMBAT' }})
            </p>

            <h3>Rincian 8 Tahapan Siklus Pengadaan:</h3>
            <ol>
                @foreach ($timeline as $stage)
                    <li>
                        <h4>
                            Tahap {{ $stage['stage_number'] }}: {{ $stage['stage_name'] }}
                            [{{ strtoupper($stage['status']) }}]
                        </h4>
                        <p>
                            <strong>Penanggung Jawab:</strong> {{ $stage['role_in_charge'] }}<br>
                            <strong>Aktor Pelaksana:</strong> {{ $stage['actor'] ?? '-' }}<br>
                            <strong>Waktu Update:</strong> {{ $stage['timestamp'] ? \Carbon\Carbon::parse($stage['timestamp'])->format('d M Y H:i:s') : 'Belum selesai' }}
                        </p>
                        @if (!empty($stage['details']))
                            <details open>
                                <summary>Metadata & Parameter Teknis Tahap {{ $stage['stage_number'] }}</summary>
                                <pre>{{ json_encode($stage['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                        <hr>
                    </li>
                @endforeach
            </ol>
        </section>

        <hr>
    @elseif ($selectedNomor)
        <section>
            <p>
                <strong>PEMBERITAHUAN:</strong> Nomor surat <em>{{ $selectedNomor }}</em> tidak ditemukan dalam basis data permohonan pengadaan.
            </p>
        </section>
        <hr>
    @endif

    <!-- Daftar Berkas Terbaru -->
    <section>
        <h2>Daftar 20 Berkas Permohonan Terkini</h2>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Nomor Surat</th>
                    <th>Judul Pengadaan</th>
                    <th>Unit</th>
                    <th>Pagu Anggaran</th>
                    <th>Status Permohonan</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allPermohonan as $item)
                    <tr>
                        <td><strong>{{ $item->nomor_surat }}</strong></td>
                        <td>{{ $item->judul_pengadaan }}</td>
                        <td>{{ $item->unit ? $item->unit->nama_unit : $item->kode_unit }}</td>
                        <td>Rp {{ number_format($item->estimasi_total_pagu, 0, ',', '.') }}</td>
                        <td>[{{ strtoupper($item->status_permohonan) }}]</td>
                        <td>{{ $item->tanggal_pengajuan ? $item->tanggal_pengajuan->format('d/m/Y H:i') : '-' }}</td>
                        <td>
                            <a href="{{ route('monitoring.web', ['nomor_surat' => $item->nomor_surat]) }}">
                                Lacak &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" align="center">Belum ada data permohonan pengadaan di sistem.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

</body>
</html>
