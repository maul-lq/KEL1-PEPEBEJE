@extends('layouts.tes_app')

@section('title', 'Pemeriksaan Berkas & Memo Cek MAK - Staf Bidang II PNJ')

@section('content')
<section>
    <h2>Antrean Pemeriksaan Berkas &amp; Penerbitan Memo Cek MAK (Staf Bidang II)</h2>
    <p>
        Surat usulan pengadaan dari Unit/Pemohon masuk terlebih dahulu ke Bidang II.
        Sebagai Staf Bidang II, Anda memeriksa kelengkapan berkas (Surat Srikandi, KAK, dan Spesifikasi Teknis),
        lalu membuat <strong>Memo Cek MAK</strong> ke Bagian Perencanaan untuk memastikan ketersediaan anggaran DIPA.
    </p>

    @if($permohonanMasuk->isEmpty())
        <p><em>Saat ini tidak ada permohonan baru yang membutuhkan pemeriksaan berkas dan Memo Cek MAK.</em></p>
    @else
        @foreach($permohonanMasuk as $p)
            <article>
                <fieldset>
                    <legend><strong>Surat Permohonan: {{ $p->nomor_surat }}</strong></legend>
                    <p>
                        <strong>Unit Kerja Pengusul:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                        <strong>Tanggal Usulan:</strong> {{ $p->tanggal_pengajuan ? date('d-m-Y', strtotime($p->tanggal_pengajuan)) : '-' }} &bull;
                        <strong>Estimasi Anggaran:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }} &bull;
                        <strong>Status Saat Ini:</strong> {{ strtoupper(str_replace('_', ' ', $p->status_permohonan)) }}
                    </p>
                    <p>
                        <strong>Perihal / Judul Pengadaan:</strong> {{ $p->perihal }}
                    </p>
                    <p>
                        <strong>Berkas Lampiran:</strong>
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                            <button type="button">Lihat Surat Permohonan (PDF)</button>
                        </a>
                    </p>

                    <form action="{{ route('verifikasi.bidang-2.teruskan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <fieldset>
                            <legend>Checklist Administrasi &amp; Memo Cek MAK ke Perencanaan</legend>
                            <p>
                                <label>
                                    <input type="checkbox" name="is_kak_valid" value="1" required>
                                    Dokumen Kerangka Acuan Kerja (KAK) telah diperiksa, lengkap, dan tervalidasi.
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="is_spesifikasi_valid" value="1" required>
                                    Dokumen Spesifikasi Teknis dan rincian harga wajar pasar telah terlampir jelas.
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="is_srikandi_valid" value="1" required>
                                    Nomor naskah dinas resmi terdaftar pada tata naskah Srikandi PNJ.
                                </label>
                            </p>

                            <p>
                                <label for="nomor_memo_cek_mak_{{ $loop->index }}"><strong>Nomor Memo Pengantar Cek MAK (Bidang II ke Perencanaan):</strong></label><br>
                                <input type="text" id="nomor_memo_cek_mak_{{ $loop->index }}" name="nomor_memo_cek_mak" value="MEMO-B2/CEK-MAK/{{ date('Y') }}/{{ rand(100, 999) }}" size="45" required>
                            </p>

                            <p>
                                <label for="catatan_staff_{{ $loop->index }}"><strong>Catatan Pengantar Cek MAK untuk Bagian Perencanaan:</strong></label><br>
                                <textarea id="catatan_staff_{{ $loop->index }}" name="catatan_staff" rows="3" cols="60" placeholder="Contoh: Berkas administrasi lengkap, mohon Bagian Perencanaan memeriksa ketersediaan MAK DIPA dan menunjuk PPK."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Kirim Memo Cek MAK ke Bagian Perencanaan &raquo;</strong></button>
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
