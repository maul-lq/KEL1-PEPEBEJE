@extends('layouts.app')

@section('title', 'Ajukan Permohonan Pengadaan Baru - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Pengajuan Usulan Permohonan Pengadaan Barang & Jasa</h2>
    <p>
        Formulir ini digunakan oleh Unit Kerja / Jurusan untuk mendaftarkan usulan pengadaan berbasis surat dinas resmi Srikandi,
        dilengkapi dengan Kerangka Acuan Kerja (KAK) dan Spesifikasi Teknis.
    </p>

    <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <fieldset>
            <legend>Data Surat & Dokumen Pendukung Pengadaan</legend>

            <p>
                <label for="nomor_surat"><strong>Nomor Surat Resmi Srikandi PNJ:</strong></label><br>
                <input type="text" id="nomor_surat" name="nomor_surat" value="{{ old('nomor_surat') }}" required placeholder="Contoh: B/123/PL3/TIK/2026">
                <br>
                <small><em>Nomor surat dicatat sesuai identitas registrasi naskah dinas Srikandi dan harus unik.</em></small>
            </p>

            <p>
                <label for="perihal"><strong>Perihal / Judul Pengadaan:</strong></label><br>
                <input type="text" id="perihal" name="perihal" value="{{ old('perihal') }}" required placeholder="Contoh: Pengadaan Perangkat Server dan Lab Cloud AI">
            </p>

            <p>
                <label for="anggaran_diajukan"><strong>Estimasi Nilai Anggaran yang Diusulkan (Rp):</strong></label><br>
                <input type="number" id="anggaran_diajukan" name="anggaran_diajukan" value="{{ old('anggaran_diajukan') }}" step="0.01" min="1" required placeholder="Contoh: 150000000">
                <br>
                <small><em>Masukkan angka estimasi total tanpa titik atau koma (misal: 150000000 untuk 150 juta).</em></small>
            </p>

            <p>
                <label for="kode_unit"><strong>Unit Kerja / Jurusan Pengusul:</strong></label><br>
                <select id="kode_unit" name="kode_unit" required>
                    @foreach($units as $u)
                        <option value="{{ $u->kode_unit }}" {{ (Auth::user()->kode_unit === $u->kode_unit) ? 'selected' : '' }}>
                            [{{ $u->kode_unit }}] {{ $u->nama_unit }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="tanggal_pengajuan"><strong>Tanggal Pengajuan Usulan:</strong></label><br>
                <input type="date" id="tanggal_pengajuan" name="tanggal_pengajuan" value="{{ old('tanggal_pengajuan', date('Y-m-d')) }}" required>
            </p>

            <p>
                <label for="file_surat_permohonan"><strong>Unggah Berkas Surat Permohonan Srikandi (PDF):</strong></label><br>
                <input type="file" id="file_surat_permohonan" name="file_surat_permohonan" accept=".pdf">
                <br>
                <small><em>Format: .pdf (Maksimal 10 MB). Opsional jika file fisik menyusul.</em></small>
            </p>

            <p>
                <label for="file_kak"><strong>Unggah Berkas Kerangka Acuan Kerja / KAK (PDF/DOCX):</strong></label><br>
                <input type="file" id="file_kak" name="file_kak" accept=".pdf,.doc,.docx">
                <br>
                <small><em>Format: .pdf, .docx (Maksimal 10 MB).</em></small>
            </p>

            <p>
                <label for="file_spesifikasi_teknis"><strong>Unggah Berkas Spesifikasi Teknis & Rincian Harga (PDF/DOCX/XLSX):</strong></label><br>
                <input type="file" id="file_spesifikasi_teknis" name="file_spesifikasi_teknis" accept=".pdf,.doc,.docx,.xlsx">
                <br>
                <small><em>Format: .pdf, .docx, .xlsx (Maksimal 10 MB).</em></small>
            </p>

            <p>
                <button type="submit"><strong>Kirimkan Usulan Permohonan Pengadaan</strong></button>
                &nbsp;
                <a href="{{ route('permohonan.index') }}">Batal dan Kembali</a>
            </p>
        </fieldset>
    </form>
</section>
@endsection
