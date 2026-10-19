@extends('layouts.tes_app')

@section('title', 'Riwayat Permohonan Pengadaan Saya - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Riwayat & Status Usulan Permohonan Pengadaan</h2>
    <p>
        Menampilkan seluruh usulan permohonan yang diajukan oleh unit kerja Anda, status verifikasi paralel oleh Wadir 2 dan Perencanaan,
        serta catatan transparansi jika usulan ditolak atau diminta revisi.
    </p>

    <p>
        <a href="{{ route('permohonan.create') }}"><button type="button"><strong>+ Ajukan Permohonan Pengadaan Baru</strong></button></a>
    </p>

    @if($permohonanList->isEmpty())
        <p><em>Belum ada riwayat permohonan pengadaan yang terdaftar untuk akun Anda.</em></p>
    @else
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nomor Surat Srikandi</th>
                    <th>Perihal Pengadaan</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Estimasi Nilai</th>
                    <th>Status Usulan</th>
                    <th>Catatan & Alasan Verifikator</th>
                    <th>Aksi & Unduh Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($permohonanList as $idx => $item)
                    <tr>
                        <td align="center">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->nomor_surat }}</strong>
                            @if($item->nomor_surat_revisi)
                                <br><small><em>(Revisi dari: {{ $item->nomor_surat_revisi }})</em></small>
                            @endif
                        </td>
                        <td>{{ $item->perihal }}</td>
                        <td>{{ $item->tanggal_pengajuan ? date('d-m-Y', strtotime($item->tanggal_pengajuan)) : '-' }}</td>
                        <td>Rp {{ number_format($item->anggaran_diajukan, 0, ',', '.') }}</td>
                        <td>
                            <strong>{{ strtoupper(str_replace('_', ' ', $item->status)) }}</strong>
                        </td>
                        <td>
                            @if($item->catatan_revisi)
                                <strong>Catatan Penolakan / Revisi:</strong>
                                <p><em>{{ $item->catatan_revisi }}</em></p>
                            @elseif($item->status === 'disetujui')
                                <small>Usulan telah disetujui pimpinan dan diteruskan ke PPBJ.</small>
                            @else
                                <small>Dalam proses telaah berkas / verifikasi paralel.</small>
                            @endif
                        </td>
                        <td>
                            <p>
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $item->nomor_surat]) }}" target="_blank">
                                    <button type="button">Unduh PDF Resmi</button>
                                </a>
                            </p>
                            @if(in_array($item->status, ['ditolak', 'revisi']))
                                <details>
                                    <summary><strong>Ajukan Revisi Surat Ini</strong></summary>
                                    <form action="{{ route('permohonan.revisi') }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="nomor_surat_lama" value="{{ $item->nomor_surat }}">
                                        <p>
                                            <label>Nomor Surat Revisi Baru:</label><br>
                                            <input type="text" name="nomor_surat_baru" required placeholder="Contoh: B/123.REV/PL3/TIK/2026">
                                        </p>
                                        <p>
                                            <label>Perihal Pengadaan Baru:</label><br>
                                            <input type="text" name="perihal" value="{{ $item->perihal }}" required>
                                        </p>
                                        <p>
                                            <label>Penyesuaian Anggaran (Rp):</label><br>
                                            <input type="number" name="anggaran_diajukan" value="{{ $item->anggaran_diajukan }}" step="0.01" required>
                                        </p>
                                        <p>
                                            <label>Keterangan Perbaikan Dokumen:</label><br>
                                            <textarea name="keterangan_revisi" rows="2" placeholder="Jelaskan perbaikan berkas KAK/Spek yang dilakukan"></textarea>
                                        </p>
                                        <p>
                                            <label>Unggah Berkas KAK Baru (Opsional):</label><br>
                                            <input type="file" name="file_kak" accept=".pdf,.doc,.docx">
                                        </p>
                                        <p>
                                            <button type="submit">Kirim Surat Revisi</button>
                                        </p>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
@endsection
