@extends('layouts.app')

@section('title', 'Manajemen Kontrak & Surat Perintah Kerja (SPK) - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Manajemen Kontrak & Surat Perintah Kerja (SPK)</h2>
    <p>
        @if(Auth::user()->role === 'ppk')
            <strong>Peran: Pejabat Pembuat Komitmen (PPK).</strong>
            Menangani paket pengadaan komitmen bernilai di atas Rp 200 Juta yang ditugaskan kepada akun Anda (<strong>{{ Auth::user()->nama }}</strong>).
            <em>Paket milik akun PPK lain diisolasi dan tidak tampil pada halaman ini.</em>
        @else
            <strong>Peran: Pejabat Pengadaan (PP).</strong>
            Menangani paket pengadaan langsung dan pemilihan rekanan bernilai sampai dengan Rp 200 Juta.
        @endif
    </p>

    {{-- 1. Formulir Penerbitan SPK Baru --}}
    <fieldset>
        <legend><strong>Penerbitan Kontrak / Surat Perintah Kerja (SPK)</strong></legend>

        <form action="{{ route('kontrak.store') }}" method="POST">
            @csrf
            <p>
                <label for="kode_paket_spk"><strong>Pilih Paket Pengadaan:</strong></label><br>
                <select id="kode_paket_spk" name="kode_paket" required>
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList as $pkt)
                        <option value="{{ $pkt->kode_paket }}">
                            [{{ $pkt->kode_paket }}] {{ $pkt->nama_paket }} &bull; Nilai HPS: Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="id_vendor"><strong>Pilih Rekanan Penyedia / Vendor Terdaftar:</strong></label><br>
                <select id="id_vendor" name="id_vendor" required>
                    <option value="">-- Pilih Rekanan Vendor PNJ --</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id_vendor }}">
                            {{ $v->nama_vendor }} (NPWP: {{ $v->npwp ?? '-' }}) &bull; Badan Usaha: {{ $v->bentuk_usaha ?? 'CV/PT' }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_kontrak_spk"><strong>Nomor Resmi SPK / Kontrak:</strong></label><br>
                <input type="text" id="nomor_kontrak_spk" name="nomor_kontrak_spk" value="SPK/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="nilai_kontrak"><strong>Nilai Kesepakatan Kontrak (Rp):</strong></label><br>
                <input type="number" id="nilai_kontrak" name="nilai_kontrak" step="0.01" min="1" required placeholder="Contoh: 145000000">
            </p>

            <p>
                <label for="tanggal_mulai"><strong>Tanggal Mulai Kontrak:</strong></label><br>
                <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ date('Y-m-d') }}" required>
            </p>

            <p>
                <label for="tanggal_selesai"><strong>Tanggal Jatuh Tempo / Selesai Kontrak:</strong></label><br>
                <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
            </p>

            <p>
                <label for="catatan_spk"><strong>Uraian Pekerjaan / Ketentuan Kontrak:</strong></label><br>
                <textarea id="catatan_spk" name="catatan_spk" rows="2" cols="60" placeholder="Contoh: Pengadaan perangkat lab lengkap dengan garansi resmi 3 tahun dan uji fungsi."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Terbitkan & Tanda Tangani Kontrak SPK</strong></button>
            </p>
        </form>
    </fieldset>

    <hr>

    {{-- 2. Daftar Kontrak SPK yang Telah Terbit --}}
    <fieldset>
        <legend><strong>Daftar Kontrak SPK yang Telah Diterbitkan</strong></legend>

        @if($kontrakList->isEmpty())
            <p><em>Belum ada Kontrak SPK yang diterbitkan untuk paket penugasan Anda.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Kontrak SPK</th>
                        <th>Kode Paket</th>
                        <th>Penyedia / Vendor</th>
                        <th>Nilai Kontrak</th>
                        <th>Masa Berlaku</th>
                        <th>Status SPK</th>
                        <th>Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kontrakList as $k)
                        <tr>
                            <td><strong>{{ $k->nomor_kontrak_spk }}</strong></td>
                            <td>{{ $k->kode_paket }}</td>
                            <td>{{ $k->vendor->nama_vendor ?? '-' }}</td>
                            <td>Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}</td>
                            <td>{{ date('d-m-Y', strtotime($k->tanggal_mulai)) }} s.d {{ date('d-m-Y', strtotime($k->tanggal_selesai)) }}</td>
                            <td>{{ strtoupper($k->status_kontrak ?? 'AKTIF') }}</td>
                            <td>
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_kontrak_spk]) }}" target="_blank">
                                    <button type="button">Unduh SPK (PDF)</button>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>
</section>
@endsection
