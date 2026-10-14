<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem PBJ</title>
    @vite('resources/js/tes_login.js')
</head>
<body>
    <header>
        <h1>Login - Sistem Informasi Pengadaan Barang dan Jasa (SIM-PBJ)</h1>
        <p>Politeknik Negeri Jakarta &bull; Antarmuka Pengujian & Simulasi Backend</p>
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
            <strong>GAGAL MASUK:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <hr>
    @endif

    <!-- Formulir Login -->
    <main>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <fieldset>
                <legend>Formulir Otentikasi Pengguna</legend>

                <p>
                    <label for="login">Username / NIP / Email (*):</label><br>
                    <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus size="40">
                </p>

                <p>
                    <label for="password">Password (*):</label><br>
                    <input type="password" id="password" name="password" required size="40">
                    <button type="button" id="togglePassword">Tampilkan</button>
                </p>

                <p>
                    <button type="submit">Login</button>
                </p>

                <p>
                    <a href="{{ route('register') }}">Belum punya akun? Daftar disini &rarr;</a>
                </p>
            </fieldset>
        </form>
    </main>

    <hr>

    <!-- Bantuan Akun Demo Uji Coba -->
    <aside>
        <details>
            <summary>
                <strong>Daftar Akun Demo Terdaftar di Database (9 Roles PNJ)</strong> - (Klik untuk membuka & isi otomatis)
            </summary>

            <p>
                <small>* Seluruh akun demo menggunakan password: <code>password123</code></small>
            </p>

            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Peran (Role)</th>
                        <th>Username</th>
                        <th>Nama Pejabat / Pegawai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>user</td>
                        <td><code>user_pengaju</code></td>
                        <td>Dr. Maulana Pengaju (TIK)</td>
                        <td><button type="button" onclick="window.fillCredentials('user_pengaju', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>staff_bidang_2</td>
                        <td><code>staff_b2</code></td>
                        <td>Ahmad Staff Bidang 2</td>
                        <td><button type="button" onclick="window.fillCredentials('staff_b2', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>wadir_2</td>
                        <td><code>wadir2</code></td>
                        <td>Prof. Wadir Dua (Wadir 2)</td>
                        <td><button type="button" onclick="window.fillCredentials('wadir2', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>perencanaan</td>
                        <td><code>perencanaan</code></td>
                        <td>Budi Perencanaan (MAK)</td>
                        <td><button type="button" onclick="window.fillCredentials('perencanaan', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>ppbj</td>
                        <td><code>ppbj</code></td>
                        <td>Citra Koordinator PPBJ</td>
                        <td><button type="button" onclick="window.fillCredentials('ppbj', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>pp</td>
                        <td><code>pp_user</code></td>
                        <td>Dedi Pejabat Pengadaan (PP)</td>
                        <td><button type="button" onclick="window.fillCredentials('pp_user', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>ppk</td>
                        <td><code>ppk_1</code></td>
                        <td>Eko PPK I (Sarana)</td>
                        <td><button type="button" onclick="window.fillCredentials('ppk_1', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>ppk</td>
                        <td><code>ppk_2</code></td>
                        <td>Fajar PPK II (Lab)</td>
                        <td><button type="button" onclick="window.fillCredentials('ppk_2', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>ppk</td>
                        <td><code>ppk_3</code></td>
                        <td>Gita PPK III (Konstruksi)</td>
                        <td><button type="button" onclick="window.fillCredentials('ppk_3', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>ppk</td>
                        <td><code>ppk_4</code></td>
                        <td>Hendra PPK IV (Jasa)</td>
                        <td><button type="button" onclick="window.fillCredentials('ppk_4', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>perlengkapan</td>
                        <td><code>perlengkapan</code></td>
                        <td>Indra Staff Perlengkapan</td>
                        <td><button type="button" onclick="window.fillCredentials('perlengkapan', 'password123');">Gunakan Akun</button></td>
                    </tr>
                    <tr>
                        <td>keuangan</td>
                        <td><code>keuangan</code></td>
                        <td>Joko Bendahara Keuangan</td>
                        <td><button type="button" onclick="window.fillCredentials('keuangan', 'password123');">Gunakan Akun</button></td>
                    </tr>
                </tbody>
            </table>
        </details>
    </aside>

    <hr>

    <footer>
        <p>
            <small>&copy; SIM-PBJ Politeknik Negeri Jakarta &bull; Plain HTML Simulation</small>
        </p>
    </footer>
</body>
</html>
