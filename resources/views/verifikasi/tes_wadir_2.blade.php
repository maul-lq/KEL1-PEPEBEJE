@extends('layouts.tes_app')

@section('title', 'Verifikasi Eksekutif Wadir 2 - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Antrean Keputusan Eksekutif Wakil Direktur II (ACC / Tolak)</h2>
    <p>
        Wakil Direktur Bidang Umum dan Keuangan memegang wewenang eksekutif institusi untuk memberikan keputusan mutlak persetujuan (ACC)
        atau penolakan/permintaan revisi atas usulan pengadaan barang dan jasa.
        <br>
        <em>Aturan Institusi: Kolom catatan bersifat opsional saat menyetujui (ACC), namun <strong>WAJIB diisi jika usulan ditolak / diminta revisi</strong>.</em>
    </p>

    @if($permohonanAntrean->isEmpty())
        <p><em>Saat ini tidak ada permohonan yang menunggu keputusan persetujuan Wadir 2.</em></p>
    @else
        @foreach($permohonanAntrean as $p)
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
                        <strong>Dokumen Permohonan:</strong>
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                            <button type="button">Unduh / Lihat PDF Surat</button>
                        </a>
                    </p>

                    {{-- Form Keputusan Eksekutif Wadir 2 --}}
                    <form action="{{ route('verifikasi.wadir-2.keputusan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <fieldset>
                            <legend>Form Keputusan Mutlak Wadir 2</legend>

                            <p>
                                <strong>Keputusan Akhir Wadir 2:</strong><br>
                                <label>
                                    <input type="radio" name="keputusan" value="disetujui" required>
                                    <strong>SETUJUI USULAN (ACC)</strong> - Usulan disetujui untuk diproses ke PPBJ. Catatan opsional.
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="keputusan" value="ditolak" required>
                                    <strong>TOLAK / MINTA REVISI</strong> - Usulan ditolak. Alasan / catatan perbaikan <u>WAJIB DIISI</u>.
                                </label>
                            </p>

                            <p>
                                <label for="catatan_alasan_{{ $p->nomor_surat }}"><strong>Catatan / Alasan Keputusan Wadir 2:</strong></label><br>
                                <textarea id="catatan_alasan_{{ $p->nomor_surat }}" name="catatan_alasan" rows="3" cols="70" placeholder="Wajib diisi jika menolak (misal: 'Spesifikasi belum mencantumkan sertifikasi garansi resmi atau pagu tidak efisien'). Opsional jika disetujui."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Simpan Keputusan Wadir 2</strong></button>
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
