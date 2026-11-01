@extends('layouts.tes_app')

@section('title', 'Registrasi Paket & Penentuan Metode Pengadaan - Koordinator PPBJ PNJ')

@section('content')
<section>
    <h2>Registrasi Paket Baru &amp; Penentuan Metode Pengadaan (Koordinator PPBJ)</h2>
    <p>
        Koordinator PPBJ menerima berkas yang telah disetujui oleh Wadir II dan PPK terpilih, mendaftarkan paket pengadaan,
        serta menentukan <strong>Metode Pengadaan</strong> (Tender SPSE, Non-Tender / E-Katalog Inaproc, atau Belanja Langsung):
    </p>
    <ul>
        <li><strong>Nilai &le; Rp 50.000.000 (Belanja Langsung):</strong> Dieksekusi oleh PPBJ, outputnya <strong>Kwitansi</strong> (<strong>tidak ada Kontrak / SPK / Surat Pesanan</strong>) &rarr; Pemeriksaan &amp; BAST Perlengkapan &rarr; Pembayaran.</li>
        <li><strong>Nilai &gt; Rp 50.000.000 s.d. Rp 200.000.000 (Melibatkan PP baik Tender maupun Non-Tender):</strong> PP mereview kelengkapan serta menginput Harga Perkiraan, Harga Akhir, dan Vendor (PP tidak dapat klik Approval) &rarr; PPK klik Approval &rarr; PP mengeksekusi belanja &rarr; PPBJ mengunggah salah satu dokumen (Surat Pesanan / SPK / Surat Kontrak) &rarr; BAST Perlengkapan.</li>
        <li><strong>Nilai &gt; Rp 200.000.000 (Melibatkan PPK tanpa Memo Pengadaan PPBJ):</strong> PPBJ menginput data lelang &rarr; PPK klik Approval &amp; mengeksekusi belanja &rarr; PPBJ mengunggah salah satu dokumen (Surat Pesanan / SPK / Surat Kontrak) &rarr; BAST Perlengkapan.</li>
    </ul>

    <form action="{{ route('paket.store') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Data Paket Pengadaan &amp; Metode Pengadaan</legend>

            <p>
                <label for="nomor_surat"><strong>Pilih Usulan Permohonan yang Telah Disetujui (Wadir II &amp; PPK):</strong></label><br>
                <select id="nomor_surat" name="nomor_surat" required>
                    <option value="">-- Pilih Permohonan Disetujui --</option>
                    @foreach($permohonanDisetujui as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} &bull; Estimasi: Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }} &bull; PPK Awal: {{ $p->ppkAwal->nama ?? '-' }}
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
                <label for="metode_pengadaan"><strong>Metode Pengadaan (Tender / Non-Tender / SPSE):</strong></label><br>
                <select id="metode_pengadaan" name="metode_pengadaan" required>
                    <option value="belanja_langsung">Belanja Langsung (&le; Rp 50 Juta &mdash; Output Kwitansi Tanpa Kontrak)</option>
                    <option value="e_katalog">Non-Tender E-Katalog / Inaproc (Surat Pesanan atau SPK)</option>
                    <option value="non_tender">Non-Tender Pihak Ketiga / Pengadaan Langsung (Surat Kontrak)</option>
                    <option value="spse">Tender SPSE (Surat Kontrak / SPK)</option>
                    <option value="tender">Tender Umum Pihak Ketiga (Surat Kontrak)</option>
                </select>
            </p>

            <p>
                <label for="nilai_hps"><strong>Nilai Pagu / HPS Paket (Rp):</strong></label><br>
                <input type="number" id="nilai_hps" name="nilai_hps" value="{{ old('nilai_hps') }}" step="0.01" min="1" required placeholder="Contoh: 150000000">
                <br>
                <small><em>Sistem otomatis menentukan keterlibatan PPBJ (&le; 50 juta), PP (50&ndash;200 juta), atau PPK (&gt; 200 juta).</em></small>
            </p>

            <fieldset>
                <legend>Data Lelang Awal PPBJ (Opsional / Khusus Paket &gt; Rp 200 Juta)</legend>
                <p>
                    <label for="nominal_harga_perkiraan"><strong>Nominal Harga Perkiraan (Rp):</strong></label><br>
                    <input type="number" id="nominal_harga_perkiraan" name="nominal_harga_perkiraan" value="{{ old('nominal_harga_perkiraan') }}" step="0.01" min="0" placeholder="Contoh: 250000000">
                </p>
                <p>
                    <label for="nominal_harga_akhir"><strong>Nominal Harga Akhir Hasil Lelang (Rp):</strong></label><br>
                    <input type="number" id="nominal_harga_akhir" name="nominal_harga_akhir" value="{{ old('nominal_harga_akhir') }}" step="0.01" min="0" placeholder="Contoh: 242000000">
                </p>
                <p>
                    <label for="npwp_vendor"><strong>Vendor / Pemenang Lelang (Opsional saat registrasi):</strong></label><br>
                    <select id="npwp_vendor" name="npwp_vendor">
                        <option value="">-- Pilih Vendor / Pemenang Lelang --</option>
                        @foreach($vendors ?? [] as $v)
                            <option value="{{ $v->npwp }}">{{ $v->nama_vendor }} (NPWP: {{ $v->npwp }})</option>
                        @endforeach
                    </select>
                </p>
            </fieldset>

            <p>
                <label for="nomor_memo_ppbj"><strong>Nomor Memo Pengadaan PPBJ (Khusus Nilai &le; Rp 200 Juta):</strong></label><br>
                <input type="text" id="nomor_memo_ppbj" name="nomor_memo_ppbj" value="{{ old('nomor_memo_ppbj', 'MEMO-PPBJ/' . date('Y') . '/' . rand(100, 999)) }}" size="40">
                <br>
                <small><em>Untuk pengadaan di atas Rp 200.000.000, sistem otomatis mengosongkan memo pengadaan PPBJ.</em></small>
            </p>

            <p>
                <label for="id_user_ppk"><strong>Pejabat Pembuat Komitmen / PPK (Otomatis mewarisi PPK pilihan Perencanaan jika dikosongkan):</strong></label><br>
                <select id="id_user_ppk" name="id_user_ppk">
                    <option value="">-- Gunakan PPK Pilihan Awal Perencanaan --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}">
                            {{ $ppk->nama }} ({{ $ppk->jabatan }})
                        </option>
                    @endforeach
                </select>
                <br>
                <small><em>PNJ memiliki 4 akun PPK mandiri; PPBJ tetap dapat menyesuaikan/mengalihkan PPK bila diperlukan.</em></small>
            </p>

            <p>
                <label for="id_user_pp"><strong>Pejabat Pengadaan / PP (Terlibat untuk Nilai &gt; Rp 50 Juta s.d. Rp 200 Juta):</strong></label><br>
                <select id="id_user_pp" name="id_user_pp">
                    @foreach($ppUsers as $pp)
                        <option value="{{ $pp->id_user }}">{{ $pp->nama }} (Pejabat Pengadaan Aktif)</option>
                    @endforeach
                </select>
            </p>

            <p>
                <button type="submit"><strong>Daftarkan Paket &amp; Tetapkan Metode Pengadaan</strong></button>
                &nbsp;
                <a href="{{ route('paket.index') }}">Batal dan Kembali</a>
            </p>
        </fieldset>
    </form>
</section>
@endsection
