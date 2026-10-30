@extends('layouts.tes_app')

@section('title', 'Detail Paket Pengadaan ' . ($paket->kode_paket ?? $paket->nomor_paket) . ' - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Detail Paket Pengadaan ({{ $paket->kode_paket ?? $paket->nomor_paket }})</h2>
    <p>
        <a href="{{ route('paket.index') }}">&larr; Kembali ke Daftar Paket</a>
    </p>

    <fieldset>
        <legend>Informasi Utama Paket Pengadaan</legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <th>Kode Paket</th>
                <td><strong>{{ $paket->kode_paket ?? $paket->nomor_paket }}</strong></td>
            </tr>
            <tr>
                <th>Nama Paket</th>
                <td>{{ $paket->nama_paket }}</td>
            </tr>
            <tr>
                <th>Status Paket</th>
                <td><u>{{ strtoupper(str_replace('_', ' ', $paket->status_paket)) }}</u></td>
            </tr>
            <tr>
                <th>Jenis Pengadaan</th>
                <td>{{ $paket->jenis_pengadaan }}</td>
            </tr>
            <tr>
                <th>Metode Pengadaan</th>
                <td>{{ $paket->metode_pengadaan }}</td>
            </tr>
            <tr>
                <th>Nilai HPS</th>
                <td><strong>Rp {{ number_format($paket->nilai_hps, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <th>Jalur Routing</th>
                <td>{{ strtoupper(str_replace('_', ' ', $paket->jalur_routing)) }}</td>
            </tr>
            <tr>
                <th>Nomor Memo PPBJ</th>
                <td>{{ $paket->nomor_memo_ppbj ?? '(Tanpa Memo Pengadaan)' }}</td>
            </tr>
            <tr>
                <th>Koordinator PPBJ</th>
                <td>{{ $paket->ppbjUser->nama ?? '-' }}</td>
            </tr>
            <tr>
                <th>Pejabat Pengadaan (PP)</th>
                <td>{{ $paket->ppUser->nama ?? 'Tidak Ditugaskan' }}</td>
            </tr>
            <tr>
                <th>Pejabat Pembuat Komitmen (PPK)</th>
                <td><strong>{{ $paket->ppkUser->nama ?? 'Belum Ditugaskan' }}</strong></td>
            </tr>
            <tr>
                <th>Rekanan Terhubung</th>
                <td>{{ $paket->vendor->nama_vendor ?? 'Belum Ditunjuk' }}</td>
            </tr>
        </table>
    </fieldset>

    @if($paket->permohonan)
        <fieldset>
            <legend>Surat Usulan Permohonan Pengadaan Terkait</legend>
            <p>
                <strong>Nomor Surat:</strong> {{ $paket->permohonan->nomor_surat }}<br>
                <strong>Perihal:</strong> {{ $paket->permohonan->perihal }}<br>
                <strong>Unit Pengusul:</strong> {{ $paket->permohonan->unit->nama_unit ?? $paket->permohonan->kode_unit }}<br>
                <strong>Pagu Usulan:</strong> Rp {{ number_format($paket->permohonan->anggaran_diajukan ?? $paket->permohonan->estimasi_total_pagu, 0, ',', '.') }}
            </p>
        </fieldset>
    @endif

    <fieldset>
        <legend>Dokumen Lanjutan Siklus Pengadaan</legend>
        <p>
            <strong>Kontrak / SPK:</strong> {{ $paket->kontrakSpk->nomor_spk ?? 'Belum Diterbitkan' }}<br>
            <strong>BAST Fisik:</strong> {{ $paket->penerimaanBarang->first()?->nomor_bast ?? 'Belum Diperiksa' }}<br>
            <strong>Pengajuan Pembayaran:</strong> {{ $paket->pembayaran->first()?->nomor_pembayaran ?? 'Belum Diajukan' }}
        </p>
    </fieldset>
</section>
@endsection
