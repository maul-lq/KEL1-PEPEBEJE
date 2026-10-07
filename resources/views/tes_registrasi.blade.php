<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes Registrasi - Sistem PBJ</title>
</head>
<body>
    <h1>Halaman Tes Registrasi Pengguna Baru</h1>
    <p>Formulir pendaftaran akun pemohon/pengaju pengadaan barang dan jasa. Akun baru memerlukan verifikasi & persetujuan Super Admin sebelum aktif.</p>

    @if ($errors->any())
        <div>
            <strong>[Gagal Registrasi]:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <p>
            <label for="nip">NIP Pegawai:</label><br>
            <input type="text" id="nip" name="nip" value="{{ old('nip') }}" required placeholder="Contoh: 198501012010121001">
        </p>
        <p>
            <label for="email">Alamat Email Kampus:</label><br>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="nama@kampus.ac.id">
        </p>
        <p>
            <label for="nama">Nama Lengkap:</label><br>
            <input type="text" id="nama" name="nama" value="{{ old('nama') }}" required placeholder="Nama lengkap beserta gelar">
        </p>
        <p>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required placeholder="Minimal 6 karakter">
        </p>
        <p>
            <label for="password_confirmation">Konfirmasi Password:</label><br>
            <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Ulangi password">
        </p>
        <p>
            <label for="jabatan">Jabatan:</label><br>
            <input type="text" id="jabatan" name="jabatan" value="{{ old('jabatan') }}" required placeholder="Contoh: Dosen / Kepala Laboratorium">
        </p>
        <p>
            <label for="unit_kerja">Unit Kerja / Jurusan:</label><br>
            <input type="text" id="unit_kerja" name="unit_kerja" value="{{ old('unit_kerja') }}" required placeholder="Contoh: Teknik Informatika / Elektro">
        </p>
        <p>
            <label for="no_hp">Nomor HP / WhatsApp:</label><br>
            <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" required placeholder="Contoh: 081234567890">
        </p>
        <p>
            <label for="no_telp_kantor">Nomor Telepon Kantor (Opsional):</label><br>
            <input type="text" id="no_telp_kantor" name="no_telp_kantor" value="{{ old('no_telp_kantor') }}" placeholder="Contoh: 021-7270036">
        </p>
        <p>
            <button type="submit">Daftarkan Akun</button>
        </p>
    </form>

    <p>
        Sudah memiliki akun? <a href="{{ route('login') }}">&larr; Kembali ke Halaman Tes Login</a>
    </p>
</body>
</html>
