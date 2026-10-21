@extends('layouts.tes_app')

@section('title', 'Masuk ke Sistem - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<section>
    <h2>Otentikasi Pengguna SIM-PBJ</h2>
    <p>Silakan masukkan kredensial akun Anda untuk mengakses sistem pengadaan sesuai wewenang dan peran jabatan.</p>

    {{-- Formulir Login Semantik Tanpa CSS --}}
    <form action="{{ route('login') }}" method="POST">
        @csrf
        <fieldset>
            <legend>Form Masuk Sistem</legend>

            <p>
                <label for="login"><strong>Username / NIP Pegawai / Email Resmi:</strong></label><br>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus placeholder="Contoh: user_pengaju atau 19850101...">
            </p>

            <p>
                <label for="password"><strong>Kata Sandi (Password):</strong></label><br>
                <input type="password" id="password" name="password" required placeholder="Masukkan kata sandi">
            </p>

            <p>
                <button type="submit"><strong>Masuk ke Sistem (Login)</strong></button>
            </p>
        </fieldset>
    </form>

    <p>
        Pegawai atau dosen baru? <a href="{{ route('register') }}">Daftarkan akun permohonan baru di sini</a>.
    </p>
</section>

<hr>

{{-- Direktori Akun Dinas Terdaftar Politeknik Negeri Jakarta --}}
<section>
    <fieldset>
        <legend><strong>Direktori Akun Dinas Pengguna Resmi Politeknik Negeri Jakarta</strong></legend>
        <p>
            <em>Pilih salah satu akun jabatan di bawah ini untuk mengisi kredensial masuk secara langsung:</em>
        </p>

        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Peran (Role)</th>
                    <th>Nama Pejabat / Pengguna</th>
                    <th>Unit Kerja</th>
                    <th>Username</th>
                    <th>Password Default</th>
                    <th>Aksi Cepat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>0</td>
                    <td><strong>Administrator Sistem (Admin)</strong></td>
                    <td>Administrator Sistem SIM-PBJ</td>
                    <td>Pusat Sistem Informasi</td>
                    <td><code>admin</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="admin" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>1</td>
                    <td><strong>Pemohon (User)</strong></td>
                    <td>Dr. Ir. Maulana Pengaju, M.T.</td>
                    <td>Jurusan TIK</td>
                    <td><code>user_pengaju</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="user_pengaju" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>2</td>
                    <td><strong>Staf Bidang 2</strong></td>
                    <td>Ahmad Staff Bidang 2, S.E.</td>
                    <td>Bidang II</td>
                    <td><code>staff_b2</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="staff_b2" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>3</td>
                    <td><strong>Wadir 2 (Hak Mutlak ACC)</strong></td>
                    <td>Prof. Dr. Ir. Wadir Dua, M.Sc.</td>
                    <td>Bidang II (Umum & Keuangan)</td>
                    <td><code>wadir2</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="wadir2" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>4</td>
                    <td><strong>Perencanaan</strong></td>
                    <td>Budi Perencanaan, S.Kom.</td>
                    <td>Subbagian Perencanaan & MAK</td>
                    <td><code>perencanaan</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="perencanaan" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>5</td>
                    <td><strong>PPBJ (Pengelola Paket)</strong></td>
                    <td>Citra Koordinator PPBJ, M.M.</td>
                    <td>Unit Kerja PBJ</td>
                    <td><code>ppbj</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="ppbj" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>6</td>
                    <td><strong>Pejabat Pengadaan (PP)</strong></td>
                    <td>Dedi Pejabat Pengadaan, S.T.</td>
                    <td>Pejabat Pengadaan (&le; 200 Juta)</td>
                    <td><code>pp_user</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="pp_user" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>7</td>
                    <td><strong>PPK 1 (Sarana - Terisolasi)</strong></td>
                    <td>Eko PPK Bidang Sarana, M.T.</td>
                    <td>PPK I (> 200 Juta)</td>
                    <td><code>ppk_1</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="ppk_1" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>8</td>
                    <td><strong>PPK 2 (Laboratorium)</strong></td>
                    <td>Fajar PPK Bidang Laboratorium, M.Kom.</td>
                    <td>PPK II (> 200 Juta)</td>
                    <td><code>ppk_2</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="ppk_2" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>9</td>
                    <td><strong>PPK 3 (Konstruksi)</strong></td>
                    <td>Gita PPK Bidang Konstruksi, S.T.</td>
                    <td>PPK III (> 200 Juta)</td>
                    <td><code>ppk_3</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="ppk_3" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>10</td>
                    <td><strong>PPK 4 (Jasa)</strong></td>
                    <td>Hendra PPK Bidang Jasa Lainnya, S.E.</td>
                    <td>PPK IV (> 200 Juta)</td>
                    <td><code>ppk_4</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="ppk_4" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>11</td>
                    <td><strong>Perlengkapan (BAST)</strong></td>
                    <td>Indra Staff Perlengkapan, A.Md.</td>
                    <td>Unit Perlengkapan & BMN</td>
                    <td><code>perlengkapan</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="perlengkapan" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
                <tr>
                    <td>12</td>
                    <td><strong>Keuangan (SP2D / Bayar)</strong></td>
                    <td>Joko Bendahara Keuangan, S.Ak.</td>
                    <td>Unit Keuangan & Pencairan</td>
                    <td><code>keuangan</code></td>
                    <td><code>password123</code></td>
                    <td><button type="button" data-username="keuangan" data-password="password123">Gunakan Akun Ini</button></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</section>
@endsection
