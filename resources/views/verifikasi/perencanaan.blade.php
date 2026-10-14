@extends('layouts.app')

@section('title', 'Verifikasi Anggaran MAK - Subbagian Perencanaan PNJ')

@section('content')
<section>
    <h2>Verifikasi Ketersediaan Anggaran & Penetapan MAK (Subbagian Perencanaan)</h2>
    <p>
        Bagian Perencanaan bertugas memeriksa ketersediaan pagu dana DIPA, mengalokasikan Mata Anggaran Kegiatan (MAK),
        serta mengunci anggaran MAK untuk menjaga integritas keuangan institusi.
    </p>

    {{-- 1. Antrean Permohonan yang Membutuhkan Alokasi MAK --}}
    <fieldset>
        <legend><strong>Antrean Usulan Permohonan Pengadaan Menunggu Alokasi MAK</strong></legend>

        @if($permohonanAntrean->isEmpty())
            <p><em>Tidak ada usulan permohonan yang menunggu alokasi MAK saat ini.</em></p>
        @else
            @foreach($permohonanAntrean as $p)
                <article>
                    <fieldset>
                        <legend><strong>Surat: {{ $p->nomor_surat }}</strong></legend>
                        <p>
                            <strong>Unit Pengusul:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                            <strong>Perihal:</strong> {{ $p->perihal }} &bull;
                            <strong>Estimasi Anggaran Diajukan:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}
                        </p>

                        <form action="{{ route('verifikasi.perencanaan.mak') }}" method="POST">
                            @csrf
                            <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                            <p>
                                <label for="kode_mak_{{ $p->nomor_surat }}"><strong>Pilih Mata Anggaran Kegiatan (MAK) DIPA:</strong></label><br>
                                <select id="kode_mak_{{ $p->nomor_surat }}" name="kode_mak" required>
                                    <option value="">-- Pilih MAK yang Tersedia --</option>
                                    @foreach($makList as $mak)
                                        <option value="{{ $mak->kode_mak }}" {{ $mak->is_locked ? 'disabled' : '' }}>
                                            [{{ $mak->kode_mak }}] {{ $mak->uraian_mak }} &bull; Sisa Pagu: Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }} ({{ $mak->sumber_dana }}) {{ $mak->is_locked ? '[TERKUNCI]' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </p>

                            <p>
                                <label for="nominal_alokasi_{{ $p->nomor_surat }}"><strong>Nominal Alokasi Anggaran (Rp):</strong></label><br>
                                <input type="number" id="nominal_alokasi_{{ $p->nomor_surat }}" name="nominal_alokasi" value="{{ $p->anggaran_diajukan }}" step="0.01" min="1" required>
                            </p>

                            <p>
                                <label>
                                    <input type="radio" name="keputusan_anggaran" value="setuju" checked>
                                    <strong>Setujui Alokasi Anggaran MAK</strong>
                                </label>
                                &nbsp;&nbsp;
                                <label>
                                    <input type="radio" name="keputusan_anggaran" value="tahan">
                                    <strong>Tahan / Tolak (Pagu Anggaran Tidak Cukup)</strong>
                                </label>
                            </p>

                            <p>
                                <label for="catatan_anggaran_{{ $p->nomor_surat }}"><strong>Catatan Perencanaan (Opsional):</strong></label><br>
                                <textarea id="catatan_anggaran_{{ $p->nomor_surat }}" name="catatan_anggaran" rows="2" cols="60" placeholder="Contoh: Menggunakan pagu BOPTN 2026, MAK telah dialokasikan penuh."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Simpan Verifikasi Anggaran MAK</strong></button>
                            </p>
                        </fieldset>
                    </form>
                </article>
                <br>
            @endforeach
        @endif
    </fieldset>

    <hr>

    {{-- 2. Daftar Master MAK DIPA & Fitur Kunci Anggaran --}}
    <fieldset>
        <legend><strong>Daftar Mata Anggaran Kegiatan (MAK) DIPA Kampus PNJ</strong></legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Kode MAK</th>
                    <th>Uraian MAK</th>
                    <th>Sumber Dana</th>
                    <th>Tahun</th>
                    <th>Pagu Awal</th>
                    <th>Sisa Pagu Aktif</th>
                    <th>Status Kunci</th>
                </tr>
            </thead>
            <tbody>
                @foreach($makList as $mak)
                    <tr>
                        <td><strong>{{ $mak->kode_mak }}</strong></td>
                        <td>{{ $mak->uraian_mak }}</td>
                        <td>{{ $mak->sumber_dana }}</td>
                        <td align="center">{{ $mak->tahun_anggaran }}</td>
                        <td>Rp {{ number_format($mak->pagu_anggaran, 0, ',', '.') }}</td>
                        <td><strong>Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }}</strong></td>
                        <td align="center">
                            @if($mak->is_locked)
                                <strong>TERKUNCI</strong>
                            @else
                                <em>Terbuka (Dapat Dialokasikan)</em>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </fieldset>
</section>
@endsection
