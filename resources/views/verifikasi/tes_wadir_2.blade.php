@extends('layouts.tes_app')

@section('title', 'Persetujuan Kegiatan Wadir II - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Antrean Persetujuan Kegiatan Wakil Direktur II (ACC Kegiatan / Tolak)</h2>
    <p>
        Setelah Bagian Perencanaan memastikan ketersediaan MAK dan mengirimkan <strong>Memo Ketersediaan MAK</strong>,
        Wakil Direktur II memberikan persetujuan kegiatan (ACC) bersama Pejabat Pembuat Komitmen (PPK) terpilih.
        Apabila Wadir II dan PPK terpilih sama-sama menyetujui, berkas otomatis diteruskan ke PPBJ.
        <br>
        <em>Aturan Institusi: Kolom catatan bersifat opsional saat menyetujui (ACC), namun <strong>WAJIB diisi jika usulan ditolak / diminta revisi</strong>.</em>
    </p>

    @if($permohonanAntrean->isEmpty())
        <p><em>Saat ini tidak ada permohonan yang menunggu persetujuan kegiatan Wadir II.</em></p>
    @else
        @foreach($permohonanAntrean as $p)
            @php
                $vpStaff = $p->verifikasiParalel->where('role_verifikator', 'staff_bidang_2')->first();
                $vpPpk = $p->verifikasiParalel->where('role_verifikator', 'ppk')->first();
            @endphp
            <article>
                <fieldset>
                    <legend><strong>Permohonan Pengadaan: {{ $p->nomor_surat }}</strong></legend>
                    <p>
                        <strong>Unit Kerja:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                        <strong>Tanggal Pengajuan:</strong> {{ $p->tanggal_pengajuan ? date('d-m-Y', strtotime($p->tanggal_pengajuan)) : '-' }} &bull;
                        <strong>Estimasi Anggaran:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}
                    </p>
                    <p>
                        <strong>Perihal / Judul:</strong> {{ $p->perihal }}
                    </p>
                    <p>
                        <strong>Kode MAK DIPA:</strong> {{ $p->kode_mak ?? 'Belum Dialokasikan' }} &bull;
                        <strong>Memo Ketersediaan MAK (Perencanaan):</strong> {{ $p->nomor_memo_ketersediaan_mak ?? '-' }} &bull;
                        <strong>PPK Terpilih:</strong> {{ $p->ppkAwal->nama ?? '-' }} (Status PPK: <u>{{ strtoupper($vpPpk->status_keputusan ?? 'menunggu') }}</u>)
                    </p>
                    @if($vpStaff && $vpStaff->catatan_alasan)
                        <p>
                            <strong>Catatan Staf Bidang II:</strong><br>
                            <em>&ldquo;{{ $vpStaff->catatan_alasan }}&rdquo;</em>
                        </p>
                    @endif
                    <p>
                        <strong>Dokumen Permohonan:</strong>
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                            <button type="button">Unduh / Lihat PDF Surat</button>
                        </a>
                    </p>

                    <form action="{{ route('verifikasi.wadir-2.keputusan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <fieldset>
                            <legend>Form Keputusan Persetujuan Kegiatan Wadir II</legend>

                            <p>
                                <strong>Keputusan Wadir II:</strong><br>
                                <label>
                                    <input type="radio" name="keputusan" value="disetujui" required>
                                    <strong>SETUJUI KEGIATAN (ACC)</strong> &mdash; Kegiatan disetujui (lanjut ke PPBJ setelah Wadir II &amp; PPK sama-sama setuju).
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="keputusan" value="ditolak" required>
                                    <strong>TOLAK / MINTA REVISI</strong> &mdash; Usulan ditolak. Alasan / catatan perbaikan <u>WAJIB DIISI</u>.
                                </label>
                            </p>

                            <p>
                                <label for="catatan_alasan_{{ $loop->index }}"><strong>Catatan / Alasan Keputusan Wadir II:</strong></label><br>
                                <textarea id="catatan_alasan_{{ $loop->index }}" name="catatan_alasan" rows="3" cols="70" placeholder="Wajib diisi jika menolak. Opsional jika disetujui."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Simpan Keputusan Wadir II</strong></button>
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
