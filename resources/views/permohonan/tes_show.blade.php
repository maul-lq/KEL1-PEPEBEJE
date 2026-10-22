@extends('layouts.tes_app')

@section('title', 'Detail & Lacak Usulan Pengadaan - SIM-PBJ PNJ')

@section('content')
<article>
    <header>
        <h2>Detail Dokumen Usulan & Pelacakan Siklus Pengadaan</h2>
        <p>
            Nomor Surat Srikandi: <strong>{{ $permohonan->nomor_surat }}</strong> &bull;
            Status Terkini: <strong>{{ strtoupper(str_replace('_', ' ', $permohonan->status_permohonan ?? $permohonan->status)) }}</strong>
        </p>
        <p>
            <a href="{{ route('permohonan.index') }}"><button type="button">&larr; Kembali ke Daftar Permohonan</button></a>
            <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $permohonan->nomor_surat]) }}" target="_blank">
                <button type="button"><strong>Unduh Dokumen PDF Resmi (< 3 Detik)</strong></button>
            </a>
        </p>
    </header>

    <hr>

    {{-- 1. INFORMASI DASAR DOKUMEN USULAN --}}
    <section>
        <h3>1. Informasi Dasar Usulan Pengadaan</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <tbody>
                <tr>
                    <th align="left">Nomor Surat Srikandi</th>
                    <td><strong>{{ $permohonan->nomor_surat }}</strong></td>
                    <th align="left">Tanggal Pengajuan</th>
                    <td>{{ $permohonan->tanggal_pengajuan ? $permohonan->tanggal_pengajuan->format('d/m/Y H:i') : '-' }}</td>
                </tr>
                <tr>
                    <th align="left">Unit Pengusul / Jurusan</th>
                    <td>{{ $permohonan->unit->nama_unit ?? $permohonan->kode_unit }} ({{ $permohonan->kode_unit }})</td>
                    <th align="left">Pegawai Pemohon</th>
                    <td>{{ $permohonan->user->nama ?? '-' }} (NIP: {{ $permohonan->user->nip ?? '-' }})</td>
                </tr>
                <tr>
                    <th align="left">Perihal / Judul Pengadaan</th>
                    <td colspan="3"><strong>{{ $permohonan->perihal }}</strong></td>
                </tr>
                <tr>
                    <th align="left">Tujuan & Justifikasi</th>
                    <td colspan="3">{{ $permohonan->tujuan_pengadaan_ringkas ?? '-' }}</td>
                </tr>
                <tr>
                    <th align="left">Estimasi Pagu Anggaran</th>
                    <td><strong>Rp {{ number_format($permohonan->estimasi_total_pagu, 0, ',', '.') }}</strong></td>
                    <th align="left">Mata Anggaran (MAK)</th>
                    <td>
                        @if($permohonan->kode_mak)
                            <strong>{{ $permohonan->kode_mak }}</strong> - {{ $permohonan->anggaranMak->nama_mak ?? 'MAK Ditetapkan' }}
                        @else
                            <em>Belum ditetapkan oleh Bagian Perencanaan</em>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th align="left">Berkas Kelengkapan</th>
                    <td colspan="3">
                        @if($permohonan->file_permohonan)
                            Surat Usulan: <code>{{ $permohonan->file_permohonan }}</code> &bull;
                        @endif
                        @if($permohonan->file_kak)
                            KAK: <code>{{ $permohonan->file_kak }}</code> &bull;
                        @endif
                        @if($permohonan->file_spesifikasi_teknis)
                            Spek Teknis: <code>{{ $permohonan->file_spesifikasi_teknis }}</code>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <br>

    {{-- 2. PELACAKAN 8 TAHAPAN SIKLUS PENGADAAN KAMPUS --}}
    <section>
        <h3>2. Status Pelacakan 8 Tahapan Siklus Pengadaan</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Tahap</th>
                    <th>Nama Siklus</th>
                    <th>Penanggung Jawab</th>
                    <th>Status</th>
                    <th>Keterangan Pelaksanaan</th>
                </tr>
            </thead>
            <tbody>
                {{-- Tahap 1: Pengajuan Usulan --}}
                <tr>
                    <td align="center">1</td>
                    <td><strong>Pengajuan Usulan Permohonan</strong></td>
                    <td>Unit / Jurusan ({{ $permohonan->kode_unit }})</td>
                    <td>[SELESAI]</td>
                    <td>Surat resmi Srikandi diajukan tanggal {{ $permohonan->tanggal_pengajuan ? $permohonan->tanggal_pengajuan->format('d/m/Y') : '-' }}</td>
                </tr>

                {{-- Tahap 2: Telaah Berkas Staf B2 --}}
                @php
                    $vpB2 = $permohonan->verifikasiParalel->where('peran_verifikator', 'staff_bidang_2')->first();
                @endphp
                <tr>
                    <td align="center">2</td>
                    <td><strong>Telaah Berkas & Agenda</strong></td>
                    <td>Staf Bidang 2 (Wadir II)</td>
                    <td>
                        @if($vpB2 && $vpB2->status_verifikasi === 'disetujui')
                            [SELESAI]
                        @elseif($vpB2 && $vpB2->status_verifikasi === 'ditolak')
                            [DITOLAK]
                        @else
                            [PROSES]
                        @endif
                    </td>
                    <td>
                        @if($vpB2)
                            {{ $vpB2->catatan ?? 'Telah ditelaah dan diteruskan ke Wadir II' }}
                        @else
                            Menunggu pemeriksaan kelengkapan berkas fisik / digital.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 3: Persetujuan Eksekutif Wadir 2 --}}
                @php
                    $vpWadir = $permohonan->verifikasiParalel->where('peran_verifikator', 'wadir_2')->first();
                @endphp
                <tr>
                    <td align="center">3</td>
                    <td><strong>Persetujuan Eksekutif (Disposisi)</strong></td>
                    <td>Wakil Direktur Bidang II</td>
                    <td>
                        @if($vpWadir && $vpWadir->status_verifikasi === 'disetujui')
                            [SELESAI / DISETUJUI]
                        @elseif($vpWadir && $vpWadir->status_verifikasi === 'ditolak')
                            [DITOLAK]
                        @else
                            [PROSES]
                        @endif
                    </td>
                    <td>
                        @if($vpWadir)
                            {{ $vpWadir->catatan ?? 'Disetujui Wadir II untuk diteruskan ke proses pengadaan' }}
                        @else
                            Menunggu persetujuan pimpinan eksekutif Wadir II.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 4: Verifikasi & Penetapan MAK Perencanaan --}}
                @php
                    $vpRenc = $permohonan->verifikasiParalel->where('peran_verifikator', 'perencanaan')->first();
                @endphp
                <tr>
                    <td align="center">4</td>
                    <td><strong>Verifikasi Ketersediaan MAK & Pagu</strong></td>
                    <td>Bagian Perencanaan</td>
                    <td>
                        @if($permohonan->kode_mak || ($vpRenc && $vpRenc->status_verifikasi === 'disetujui'))
                            [SELESAI / MAK TERKUNCI]
                        @elseif($vpRenc && $vpRenc->status_verifikasi === 'ditolak')
                            [DITOLAK / ANGGARAN TIDAK CUKUP]
                        @else
                            [PROSES]
                        @endif
                    </td>
                    <td>
                        @if($permohonan->kode_mak)
                            Dialokasikan pada MAK: <strong>{{ $permohonan->kode_mak }}</strong>
                        @else
                            Pemeriksaan ketersediaan pagu DIPA kampus.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 5: Pembentukan Paket & Routing PPBJ --}}
                @php
                    $paket = $permohonan->paket;
                @endphp
                <tr>
                    <td align="center">5</td>
                    <td><strong>Registrasi Paket & Penetapan PPK/PP</strong></td>
                    <td>Koordinator PPBJ</td>
                    <td>
                        @if($paket)
                            [SELESAI - {{ strtoupper($paket->status_paket ?? 'AKTIF') }}]
                        @else
                            [MENUNGGU PERSETUJUAN PARALEL]
                        @endif
                    </td>
                    <td>
                        @if($paket)
                            Kode Paket: <strong>{{ $paket->kode_paket }}</strong> &bull; HPS: Rp {{ number_format($paket->nilai_hps ?? $paket->pagu_anggaran, 0, ',', '.') }}
                            <br>Ditugaskan kepada: <strong>{{ $paket->ppkUser->nama ?? $paket->id_ppk }}</strong>
                        @else
                            Paket pengadaan akan dibentuk oleh PPBJ setelah tahap 1 s.d 4 disetujui.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 6: Kontrak SPK / Surat Pesanan --}}
                @php
                    $kontrak = $paket ? $paket->kontrak : null;
                @endphp
                <tr>
                    <td align="center">6</td>
                    <td><strong>Penerbitan Kontrak SPK / Pesanan</strong></td>
                    <td>PPBJ & Pejabat Pengadaan / PPK</td>
                    <td>
                        @if($kontrak)
                            [KONTRAK TERBIT]
                        @else
                            [MENUNGGU PEMILIHAN / REVIU]
                        @endif
                    </td>
                    <td>
                        @if($kontrak)
                            No. SPK: <strong>{{ $kontrak->nomor_spk }}</strong> &bull; Nilai Kontrak: Rp {{ number_format($kontrak->nilai_kontrak, 0, ',', '.') }}
                            <br>Penyedia/Vendor: <strong>{{ $kontrak->vendor->nama_vendor ?? $kontrak->id_vendor }}</strong>
                        @else
                            Penerbitan SPK tunggal oleh PPBJ setelah proses pengadaan selesai.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 7: Penerimaan Barang & BAST --}}
                @php
                    $bast = $kontrak ? $kontrak->penerimaan : null;
                @endphp
                <tr>
                    <td align="center">7</td>
                    <td><strong>Pemeriksaan Fisik & Penerbitan BAST</strong></td>
                    <td>Unit Perlengkapan / Tim BMN</td>
                    <td>
                        @if($bast)
                            [BAST TERBIT]
                        @else
                            [MENUNGGU PENGIRIMAN FISIK]
                        @endif
                    </td>
                    <td>
                        @if($bast)
                            No. BAST: <strong>{{ $bast->nomor_bast }}</strong> &bull; Kategori: <strong>{{ strtoupper(str_replace('_', ' ', $bast->kategori_belanja ?? 'BARANG')) }}</strong>
                            <br>Tanggal Penerimaan: {{ $bast->tanggal_penerimaan ? date('d/m/Y', strtotime($bast->tanggal_penerimaan)) : '-' }}
                        @else
                            Pemeriksaan kesesuaian fisik barang dan spesifikasi teknis KAK.
                        @endif
                    </td>
                </tr>

                {{-- Tahap 8: Verifikasi Tagihan SPP & SP2D --}}
                @php
                    $transaksi = $bast && $bast->memoBayar ? $bast->memoBayar->transaksiPencairan : null;
                @endphp
                <tr>
                    <td align="center">8</td>
                    <td><strong>Pembayaran SPP & Pencairan SP2D</strong></td>
                    <td>Bagian Keuangan / Bendahara</td>
                    <td>
                        @if($transaksi && $transaksi->status_transaksi === 'cair')
                            [DANA CAIR (SP2D)]
                        @elseif($transaksi)
                            [PROSES PENCAIRAN]
                        @else
                            [MENUNGGU BAST & SPP]
                        @endif
                    </td>
                    <td>
                        @if($transaksi)
                            No. SP2D: <strong>{{ $transaksi->nomor_sp2d ?? '-' }}</strong> &bull; Nilai Cair: Rp {{ number_format($transaksi->jumlah_bayar, 0, ',', '.') }}
                        @else
                            Proses penerbitan SPP oleh PPBJ dilanjutkan pencairan SP2D oleh Keuangan.
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <br>

    {{-- 3. CATATAN VERIFIKASI TIGA PIHAK (PARALEL) --}}
    <section>
        <h3>3. Catatan Lengkap Verifikasi Tiga Pihak (Paralel)</h3>
        @if($permohonan->verifikasiParalel->isEmpty())
            <p><em>Belum ada catatan verifikasi paralel yang tersimpan pada berkas ini.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Peran Pihak</th>
                        <th>Nama Verifikator</th>
                        <th>Keputusan Status</th>
                        <th>Tanggal Keputusan</th>
                        <th>Catatan / Alasan Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($permohonan->verifikasiParalel as $vp)
                        <tr>
                            <td><strong>{{ strtoupper(str_replace('_', ' ', $vp->peran_verifikator)) }}</strong></td>
                            <td>{{ $vp->user->nama ?? '-' }} (NIP: {{ $vp->user->nip ?? '-' }})</td>
                            <td>
                                <u>{{ strtoupper(str_replace('_', ' ', $vp->status_verifikasi)) }}</u>
                            </td>
                            <td>{{ $vp->tanggal_verifikasi ? $vp->tanggal_verifikasi->format('d/m/Y H:i') : '-' }}</td>
                            <td>{{ $vp->catatan ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <br>

    {{-- 4. POHON REVISI & PEMOHONAN PEMECAHAN (SPLIT) --}}
    <section>
        <h3>4. Informasi Revisi Dokumen & Pemecahan Usulan (Split)</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <tbody>
                <tr>
                    <th align="left">Status Revisi (Unary Tree)</th>
                    <td>
                        @if($permohonan->isRevisi())
                            Dokumen ini merupakan <strong>Revisi ke-{{ $permohonan->revisi_ke }}</strong> dari surat sebelumnya:
                            <a href="{{ route('permohonan.show', $permohonan->nomor_surat_revisi) }}">
                                <strong>{{ $permohonan->nomor_surat_revisi }}</strong>
                            </a>
                            <br><small>Alasan Revisi: {{ $permohonan->alasan_revisi ?? '-' }}</small>
                        @else
                            Surat usulan awal (Belum pernah direvisi).
                        @endif
                    </td>
                </tr>
                <tr>
                    <th align="left">Status Pemecahan Usulan (Split)</th>
                    <td>
                        @if($permohonan->isSplit())
                            Dokumen ini merupakan hasil pemecahan (split) dari surat induk:
                            <a href="{{ route('permohonan.show', $permohonan->nomor_surat_induk_split) }}">
                                <strong>{{ $permohonan->nomor_surat_induk_split }}</strong>
                            </a>
                            <br><small>Alasan Pemecahan: {{ $permohonan->alasan_pemecahan ?? '-' }} &bull; Jenis Split: {{ $permohonan->jenis_pengadaan_split ?? 'Perencanaan MAK' }}</small>
                        @else
                            Usulan mandiri (Bukan turunan split).
                        @endif

                        @if($permohonan->anakSplit && $permohonan->anakSplit->isNotEmpty())
                            <br><strong>Surat Anak Hasil Pemecahan:</strong>
                            <ul>
                                @foreach($permohonan->anakSplit as $anak)
                                    <li>
                                        <a href="{{ route('permohonan.show', $anak->nomor_surat) }}">
                                            <strong>{{ $anak->nomor_surat }}</strong>
                                        </a>
                                        - Pagu: Rp {{ number_format($anak->estimasi_total_pagu, 0, ',', '.') }} ({{ $anak->perihal }})
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Form Pengajuan Revisi Mandiri Jika Ditolak --}}
        @if(in_array($permohonan->status_permohonan, ['ditolak', 'revisi']) && (Auth::user()->hasRole('user') || Auth::user()->id_user === $permohonan->id_user))
            <br>
            <fieldset>
                <legend><strong>Ajukan Revisi Perbaikan Usulan Ini</strong></legend>
                <form action="{{ route('permohonan.revisi') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="nomor_surat_lama" value="{{ $permohonan->nomor_surat }}">
                    <p>
                        <label>Nomor Surat Revisi Baru:</label><br>
                        <input type="text" name="nomor_surat_baru" required placeholder="Contoh: B/{{ date('Ymd') }}.REV/PL3/TIK/{{ date('Y') }}">
                    </p>
                    <p>
                        <label>Perihal Pengadaan Baru:</label><br>
                        <input type="text" name="perihal" value="{{ $permohonan->perihal }}" required size="60">
                    </p>
                    <p>
                        <label>Penyesuaian Anggaran Pagu (Rp):</label><br>
                        <input type="number" name="anggaran_diajukan" value="{{ $permohonan->estimasi_total_pagu }}" step="0.01" required>
                    </p>
                    <p>
                        <label>Keterangan Perbaikan Dokumen:</label><br>
                        <textarea name="keterangan_revisi" rows="3" cols="60" required placeholder="Jelaskan perbaikan yang telah dilakukan sesuai catatan penolakan..."></textarea>
                    </p>
                    <p>
                        <label>Unggah Berkas KAK Baru (PDF):</label><br>
                        <input type="file" name="file_kak" accept=".pdf">
                    </p>
                    <p>
                        <button type="submit"><strong>Kirimkan Surat Revisi ke Verifikator</strong></button>
                    </p>
                </form>
            </fieldset>
        @endif
    </section>

    <br>
    <hr>

    <footer>
        <p>
            <a href="{{ route('permohonan.index') }}"><button type="button">&larr; Kembali ke Daftar Permohonan</button></a>
            <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $permohonan->nomor_surat]) }}" target="_blank">
                <button type="button">Unduh PDF Resmi Permohonan</button>
            </a>
        </p>
    </footer>
</article>
@endsection
