@extends('layouts.tes_app')

@section('title', 'Persetujuan Awal Usulan PPK - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Antrean Persetujuan Awal Usulan Pengadaan (Pejabat Pembuat Komitmen Terpilih)</h2>
    <p>
        Halaman ini menampilkan usulan pengadaan yang telah diperiksa ketersediaan MAK-nya oleh Bagian Perencanaan
        dan ditujukan kepada akun PPK Anda (<strong>{{ Auth::user()->nama }}</strong>) melalui <strong>Memo Ketersediaan MAK</strong>.
        Setelah Wadir II (ACC Kegiatan) dan Anda (ACC Usulan) sama-sama menyetujui, berkas otomatis diteruskan ke PPBJ.
    </p>

    @if($permohonanAntrean->isEmpty())
        <p><em>Saat ini tidak ada usulan permohonan awal yang menunggu persetujuan pada akun PPK Anda.</em></p>
    @else
        @foreach($permohonanAntrean as $p)
            @php
                $vpWadir = $p->verifikasiParalel->where('role_verifikator', 'wadir_2')->first();
                $vpPpk = $p->verifikasiParalel->where('role_verifikator', 'ppk')->first();
            @endphp
            <article>
                <fieldset>
                    <legend><strong>Permohonan Pengadaan: {{ $p->nomor_surat }}</strong></legend>
                    <p>
                        <strong>Unit Pengusul:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                        <strong>Tanggal Pengajuan:</strong> {{ $p->tanggal_pengajuan ? date('d-m-Y', strtotime($p->tanggal_pengajuan)) : '-' }} &bull;
                        <strong>Estimasi Anggaran:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}
                    </p>
                    <p>
                        <strong>Perihal / Judul:</strong> {{ $p->perihal }}
                    </p>
                    <p>
                        <strong>Kode MAK DIPA:</strong> {{ $p->kode_mak ?? '-' }} &bull;
                        <strong>Memo Ketersediaan MAK:</strong> {{ $p->nomor_memo_ketersediaan_mak ?? '-' }} &bull;
                        <strong>Status Wadir II:</strong> <u>{{ strtoupper($vpWadir->status_keputusan ?? 'menunggu') }}</u> &bull;
                        <strong>Status PPK Anda:</strong> <u>{{ strtoupper($vpPpk->status_keputusan ?? 'menunggu') }}</u>
                    </p>
                    @if($p->catatan_memo_ketersediaan_mak)
                        <p>
                            <strong>Catatan Perencanaan:</strong> <em>&ldquo;{{ $p->catatan_memo_ketersediaan_mak }}&rdquo;</em>
                        </p>
                    @endif
                    <p>
                        <strong>Dokumen Permohonan:</strong>
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                            <button type="button">Unduh / Lihat PDF Surat</button>
                        </a>
                    </p>

                    <form action="{{ route('verifikasi.ppk.keputusan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <fieldset>
                            <legend>Form Persetujuan Awal PPK</legend>
                            <p>
                                <strong>Keputusan PPK:</strong><br>
                                <label>
                                    <input type="radio" name="keputusan" value="disetujui" required>
                                    <strong>SETUJUI USULAN (ACC PPK)</strong> &mdash; Setujui usulan pengadaan untuk dilanjutkan ke PPBJ bersama persetujuan Wadir II.
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="keputusan" value="ditolak" required>
                                    <strong>TOLAK / MINTA REVISI</strong> &mdash; Kembalikan ke Pemohon disertai alasan penolakan (wajib diisi).
                                </label>
                            </p>

                            <p>
                                <label for="catatan_ppk_{{ $loop->index }}"><strong>Catatan / Alasan Keputusan PPK:</strong></label><br>
                                <textarea id="catatan_ppk_{{ $loop->index }}" name="catatan_alasan" rows="3" cols="70" placeholder="Wajib diisi apabila menolak usulan. Opsional apabila menyetujui."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Simpan Keputusan Persetujuan Awal PPK</strong></button>
                            </p>
                        </fieldset>
                    </form>
                </fieldset>
            </article>
            <br>
        @endforeach
    @endif
</section>
@endsection
