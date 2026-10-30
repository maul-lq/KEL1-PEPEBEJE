@extends('layouts.tes_app')

@section('title', 'Alih Tugas Penugasan PPK - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Alih Tugas Penugasan PPK (FR 13)</h2>
    <p>
        <a href="{{ route('paket.index') }}">&larr; Kembali ke Daftar Paket</a>
    </p>

    <form action="{{ route('paket.reassign-ppk') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Form Pengalihan Tugas PPK</legend>

            <p>
                <label for="kode_paket"><strong>Pilih Paket Pengadaan:</strong></label><br>
                <select id="kode_paket" name="kode_paket" required>
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList as $pkt)
                        <option value="{{ $pkt->nomor_paket }}">
                            [{{ $pkt->kode_paket ?? $pkt->nomor_paket }}] {{ $pkt->nama_paket }} (PPK: {{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="id_user_ppk_baru"><strong>Pilih Akun PPK Baru:</strong></label><br>
                <select id="id_user_ppk_baru" name="id_user_ppk_baru" required>
                    <option value="">-- Pilih Akun PPK --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}">
                            {{ $ppk->nama }} ({{ $ppk->jabatan ?? 'PPK PNJ' }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="alasan_pengalihan"><strong>Alasan Pengalihan Tugas:</strong></label><br>
                <textarea id="alasan_pengalihan" name="alasan_pengalihan" rows="3" cols="50" required></textarea>
            </p>

            <p>
                <button type="submit"><strong>Simpan Alih Tugas PPK</strong></button>
            </p>
        </fieldset>
    </form>
</section>
@endsection
