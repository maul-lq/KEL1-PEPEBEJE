@extends('layouts.app')

@section('title', 'Telaah Berkas Permohonan Masuk - Staf Bidang II PNJ')

@section('content')
<section>
    <h2>Antrean Telaah Kelengkapan Berkas Administrasi (Staf Bidang II)</h2>
    <p>
        Sebagai Staf Bidang II, Anda bertugas menelaah dan memeriksa kelengkapan fisik berkas usulan pengadaan
        (Surat Srikandi, Kerangka Acuan Kerja, dan Spesifikasi Teknis) sebelum diteruskan kepada Wakil Direktur II.
    </p>

    @if($permohonanMasuk->isEmpty())
        <p><em>Saat ini tidak ada permohonan baru yang membutuhkan pemeriksaan berkas.</em></p>
    @else
        @foreach($permohonanMasuk as $p)
            <article>
                <fieldset>
                    <legend><strong>Surat Permohonan: {{ $p->nomor_surat }}</strong></legend>
                    <p>
                        <strong>Unit Kerja Pengusul:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                        <strong>Tanggal Usulan:</strong> {{ $p->tanggal_pengajuan ? date('d-m-Y', strtotime($p->tanggal_pengajuan)) : '-' }} &bull;
                        <strong>Estimasi Anggaran:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}
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

                    {{-- Form Telaah Staf Bidang 2 --}}
                    <form action="{{ route('verifikasi.bidang-2.teruskan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <fieldset>
                            <legend>Checklist Pemeriksaan Administrasi</legend>
                            <p>
                                <label>
                                    <input type="checkbox" name="is_kak_valid" value="1" required>
                                    Dokumen Kerangka Acuan Kerja (KAK) telah diperiksa, lengkap, dan bermeterai/tervalidasi.
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
                                <label for="catatan_staff_{{ $p->nomor_surat }}"><strong>Catatan Hasil Telaah Staf Bidang 2 untuk Wadir 2:</strong></label><br>
                                <textarea id="catatan_staff_{{ $p->nomor_surat }}" name="catatan_staff" rows="3" cols="60" placeholder="Contoh: Berkas telah diverifikasi lengkap, spesifikasi server sesuai standar lab TIK. Direkomendasikan untuk disetujui."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Teruskan Telaah Berkas ke Wadir 2 &raquo;</strong></button>
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
