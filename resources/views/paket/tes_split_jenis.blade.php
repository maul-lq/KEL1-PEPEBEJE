@extends('layouts.tes_app')

@section('title', 'Pemecahan Jenis Pengadaan - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Pemecahan Jenis Pengadaan (Split Jenis)</h2>
    <p>
        <a href="{{ route('paket.index') }}">&larr; Kembali ke Daftar Paket</a>
    </p>

    <form action="{{ route('paket.split-jenis') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Form Pemecahan Jenis Pengadaan</legend>

            <p>
                <label for="nomor_surat_induk"><strong>Pilih Surat Permohonan Induk Disetujui:</strong></label><br>
                <select id="nomor_surat_induk" name="nomor_surat_induk" required>
                    <option value="">-- Pilih Berkas Induk --</option>
                    @foreach($permohonanDisetujui as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} (Pagu: Rp {{ number_format($p->anggaran_diajukan ?? $p->estimasi_total_pagu, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_surat_baru"><strong>Nomor Surat Baru Hasil Split:</strong></label><br>
                <input type="text" id="nomor_surat_baru" name="nomor_surat_baru" required>
            </p>

            <p>
                <label for="jenis_pengadaan_split"><strong>Jenis Pengadaan Pecahan:</strong></label><br>
                <select id="jenis_pengadaan_split" name="jenis_pengadaan_split" required>
                    <option value="barang">Pengadaan Barang</option>
                    <option value="jasa">Pengadaan Jasa Lainnya</option>
                    <option value="konstruksi">Pekerjaan Konstruksi</option>
                    <option value="jasa_konsultansi">Jasa Konsultansi</option>
                </select>
            </p>

            <p>
                <label for="judul_pengadaan"><strong>Judul Pengadaan Paket Hasil Pemecahan:</strong></label><br>
                <input type="text" id="judul_pengadaan" name="judul_pengadaan" size="50" required>
            </p>

            <p>
                <label for="estimasi_total_pagu"><strong>Nominal Pagu Pecahan:</strong></label><br>
                <input type="number" id="estimasi_total_pagu" name="estimasi_total_pagu" step="0.01" min="1" required>
            </p>

            <p>
                <label for="alasan_pemecahan"><strong>Alasan Pemecahan:</strong></label><br>
                <textarea id="alasan_pemecahan" name="alasan_pemecahan" rows="3" cols="50" required></textarea>
            </p>

            <p>
                <button type="submit"><strong>Proses Pemecahan Berkas</strong></button>
            </p>
        </fieldset>
    </form>
</section>
@endsection
