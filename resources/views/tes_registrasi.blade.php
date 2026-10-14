<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi Akun - Sistem PBJ</title>
</head>
<body>
    <header>
        <h1>Daftar Akun Baru - Sistem Informasi PBJ</h1>
        <p>Politeknik Negeri Jakarta &bull; Antarmuka Pengujian & Simulasi Backend</p>
        <p>
            <a href="{{ route('login') }}">&larr; Sudah ada akun? Kembali ke Halaman Login</a>
        </p>
    </header>

    <hr>

    @if (session('success'))
        <p>
            <strong>SUKSES:</strong> {{ session('success') }}
        </p>
        <hr>
    @endif

    @if ($errors->any())
        <div>
            <strong>TERJADI KESALAHAN VALIDASI:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <hr>
    @endif

    <!-- Formulir Registrasi Pengguna Baru -->
    <main>
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <fieldset>
                <legend>Data Identitas Pegawai & Kedinasan</legend>

                <p>
                    <label for="nip">NIP Pegawai (*):</label><br>
                    <input type="text" id="nip" name="nip" value="{{ old('nip') }}" placeholder="Contoh: 198501012010011001" required autofocus size="40">
                </p>

                <p>
                    <label for="username">Username Unik (*):</label><br>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Contoh: maulana_pbj" required size="40">
                </p>

                <p>
                    <label for="nama">Nama Lengkap & Gelar (*):</label><br>
                    <input type="text" id="nama" name="nama" value="{{ old('nama') }}" placeholder="Contoh: Dr. Ir. Maulana, M.T." required size="50">
                </p>

                <p>
                    <label for="email">Alamat Email Resmi (*):</label><br>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Contoh: maulana@pnj.ac.id" required size="40">
                </p>

                <p>
                    <label for="kode_unit">Unit Kerja / Jurusan (*):</label><br>
                    <select id="kode_unit" name="kode_unit" required>
                        <option value="">-- Pilih Unit Kerja / Jurusan --</option>
                        @foreach ($units as $u)
                            <option value="{{ $u->kode_unit }}" {{ old('kode_unit') == $u->kode_unit ? 'selected' : '' }}>
                                [{{ $u->kode_unit }}] {{ $u->nama_unit }} ({{ strtoupper($u->jenis_unit) }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="jabatan">Jabatan Kedinasan (*):</label><br>
                    <input type="text" id="jabatan" name="jabatan" value="{{ old('jabatan') }}" placeholder="Contoh: Dosen / Ketua Jurusan TIK" required size="50">
                </p>
            </fieldset>

            <br>

            <fieldset>
                <legend>Peran Sistem PBJ & Kontak</legend>

                <p>
                    <label for="role">Peran (Role) Sistem (*):</label><br>
                    <select id="role" name="role" required>
                        <option value="">-- Pilih Peran Pengguna --</option>
                        <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>1. User / Pengusul PBJ (Jurusan / Unit)</option>
                        <option value="staff_bidang_2" {{ old('role') == 'staff_bidang_2' ? 'selected' : '' }}>2. Staff Bidang II (Pemeriksa Kelengkapan)</option>
                        <option value="wadir_2" {{ old('role') == 'wadir_2' ? 'selected' : '' }}>3. Wakil Direktur II (Persetujuan Eksekutif)</option>
                        <option value="perencanaan" {{ old('role') == 'perencanaan' ? 'selected' : '' }}>4. Perencanaan (Verifikasi Anggaran MAK)</option>
                        <option value="ppbj" {{ old('role') == 'ppbj' ? 'selected' : '' }}>5. PPBJ (Pengelola Paket & Routing)</option>
                        <option value="pp" {{ old('role') == 'pp' ? 'selected' : '' }}>6. Pejabat Pengadaan / PP (Pagu &le; 200 Juta)</option>
                        <option value="ppk" {{ old('role') == 'ppk' ? 'selected' : '' }}>7. PPK (Pagu &gt; 200 Juta - 4 Akun)</option>
                        <option value="perlengkapan" {{ old('role') == 'perlengkapan' ? 'selected' : '' }}>8. Perlengkapan (Pemeriksa Fisik & BAST)</option>
                        <option value="keuangan" {{ old('role') == 'keuangan' ? 'selected' : '' }}>9. Keuangan (Memo Bayar & Pencairan Dana)</option>
                    </select>
                </p>

                <p>
                    <label for="no_hp">Nomor HP / WhatsApp (*):</label><br>
                    <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp') }}" placeholder="Contoh: 081234567890" required size="30">
                </p>

                <p>
                    <label for="no_telp_kantor">Nomor Telepon Kantor (Opsional):</label><br>
                    <input type="text" id="no_telp_kantor" name="no_telp_kantor" value="{{ old('no_telp_kantor') }}" placeholder="Contoh: 021-7270001" size="30">
                </p>
            </fieldset>

            <br>

            <fieldset>
                <legend>Keamanan Akun</legend>

                <p>
                    <label for="password">Password (*):</label><br>
                    <input type="password" id="password" name="password" required size="30"><br>
                    <small>Minimal 6 karakter</small>
                </p>

                <p>
                    <label for="password_confirmation">Konfirmasi Password (*):</label><br>
                    <input type="password" id="password_confirmation" name="password_confirmation" required size="30">
                </p>
            </fieldset>

            <br>

            <p>
                <button type="submit">Daftarkan Akun Pengguna</button>
            </p>
        </form>
    </main>

    <hr>

    <footer>
        <p>
            <small>&copy; SIM-PBJ Politeknik Negeri Jakarta &bull; Plain HTML Simulation</small>
        </p>
    </footer>
</body>
</html>
