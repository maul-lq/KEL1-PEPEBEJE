<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIM-PBJ Politeknik Negeri Jakarta')</title>
    @vite('resources/js/tes_app.js')
</head>
<body>
    {{-- Header Utama Semantik --}}
    <header>
        <h1>Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ)</h1>
        <p><strong>Politeknik Negeri Jakarta</strong> &bull; Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425</p>

        @auth
            <fieldset>
                <legend>Profil Pengguna Aktif</legend>
                <p>
                    <strong>Nama:</strong> {{ Auth::user()->nama }} &bull;
                    <strong>NIP:</strong> {{ Auth::user()->nip }} &bull;
                    <strong>Jabatan:</strong> {{ Auth::user()->jabatan }} &bull;
                    <strong>Unit Kerja:</strong> {{ Auth::user()->unit->nama_unit ?? Auth::user()->kode_unit }} &bull;
                    <strong>Peran Sistem:</strong> <u>{{ strtoupper(str_replace('_', ' ', Auth::user()->role)) }}</u>
                </p>
                <form id="form-logout-app" action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button id="btn-logout-app" type="submit">Keluar (Logout)</button>
                </form>
            </fieldset>
        @endauth
        <hr>
    </header>

    {{-- Navigasi Semantik Berbasis Peran (RBAC) --}}
    @auth
        <nav>
            <strong>Menu Navigasi Operasional:</strong>
            <ul>
                <li><a href="{{ route('dashboard') }}">Beranda (Dashboard)</a></li>

                {{-- Menu Khusus Peran: Pemohon / Jurusan (user) --}}
                @if(Auth::user()->role === 'user')
                    <li><a href="{{ route('permohonan.create') }}">Ajukan Permohonan Pengadaan Baru</a></li>
                    <li><a href="{{ route('permohonan.index') }}">Daftar & Riwayat Permohonan Saya</a></li>
                @endif

                {{-- Menu Khusus Peran: Staf Bidang 2 --}}
                @if(Auth::user()->role === 'staff_bidang_2')
                    <li><a href="{{ route('verifikasi.bidang-2') }}">Antrean Telaah Berkas Permohonan Masuk</a></li>
                @endif

                {{-- Menu Khusus Peran: Wadir 2 --}}
                @if(Auth::user()->role === 'wadir_2')
                    <li><a href="{{ route('verifikasi.wadir-2') }}">Antrean Verifikasi Eksekutif Wadir 2 (ACC/Tolak)</a></li>
                @endif

                {{-- Menu Khusus Peran: Perencanaan --}}
                @if(Auth::user()->role === 'perencanaan')
                    <li><a href="{{ route('verifikasi.perencanaan') }}">Verifikasi Anggaran MAK & Penetapan Pagu</a></li>
                @endif

                {{-- Menu Khusus Peran: PPBJ --}}
                @if(Auth::user()->role === 'ppbj')
                    <li><a href="{{ route('paket.index') }}">Daftar Paket Pengadaan & Alih Tugas PPK</a></li>
                    <li><a href="{{ route('paket.create') }}">Registrasi Paket Baru (Auto-Routing HPS)</a></li>
                    <li><a href="{{ route('pembayaran.index') }}">Pengajuan Berkas Pembayaran & Terbitkan SPP</a></li>
                @endif

                {{-- Menu Khusus Peran: Pejabat Pengadaan (PP) --}}
                @if(Auth::user()->role === 'pp')
                    <li><a href="{{ route('kontrak.index') }}">Paket Pengadaan & Penerbitan SPK (&le; 200 Juta)</a></li>
                @endif

                {{-- Menu Khusus Peran: Pejabat Pembuat Komitmen (PPK 1-4 Terisolasi) --}}
                @if(Auth::user()->role === 'ppk')
                    <li><a href="{{ route('kontrak.index') }}">Paket Komitmen Saya (> 200 Juta - Terisolasi)</a></li>
                    <li><a href="{{ route('pembayaran.index') }}">Persetujuan Memo Pembayaran (PPK)</a></li>
                @endif

                {{-- Menu Khusus Peran: Wadir 2 --}}
                @if(Auth::user()->role === 'wadir_2')
                    <li><a href="{{ route('pembayaran.index') }}">Disposisi Memo Pembayaran (Wadir II)</a></li>
                @endif

                {{-- Menu Khusus Peran: Perlengkapan / BMN --}}
                @if(Auth::user()->role === 'perlengkapan')
                    <li><a href="{{ route('penerimaan.index') }}">Penerimaan Fisik Barang & Penerbitan BAST</a></li>
                @endif

                {{-- Menu Khusus Peran: Keuangan / Bendahara --}}
                @if(Auth::user()->role === 'keuangan')
                    <li><a href="{{ route('pembayaran.index') }}">Verifikasi Tagihan SPP & Pencairan Dana (SP2D)</a></li>
                @endif

                {{-- Menu Terbuka untuk Seluruh Peran --}}
                <li><a href="{{ route('monitoring.index') }}">Monitoring Tahapan Siklus Pengadaan & Jejak Audit</a></li>
                <li><a href="{{ route('laporan.rekapitulasi') }}">Laporan Rekapitulasi Total Anggaran</a></li>
            </ul>
        </nav>
        <hr>
    @endauth

    {{-- Penampil Notifikasi Flash & Validasi --}}
    @if(session('success'))
        <section>
            <p><strong>[PEMBERITAHUAN BERHASIL]:</strong> {{ session('success') }}</p>
        </section>
        <hr>
    @endif

    @if(session('error'))
        <section>
            <p><strong>[PERINGATAN / KENDALA]:</strong> {{ session('error') }}</p>
        </section>
        <hr>
    @endif

    @if($errors->any())
        <section>
            <strong>[DATA MASUKAN BELUM LENGKAP]:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
        <hr>
    @endif

    {{-- Konten Utama Halaman --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer Resmi Informasi Institusi Politeknik Negeri Jakarta --}}
    <footer>
        <hr>
        <p>
            <small>
                &copy; {{ date('Y') }} <strong>SIM-PBJ Politeknik Negeri Jakarta</strong>. Seluruh hak cipta dilindungi.
                <br>
                <em>Sistem Informasi Manajemen Pengadaan Barang dan Jasa &bull; Kampus Politeknik Negeri Jakarta.</em>
            </small>
        </p>
    </footer>
</body>
</html>
