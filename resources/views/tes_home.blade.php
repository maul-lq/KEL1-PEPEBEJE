<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Beranda Dashboard - SIM-PBJ Politeknik Negeri Jakarta</title>
</head>
<body>

    <!-- Header & Informasi Pengguna Login -->
    <header>
        <h1>Beranda Dashboard - SIM-PBJ Politeknik Negeri Jakarta</h1>
        <p>Sistem Informasi Manajemen Pengadaan Barang dan Jasa &bull; Antarmuka Pengujian & Simulasi Backend</p>

        @if ($user)
            <p>
                Pengguna Aktif: <strong>{{ $user->nama }}</strong> |
                Peran: <strong>{{ strtoupper(str_replace('_', ' ', $user->role)) }}</strong> |
                Unit Kerja: <strong>{{ $user->unit ? $user->unit->nama_unit : ($user->kode_unit ?? '-') }}</strong> |
                NIP: <code>{{ $user->nip }}</code>
            </p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout (Keluar Sesi)</button>
            </form>
        @else
            <p>
                Status: <em>Belum Login (Tamu)</em> |
                <a href="{{ route('login') }}">Ke Halaman Login &rarr;</a>
            </p>
        @endif

        <hr>

        <!-- Menu Navigasi Modul Simulasi Pengujian Backend -->
        <nav>
            <h3>Navigasi Modul Pengujian & Simulasi Backend:</h3>
            <ul>
                <li><a href="{{ route('permohonan.web') }}">1. Simulasi Permohonan Srikandi & Revisi (FR 3 & FR 4)</a></li>
                <li><a href="{{ route('verifikasi-b2.web') }}">2. Simulasi Verifikasi Bidang 2 & Persetujuan Wadir 2 (FR 5)</a></li>
                <li><a href="{{ route('paket.web') }}">3. Simulasi Paket Pengadaan & Alih Tugas PPK (NFR 4 & FR 7)</a></li>
                <li><a href="{{ route('monitoring.web') }}">4. Simulasi Monitoring 8 Tahapan Berkas & SLA Respon (NFR 7)</a></li>
                <li><a href="{{ route('test.api') }}">5. Konsol Pengujian 17 Endpoint RESTful API Backend</a></li>
            </ul>
        </nav>
    </header>

    <hr>

    <!-- Bagian 1: Ringkasan Statistik Agregat -->
    <section>
        <h2>Ringkasan Statistik Sistem (Agregat Database)</h2>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Total Unit Kerja / Jurusan</th>
                    <th>Total Usulan Permohonan Masuk</th>
                    <th>Akumulasi Pagu Anggaran (MAK)</th>
                    <th>Total Paket Pengadaan</th>
                    <th>Jumlah Rekanan / Vendor</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td align="center"><strong>{{ $stats['total_units'] }}</strong> Unit</td>
                    <td align="center"><strong>{{ $stats['total_permohonan'] }}</strong> Berkas</td>
                    <td align="right"><strong>Rp {{ number_format($stats['total_anggaran'], 0, ',', '.') }}</strong></td>
                    <td align="center"><strong>{{ $stats['total_paket'] }}</strong> Paket</td>
                    <td align="center"><strong>{{ $stats['total_vendor'] }}</strong> Perusahaan</td>
                </tr>
            </tbody>
        </table>
    </section>

    <hr>

    <!-- Bagian 2: Daftar Permohonan Pengadaan Terbaru -->
    <section>
        <h2>5 Usulan Permohonan Pengadaan Terbaru</h2>
        @if ($recentPermohonan->isEmpty())
            <p><em>Belum ada data usulan permohonan pengadaan yang tercatat.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Surat Srikandi</th>
                        <th>Judul Pengadaan</th>
                        <th>Unit Pengusul</th>
                        <th>Pemohon</th>
                        <th>Estimasi Pagu</th>
                        <th>Status Permohonan</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentPermohonan as $p)
                        <tr>
                            <td><code>{{ $p->nomor_surat }}</code></td>
                            <td>{{ $p->judul_pengadaan }}</td>
                            <td>{{ $p->unit ? $p->unit->nama_unit : $p->kode_unit }}</td>
                            <td>{{ $p->user ? $p->user->nama : '-' }}</td>
                            <td align="right">Rp {{ number_format($p->estimasi_total_pagu ?? 0, 0, ',', '.') }}</td>
                            <td align="center"><strong>[{{ strtoupper($p->status_permohonan) }}]</strong></td>
                            <td align="center">{{ $p->tanggal_pengajuan ? $p->tanggal_pengajuan->format('d/m/Y H:i') : '-' }}</td>
                            <td align="center">
                                <a href="{{ route('monitoring.web', ['nomor_surat' => $p->nomor_surat]) }}">Lacak Posisi</a> |
                                <a href="{{ route('permohonan.pdf', ['permohonan_pengadaan' => $p->nomor_surat]) }}" target="_blank">Unduh PDF</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p>
            <a href="{{ route('permohonan.web') }}">&rarr; Lihat Seluruh Permohonan & Formulir Pengajuan</a>
        </p>
    </section>

    <hr>

    <!-- Bagian 3: Paket Pengadaan Terbaru (Tersaring Berdasarkan Wewenang Akun) -->
    <section>
        <h2>5 Paket Pengadaan Terbaru (Hak Akses Peran)</h2>
        @if ($recentPaket->isEmpty())
            <p><em>Tidak ada paket pengadaan yang dapat ditampilkan untuk hak akses peran Anda saat ini.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Paket</th>
                        <th>Nama Paket</th>
                        <th>Nilai HPS</th>
                        <th>Jalur Routing</th>
                        <th>Pejabat Ditugaskan</th>
                        <th>Status Paket</th>
                        <th>Tanggal Registrasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentPaket as $pkt)
                        <tr>
                            <td><code>{{ $pkt->nomor_paket }}</code></td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td align="right">Rp {{ number_format($pkt->nilai_hps ?? 0, 0, ',', '.') }}</td>
                            <td align="center"><code>{{ $pkt->jalur_routing }}</code></td>
                            <td>
                                @if ($pkt->ppkUser)
                                    PPK: {{ $pkt->ppkUser->nama }}
                                @elseif ($pkt->ppUser)
                                    PP: {{ $pkt->ppUser->nama }}
                                @else
                                    <em>Belum Ditugaskan</em>
                                @endif
                            </td>
                            <td align="center"><strong>[{{ strtoupper($pkt->status_paket) }}]</strong></td>
                            <td align="center">{{ $pkt->tanggal_registrasi_paket ? $pkt->tanggal_registrasi_paket->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <p>
            <a href="{{ route('paket.web') }}">&rarr; Buka Modul Registrasi Paket & Alih Penugasan PPK</a>
        </p>
    </section>

    <hr>

    <!-- Bagian 4: Notifikasi Pengguna -->
    <section>
        <h2>Notifikasi & Pengingat Pengguna</h2>
        @if ($notifications->isEmpty())
            <p><em>Tidak ada notifikasi baru saat ini.</em></p>
        @else
            <ul>
                @foreach ($notifications as $n)
                    <li>
                        <strong>{{ $n->reminder ? $n->reminder->judul_alert : 'Pemberitahuan Sistem' }}</strong><br>
                        {{ $n->reminder ? $n->reminder->pesan_alert : '-' }}<br>
                        <small>Waktu: {{ $n->created_at ? $n->created_at->diffForHumans() : '-' }}</small>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <hr>

    <!-- Footer -->
    <footer>
        <p>
            <small>&copy; SIM-PBJ Politeknik Negeri Jakarta &bull; Plain HTML Simulation</small>
        </p>
    </footer>

</body>
</html>
