<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes Login - Sistem PBJ</title>
</head>
<body>
    <h1>Halaman Tes Login Sistem PBJ</h1>
    <p>Silakan masuk menggunakan NIP atau Email yang terdaftar.</p>

    @if (session('success'))
        <p><strong>[Sukses]:</strong> {{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <div>
            <strong>[Error Login]:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <p>
            <label for="login">NIP atau Email:</label><br>
            <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus placeholder="Contoh: tes_pengaju@kampus.ac.id atau NIP">
        </p>
        <p>
            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required placeholder="Password Anda">
        </p>
        <p>
            <button type="submit">Masuk (Login)</button>
        </p>
    </form>

    <p>
        Belum punya akun? <a href="{{ route('register') }}">Daftar di sini (tes_registrasi)</a>
    </p>

    <hr>

    <h3>Daftar Akun Tes Demo (Password: tes_password123):</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Role</th>
                <th>Email Tes</th>
                <th>NIP Tes</th>
                <th>Nama Pegawai</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>user_pengaju</td>
                <td>tes_pengaju@kampus.ac.id</td>
                <td>tes_197508152002121001</td>
                <td>tes_Budi Pengaju Unit</td>
            </tr>
            <tr>
                <td>wadir_2</td>
                <td>tes_wadir2@kampus.ac.id</td>
                <td>tes_197001011995011001</td>
                <td>tes_Wadir 2 Bidang Keuangan</td>
            </tr>
            <tr>
                <td>perencanaan</td>
                <td>tes_perencanaan@kampus.ac.id</td>
                <td>tes_198503032007031004</td>
                <td>tes_Staf Perencanaan MAK</td>
            </tr>
            <tr>
                <td>ppbj</td>
                <td>tes_ppbj@kampus.ac.id</td>
                <td>tes_198001012005011002</td>
                <td>tes_Pengelola PPBJ</td>
            </tr>
            <tr>
                <td>pp</td>
                <td>tes_pp@kampus.ac.id</td>
                <td>tes_198102022006021003</td>
                <td>tes_Pejabat Pengadaan (PP)</td>
            </tr>
            <tr>
                <td>ppk</td>
                <td>tes_ppk@kampus.ac.id</td>
                <td>tes_198203032007031004</td>
                <td>tes_Pejabat Pembuat Komitmen (PPK)</td>
            </tr>
            <tr>
                <td>perlengkapan</td>
                <td>tes_perlengkapan@kampus.ac.id</td>
                <td>tes_198405052009051006</td>
                <td>tes_Staf Perlengkapan</td>
            </tr>
            <tr>
                <td>keuangan</td>
                <td>tes_keuangan@kampus.ac.id</td>
                <td>tes_198704042008041005</td>
                <td>tes_Staf Keuangan</td>
            </tr>
            <tr>
                <td>staff_bidang_2 (Admin)</td>
                <td>tes_admin@kampus.ac.id</td>
                <td>tes_199001012015011001</td>
                <td>tes_Super Admin PBJ</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
