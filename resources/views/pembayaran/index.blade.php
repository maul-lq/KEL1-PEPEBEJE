@extends('layouts.app')

@section('title', 'Pencairan Dana & Pembayaran - Unit Keuangan PNJ')

@section('content')
<section>
    <h2>Verifikasi Tagihan SPP & Pencairan Dana (Unit Keuangan)</h2>
    <p>
        Bagian Keuangan memverifikasi dokumen Berita Acara Serah Terima (BAST), memeriksa alokasi pagu DIPA,
        mengeksekusi pencairan dana (SP2D) dengan proteksi anti-pembayaran ganda, serta mengunggah slip bukti transfer pemindahbukuan bank.
    </p>

    {{-- 1. Formulir Pencairan Dana SP2D --}}
    <fieldset>
        <legend><strong>Eksekusi Pencairan Dana & Pembayaran Termin / Lunas</strong></legend>

        <form action="{{ route('pembayaran.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p>
                <label for="nomor_bast"><strong>Pilih Berita Acara Serah Terima (BAST) Siap Bayar:</strong></label><br>
                <select id="nomor_bast" name="nomor_bast" required>
                    <option value="">-- Pilih Berkas BAST --</option>
                    @foreach($bastSiapBayar as $b)
                        <option value="{{ $b->nomor_bast }}">
                            [{{ $b->nomor_bast }}] SPK: {{ $b->nomor_kontrak_spk }} &bull; Paket: {{ $b->kontrak->kode_paket ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_transaksi"><strong>Nomor SP2D / Bukti Transaksi Kas:</strong></label><br>
                <input type="text" id="nomor_transaksi" name="nomor_transaksi" value="SP2D/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="tanggal_cair"><strong>Tanggal Pencairan / Realisasi Pembayaran:</strong></label><br>
                <input type="date" id="tanggal_cair" name="tanggal_cair" value="{{ date('Y-m-d') }}" required>
            </p>

            <p>
                <label for="nominal_cair"><strong>Nominal Pencairan Dana (Rp):</strong></label><br>
                <input type="number" id="nominal_cair" name="nominal_cair" step="0.01" min="1" required placeholder="Contoh: 145000000">
                <br>
                <small><em>Sistem backend akan mencegah pencairan melebihi pagu MAK atau pembayaran ganda (NFR 8).</em></small>
            </p>

            <p>
                <label for="bank_tujuan"><strong>Bank Penyalur & Rekening Rekanan / Vendor:</strong></label><br>
                <input type="text" id="bank_tujuan" name="bank_tujuan" size="50" required placeholder="Contoh: Bank Mandiri KCP Kampus UI - Rek. 1570001234567 a.n PT Rekanan">
            </p>

            <p>
                <label for="file_bukti_transfer"><strong>Unggah Berkas Slip Transfer Bank / Bukti SP2D (PDF):</strong></label><br>
                <input type="file" id="file_bukti_transfer" name="file_bukti_transfer" accept=".pdf" required>
                <br>
                <small><em>Format: .pdf (Maksimal 10 MB). Wajib diunggah sebagai bukti sah audit institusi.</em></small>
            </p>

            <p>
                <label for="keterangan_cair"><strong>Keterangan / Berita Acara Transfer:</strong></label><br>
                <textarea id="keterangan_cair" name="keterangan_cair" rows="2" cols="60" placeholder="Contoh: Pembayaran lunas 100% termin pengadaan lab TIK DIPA BOPTN 2026."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Eksekusi Pencairan Dana & Selesaikan Siklus Pengadaan</strong></button>
            </p>
        </fieldset>
    </form>

    <hr>

    {{-- 2. Daftar Transaksi Pencairan Dana yang Telah Selesai --}}
    <fieldset>
        <legend><strong>Riwayat Realisasi Pencairan Dana (SP2D / Slip Transfer Bank)</strong></legend>

        @if($transaksiList->isEmpty())
            <p><em>Belum ada transaksi pencairan dana yang tercatat.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor SP2D / Transaksi</th>
                        <th>Nomor BAST</th>
                        <th>Tanggal Realisasi</th>
                        <th>Nominal Dicairkan</th>
                        <th>Status Dana</th>
                        <th>Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaksiList as $t)
                        <tr>
                            <td><strong>{{ $t->nomor_transaksi }}</strong></td>
                            <td>{{ $t->nomor_bast }}</td>
                            <td>{{ date('d-m-Y', strtotime($t->tanggal_cair ?? $t->created_at)) }}</td>
                            <td><strong>Rp {{ number_format($t->nominal_cair, 0, ',', '.') }}</strong></td>
                            <td>{{ strtoupper($t->status_transaksi ?? 'LUNAS / TERCURAHKAN') }}</td>
                            <td>
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'transfer', 'id' => $t->nomor_transaksi]) }}" target="_blank">
                                    <button type="button">Unduh Slip Transfer (PDF)</button>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>
</section>
@endsection
