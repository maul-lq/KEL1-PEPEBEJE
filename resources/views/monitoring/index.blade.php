@extends('layouts.app')

@section('title', 'Monitoring 8 Siklus Pengadaan & Audit Trail - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Pemantauan (Monitoring) 8 Siklus Pengadaan & Jejak Audit Institusi</h2>
    <p>
        Menampilkan pelacakan alur pengadaan dari usulan awal hingga pencairan dana, serta rekam jejak audit (*Audit Trail*)
        secara transparan untuk pimpinan, PPBJ, Perencanaan, Keuangan, dan Pengawas Internal.
    </p>

    {{-- Filter Pencarian Dokumen & Paket --}}
    <fieldset>
        <legend>Filter Pencarian Siklus Pengadaan</legend>
        <form action="{{ route('monitoring.index') }}" method="GET">
            <p>
                <label for="q"><strong>Kata Kunci (Nomor Surat / Nama Paket):</strong></label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Cari nomor surat atau perihal...">
                &nbsp;
                <label for="unit"><strong>Unit Kerja:</strong></label>
                <select id="unit" name="unit">
                    <option value="">-- Seluruh Unit --</option>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('unit') === $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit }}
                        </option>
                    @endforeach
                </select>
                &nbsp;
                <label for="status"><strong>Status:</strong></label>
                <select id="status" name="status">
                    <option value="">-- Seluruh Status --</option>
                    <option value="diajukan" {{ request('status') === 'diajukan' ? 'selected' : '' }}>Diajukan</option>
                    <option value="reviu_paralel" {{ request('status') === 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel</option>
                    <option value="disetujui" {{ request('status') === 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
                &nbsp;
                <button type="submit">Saring Data (Filter)</button>
                <a href="{{ route('monitoring.index') }}"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br>

    {{-- 1. Tabel Monitoring 8 Siklus Pengadaan --}}
    <fieldset>
        <legend><strong>Pelacakan 8 Tahapan Siklus Pengadaan</strong></legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nomor Surat Srikandi</th>
                    <th>Unit Pengusul</th>
                    <th>Perihal Pengadaan</th>
                    <th>Estimasi Nilai</th>
                    <th>1. Pengajuan</th>
                    <th>2. Verifikasi Wadir 2 & MAK</th>
                    <th>3. Registrasi Paket (PPBJ)</th>
                    <th>4. Kontrak SPK (PP/PPK)</th>
                    <th>5. BAST (Perlengkapan)</th>
                    <th>6. Pencairan (Keuangan)</th>
                    <th>Status Siklus</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permohonanList as $idx => $p)
                    <tr>
                        <td align="center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $p->nomor_surat }}</strong></td>
                        <td>{{ $p->unit->nama_unit ?? $p->kode_unit }}</td>
                        <td>{{ $p->perihal }}</td>
                        <td>Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</td>
                        <td align="center">&#10003; Selesai</td>
                        <td align="center">
                            @if(in_array($p->status, ['disetujui', 'selesai']))
                                &#10003; ACC
                            @elseif($p->status === 'ditolak')
                                &#10007; Ditolak
                            @else
                                &bull; Menunggu
                            @endif
                        </td>
                        <td align="center">
                            @if($p->paket)
                                &#10003; {{ $p->paket->kode_paket }}
                            @else
                                -
                            @endif
                        </td>
                        <td align="center">
                            @if($p->paket && $p->paket->kontrak)
                                &#10003; SPK Terbit
                            @else
                                -
                            @endif
                        </td>
                        <td align="center">
                            @if($p->paket && $p->paket->kontrak && $p->paket->kontrak->penerimaan)
                                &#10003; BAST Terbit
                            @else
                                -
                            @endif
                        </td>
                        <td align="center">
                            @if($p->status === 'selesai')
                                &#10003; Lunas
                            @else
                                -
                            @endif
                        </td>
                        <td><strong>{{ strtoupper(str_replace('_', ' ', $p->status)) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" align="center"><em>Tidak ada data pengadaan yang sesuai dengan kriteria filter.</em></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </fieldset>

    <br>

    {{-- 2. Tabel Jejak Audit (Audit Trail) Sistem --}}
    <fieldset>
        <legend><strong>Jejak Audit Aktivitas Pengguna (Audit Trail Transparansi)</strong></legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Waktu Aktivitas</th>
                    <th>Nama Pengguna</th>
                    <th>Peran</th>
                    <th>Jenis Aksi</th>
                    <th>Entitas / Tabel</th>
                    <th>Deskripsi Aktivitas</th>
                    <th>Alamat IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td>{{ date('d-m-Y H:i:s', strtotime($log->created_at)) }}</td>
                        <td>{{ $log->user->nama ?? 'Sistem' }}</td>
                        <td>{{ strtoupper($log->user->role ?? '-') }}</td>
                        <td><strong>{{ strtoupper($log->action ?? $log->aktivitas ?? 'MUTASI') }}</strong></td>
                        <td><code>{{ $log->table_name ?? $log->modul ?? '-' }}</code></td>
                        <td>{{ $log->description ?? $log->keterangan ?? '-' }}</td>
                        <td><small>{{ $log->ip_address ?? '127.0.0.1' }}</small></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" align="center"><em>Belum ada jejak audit yang terekam.</em></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </fieldset>
</section>
@endsection
