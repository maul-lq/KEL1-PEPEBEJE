<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIM-PBJ Politeknik Negeri Jakarta')</title>
    @vite(['resources/css/tes_app.css', 'resources/js/tes_app.js'])
</head>
<body>
    {{-- Header Utama Semantik --}}
    <header>
        <h1>Sistem Informasi Manajemen Pengadaan Barang dan Jasa (SIM-PBJ)</h1>
        <p><strong>Politeknik Negeri Jakarta</strong> &bull; Jl. Prof. Dr. G.A. Siwabessy, Kampus UI Depok 16425</p>
        <p><small><strong>Mode Tampilan Aktif:</strong> Backend (Zero CSS - Berkas <code>tes_*</code>) &bull; Flag: <code>DEV_VIEWMODE={{ config('app.dev_viewmode', 'backend') }}</code></small></p>

        @auth
            <fieldset>
                <legend>Profil Pengguna Aktif</legend>
                <p>
                    <strong>Nama:</strong> {{ Auth::user()->nama }} &bull;
                    <strong>NIP:</strong> {{ Auth::user()->nip }} &bull;
                    <strong>Jabatan:</strong> {{ Auth::user()->jabatan }} &bull;
                    <strong>Unit Kerja:</strong> {{ Auth::user()->unit->nama_unit ?? Auth::user()->kode_unit }} &bull;
                    <strong>Peran Sistem:</strong> <u>{{ Auth::user()->roleEntity ? Auth::user()->roleEntity->label . ' (' . strtoupper(Auth::user()->role) . ')' : strtoupper(str_replace('_', ' ', Auth::user()->role)) }}</u> &bull;
                    <a href="{{ route('notifikasi.index') }}"><strong>Notifikasi Personal</strong> ({{ \App\Models\NotifikasiUser::where('id_user', Auth::id())->where('is_read', false)->count() }} belum dibaca)</a>
                </p>
                <form id="form-logout-app" action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button id="btn-logout-app" type="submit">Keluar (Logout)</button>
                </form>
            </fieldset>

            {{-- Panel Status & Izin Notifikasi Desktop OS (Zero CSS) --}}
            <section id="section-os-notification">
                <fieldset>
                    <legend>Status &amp; Izin Notifikasi Desktop Komputer (OS Windows / Mac)</legend>
                    <p>
                        Status Izin Desktop: <strong id="os-notif-status-badge">Mengecek izin...</strong> &bull;
                        <button type="button" id="btn-request-os-notif" data-action="request-os-notification">Minta / Aktifkan Izin Notifikasi Desktop</button>
                        &bull;
                        <button type="button" id="btn-test-os-notif" data-action="test-os-notification">Uji Munculkan Notifikasi Pop-up Desktop</button>
                        &bull;
                        <a href="{{ route('notifikasi.index') }}"><button type="button">Buka Pusat Notifikasi &amp; Alarm</button></a>
                    </p>
                    <p id="os-notif-message-box">
                        <small><em>Notifikasi desktop memunculkan alert suara dan pop-up resmi di Windows Action Center / macOS Notification Center secara real-time saat usulan disetujui, ditolak, atau ada batas waktu pengadaan.</em></small>
                    </p>
                </fieldset>
            </section>
        @endauth
        <hr>
    </header>

    {{-- Navigasi Semantik Berbasis Peran (RBAC) --}}
    @auth
        <nav>
            <strong>Menu Navigasi Operasional:</strong>
            <ul>
                <li><a href="{{ route('dashboard') }}">Beranda (Dashboard)</a></li>

                {{-- Menu Khusus Peran: Administrator (admin) --}}
                @if(Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['admin.approval_registrasi', 'admin.role_manage', 'admin.user_manage']))
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.approval_registrasi'))
                        <li><a href="{{ route('admin.persetujuan-registrasi.index') }}">Persetujuan Registrasi Pengguna Baru</a></li>
                    @endif
                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('admin.role_manage'))
                        <li><a href="{{ route('admin.roles.index') }}">Manajemen Role & Hak Akses (Permissions)</a></li>
                    @endif
                @endif

                {{-- Menu Khusus Peran: Pemohon / Jurusan (user) --}}
                @if(Auth::user()->hasRole('user') || Auth::user()->hasAnyPermission(['permohonan.create', 'permohonan.view_own', 'permohonan.view_all', 'permohonan.revisi']))
                    @if(Auth::user()->hasRole('user') || Auth::user()->hasPermission('permohonan.create'))
                        <li><a href="{{ route('permohonan.create') }}">Ajukan Permohonan Pengadaan Baru</a></li>
                    @endif
                    <li><a href="{{ route('permohonan.index') }}">Daftar & Riwayat Permohonan Saya</a></li>
                @endif

                {{-- Menu Khusus Peran: Staf Bidang 2 --}}
                @if(Auth::user()->hasRole('staff_bidang_2') || Auth::user()->hasAnyPermission(['verifikasi.bidang_2_view', 'verifikasi.bidang_2_teruskan']))
                    <li><a href="{{ route('verifikasi.bidang-2') }}">Antrean Telaah Berkas Permohonan Masuk</a></li>
                @endif

                {{-- Menu Khusus Peran: Wadir 2 --}}
                @if(Auth::user()->hasRole('wadir_2') || Auth::user()->hasAnyPermission(['verifikasi.wadir_2_view', 'verifikasi.wadir_2_decide']))
                    <li><a href="{{ route('verifikasi.wadir-2') }}">Antrean Verifikasi Eksekutif Wadir 2 (ACC/Tolak)</a></li>
                @endif

                {{-- Menu Khusus Peran: Perencanaan --}}
                @if(Auth::user()->hasRole('perencanaan') || Auth::user()->hasAnyPermission(['anggaran.mak_view', 'anggaran.mak_manage', 'anggaran.mak_alokasi', 'anggaran.mak_lock', 'permohonan.split_mak']))
                    <li><a href="{{ route('verifikasi.perencanaan') }}">Verifikasi Anggaran MAK & Penetapan Pagu</a></li>
                @endif

                {{-- Menu Khusus Peran: PPBJ --}}
                @if(Auth::user()->hasRole('ppbj') || Auth::user()->hasAnyPermission(['paket.create', 'paket.view_all', 'paket.reassign_ppk', 'paket.split_jenis', 'spk.create', 'reminder.manage', 'pembayaran.pengajuan', 'pembayaran.spp_terbitkan']))
                    <li><a href="{{ route('paket.index') }}">Daftar Paket Pengadaan, Split Jenis & Alih Tugas PPK</a></li>
                    @if(Auth::user()->hasRole('ppbj') || Auth::user()->hasPermission('paket.create'))
                        <li><a href="{{ route('paket.create') }}">Registrasi Paket Baru (Auto-Routing HPS)</a></li>
                    @endif
                    @if(Auth::user()->hasRole('ppbj') || Auth::user()->hasAnyPermission(['spk.create', 'reminder.manage']))
                        <li><a href="{{ route('kontrak.index') }}">Penerbitan Surat Kontrak / SPK & Reminder (PPBJ)</a></li>
                    @endif
                    @if(Auth::user()->hasRole('ppbj') || Auth::user()->hasAnyPermission(['pembayaran.pengajuan', 'pembayaran.spp_terbitkan']))
                        <li><a href="{{ route('pembayaran.index') }}">Pengajuan Berkas Pembayaran & Terbitkan SPP</a></li>
                    @endif
                @endif

                {{-- Menu Khusus Peran: Pejabat Pengadaan (PP) --}}
                @if(Auth::user()->hasRole('pp') || Auth::user()->hasPermission('reviu.pp'))
                    <li><a href="{{ route('kontrak.index') }}">Verifikasi & Negosiasi Harga Paket (&gt; 50 Juta s.d &lt; 200 Juta)</a></li>
                @endif

                {{-- Menu Khusus Peran: Pejabat Pembuat Komitmen (PPK 1-4 Terisolasi) --}}
                @if(Auth::user()->hasRole('ppk') || Auth::user()->hasAnyPermission(['reviu.ppk', 'pembayaran.memo_ppk']))
                    @if(Auth::user()->hasRole('ppk') || Auth::user()->hasPermission('reviu.ppk'))
                        <li><a href="{{ route('kontrak.index') }}">Tinjau Dokumen & Kunci MAK Paket Komitmen Saya (Terisolasi)</a></li>
                    @endif
                    @if(Auth::user()->hasRole('ppk') || Auth::user()->hasPermission('pembayaran.memo_ppk'))
                        <li><a href="{{ route('pembayaran.index') }}">Persetujuan Memo Pembayaran (PPK)</a></li>
                    @endif
                @endif

                {{-- Menu Khusus Peran: Wadir 2 (Memo Pembayaran) --}}
                @if(Auth::user()->hasRole('wadir_2') || Auth::user()->hasPermission('pembayaran.memo_wadir2'))
                    <li><a href="{{ route('pembayaran.index') }}">Disposisi Memo Pembayaran (Wadir II)</a></li>
                @endif

                {{-- Menu Khusus Peran: Perlengkapan / BMN --}}
                @if(Auth::user()->hasRole('perlengkapan') || Auth::user()->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create', 'penerimaan.bast_sign']))
                    <li><a href="{{ route('penerimaan.index') }}">Penerimaan Fisik Barang & Penerbitan BAST</a></li>
                @endif

                {{-- Menu Khusus Peran: Keuangan / Bendahara --}}
                @if(Auth::user()->hasRole('keuangan') || Auth::user()->hasPermission('pembayaran.pencairan_sp2d'))
                    <li><a href="{{ route('pembayaran.index') }}">Verifikasi Tagihan SPP & Pencairan Dana (SP2D)</a></li>
                @endif

                {{-- Menu Terbuka untuk Seluruh Peran --}}
                <li><a href="{{ route('notifikasi.index') }}">Pusat Notifikasi, Pengingat Jadwal &amp; Alarm PBJ</a></li>
                @if(Auth::user()->hasRole(['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan']) || Auth::user()->hasPermission('monitoring.view'))
                    <li><a href="{{ route('monitoring.index') }}">Monitoring Tahapan Siklus Pengadaan & Jejak Audit</a></li>
                @endif
                @if(Auth::user()->hasRole(['admin', 'user', 'staff_bidang_2', 'wadir_2', 'perencanaan', 'ppbj', 'pp', 'ppk', 'perlengkapan', 'keuangan']) || Auth::user()->hasPermission('laporan.rekapitulasi'))
                    <li><a href="{{ route('laporan.rekapitulasi') }}">Laporan Rekapitulasi Total Anggaran</a></li>
                @endif
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
