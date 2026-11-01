@extends('layouts.tes_app')

@section('title', 'Informasi Dokumen Pembayaran & Pencairan Dana - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<article>
    <header>
        <h2>Alur Pengajuan, Persetujuan Memo, &amp; Pencairan Pembayaran</h2>
        <p>
            Pengelolaan <strong>Informasi Dokumen Pembayaran</strong> pengadaan barang dan jasa Politeknik Negeri Jakarta
            (mencakup dokumen perikatan tunggal <strong>SPK / Surat Pesanan / Surat Kontrak</strong> untuk paket &gt; Rp 50 Juta,
            ditambah <strong>Kwitansi</strong> dan <strong>Invoice</strong>), dilanjutkan persetujuan Memo Bayar berjenjang oleh PPK dan Wadir II,
            penerbitan Surat Perintah Pembayaran (SPP) oleh PPBJ, serta pencairan dana SP2D oleh Bagian Keuangan.
        </p>
    </header>

    {{-- 1. FORMULIR PENGAJUAN INFORMASI DOKUMEN PEMBAYARAN OLEH KOORDINATOR PBJ --}}
    @if(Auth::user()->role === 'ppbj')
    <section>
        <form action="{{ route('pembayaran.pengajuan') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend><strong>1. Pengajuan Berkas &amp; Informasi Dokumen Pembayaran (Koordinator PBJ)</strong></legend>
                <p>
                    <label for="nomor_paket"><strong>Pilih Paket Pengadaan (BAST Perlengkapan Diterima):</strong></label><br>
                    <select id="nomor_paket" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Hasil BAST Perlengkapan --</option>
                        @foreach($bastSiapBayar as $b)
                            @php
                                $dokPerikatan = $b->paket?->kontrakSpk;
                                $infoPerikatan = $dokPerikatan
                                    ? strtoupper(str_replace('_', ' ', $dokPerikatan->tipe_dokumen)) . ': ' . $dokPerikatan->nomor_spk
                                    : 'Belanja Langsung Tanpa Kontrak (Kwitansi: ' . ($b->paket?->nomor_kwitansi_belanja ?? '-') . ')';
                            @endphp
                            <option value="{{ $b->nomor_paket }}">
                                [BAST: {{ $b->nomor_bast }}] Paket: {{ $b->nomor_paket }} &bull; {{ $infoPerikatan }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_pembayaran"><strong>Nomor Pengajuan Pembayaran / SPP:</strong></label><br>
                    <input type="text" id="nomor_pembayaran" name="nomor_pembayaran" value="SPP/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="tahap_termin"><strong>Tahap Pembayaran / Termin:</strong></label><br>
                    <input type="number" id="tahap_termin" name="tahap_termin" value="1" min="1" max="12" required>
                </p>

                <p>
                    <label for="nominal_pengajuan"><strong>Nominal Tagihan yang Diajukan (Rp):</strong></label><br>
                    <input type="number" id="nominal_pengajuan" name="nominal_pengajuan" step="0.01" min="1" required placeholder="Contoh: 150000000">
                </p>

                <p>
                    <label for="tanggal_pengajuan"><strong>Tanggal Pengajuan Berkas:</strong></label><br>
                    <input type="date" id="tanggal_pengajuan" name="tanggal_pengajuan" value="{{ date('Y-m-d') }}" required>
                </p>

                <p>
                    <label for="jatuh_tempo"><strong>Batas Jatuh Tempo Termin:</strong></label><br>
                    <input type="date" id="jatuh_tempo" name="jatuh_tempo" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                </p>

                <fieldset>
                    <legend>Kelengkapan Informasi Dokumen Pembayaran (SPK / Surat Pesanan / Surat Kontrak + Kwitansi + Invoice)</legend>
                    <p>
                        <small><em>Dokumen perikatan tunggal (SPK / Surat Pesanan / Surat Kontrak) otomatis ditautkan dari paket pengadaan; untuk Belanja Langsung (&le; 50 juta), Kwitansi belanja langsung diwariskan otomatis bila tidak diunggah ulang.</em></small>
                    </p>
                    <p>
                        <label for="nomor_kwitansi"><strong>Nomor Kwitansi Pembayaran:</strong></label><br>
                        <input type="text" id="nomor_kwitansi" name="nomor_kwitansi" value="KWT/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" size="40">
                    </p>
                    <p>
                        <label for="file_kwitansi"><strong>Unggah Berkas Kwitansi (PDF / Gambar, Maks 10MB):</strong></label><br>
                        <input type="file" id="file_kwitansi" name="file_kwitansi" accept=".pdf,image/*">
                    </p>
                    <p>
                        <label for="nomor_invoice"><strong>Nomor Invoice Tagihan Penyedia:</strong></label><br>
                        <input type="text" id="nomor_invoice" name="nomor_invoice" value="INV/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" size="40">
                    </p>
                    <p>
                        <label for="file_invoice"><strong>Unggah Berkas Invoice (PDF / Gambar, Maks 10MB):</strong></label><br>
                        <input type="file" id="file_invoice" name="file_invoice" accept=".pdf,image/*">
                    </p>
                </fieldset>

                <p>
                    <label for="nomor_memo"><strong>Nomor Memo Pembayaran Internal:</strong></label><br>
                    <input type="text" id="nomor_memo" name="nomor_memo" value="MEMO/BHP/{{ date('Y') }}/{{ rand(10, 99) }}">
                </p>

                <p>
                    <label for="file_dokumen_penunjang"><strong>Unggah Dokumen Penunjang Tambahan (PDF/Gambar, Opsional):</strong></label><br>
                    <input type="file" id="file_dokumen_penunjang" name="file_dokumen_penunjang" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="file_memo"><strong>Unggah Berkas Fisik Memo Pembayaran (PDF, Opsional):</strong></label><br>
                    <input type="file" id="file_memo" name="file_memo" accept=".pdf">
                </p>

                <p>
                    <button type="submit"><strong>Kirim Informasi Dokumen Pembayaran &amp; Memo ke PPK serta Wadir II</strong></button>
                </p>
            </fieldset>
        </form>
    </section>
    <hr>
    @endif

    {{-- RINGKASAN INFORMASI DOKUMEN PEMBAYARAN (UNTUK SELURUH PERAN PEMBAYARAN) --}}
    <section>
        <fieldset>
            <legend><strong>Ringkasan Informasi Dokumen Pembayaran Aktif (SPK / Surat Pesanan / Surat Kontrak + Kwitansi + Invoice)</strong></legend>
            @if($pembayaranList->isEmpty())
                <p><em>Belum ada berkas pengajuan pembayaran yang terdaftar.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor SPP</th>
                            <th>Paket Pengadaan</th>
                            <th>Dokumen Perikatan Terpilih</th>
                            <th>Kwitansi</th>
                            <th>Invoice</th>
                            <th>Nominal Tagihan</th>
                            <th>Status Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pembayaranList as $pb)
                            @php
                                $infoDok = $pb->informasi_dokumen_pembayaran;
                            @endphp
                            <tr>
                                <td><strong>{{ $pb->nomor_pembayaran }}</strong></td>
                                <td>{{ $pb->nomor_paket }}</td>
                                <td>
                                    @if(($infoDok['dokumen_perikatan']['jenis'] ?? '') === 'tanpa_kontrak_belanja_langsung')
                                        <em>Tanpa Kontrak (Belanja Langsung &le; 50 Juta)</em>
                                    @else
                                        <strong>{{ strtoupper(str_replace('_', ' ', $infoDok['dokumen_perikatan']['jenis'] ?? '-')) }}</strong>:
                                        {{ $infoDok['dokumen_perikatan']['nomor'] ?? '-' }}
                                    @endif
                                </td>
                                <td>{{ $infoDok['kwitansi']['nomor_kwitansi'] ?? '-' }}</td>
                                <td>{{ $infoDok['invoice']['nomor_invoice'] ?? '-' }}</td>
                                <td>Rp {{ number_format($pb->nominal_pengajuan, 0, ',', '.') }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $pb->status_pembayaran)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>
    <hr>

    {{-- 2. PANEL PERSETUJUAN MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR II) --}}
    @if(in_array(Auth::user()->role, ['ppk', 'wadir_2']))
    <section>
        <fieldset>
            <legend><strong>2. Peninjauan &amp; Persetujuan Memo Pembayaran ({{ strtoupper(str_replace('_', ' ', Auth::user()->role)) }})</strong></legend>

            @if($memoMenungguReview->isEmpty())
                <p><em>Saat ini tidak ada disposisi memo pembayaran yang menunggu persetujuan Anda.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor SPP</th>
                            <th>Paket Pengadaan</th>
                            <th>Dokumen (Perikatan / Kwitansi / Invoice)</th>
                            <th>Tingkat Verifikasi</th>
                            <th>Nominal Tagihan</th>
                            <th>Keputusan &amp; Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memoMenungguReview as $m)
                            <tr>
                                <td>{{ $m->nomor_pembayaran }}</td>
                                <td>{{ $m->pembayaran->nomor_paket ?? '-' }}</td>
                                <td>
                                    Perikatan: {{ $m->pembayaran->paket->kontrakSpk->nomor_spk ?? 'Belanja Langsung' }}<br>
                                    Kwitansi: {{ $m->pembayaran->nomor_kwitansi ?? '-' }} &bull; Invoice: {{ $m->pembayaran->nomor_invoice ?? '-' }}
                                </td>
                                <td>Tingkat {{ $m->urutan_level }} ({{ strtoupper($m->role_approver) }})</td>
                                <td>Rp {{ number_format($m->pembayaran->nominal_pengajuan ?? 0, 0, ',', '.') }}</td>
                                <td>
                                    <form action="{{ route('pembayaran.memo.approve') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="id_memo_bayar" value="{{ $m->id }}">
                                        <input type="text" name="catatan_memo" placeholder="Catatan persetujuan (opsional)" size="30">
                                        <button type="submit" name="status_memo" value="disetujui">Setujui Memo</button>
                                        <button type="submit" name="status_memo" value="ditolak">Tolak Memo</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>
    <hr>
    @endif

    {{-- 3. REVIU AKHIR & PENERBITAN SURAT PERINTAH PEMBAYARAN (PPBJ) --}}
    @if(Auth::user()->role === 'ppbj')
    <section>
        <fieldset>
            <legend><strong>3. Penerbitan Surat Perintah Pembayaran (SPP) ke Bagian Keuangan (Koordinator PBJ)</strong></legend>

            @if($siapTerbitkanSpp->isEmpty())
                <p><em>Tidak ada berkas pembayaran yang telah selesai disetujui lengkap oleh PPK dan Wadir II.</em></p>
            @else
                <p>Berkas pembayaran berikut telah memperoleh persetujuan lengkap dari PPK dan Wadir II. Terbitkan SPP agar dapat dicairkan oleh Keuangan:</p>
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor SPP</th>
                            <th>Paket Pengadaan</th>
                            <th>Kwitansi &amp; Invoice</th>
                            <th>Nominal Tagihan</th>
                            <th>Persetujuan Memo</th>
                            <th>Aksi Penerbitan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($siapTerbitkanSpp as $spp)
                            <tr>
                                <td>{{ $spp->nomor_pembayaran }}</td>
                                <td>{{ $spp->nomor_paket }}</td>
                                <td>{{ $spp->nomor_kwitansi ?? '-' }} / {{ $spp->nomor_invoice ?? '-' }}</td>
                                <td>Rp {{ number_format($spp->nominal_pengajuan, 0, ',', '.') }}</td>
                                <td>Lengkap Disetujui (PPK &amp; Wadir II)</td>
                                <td>
                                    <form action="{{ route('pembayaran.terbitkan-spp') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="nomor_pembayaran" value="{{ $spp->nomor_pembayaran }}">
                                        <input type="text" name="catatan_reviu_akhir" placeholder="Catatan verifikasi akhir" size="25">
                                        <button type="submit">Terbitkan SPP ke Keuangan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>
    <hr>
    @endif

    {{-- 4. FORMULIR PENCAIRAN DANA SP2D OLEH BAGIAN KEUANGAN --}}
    @if(Auth::user()->role === 'keuangan')
    <section>
        <form action="{{ route('pembayaran.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend><strong>4. Eksekusi Realisasi Pencairan Dana SP2D (Bagian Keuangan)</strong></legend>

                <p>
                    <label for="nomor_pembayaran_cair"><strong>Pilih Tagihan SPP Siap Cair:</strong></label><br>
                    <select id="nomor_pembayaran_cair" name="nomor_pembayaran" required>
                        <option value="">-- Pilih Berkas SPP Resmi --</option>
                        @foreach($siapCairKeuangan as $sc)
                            <option value="{{ $sc->nomor_pembayaran }}">
                                [{{ $sc->nomor_pembayaran }}] Paket: {{ $sc->nomor_paket }} &bull; Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }} &bull; Kwitansi: {{ $sc->nomor_kwitansi ?? '-' }} &bull; Invoice: {{ $sc->nomor_invoice ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_transaksi"><strong>Nomor SP2D / Mutasi Kas Bank:</strong></label><br>
                    <input type="text" id="nomor_transaksi" name="nomor_transaksi" value="SP2D/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="tanggal_cair"><strong>Tanggal Pencairan / Realisasi Transfer:</strong></label><br>
                    <input type="date" id="tanggal_cair" name="tanggal_cair" value="{{ date('Y-m-d') }}" required>
                </p>

                <p>
                    <label for="nominal_cair"><strong>Nominal Pencairan Dana (Rp):</strong></label><br>
                    <input type="number" id="nominal_cair" name="nominal_cair" step="0.01" min="1" required placeholder="Contoh: 150000000">
                </p>

                <p>
                    <label for="bank_tujuan"><strong>Bank Penyalur &amp; Rekening Rekanan Penerima:</strong></label><br>
                    <input type="text" id="bank_tujuan" name="bank_tujuan" size="50" required placeholder="Contoh: Bank Mandiri KCP Kampus UI - Rek. 1570001234567 a.n PT Rekanan">
                </p>

                <p>
                    <label for="file_bukti_transfer"><strong>Unggah Slip Bukti Transfer Bank (PDF/Gambar, Maks 10MB):</strong></label><br>
                    <input type="file" id="file_bukti_transfer" name="file_bukti_transfer" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="keterangan_cair"><strong>Catatan Pembukuan Keuangan:</strong></label><br>
                    <textarea id="keterangan_cair" name="keterangan_cair" rows="2" cols="60" placeholder="Pencairan dana SP2D pengadaan kampus PNJ sesuai dokumen pembayaran."></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Eksekusi Pencairan SP2D &amp; Selesaikan Siklus Pengadaan</strong></button>
                </p>
            </fieldset>
        </form>
    </section>
    <hr>
    @endif

    {{-- 5. RIWAYAT SELURUH TRANSAKSI PEMBAYARAN DAN PENCAIRAN DANA --}}
    <section>
        <fieldset>
            <legend><strong>Riwayat Transaksi Pencairan Dana SP2D Politeknik Negeri Jakarta</strong></legend>

            @if($transaksiList->isEmpty())
                <p><em>Belum ada transaksi pencairan dana SP2D yang tercatat.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor SP2D / Mutasi Bank</th>
                            <th>Nomor SPP</th>
                            <th>Tanggal Transfer</th>
                            <th>Nominal Transfer</th>
                            <th>Petugas Keuangan</th>
                            <th>Salinan Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaksiList as $t)
                            <tr>
                                <td>{{ $t->nomor_transaksi_bank }}</td>
                                <td>{{ $t->nomor_pembayaran }}</td>
                                <td>{{ $t->tanggal_transfer ? $t->tanggal_transfer->format('d/m/Y') : '-' }}</td>
                                <td>Rp {{ number_format($t->nominal_transfer, 0, ',', '.') }}</td>
                                <td>{{ $t->user->nama ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'transfer', 'id' => $t->nomor_transaksi_bank]) }}">
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
</article>
@endsection
