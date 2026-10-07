<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tes Beranda (Home) - Sistem PBJ</title>
</head>
<body>
    <h1>Halaman Tes Beranda (Home) Sistem PBJ</h1>
    <p>Selamat datang di sistem pengadaan barang dan jasa kampus.</p>

    <hr>

    <h3>Data Akun Pengguna yang Sedang Login:</h3>
    <ul>
        <li><strong>ID User:</strong> {{ auth()->user()->id_user }}</li>
        <li><strong>Nama Lengkap:</strong> {{ auth()->user()->nama }}</li>
        <li><strong>NIP:</strong> {{ auth()->user()->nip }}</li>
        <li><strong>Alamat Email:</strong> {{ auth()->user()->email }}</li>
        <li><strong>Hak Akses (Role):</strong> {{ auth()->user()->role }}</li>
        <li><strong>Unit Kerja:</strong> {{ auth()->user()->unit_kerja }}</li>
        <li><strong>Jabatan:</strong> {{ auth()->user()->jabatan }}</li>
        <li><strong>Nomor HP:</strong> {{ auth()->user()->no_hp }}</li>
        <li><strong>Nomor Kantor:</strong> {{ auth()->user()->no_telp_kantor ?? '-' }}</li>
        <li><strong>Status Akun:</strong> {{ auth()->user()->status_aktif ? 'Aktif' : 'Nonaktif' }}</li>
    </ul>

    <hr>

    <h3>Menu Navigasi Pengujian:</h3>
    <p>
        <a href="{{ route('test.api') }}"><strong>[ Buka Halaman Tes API Backend (17 Entitas) ]</strong></a>
    </p>

    <hr>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar (Logout)</button>
    </form>
</body>
</html>
