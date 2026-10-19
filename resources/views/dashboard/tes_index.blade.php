@extends('layouts.tes_app')

@section('title', 'Beranda Operasional - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<section>
    <h2>Beranda Operasional SIM-PBJ PNJ</h2>
    <p>
        Selamat datang, <strong>{{ $user->nama }}</strong> ({{ $user->jabatan }} &bull; {{ $user->unit->nama_unit ?? $user->kode_unit }}).
        Sistem saat ini aktif dalam mode penugasan peran: <strong>{{ strtoupper(str_replace('_', ' ', $user->role)) }}</strong>.
    </p>

    {{-- Ringkasan Agregat Sistem SIM-PBJ --}}
    <fieldset>
        <legend>Statistik Agregat Sistem Institusi</legend>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Total Unit & Jurusan</th>
                    <th>Total Usulan Permohonan</th>
                    <th>Total Paket Pengadaan</th>
                    <th>Akumulasi Pagu DIPA</th>
                    <th>Rekanan / Vendor Terdaftar</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td align="center"><strong>{{ $stats['total_units'] ?? 0 }}</strong></td>
                    <td align="center"><strong>{{ $stats['total_permohonan'] ?? 0 }}</strong></td>
                    <td align="center"><strong>{{ $stats['total_paket'] ?? 0 }}</strong></td>
                    <td align="center"><strong>Rp {{ number_format($stats['total_anggaran'] ?? 0, 0, ',', '.') }}</strong></td>
                    <td align="center"><strong>{{ $stats['total_vendor'] ?? 0 }}</strong></td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</section>

<hr>

{{-- ========================================================================= --}}
{{-- 1. TAMPILAN KHUSUS PERAN: USER (PEMOHON / JURUSAN)                        --}}
{{-- ========================================================================= --}}
@if($user->role === 'user')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Pemohon (Unit / Jurusan)</strong></legend>
            <p>
                Anda berhak mengajukan usulan permohonan pengadaan barang/jasa dari unit kerja Anda, memantau alur verifikasi,
                melihat alasan jika usulan ditolak verifikator, serta mengunduh dokumen surat permohonan resmi berformat PDF.
            </p>
            <p>
                <a href="{{ route('permohonan.create') }}"><button type="button"><strong>+ Ajukan Permohonan Pengadaan Baru</strong></button></a>
                &nbsp;
                <a href="{{ route('permohonan.index') }}"><button type="button">Lihat Riwayat Lengkap & Status Usulan</button></a>
            </p>

            <h3>Daftar Permohonan Pengadaan Terbaru Anda</h3>
            @if($recentPermohonan->isEmpty())
                <p><em>Belum ada permohonan pengadaan yang Anda ajukan. Silakan klik tombol di atas untuk mengajukan usulan baru.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor Surat Srikandi</th>
                            <th>Judul / Perihal Pengadaan</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Estimasi Anggaran</th>
                            <th>Status Usulan</th>
                            <th>Verifikator & Catatan</th>
                            <th>Aksi Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPermohonan as $p)
                            <tr>
                                <td><strong>{{ $p->nomor_surat }}</strong></td>
                                <td>{{ $p->perihal }}</td>
                                <td>{{ $p->tanggal_pengajuan ? date('d-m-Y', strtotime($p->tanggal_pengajuan)) : '-' }}</td>
                                <td>Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</td>
                                <td>
                                    <strong>{{ strtoupper(str_replace('_', ' ', $p->status)) }}</strong>
                                </td>
                                <td>
                                    @if($p->catatan_revisi)
                                        <details>
                                            <summary><em>Ada Catatan Penolakan/Revisi</em></summary>
                                            <p>{{ $p->catatan_revisi }}</p>
                                        </details>
                                    @else
                                        <small>Menunggu proses verifikasi</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                                        <button type="button">Unduh PDF Resmi</button>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 2. TAMPILAN KHUSUS PERAN: STAF BIDANG 2                                   --}}
{{-- ========================================================================= --}}
@if($user->role === 'staff_bidang_2')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Staf Bidang II (Pemeriksa Berkas Administrasi)</strong></legend>
            <p>
                Tugas utama Staf Bidang 2 adalah memeriksa kelengkapan berkas fisik dan lampiran Srikandi (dokumen KAK, spesifikasi teknis)
                dari permohonan yang masuk sebelum diteruskan secara resmi kepada Wakil Direktur II.
            </p>
            <p>
                <a href="{{ route('verifikasi.bidang-2') }}">
                    <button type="button"><strong>Buka Antrean Telaah Berkas Permohonan Masuk</strong></button>
                </a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Surat</th>
                        <th>Unit Pengusul</th>
                        <th>Perihal Pengadaan</th>
                        <th>Estimasi Nilai</th>
                        <th>Status Telaah Staf</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPermohonan as $p)
                        <tr>
                            <td>{{ $p->nomor_surat }}</td>
                            <td>{{ $p->unit->nama_unit ?? $p->kode_unit }}</td>
                            <td>{{ $p->perihal }}</td>
                            <td>Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</td>
                            <td>{{ strtoupper($p->status) }}</td>
                            <td>
                                <a href="{{ route('verifikasi.bidang-2') }}">
                                    <button type="button">Periksa Kelengkapan</button>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Tidak ada permohonan dalam antrean saat ini.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 3. TAMPILAN KHUSUS PERAN: WADIR 2                                         --}}
{{-- ========================================================================= --}}
@if($user->role === 'wadir_2')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Wakil Direktur II (Otoritas Mutlak Persetujuan / ACC)</strong></legend>
            <p>
                Sebagai Wakil Direktur Bidang Umum dan Keuangan, Anda memegang hak mutlak persetujuan institusi (ACC atau Penolakan/Revisi).
                Permohonan yang telah disetujui bersama Bagian Perencanaan akan otomatis beralih status ke <strong>DISANGKUPI / DISETUJUI</strong> dan diteruskan ke PPBJ.
            </p>
            <p>
                <a href="{{ route('verifikasi.wadir-2') }}">
                    <button type="button"><strong>Buka Antrean Verifikasi Eksekutif Wadir 2</strong></button>
                </a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Surat Srikandi</th>
                        <th>Unit Pengusul</th>
                        <th>Perihal Usulan</th>
                        <th>Estimasi Anggaran</th>
                        <th>Status Wadir 2</th>
                        <th>Aksi Cepat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPermohonan as $p)
                        <tr>
                            <td><strong>{{ $p->nomor_surat }}</strong></td>
                            <td>{{ $p->unit->nama_unit ?? $p->kode_unit }}</td>
                            <td>{{ $p->perihal }}</td>
                            <td>Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $p->status)) }}</td>
                            <td>
                                <a href="{{ route('verifikasi.wadir-2') }}">
                                    <button type="button">Beri Keputusan ACC / Tolak</button>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Tidak ada permohonan antrean verifikasi eksekutif saat ini.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 4. TAMPILAN KHUSUS PERAN: PERENCANAAN                                     --}}
{{-- ========================================================================= --}}
@if($user->role === 'perencanaan')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Subbagian Perencanaan & Anggaran MAK</strong></legend>
            <p>
                Anda bertugas memverifikasi ketersediaan pagu dana DIPA, menetapkan kode Mata Anggaran Kegiatan (MAK),
                serta mengunci anggaran MAK untuk mencegah manipulasi pagu kas.
            </p>
            <p>
                <a href="{{ route('verifikasi.perencanaan') }}">
                    <button type="button"><strong>Buka Antrean Verifikasi & Penetapan MAK</strong></button>
                </a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Surat Srikandi</th>
                        <th>Unit Pengusul</th>
                        <th>Perihal Pengadaan</th>
                        <th>Estimasi Nilai</th>
                        <th>Status Anggaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPermohonan as $p)
                        <tr>
                            <td>{{ $p->nomor_surat }}</td>
                            <td>{{ $p->unit->nama_unit ?? $p->kode_unit }}</td>
                            <td>{{ $p->perihal }}</td>
                            <td>Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</td>
                            <td>{{ strtoupper($p->status) }}</td>
                            <td>
                                <a href="{{ route('verifikasi.perencanaan') }}">
                                    <button type="button">Alokasikan MAK</button>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Tidak ada usulan permohonan menunggu penetapan MAK.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 5. TAMPILAN KHUSUS PERAN: PPBJ                                            --}}
{{-- ========================================================================= --}}
@if($user->role === 'ppbj')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Koordinator PPBJ (Registrasi Paket & Penugasan)</strong></legend>
            <p>
                Koordinator PPBJ mengelola registrasi paket pengadaan, penentuan metode, dan penugasan dengan aturan auto-routing HPS presisi 100%:
                <br>
                &bull; &le; Rp 50 Juta: Pengadaan Langsung
                <br>
                &bull; Rp 50 Juta s.d Rp 200 Juta: Pejabat Pengadaan (PP)
                <br>
                &bull; &gt; Rp 200 Juta: Pejabat Pembuat Komitmen (1 dari 4 akun PPK PNJ)
            </p>
            <p>
                <a href="{{ route('paket.create') }}"><button type="button"><strong>+ Registrasi Paket Pengadaan Baru</strong></button></a>
                &nbsp;
                <a href="{{ route('paket.index') }}"><button type="button">Kelola Paket & Alih Tugas PPK (Reassign)</button></a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode Paket</th>
                        <th>Nama Paket Pengadaan</th>
                        <th>Nilai HPS</th>
                        <th>Jalur Routing HPS</th>
                        <th>Pejabat Ditugaskan</th>
                        <th>Status Siklus</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPaket as $pkt)
                        <tr>
                            <td><strong>{{ $pkt->kode_paket }}</strong></td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td>Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}</td>
                            <td><u>{{ strtoupper(str_replace('_', ' ', $pkt->jalur_routing)) }}</u></td>
                            <td>
                                @if($pkt->jalur_routing === 'ppk_diatas_200jt')
                                    PPK: {{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }}
                                @else
                                    PP: {{ $pkt->ppUser->nama ?? 'Pejabat Pengadaan' }}
                                @endif
                            </td>
                            <td>{{ strtoupper($pkt->status_paket) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Belum ada paket pengadaan yang terdaftar.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 6. TAMPILAN KHUSUS PERAN: PEJABAT PENGADAAN (PP)                          --}}
{{-- ========================================================================= --}}
@if($user->role === 'pp')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Pejabat Pengadaan (Paket &le; Rp 200 Juta)</strong></legend>
            <p>
                Pejabat Pengadaan menangani paket pengadaan langsung dan pemilihan rekanan penyedia bernilai sampai dengan Rp 200 Juta,
                serta menerbitkan Surat Perintah Kerja (SPK).
            </p>
            <p>
                <a href="{{ route('kontrak.index') }}">
                    <button type="button"><strong>Buka Daftar Paket Pengadaan & Penerbitan SPK</strong></button>
                </a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode Paket</th>
                        <th>Nama Paket</th>
                        <th>Nilai HPS</th>
                        <th>Metode Pengadaan</th>
                        <th>Status SPK</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPaket as $pkt)
                        <tr>
                            <td>{{ $pkt->kode_paket }}</td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td>Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}</td>
                            <td>{{ $pkt->metode_pengadaan }}</td>
                            <td>{{ strtoupper($pkt->status_paket) }}</td>
                            <td>
                                <a href="{{ route('kontrak.index') }}">
                                    <button type="button">Terbitkan / Lihat SPK</button>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Tidak ada paket pengadaan PP aktif saat ini.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 7. TAMPILAN KHUSUS PERAN: PEJABAT PEMBUAT KOMITMEN (PPK)                  --}}
{{-- ========================================================================= --}}
@if($user->role === 'ppk')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Pejabat Pembuat Komitmen (Paket &gt; Rp 200 Juta - Terisolasi)</strong></legend>
            <p>
                <strong>Penegakan Isolasi Data:</strong> Anda hanya dapat melihat dan mengelola paket komitmen yang ditugaskan secara spesifik
                kepada akun Anda (<strong>{{ $user->nama }}</strong>). Paket milik PPK lainnya tidak akan tampil pada daftar ini.
            </p>
            <p>
                <a href="{{ route('kontrak.index') }}">
                    <button type="button"><strong>Buka Daftar Paket Komitmen Saya & SPK</strong></button>
                </a>
            </p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode Paket</th>
                        <th>Nama Paket Komitmen</th>
                        <th>Nilai HPS (> 200jt)</th>
                        <th>PPK Penanggung Jawab</th>
                        <th>Status Paket</th>
                        <th>Aksi Kontrak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPaket as $pkt)
                        <tr>
                            <td><strong>{{ $pkt->kode_paket }}</strong></td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td>Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}</td>
                            <td>{{ $pkt->ppkUser->nama ?? $user->nama }}</td>
                            <td>{{ strtoupper($pkt->status_paket) }}</td>
                            <td>
                                <a href="{{ route('kontrak.index') }}">
                                    <button type="button">Reviu & Terbitkan Kontrak</button>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" align="center"><em>Tidak ada paket bernilai di atas Rp 200 juta yang ditugaskan ke akun PPK Anda saat ini.</em></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 8. TAMPILAN KHUSUS PERAN: PERLENGKAPAN (BMN / PENERIMAAN BARANG)          --}}
{{-- ========================================================================= --}}
@if($user->role === 'perlengkapan')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Unit Perlengkapan (Pemeriksaan Fisik & BAST)</strong></legend>
            <p>
                Bagian Perlengkapan bertugas memeriksa kedatangan fisik barang dari penyedia, mengunggah foto dokumentasi fisik,
                menerbitkan Berita Acara Serah Terima (BAST) bertoken HMAC resmi, dan meneruskannya ke Bagian Keuangan.
            </p>
            <p>
                <a href="{{ route('penerimaan.index') }}">
                    <button type="button"><strong>Buka Daftar Pemeriksaan Fisik & Penerbitan BAST</strong></button>
                </a>
            </p>
        </fieldset>
    </section>
