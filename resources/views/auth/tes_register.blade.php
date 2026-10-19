@extends('layouts.tes_app')

@section('title', 'Daftar Akun Baru - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<section>
    <h2>Pendaftaran Akun Pegawai / Pengusul Baru</h2>
    <p>Silakan lengkapi formulir berikut untuk mendaftarkan akun pengusul permohonan pengadaan barang dan jasa.</p>

    <form action="{{ route('register') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Formulir Biodata Pengguna</legend>

            <p>
                <label for="nip"><strong>Nomor Induk Pegawai (NIP):</strong></label><br>
                <input type="text" id="nip" name="nip" value="{{ old('nip') }}" required placeholder="Contoh: 199001012020011001">
            </p>

            <p>
                <label for="nama"><strong>Nama Lengkap Beserta Gelar:</strong></label><br>
                <input type="text" id="nama" name="nama" value="{{ old('nama') }}" required placeholder="Contoh: Dr. Budi Santoso, M.T.">
            </p>

            <p>
                <label for="username"><strong>Username Sistem:</strong></label><br>
                <input type="text" id="username" name="username" value="{{ old('username') }}" required placeholder="Contoh: budi_santoso">
            </p>

            <p>
                <label for="email"><strong>Email Resmi Institusi:</strong></label><br>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="Contoh: budi@pnj.ac.id">
            </p>

            <p>
                <label for="password"><strong>Kata Sandi:</strong></label><br>
                <input type="password" id="password" name="password" required placeholder="Minimal 8 karakter">
            </p>

            <p>
                <label for="password_confirmation"><strong>Konfirmasi Kata Sandi:</strong></label><br>
                <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Ulangi kata sandi">
            </p>

            <p>
                <label for="jabatan"><strong>Jabatan Struktural / Akademik:</strong></label><br>
                <input type="text" id="jabatan" name="jabatan" value="{{ old('jabatan') }}" required placeholder="Contoh: Dosen Pengajar / Kepala Lab">
            </p>

            <p>
                <label for="kode_unit"><strong>Unit Kerja / Jurusan Asal:</strong></label><br>
                <select id="kode_unit" name="kode_unit" required>
                    <option value="">-- Pilih Unit Kerja / Jurusan --</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->kode_unit }}" {{ old('kode_unit') === $unit->kode_unit ? 'selected' : '' }}>
                            [{{ $unit->kode_unit }}] {{ $unit->nama_unit }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="no_hp"><strong>Nomor Handphone / WhatsApp Aktif:</strong></label><br>
                <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" required placeholder="Contoh: 081234567890">
            </p>

            <p>
                <label for="no_telp_kantor"><strong>Nomor Telepon Kantor / Ruangan:</strong></label><br>
                <input type="text" id="no_telp_kantor" name="no_telp_kantor" value="{{ old('no_telp_kantor') }}" placeholder="Contoh: 0217270001 (Opsional)">
            </p>

            {{-- Role default pendaftaran adalah 'user' (pemohon pengadaan) --}}
            <input type="hidden" name="role" value="user">

            <p>
                <button type="submit"><strong>Daftarkan Akun Pengguna</strong></button>
            </p>
        </fieldset>
    </form>

    <p>
        Sudah memiliki akun? <a href="{{ route('login') }}">Kembali ke halaman login</a>.
    </p>
</section>
@endsection
