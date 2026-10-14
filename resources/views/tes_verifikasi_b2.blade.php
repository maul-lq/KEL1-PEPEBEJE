<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Simulasi Verifikasi Bidang 2 & Wadir 2 (FR 5)</title>
</head>
<body>

    <header>
        <h1>Simulasi Verifikasi Bidang 2 & Persetujuan Eksekutif Wadir 2 (FR 5)</h1>
        <p>SIM-PBJ Politeknik Negeri Jakarta &bull; Khusus Pengujian & Simulasi Backend</p>
        <p>
            Login: <strong>{{ $user ? $user->nama : 'Guest' }}</strong> ({{ $user ? strtoupper($user->role) : '-' }}) |
            <a href="{{ route('home') }}">&larr; Beranda</a> |
            <a href="{{ route('permohonan.web') }}">Permohonan Srikandi (FR 3 & 4)</a> |
            <a href="{{ route('paket.web') }}">Paket Pengadaan (NFR 4)</a> |
            <a href="{{ route('monitoring.web') }}">Monitoring Status (NFR 7) &rarr;</a>
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
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
        <hr>
    @endif

    <!-- Bagian 1: Peran Staff Bidang 2 (Read-Only & Penyampaian Berkas ke Wadir 2 - FR 5.1) -->
    <section>
        <h2>1. Staff Bidang 2: Pemeriksaan & Penyampaian Berkas Masuk (FR 5.1)</h2>
        <p>
            <small>
                * <em>Catatan Bisnis (Client & Notulensi ke-2):</em> Fungsi Staff Bidang 2 bersifat <strong>read-only</strong>. Staff bertugas memeriksa kelengkapan berkas yang masuk dari unit pemohon, lalu menyampaikan/meneruskan berkas tersebut ke Wadir 2 untuk memperoleh persetujuan eksekutif.
            </small>
        </p>
        <form action="{{ route('verifikasi-b2.web.teruskan') }}" method="POST">
            @csrf
            <fieldset>
                <legend>Penyampaian Usulan Permohonan ke Wadir 2</legend>

                <p>
                    <label for="nomor_surat_staff">Pilih Berkas Usulan Permohonan Masuk (*):</label><br>
                    <select id="nomor_surat_staff" name="nomor_surat" required>
                        <option value="">-- Pilih Nomor Surat Usulan --</option>
                        @foreach ($permohonanList as $itemP)
                            @if ($itemP->status_permohonan === 'reviu_paralel')
                                <option value="{{ $itemP->nomor_surat }}">
                                    {{ $itemP->nomor_surat }} - {{ $itemP->judul_pengadaan }} ({{ $itemP->unit ? $itemP->unit->nama_unit : $itemP->kode_unit }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="catatan_pemeriksaan">Catatan Pemeriksaan Staff Bidang 2 (Opsional):</label><br>
                    <textarea id="catatan_pemeriksaan" name="catatan_pemeriksaan" rows="2" cols="60" placeholder="Contoh: Berkas KAK dan lampiran Srikandi telah lengkap dan diteruskan ke Wadir 2."></textarea>
                </p>

                <p>
                    <button type="submit">Sampaikan / Teruskan ke Wadir 2 &rarr;</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Bagian 2: Wadir 2 Hak Mutlak Persetujuan (ACC / Tolak / Minta Revisi - FR 5.2 - FR 5.4) -->
    <section>
        <h2>2. Wadir 2: Hak Mutlak Persetujuan Usulan Pengadaan (FR 5.2 - FR 5.4)</h2>
        <p>
            <small>
                * <strong>Aturan Validasi Wadir 2 (FR 5.3):</strong><br>
                &bull; Jika <strong>Disetujui (ACC)</strong>: Kolom catatan bersifat <strong>opsional</strong>.<br>
                &bull; Jika <strong>Ditolak / Minta Revisi</strong>: Kolom catatan/alasan penolakan <strong>WAJIB diisi</strong> (akan ditolak validasi HTTP 422 bila kosong).<br>
                &bull; Jika Wadir 2 ACC dan Perencanaan juga ACC: Status otomatis menjadi <strong>DISETUJUI</strong> dan diteruskan ke PPBJ (FR 5.4).
            </small>
        </p>
        <form action="{{ route('verifikasi-b2.web.keputusan') }}" method="POST">
            @csrf
            <fieldset>
                <legend>Formulir Keputusan Eksekutif Wadir 2</legend>

                <p>
                    <label for="nomor_surat_wadir">Pilih Berkas Usulan Permohonan (*):</label><br>
                    <select id="nomor_surat_wadir" name="nomor_surat" required>
                        <option value="">-- Pilih Nomor Surat Usulan --</option>
                        @foreach ($permohonanList as $itemW)
                            <option value="{{ $itemW->nomor_surat }}">
                                {{ $itemW->nomor_surat }} - {{ $itemW->judul_pengadaan }} [Status: {{ strtoupper($itemW->status_permohonan) }}]
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="status_keputusan">Keputusan Wadir 2 (*):</label><br>
                    <select id="status_keputusan" name="status_keputusan" required>
                        <option value="disetujui">1. DISETUJUI (ACC Permohonan)</option>
                        <option value="ditolak">2. DITOLAK (Wajib isi alasan penolakan)</option>
                        <option value="revisi">3. PERLU REVISI (Wajib isi catatan perbaikan)</option>
                    </select>
                </p>

                <p>
                    <label for="catatan_alasan_wadir">Catatan / Alasan Keputusan Wadir 2:</label><br>
                    <textarea id="catatan_alasan_wadir" name="catatan_alasan" rows="3" cols="70" placeholder="Wajib diisi jika menolak atau meminta revisi permohonan.."></textarea>
                </p>

                <p>
                    <button type="submit">Simpan Keputusan Wadir 2 &rarr;</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Bagian 3: Tabel Status Verifikasi Paralel Usulan -->
    <section>
        <h2>3. Status Alur Verifikasi Paralel (Real-Time Database)</h2>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Nomor Surat Srikandi</th>
                    <th>Judul Usulan & Unit</th>
                    <th>Estimasi Pagu</th>
                    <th>Status Permohonan</th>
                    <th>Keputusan Wadir 2</th>
                    <th>Keputusan Perencanaan</th>
                    <th>Catatan Verifikator</th>
                    <th>Aksi Unduh Berkas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($permohonanList as $p)
                    @php
                        $vWadir = $p->verifikasiParalel->where('role_verifikator', 'wadir_2')->first();
                        $vPerencanaan = $p->verifikasiParalel->where('role_verifikator', 'perencanaan')->first();
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $p->nomor_surat }}</strong><br>
                            <small>{{ $p->tanggal_pengajuan ? $p->tanggal_pengajuan->format('d/m/Y H:i') : '-' }}</small>
                        </td>
                        <td>
                            {{ $p->judul_pengadaan }}<br>
                            <small>Unit: {{ $p->unit ? $p->unit->nama_unit : $p->kode_unit }}</small>
                        </td>
                        <td>
                            Rp {{ number_format($p->estimasi_total_pagu, 0, ',', '.') }}
                        </td>
                        <td>
                            <strong>[{{ strtoupper($p->status_permohonan) }}]</strong>
                        </td>
                        <td>
                            @if ($vWadir)
                                <strong>[{{ strtoupper($vWadir->status_keputusan) }}]</strong>
                            @else
                                <small>Belum Ada Antrean</small>
                            @endif
                        </td>
                        <td>
                            @if ($vPerencanaan)
                                <strong>[{{ strtoupper($vPerencanaan->status_keputusan) }}]</strong>
                            @else
                                <small>Belum Ada Antrean</small>
                            @endif
                        </td>
                        <td>
                            @if ($vWadir && $vWadir->catatan_alasan)
                                <small><strong>Wadir 2:</strong> {{ $vWadir->catatan_alasan }}</small><br>
                            @endif
                            @if ($vPerencanaan && $vPerencanaan->catatan_alasan)
                                <small><strong>Perencanaan:</strong> {{ $vPerencanaan->catatan_alasan }}</small>
                            @endif
                            @if (! ($vWadir && $vWadir->catatan_alasan) && ! ($vPerencanaan && $vPerencanaan->catatan_alasan))
                                <small><em>- Tidak ada catatan khusus -</em></small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('unduh.pdf.web', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank">
                                Unduh Lampiran PDF &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" align="center">Belum ada usulan permohonan dalam antrean verifikasi paralel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div>
            {{ $permohonanList->links() }}
        </div>
    </section>

</body>
</html>