@endif

{{-- ========================================================================= --}}
{{-- 9. TAMPILAN KHUSUS PERAN: KEUANGAN (BENDAHARA / PENCAIRAN)                --}}
{{-- ========================================================================= --}}
@if($user->role === 'keuangan')
    <section>
        <fieldset>
            <legend><strong>Ruang Kerja Bagian Keuangan (Verifikasi SPP & Pencairan Dana SP2D)</strong></legend>
            <p>
                Bagian Keuangan memverifikasi berkas serah terima dari Perlengkapan, menerbitkan memo bayar,
                mengeksekusi pencairan dana SP2D anti-pembayaran ganda, serta mengunggah slip bukti transfer pemindahbukuan bank.
            </p>
            <p>
                <a href="{{ route('pembayaran.index') }}">
                    <button type="button"><strong>Buka Antrean Verifikasi Tagihan & Pencairan Dana</strong></button>
                </a>
            </p>
        </fieldset>
    </section>
@endif

<hr>

{{-- Notifikasi Sistem Pengguna Aktif --}}
<section>
    <fieldset>
        <legend>Pemberitahuan & Pengingat Jadwal Sistem</legend>
        @if($notifications->isEmpty())
            <p><em>Tidak ada pemberitahuan atau alert pengingat baru untuk Anda saat ini.</em></p>
        @else
            <ul>
                @foreach($notifications as $notif)
                    <li>
                        <strong>[{{ date('d-m-Y H:i', strtotime($notif->created_at)) }}]</strong>
                        <strong>{{ $notif->judul }}</strong>: {{ $notif->pesan }}
                        <form action="{{ route('notifikasi.dismiss', $notif->id) }}" method="POST">
                            @csrf
                            <button type="submit">Tandai Telah Dibaca</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </fieldset>
</section>
@endsection
