@extends('layouts.app')

@section('title', 'Alur Pembayaran & Pencairan Dana - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<article>
    <header>
        <h2>Alur Pengajuan, Persetujuan Memo, & Pencairan Pembayaran</h2>
        <p>
            Pengelolaan tahapan pembayaran tagihan pengadaan barang dan jasa Politeknik Negeri Jakarta:
            pengajuan berkas oleh Koordinator PBJ, peninjauan memo bertingkat oleh PPK dan Wadir II,
            penerbitan Surat Perintah Pembayaran (SPP), serta realisasi pencairan dana SP2D oleh Bagian Keuangan.
        </p>
    </header>

    {{-- 1. FORMULIR PENGAJUAN BERKAS PEMBAYARAN OLEH KOORDINATOR PBJ --}}
    @if(Auth::user()->role === 'ppbj')
    <section>
        <form action="{{ route('pembayaran.pengajuan') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend><strong>1. Pengajuan Berkas Tagihan Pembayaran & Memo Internal (Koordinator PBJ)</strong></legend>
                <p>
                    <label for="nomor_paket"><strong>Pilih Paket Pengadaan (BAST Diterima):</strong></label><br>
                    <select id="nomor_paket" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Hasil Penerimaan Barang --</option>
                        @foreach($bastSiapBayar as $b)
                            <option value="{{ $b->nomor_paket }}">
                                [{{ $b->nomor_bast }}] Paket: {{ $b->nomor_paket }} &bull; Tgl BAST: {{ $b->tanggal_penerimaan ? $b->tanggal_penerimaan->format('d/m/Y') : '-' }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_pembayaran"><strong>Nomor Surat Permintaan Pembayaran (SPP):</strong></label><br>
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

                <p>
                    <label for="nomor_memo"><strong>Nomor Memo Pembayaran Internal:</strong></label><br>
                    <input type="text" id="nomor_memo" name="nomor_memo" value="MEMO/BHP/{{ date('Y') }}/{{ rand(10, 99) }}">
                </p>

                <p>
                    <label for="file_tagihan_vendor"><strong>Unggah Invoice Rekanan / Kuitansi Tagihan (PDF/Gambar, Maks 10MB):</strong></label><br>
                    <input type="file" id="file_tagihan_vendor" name="file_tagihan_vendor" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="file_dokumen_penunjang"><strong>Unggah Resume Kontrak / Dokumen Penunjang (PDF/Gambar, Maks 10MB):</strong></label><br>
                    <input type="file" id="file_dokumen_penunjang" name="file_dokumen_penunjang" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="file_memo"><strong>Unggah Berkas Fisik Memo Pembayaran BHP/TIK (PDF, Maks 10MB):</strong></label><br>
                    <input type="file" id="file_memo" name="file_memo" accept=".pdf">
                </p>

                <p>
                    <button type="submit">Kirim Berkas Pengajuan Pembayaran ke PPK & Wadir II</button>
                </p>
            </fieldset>
        </form>
    </section>
    <hr>
    @endif

    {{-- 2. PANEL PERSETUJUAN MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR II) --}}
    @if(in_array(Auth::user()->role, ['ppk', 'wadir_2']))
    <section>
        <fieldset>
            <legend><strong>2. Peninjauan & Persetujuan Memo Pembayaran ({{ strtoupper(str_replace('_', ' ', Auth::user()->role)) }})</strong></legend>

            @if($memoMenungguReview->isEmpty())
                <p><em>Saat ini tidak ada disposisi memo pembayaran yang menunggu persetujuan Anda.</em></p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Nomor SPP</th>
                            <th>Paket Pengadaan</th>
                            <th>Tingkat Verifikasi</th>
                            <th>Nominal Tagihan</th>
                            <th>Keputusan & Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memoMenungguReview as $m)
                            <tr>
                                <td>{{ $m->nomor_pembayaran }}</td>
                                <td>{{ $m->pembayaran->nomor_paket ?? '-' }}</td>
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
                <table>
                    <thead>
                        <tr>
                            <th>Nomor SPP</th>
                            <th>Paket Pengadaan</th>
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
                                <td>Rp {{ number_format($spp->nominal_pengajuan, 0, ',', '.') }}</td>
                                <td>Lengkap Disetujui (PPK & Wadir II)</td>
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
                                [{{ $sc->nomor_pembayaran }}] Paket: {{ $sc->nomor_paket }} &bull; Rp {{ number_format($sc->nominal_pengajuan, 0, ',', '.') }} &bull; Vendor: {{ $sc->paket->kontrak->vendor->nama_perusahaan ?? '-' }}
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
                    <label for="bank_tujuan"><strong>Bank Penyalur & Rekening Rekanan Penerima:</strong></label><br>
                    <input type="text" id="bank_tujuan" name="bank_tujuan" size="50" required placeholder="Contoh: Bank Mandiri KCP Kampus UI - Rek. 1570001234567 a.n PT Rekanan">
                </p>

                <p>
                    <label for="file_bukti_transfer"><strong>Unggah Slip Bukti Transfer Bank (PDF/Gambar, Maks 10MB):</strong></label><br>
                    <input type="file" id="file_bukti_transfer" name="file_bukti_transfer" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="keterangan_cair"><strong>Catatan Pembukuan Keuangan:</strong></label><br>
                    <textarea id="keterangan_cair" name="keterangan_cair" rows="2" cols="60" placeholder="Pencairan dana SP2D pengadaan kampus PNJ sesuai perikatan SPK."></textarea>
                </p>

                <p>
                    <button type="submit">Eksekusi Pencairan SP2D & Selesaikan Siklus Pengadaan</button>
                </p>
            </fieldset>
        </form>
    </section>
    <hr>
    @endif

    {{-- ===================================================================== --}}
    {{-- 5. RIWAYAT SELURUH TRANSAKSI PEMBAYARAN DAN PENCAIRAN DANA --}}
    {{-- ===================================================================== --}}
    <section>
        <fieldset>
            <legend><strong>Riwayat Transaksi Pencairan Dana SP2D Politeknik Negeri Jakarta</strong></legend>

            @if($transaksiList->isEmpty())
                <p><em>Belum ada transaksi pencairan dana SP2D yang tercatat.</em></p>
            @else
                <table>
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
