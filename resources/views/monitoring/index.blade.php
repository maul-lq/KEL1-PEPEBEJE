@extends('layouts.app')

@section('title', 'Monitoring 8 Siklus Pengadaan & Audit Trail - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Pemantauan Real-Time 8 Siklus Pengadaan & Jejak Audit (FR 19 & NFR 9)</h2>
    <p>
        Menampilkan pelacakan alur pengadaan dari usulan awal hingga pencairan dana secara real-time (target respons &lt; 5 detik),
        serta rekam jejak audit (<em>Audit Trail</em> transaksi mutasi &lt; 2 detik) secara transparan untuk pimpinan, PPBJ, Perencanaan, Keuangan, dan Pengawas Internal.
    </p>

    {{-- Filter Pencarian Dokumen & Paket Berdasarkan Multi-Kriteria (FR 19) --}}
    <fieldset>
        <legend><strong>Pencarian Multi-Filter Siklus Pengadaan (Nomor Surat, Unit/Jurusan, Tahun, Bulan, Status)</strong></legend>
        <form action="{{ route('monitoring.index') }}" method="GET">
            <p>
                <label for="q"><strong>Nomor Surat / Perihal Pengadaan:</strong></label><br>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Contoh: 001/PL3/TU/2026 atau Komputer Lab">
            </p>

            <p>
                <label for="unit"><strong>Unit / Jurusan Pengusul:</strong></label><br>
                <select id="unit" name="unit">
                    <option value="">-- Seluruh Unit & Jurusan --</option>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ request('unit') === $u->kode_unit ? 'selected' : '' }}>
                            {{ $u->nama_unit }} ({{ $u->kode_unit }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="tahun"><strong>Tahun Anggaran:</strong></label>
                <input type="number" id="tahun" name="tahun" value="{{ request('tahun', date('Y')) }}" min="2020" max="2035" placeholder="YYYY">
                &nbsp;&nbsp;
                <label for="bulan"><strong>Bulan Pengajuan:</strong></label>
                <select id="bulan" name="bulan">
                    <option value="">-- Seluruh Bulan --</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ request('bulan') == $m ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }} (Bulan {{ $m }})
                        </option>
                    @endfor
                </select>
                &nbsp;&nbsp;
                <label for="status"><strong>Status Siklus:</strong></label>
                <select id="status" name="status">
                    <option value="">-- Seluruh Status --</option>
                    <option value="diajukan" {{ request('status') === 'diajukan' ? 'selected' : '' }}>Diajukan (Baru)</option>
                    <option value="reviu_paralel" {{ request('status') === 'reviu_paralel' ? 'selected' : '' }}>Reviu Paralel (Wadir 2 & Perencanaan)</option>
                    <option value="pending_anggaran" {{ request('status') === 'pending_anggaran' ? 'selected' : '' }}>Pending Anggaran (Hold MAK)</option>
                    <option value="disetujui" {{ request('status') === 'disetujui' ? 'selected' : '' }}>Disetujui Penuh (Siap PPBJ)</option>
                    <option value="ditolak" {{ request('status') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai / Lunas</option>
                </select>
            </p>

            <p>
                <button type="submit"><strong>Saring Data Pengadaan (Multi-Filter)</strong></button>
                <a href="{{ route('monitoring.index') }}"><button type="button">Reset Filter</button></a>
            </p>
        </form>
    </fieldset>

    <br>

    {{-- 1. Tabel Monitoring 8 Siklus Pengadaan --}}
    <fieldset>
        <legend><strong>1. Pelacakan 8 Tahapan Siklus Pengadaan (Monitoring Real-Time &lt; 5 Detik - FR 19)</strong></legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nomor Surat Srikandi</th>
                    <th>Unit / Jurusan</th>
                    <th>Perihal Pengadaan</th>
                    <th>Estimasi Nilai</th>
                    <th>1. Pengajuan</th>
                    <th>2. Verif Wadir 2 & MAK</th>
                    <th>3. Paket (PPBJ)</th>
                    <th>4. Reviu Dokumen</th>
                    <th>5. SPK (PP/PPK)</th>
                    <th>6. BAST (BMN)</th>
                    <th>7. Cair SP2D</th>
                    <th>Status Siklus Saat Ini</th>
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
                        <td align="center">&#10003; Terdaftar</td>
                        <td align="center">
                            @if(in_array($p->status, ['disetujui', 'selesai']))
                                &#10003; ACC Penuh
                            @elseif($p->status === 'pending_anggaran')
                                <em>HOLD (MAK)</em>
                            @elseif($p->status === 'ditolak')
                                &#10007; Ditolak
                            @else
                                &bull; Proses
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
                            @if($p->paket && $p->paket->reviuDokumen && $p->paket->reviuDokumen->count() > 0)
                                &#10003; {{ strtoupper($p->paket->reviuDokumen->last()->status_reviu) }}
                            @elseif($p->paket)
                                <em>Antrean Reviu</em>
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
                        <td colspan="13" align="center"><em>Tidak ada data permohonan pengadaan yang sesuai dengan parameter filter.</em></td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if(method_exists($permohonanList, 'links'))
            <p>{{ $permohonanList->appends(request()->query())->links() }}</p>
        @endif
    </fieldset>

    <br>

    {{-- 2. Tabel Jejak Audit (Audit Trail) Sistem --}}
    <fieldset>
        <legend><strong>2. Jejak Audit Transaksi & Mutasi Sistem (Audit Trail Otomatis &lt; 2 Detik - NFR 9)</strong></legend>
        <p>
            Merekam setiap mutasi data penting: pengajuan, revisi surat, alokasi MAK, penahanan (hold) anggaran, split MAK, penugasan/alih tugas PPK, telaah reviu dokumen, penerbitan SPK pengunci MAK, BAST HMAC, hingga pencairan SP2D.
        </p>

        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Waktu Aktivitas</th>
                    <th>Pelaksana / Aktor</th>
                    <th>Peran</th>
                    <th>Aksi Transaksi</th>
                    <th>Tabel Entitas</th>
                    <th>Identitas Kunci</th>
                    <th>Deskripsi Lengkap Mutasi Data</th>
                    <th>Alamat IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($auditLogs as $log)
                    <tr>
                        <td>{{ date('d-m-Y H:i:s', strtotime($log->waktu_log ?? $log->created_at)) }}</td>
                        <td>{{ $log->user->nama ?? 'Sistem' }}</td>
                        <td>{{ strtoupper($log->user->role ?? '-') }}</td>
                        <td><strong>{{ strtoupper($log->jenis_aksi ?? $log->action ?? 'MUTASI') }}</strong></td>
                        <td><code>{{ $log->nama_entitas ?? $log->table_name ?? '-' }}</code></td>
                        <td>{{ $log->id_entitas ?? $log->record_id ?? '-' }}</td>
                        <td>{{ $log->alasan ?? $log->description ?? '-' }}</td>
                        <td><small>{{ $log->ip_address ?? '127.0.0.1' }}</small></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" align="center"><em>Belum ada catatan jejak audit transaksi.</em></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </fieldset>
</section>
@endsection
