@extends('layouts.app')

@section('title', 'Penerimaan Barang & Penerbitan BAST - Unit Perlengkapan PNJ')

@section('content')
<section>
    <h2>Pemeriksaan Fisik Barang & Penerbitan BAST (Unit Perlengkapan / BMN)</h2>
    <p>
        Petugas Perlengkapan bertugas memeriksa barang kiriman rekanan/vendor, mengambil dan mengunggah foto dokumentasi fisik
        (sesuai notulensi klien: minimal 1 foto barang terlihat jelas), serta menerbitkan Berita Acara Serah Terima (BAST) resmi
        bertoken HMAC untuk diteruskan ke Bagian Keuangan.
    </p>

    {{-- 1. Formulir Pemeriksaan Barang & Penerbitan BAST --}}
    <fieldset>
        <legend><strong>Formulir Pemeriksaan Fisik & Penerbitan BAST</strong></legend>

        <form action="{{ route('penerimaan.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p>
                <label for="nomor_kontrak_spk"><strong>Pilih Kontrak SPK Aktif:</strong></label><br>
                <select id="nomor_kontrak_spk" name="nomor_kontrak_spk" required>
                    <option value="">-- Pilih Kontrak SPK --</option>
                    @foreach($kontrakList as $k)
                        <option value="{{ $k->nomor_kontrak_spk }}">
                            [{{ $k->nomor_kontrak_spk }}] {{ $k->paket->nama_paket ?? $k->kode_paket }} &bull; Vendor: {{ $k->vendor->nama_vendor ?? '-' }} &bull; Nilai: Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_bast"><strong>Nomor Berita Acara Serah Terima (BAST):</strong></label><br>
                <input type="text" id="nomor_bast" name="nomor_bast" value="BAST/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="tanggal_terima"><strong>Tanggal Pemeriksaan & Serah Terima Fisik:</strong></label><br>
                <input type="date" id="tanggal_terima" name="tanggal_terima" value="{{ date('Y-m-d') }}" required>
            </p>

            <p>
                <label for="kondisi_barang"><strong>Hasil Pemeriksaan Kondisi Fisik Barang:</strong></label><br>
                <select id="kondisi_barang" name="kondisi_barang" required>
                    <option value="Sesuai Spesifikasi 100%">Sesuai Spesifikasi 100% (Baik dan Lengkap)</option>
                    <option value="Diterima dengan Catatan">Diterima dengan Catatan Minor</option>
                    <option value="Rusak / Tidak Sesuai">Rusak / Tidak Sesuai KAK (Retur)</option>
                </select>
            </p>

            <p>
                <label for="foto_barang"><strong>Unggah Foto Dokumentasi Fisik Barang:</strong></label><br>
                <input type="file" id="foto_barang" name="foto_barang" accept="image/*,.pdf" required>
                <br>
                <small><em>Format: Gambar JPG/PNG atau PDF. Wajib diunggah agar fisik barang terbukti tiba di gudang PNJ.</em></small>
            </p>

            <p>
                <label for="catatan_pemeriksaan"><strong>Catatan Pemeriksaan Fisik Perlengkapan:</strong></label><br>
                <textarea id="catatan_pemeriksaan" name="catatan_pemeriksaan" rows="3" cols="60" placeholder="Contoh: Barang telah diperiksa bersama teknisi lab, kuantitas dan nomor seri sesuai faktur vendor."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Terbitkan BAST & Teruskan Berkas ke Keuangan &raquo;</strong></button>
            </p>
        </fieldset>
    </form>

    <hr>

    {{-- 2. Daftar BAST yang Telah Diterbitkan --}}
    <fieldset>
        <legend><strong>Daftar Berita Acara Serah Terima (BAST) Terbit</strong></legend>

        @if($bastList->isEmpty())
            <p><em>Belum ada dokumen BAST yang diterbitkan.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor BAST</th>
                        <th>Nomor SPK</th>
                        <th>Tanggal Terima</th>
                        <th>Kondisi Fisik</th>
                        <th>Token HMAC Validasi</th>
                        <th>Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bastList as $b)
                        <tr>
                            <td><strong>{{ $b->nomor_bast }}</strong></td>
                            <td>{{ $b->nomor_kontrak_spk }}</td>
                            <td>{{ date('d-m-Y', strtotime($b->tanggal_terima ?? $b->created_at)) }}</td>
                            <td>{{ $b->kondisi_barang ?? 'Baik' }}</td>
                            <td><code>{{ substr($b->token_bast_hmac ?? md5($b->nomor_bast), 0, 16) }}...</code></td>
                            <td>
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'bast', 'id' => $b->nomor_bast]) }}" target="_blank">
                                    <button type="button">Unduh BAST (PDF)</button>
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
