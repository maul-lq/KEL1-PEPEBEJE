@extends('layouts.tes_app')

@section('title', 'Manajemen Paket, Belanja Langsung, & Alih Tugas PPK - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Manajemen Paket Pengadaan, Belanja Langsung (&le; 50 Juta), &amp; Alih Penugasan PPK</h2>
    <p>
        Halaman ini menampilkan seluruh paket pengadaan Politeknik Negeri Jakarta beserta metode pengadaan yang dipilih,
        eksekusi <strong>Belanja Langsung (&le; Rp 50.000.000)</strong> dengan output <strong>Kwitansi</strong> (tanpa Kontrak/SPK),
        pemecahan berkas berdasarkan jenis pengadaan, serta alih penugasan PPK.
    </p>

    @if(Auth::user()->role === 'ppbj')
        <p>
            <a href="{{ route('paket.create') }}"><button type="button"><strong>+ Registrasi Paket &amp; Pilih Metode Pengadaan Baru</strong></button></a>
        </p>
    @endif

    {{-- 1. Tabel Daftar Paket Pengadaan --}}
    <fieldset>
        <legend><strong>Daftar Paket Pengadaan Aktif</strong></legend>

        @if($paketList->isEmpty())
            <p><em>Belum ada paket pengadaan yang terdaftar.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode Paket</th>
                        <th>Nama Paket</th>
                        <th>Jenis &amp; Metode Pengadaan</th>
                        <th>Harga Perkiraan / HPS</th>
                        <th>Harga Akhir &amp; Vendor</th>
                        <th>Jalur Eksekusi</th>
                        <th>PP / PPK</th>
                        <th>Kwitansi / Pelaksana Belanja</th>
                        <th>Status Paket</th>
                        <th>Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paketList as $pkt)
                        <tr>
                            <td><strong>{{ $pkt->kode_paket }}</strong></td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td>{{ $pkt->jenis_pengadaan }} &bull; <strong>{{ strtoupper(str_replace('_', ' ', $pkt->metode_pengadaan)) }}</strong></td>
                            <td>Rp {{ number_format($pkt->nominal_harga_perkiraan ?? $pkt->nilai_hps, 0, ',', '.') }}</td>
                            <td>
                                @if($pkt->nominal_harga_akhir)
                                    <strong>Rp {{ number_format($pkt->nominal_harga_akhir, 0, ',', '.') }}</strong><br>
                                @endif
                                <small>{{ $pkt->vendor->nama_vendor ?? ($pkt->npwp_vendor ?? '-') }}</small>
                            </td>
                            <td><u>{{ strtoupper(str_replace('_', ' ', $pkt->jalur_routing)) }}</u></td>
                            <td>
                                PP: {{ $pkt->ppUser->nama ?? '-' }}<br>
                                PPK: <strong>{{ $pkt->ppkUser->nama ?? '-' }}</strong>
                            </td>
                            <td>
                                @if($pkt->nomor_kwitansi_belanja)
                                    Kwitansi: <strong>{{ $pkt->nomor_kwitansi_belanja }}</strong><br>
                                @endif
                                <small>Belanja oleh: {{ $pkt->pelaksanaBelanja->nama ?? '-' }}</small>
                            </td>
                            <td>{{ strtoupper(str_replace('_', ' ', $pkt->status_paket)) }}</td>
                            <td>
                                @if($pkt->jalur_routing === 'langsung_sd_50jt')
                                    <small>Belanja Langsung (Kwitansi)</small>
                                @elseif(in_array(Auth::user()->role, ['pp', 'ppk', 'ppbj', 'admin']))
                                    <a href="{{ route('kontrak.index') }}">
                                        <button type="button">Review / Approval / Dokumen</button>
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>

    @if(Auth::user()->role === 'ppbj')
        <hr>

        {{-- 1A. Eksekusi Belanja Langsung <= Rp 50 Juta (Output Kwitansi, Tanpa Kontrak/SPK) --}}
        <fieldset>
            <legend><strong>Eksekusi Belanja Langsung (&le; Rp 50.000.000 &mdash; Output Kwitansi, Tanpa Kontrak / SPK)</strong></legend>
            <p>
                Pengadaan dengan nilai sampai dengan Rp 50.000.000 merupakan <strong>Belanja Langsung</strong> oleh PPBJ.
                Outputnya berupa <strong>Kwitansi</strong> (tidak menerbitkan Kontrak, SPK, maupun Surat Pesanan), lalu diteruskan ke Unit Perlengkapan untuk BAST.
            </p>

            <form action="{{ route('paket.belanja-langsung') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <p>
                    <label for="nomor_paket_bl"><strong>Pilih Paket Belanja Langsung (&le; Rp 50 Juta):</strong></label><br>
                    <select id="nomor_paket_bl" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Belanja Langsung --</option>
                        @foreach($paketList->where('jalur_routing', 'langsung_sd_50jt') as $pktBl)
                            <option value="{{ $pktBl->nomor_paket }}">
                                [{{ $pktBl->nomor_paket }}] {{ $pktBl->nama_paket }} &bull; HPS: Rp {{ number_format($pktBl->nilai_hps, 0, ',', '.') }} ({{ $pktBl->status_paket }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_kwitansi_belanja"><strong>Nomor Kwitansi Belanja Langsung:</strong></label><br>
                    <input type="text" id="nomor_kwitansi_belanja" name="nomor_kwitansi_belanja" value="KWT-BL/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="nominal_harga_akhir_bl"><strong>Nominal Realisasi Kwitansi / Harga Akhir (Rp):</strong></label><br>
                    <input type="number" id="nominal_harga_akhir_bl" name="nominal_harga_akhir" step="0.01" min="1" placeholder="Contoh: 45000000">
                </p>

                <p>
                    <label for="npwp_vendor_bl"><strong>Penyedia / Toko Rekanan (Opsional):</strong></label><br>
                    <select id="npwp_vendor_bl" name="npwp_vendor">
                        <option value="">-- Pilih Vendor / Rekanan --</option>
                        @foreach($vendors ?? [] as $v)
                            <option value="{{ $v->npwp }}">{{ $v->nama_vendor }} (NPWP: {{ $v->npwp }})</option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="jadwal_pengiriman_bl"><strong>Target Pengiriman Barang / Pengingat Reminder (Opsional):</strong></label><br>
                    <input type="date" id="jadwal_pengiriman_bl" name="jadwal_pengiriman" value="{{ date('Y-m-d', strtotime('+7 days')) }}">
                </p>

                <p>
                    <label for="file_kwitansi_belanja"><strong>Unggah Berkas Kwitansi Belanja Langsung (PDF / Gambar):</strong></label><br>
                    <input type="file" id="file_kwitansi_belanja" name="file_kwitansi_belanja" accept=".pdf,image/*">
                </p>

                <p>
                    <button type="submit"><strong>Simpan Kwitansi Belanja Langsung &amp; Teruskan ke Perlengkapan</strong></button>
                </p>
            </form>
        </fieldset>

        <hr>

        {{-- 1B. Formulir Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan oleh PPBJ --}}
        <fieldset>
            <legend><strong>Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan Barang / Jasa / Konstruksi</strong></legend>
            <p>
                Jika satu usulan pengadaan yang telah disetujui menggabungkan lebih dari satu jenis pengadaan,
                PPBJ dapat memecah berkas tersebut sebelum diregistrasikan menjadi paket pengadaan terpisah.
            </p>

            <form action="{{ route('paket.split-jenis') }}" method="POST">
                @csrf
                <p>
                    <label for="nomor_surat_induk_ppbj"><strong>Pilih Usulan Disetujui yang Akan Dipecah:</strong></label><br>
                    <select id="nomor_surat_induk_ppbj" name="nomor_surat_induk" required>
                        <option value="">-- Pilih Surat Permohonan Disetujui --</option>
                        @foreach($permohonanDisetujui ?? [] as $p)
                            <option value="{{ $p->nomor_surat }}">
                                [{{ $p->nomor_surat }}] {{ $p->perihal }} (Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_surat_baru_ppbj"><strong>Nomor Surat Pecahan Baru:</strong></label><br>
                    <input type="text" id="nomor_surat_baru_ppbj" name="nomor_surat_baru" value="B/SPLIT-PBJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="judul_pengadaan_ppbj"><strong>Judul / Item Pengadaan Pecahan:</strong></label><br>
                    <input type="text" id="judul_pengadaan_ppbj" name="judul_pengadaan" placeholder="Contoh: Pekerjaan Instalasi Jaringan Lab TIK" required size="50">
                </p>

                <p>
                    <label for="jenis_pengadaan_split"><strong>Jenis Pengadaan Pecahan:</strong></label><br>
                    <select id="jenis_pengadaan_split" name="jenis_pengadaan_split" required>
                        <option value="barang">Pengadaan Barang</option>
                        <option value="jasa">Pengadaan Jasa Lainnya / Konsultansi</option>
                        <option value="konstruksi">Pekerjaan Konstruksi</option>
                    </select>
                </p>

                <p>
                    <label for="estimasi_total_pagu_ppbj"><strong>Alokasi Pagu Pecahan (Rp):</strong></label><br>
                    <input type="number" id="estimasi_total_pagu_ppbj" name="estimasi_total_pagu" min="1" step="0.01" required placeholder="Contoh: 45000000">
                </p>

                <p>
                    <label for="alasan_pemecahan_ppbj"><strong>Alasan Pemecahan Jenis Pengadaan:</strong></label><br>
                    <textarea id="alasan_pemecahan_ppbj" name="alasan_pemecahan" rows="2" cols="60" required placeholder="Contoh: Pemisahan paket pengadaan barang perangkat keras dan jasa instalasi konstruksi jaringan."></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Proses Pemecahan Berkas Berdasarkan Jenis Pengadaan</strong></button>
                </p>
            </form>
        </fieldset>

        <hr>

        {{-- 2. Formulir Alih Tugas (Reassign) PPK --}}
        <fieldset>
            <legend><strong>Alih Tugas (Reassign) Pejabat Pembuat Komitmen (PPK)</strong></legend>
            <p>
                Meskipun PPK awal telah dipilih oleh Perencanaan, Koordinator PPBJ tetap dapat mengalihkan penugasan paket
                ke salah satu dari 4 akun PPK lainnya apabila terdapat penyesuaian bidang atau pemerataan beban kerja.
            </p>

            <form action="{{ route('paket.reassign-ppk') }}" method="POST">
                @csrf
                <p>
                    <label for="kode_paket_reassign"><strong>Pilih Paket Pengadaan:</strong></label><br>
                    <select id="kode_paket_reassign" name="kode_paket" required>
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketList as $pktPpk)
                            <option value="{{ $pktPpk->kode_paket }}">
                                [{{ $pktPpk->kode_paket }}] {{ $pktPpk->nama_paket }} &bull; PPK Saat Ini: {{ $pktPpk->ppkUser->nama ?? 'Belum Ditugaskan' }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="id_user_ppk_baru"><strong>Alihkan ke PPK Baru:</strong></label><br>
                    <select id="id_user_ppk_baru" name="id_user_ppk_baru" required>
                        <option value="">-- Pilih PPK Penerima Tugas --</option>
                        @foreach($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}">{{ $ppk->nama }} ({{ $ppk->jabatan }})</option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="alasan_pengalihan"><strong>Alasan Resmi Alih Tugas / Mutasi PPK:</strong></label><br>
                    <textarea id="alasan_pengalihan" name="alasan_pengalihan" rows="2" cols="60" required placeholder="Contoh: Pemerataan beban kerja PPK dan penyesuaian bidang paket."></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Simpan &amp; Eksekusi Alih Tugas PPK</strong></button>
                </p>
            </form>
        </fieldset>
    @endif

    <hr>

    {{-- 3. Tabel Riwayat Penugasan PPK --}}
    <fieldset>
        <legend><strong>Jejak Riwayat Mutasi Penugasan PPK</strong></legend>
        @if($riwayatPenugasan->isEmpty())
            <p><em>Belum ada riwayat alih tugas PPK yang tercatat.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Waktu Mutasi</th>
                        <th>Kode Paket</th>
                        <th>PPK Sebelumnya</th>
                        <th>PPK Baru</th>
                        <th>Alasan Pengalihan</th>
                        <th>Ditetapkan Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riwayatPenugasan as $rw)
                        <tr>
                            <td>{{ date('d-m-Y H:i', strtotime($rw->created_at)) }}</td>
                            <td>{{ $rw->kode_paket }}</td>
                            <td>{{ $rw->ppkLama->nama ?? '-' }}</td>
                            <td><strong>{{ $rw->ppkBaru->nama ?? '-' }}</strong></td>
                            <td>{{ $rw->alasan_pengalihan }}</td>
                            <td>{{ $rw->userPenetap->nama ?? 'Sistem' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>
</section>
@endsection
