@extends('layouts.tes_app')

@section('title', 'Monitoring Siklus Pengadaan & Jejak Audit - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<article>
    <header>
        <h2>Pemantauan Real-Time 8 Siklus Pengadaan</h2>
        <p>
            Pelacakan status berkas usulan pengadaan dari tahap pengajuan awal hingga realisasi pencairan dana SP2D,
            serta catatan jejak audit aktivitas transaksi secara transparan dan akuntabel.
        </p>
    </header>

    {{-- Filter Pencarian Dokumen & Paket Berdasarkan Multi-Kriteria --}}
    <section>
        <fieldset>
            <legend><strong>Pencarian Multi-Filter Siklus Pengadaan</strong></legend>
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
                    <button type="submit">Saring Data Pengadaan</button>
                    <a href="{{ route('monitoring.index') }}"><button type="button">Reset Filter</button></a>
                </p>
            </form>
        </fieldset>
    </section>

    <hr>

    {{-- Tabel Monitoring Siklus Pengadaan --}}
    <section>
        <fieldset>
            <legend><strong>Pelacakan 8 Tahapan Siklus Pengadaan</strong></legend>
            <table>
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
                                    &#10003; Disetujui
                                @elseif($p->status === 'ditolak')
                                    &#10007; Ditolak
                                @elseif($p->status === 'pending_anggaran')
                                    &#9208; Hold MAK
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
                                @if($p->paket && $p->paket->reviuDokumen->isNotEmpty())
                                    &#10003; Selesai
                                @else
                                    -
                                @endif
                            </td>
                            <td align="center">
                                @if($p->paket && $p->paket->kontrak)
                                    &#10003; Terbit
                                @else
                                    -
                                @endif
                            </td>
                            <td align="center">
                                @if($p->paket && $p->paket->kontrak && $p->paket->kontrak->penerimaan)
                                    &#10003; Sah BAST
                                @else
                                    -
                                @endif
                            </td>
                            <td align="center">
                                @if($p->status === 'selesai' || ($p->paket && $p->paket->status_paket === 'selesai'))
                                    <strong>&#10003; Cair SP2D</strong>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <strong>{{ strtoupper(str_replace('_', ' ', $p->status_permohonan ?? $p->status)) }}</strong>
                            </td>
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
    </section>

    <hr>

    {{-- Tabel Jejak Audit (Audit Trail) Sistem --}}
    <section>
        <fieldset>
            <legend><strong>Jejak Audit Transaksi & Mutasi Sistem</strong></legend>
            <p>
                Merekam riwayat mutasi data penting: pengajuan, revisi surat dinas, alokasi MAK, penahanan (hold) anggaran, split MAK, penugasan PPK, telaah reviu dokumen, penerbitan SPK, BAST HMAC, hingga realisasi pencairan SP2D.
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Waktu Aktivitas</th>
                        <th>Pelaksana / Aktor</th>
                        <th>Peran Jabatan</th>
                        <th>Aksi Transaksi</th>
                        <th>Tabel Entitas</th>
                        <th>Identitas Rekaman</th>
                        <th>Deskripsi Aktivitas</th>
                        <th>Alamat IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auditLogs as $log)
                        <tr>
                            <td>{{ date('d-m-Y H:i:s', strtotime($log->waktu_log ?? $log->created_at)) }}</td>
                            <td>{{ $log->user->nama ?? 'Sistem' }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $log->user->role ?? '-')) }}</td>
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
</article>
@endsection
