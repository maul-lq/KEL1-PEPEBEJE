@extends('layouts.app')

@section('title', 'Registrasi Paket Pengadaan Baru - Koordinator PPBJ PNJ')

@section('content')
<section>
    <h2>Registrasi Paket Pengadaan Baru (Koordinator PPBJ)</h2>
    <p>
        Koordinator PPBJ mendaftarkan paket pengadaan dari usulan permohonan yang telah disetujui pimpinan,
        menetapkan Harga Perkiraan Sendiri (HPS), dan menentukan jalur penugasan berdasarkan ambang batas anggaran:
    </p>
    <ul>
        <li><strong>Nilai HPS &le; Rp 50.000.000:</strong> Pengadaan Langsung (PL).</li>
        <li><strong>Nilai HPS Rp 50.000.001 s.d Rp 200.000.000:</strong> Ditugaskan ke Pejabat Pengadaan (PP).</li>
        <li><strong>Nilai HPS &gt; Rp 200.000.000:</strong> Ditugaskan ke Pejabat Pembuat Komitmen (1 dari 4 akun PPK PNJ).</li>
    </ul>

    <form action="{{ route('paket.store') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Data Paket Pengadaan & Penugasan</legend>

            <p>
                <label for="nomor_surat"><strong>Pilih Usulan Permohonan Pengadaan yang Telah Disetujui:</strong></label><br>
                <select id="nomor_surat" name="nomor_surat" required>
                    <option value="">-- Pilih Permohonan Disetujui --</option>
                    @foreach($permohonanDisetujui as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} &bull; Estimasi: Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }} ({{ $p->unit->nama_unit ?? $p->kode_unit }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="kode_paket"><strong>Kode Unik Paket Pengadaan:</strong></label><br>
                <input type="text" id="kode_paket" name="kode_paket" value="{{ old('kode_paket', 'PKT-PNJ-' . date('Ymd') . '-' . rand(100, 999)) }}" required>
            </p>

            <p>
                <label for="nama_paket"><strong>Nama Paket Pengadaan Barang/Jasa:</strong></label><br>
                <input type="text" id="nama_paket" name="nama_paket" value="{{ old('nama_paket') }}" size="60" required placeholder="Contoh: Pengadaan Fasilitas Komputer AI Lab TIK">
            </p>

            <p>
                <label for="jenis_pengadaan"><strong>Jenis Pengadaan:</strong></label><br>
                <select id="jenis_pengadaan" name="jenis_pengadaan" required>
                    <option value="Barang">Barang</option>
                    <option value="Jasa Lainnya">Jasa Lainnya</option>
                    <option value="Pekerjaan Konstruksi">Pekerjaan Konstruksi</option>
                    <option value="Jasa Konsultansi">Jasa Konsultansi</option>
                </select>
            </p>

            <p>
                <label for="metode_pengadaan"><strong>Metode Pengadaan:</strong></label><br>
                <select id="metode_pengadaan" name="metode_pengadaan" required>
                    <option value="Pengadaan Langsung">Pengadaan Langsung (&le; 50jt / non-tender)</option>
                    <option value="E-Purchasing">E-Purchasing (Katalog Elektronik / Inaproc)</option>
                    <option value="Tender">Tender Terbuka (SPSE)</option>
                    <option value="Seleksi">Seleksi Konsultansi</option>
                    <option value="Penunjukan Langsung">Penunjukan Langsung</option>
                </select>
            </p>

            <p>
                <label for="nilai_hps"><strong>Nilai Harga Perkiraan Sendiri / HPS (Rp):</strong></label><br>
                <input type="number" id="nilai_hps" name="nilai_hps" value="{{ old('nilai_hps') }}" step="0.01" min="1" required placeholder="Contoh: 250000000">
                <br>
                <small><em>Sistem backend akan memvalidasi nilai HPS secara otomatis dan mengarahkan jalur pengadaan.</em></small>
            </p>

            <p>
                <label for="id_user_ppk"><strong>Pilih Pejabat Pembuat Komitmen / PPK (Wajib Jika HPS &gt; 200 Juta):</strong></label><br>
                <select id="id_user_ppk" name="id_user_ppk">
                    <option value="">-- Pilih Akun PPK (Khusus Nilai > 200 Juta) --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}">
                            {{ $ppk->nama }} ({{ $ppk->jabatan }})
                        </option>
                    @endforeach
                </select>
                <br>
                <small><em>PNJ memiliki 4 akun PPK mandiri (Sarana, Laboratorium, Konstruksi, Jasa).</em></small>
            </p>

            <p>
                <label for="id_user_pp"><strong>Pejabat Pengadaan / PP Penanggung Jawab:</strong></label><br>
                <select id="id_user_pp" name="id_user_pp">
                    @foreach($ppUsers as $pp)
                        <option value="{{ $pp->id_user }}">{{ $pp->nama }} (Pejabat Pengadaan Aktif)</option>
                    @endforeach
                </select>
            </p>

            <p>
                <button type="submit"><strong>Daftarkan & Terbitkan Paket Pengadaan</strong></button>
                &nbsp;
                <a href="{{ route('paket.index') }}">Batal dan Kembali</a>
            </p>
        </fieldset>
    </form>
</section>
@endsection
