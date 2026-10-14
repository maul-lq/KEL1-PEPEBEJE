<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Simulasi Paket Pengadaan & Alih Tugas PPK (NFR 4 & FR 7)</title>
</head>
<body>

    <header>
        <h1>Simulasi Paket Pengadaan, Routing Ambang Batas, & Alih Tugas PPK (NFR 4 & FR 7)</h1>
        <p>SIM-PBJ Politeknik Negeri Jakarta &bull; Khusus Pengujian & Simulasi Backend</p>
        <p>
            Login: <strong>{{ $user ? $user->nama : 'Guest' }}</strong> ({{ $user ? strtoupper($user->role) : '-' }}) |
            <a href="{{ route('home') }}">&larr; Beranda</a> |
            <a href="{{ route('permohonan.web') }}">Permohonan Srikandi (FR 3 & 4)</a> |
            <a href="{{ route('verifikasi-b2.web') }}">Verifikasi B2 & Wadir 2 (FR 5)</a> |
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

    <!-- Formulir Registrasi Paket Pengadaan Baru (NFR 4 & FR 7.1) -->
    <section>
        <h2>1. Registrasi Paket Pengadaan & Auto-Routing (NFR 4 & FR 7.1)</h2>
        <form action="{{ route('paket.web.store') }}" method="POST">
            @csrf
            <fieldset>
                <legend>Formulir Paket Pengadaan Baru</legend>

                <p>
                    <label for="nomor_paket">Nomor Paket Pengadaan (*):</label><br>
                    <input type="text" id="nomor_paket" name="nomor_paket" value="{{ old('nomor_paket') }}" placeholder="Contoh: PKT-2026-TIK-001" required size="40">
                </p>

                <p>
                    <label for="nama_paket">Nama Paket Pengadaan (*):</label><br>
                    <input type="text" id="nama_paket" name="nama_paket" value="{{ old('nama_paket') }}" placeholder="Contoh: Pengadaan Komputer Desktop Lab Rekayasa" required size="60">
                </p>

                <p>
                    <label for="nomor_surat">Pilih Usulan Permohonan Induk (*):</label><br>
                    <select id="nomor_surat" name="nomor_surat" required>
                        <option value="">-- Pilih Permohonan --</option>
                        @foreach ($permohonanList as $itemP)
                            <option value="{{ $itemP->nomor_surat }}" {{ old('nomor_surat') == $itemP->nomor_surat ? 'selected' : '' }}>
                                {{ $itemP->nomor_surat }} - {{ $itemP->judul_pengadaan }} [{{ strtoupper($itemP->status_permohonan) }}]
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="jenis_pengadaan">Jenis Pengadaan (*):</label><br>
                    <select id="jenis_pengadaan" name="jenis_pengadaan" required>
                        <option value="barang">Barang</option>
                        <option value="jasa_lainnya">Jasa Lainnya</option>
                        <option value="konstruksi">Konstruksi</option>
                        <option value="jasa_konsultansi">Jasa Konsultansi</option>
                    </select>
                </p>

                <p>
                    <label for="platform_pengadaan">Platform Pengadaan (*):</label><br>
                    <input type="text" id="platform_pengadaan" name="platform_pengadaan" value="{{ old('platform_pengadaan', 'SPSE-Inaproc') }}" required size="30">
                </p>

                <p>
                    <label for="mekanisme_pengadaan">Mekanisme Pengadaan (*):</label><br>
                    <input type="text" id="mekanisme_pengadaan" name="mekanisme_pengadaan" value="{{ old('mekanisme_pengadaan', 'Non-Tender / Pengadaan Langsung') }}" required size="40">
                </p>

                <p>
                    <label for="nilai_hps">Nilai HPS (Harga Perkiraan Sendiri) (Rp) (*):</label><br>
                    <input type="number" id="nilai_hps" name="nilai_hps" value="{{ old('nilai_hps') }}" placeholder="Contoh: 120000000" min="1" step="0.01" required><br>
                    <small>
                        * <strong>Aturan Auto-Routing Sistem (NFR 4):</strong><br>
                        &bull; &le; Rp 50.000.000: Pengadaan Langsung (langsung_sd_50jt)<br>
                        &bull; Rp 50.000.001 s/d Rp 200.000.000: Pejabat Pengadaan Tunggal (pp_50jt_sd_200jt)<br>
                        &bull; &gt; Rp 200.000.000: Pejabat Pembuat Komitmen (ppk_diatas_200jt)
                    </small>
                </p>

                <p>
                    <label for="alokasi_pagu_paket">Alokasi Pagu Paket (Rp) (*):</label><br>
                    <input type="number" id="alokasi_pagu_paket" name="alokasi_pagu_paket" value="{{ old('alokasi_pagu_paket') }}" placeholder="Contoh: 120000000" min="1" step="0.01" required>
                </p>

                <p>
                    <label for="id_user_pp">Penugasan Pejabat Pengadaan (PP Tunggal - 50jt s/d 200jt):</label><br>
                    <select id="id_user_pp" name="id_user_pp">
                        <option value="">-- Tanpa / Otomatis --</option>
                        @foreach ($ppUsers as $pp)
                            <option value="{{ $pp->id_user }}" {{ old('id_user_pp') == $pp->id_user ? 'selected' : '' }}>
                                {{ $pp->nama }} ({{ $pp->username }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="id_user_ppk">Penugasan PPK (1 dari 4 Akun PPK - &gt;200jt):</label><br>
                    <select id="id_user_ppk" name="id_user_ppk">
                        <option value="">-- Tanpa / Otomatis --</option>
                        @foreach ($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}" {{ old('id_user_ppk') == $ppk->id_user ? 'selected' : '' }}>
                                {{ $ppk->nama }} ({{ $ppk->username }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <button type="submit">Daftarkan Paket Pengadaan &rarr; Auto-Routing</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Formulir Alih Tugas PPK (Reassign PPK - NFR 4 & FR 7.3) -->
    <section>
        <h2>2. Alih Tugas PPK / Reassign Assignment (NFR 4 & FR 7.3)</h2>
        <form action="{{ route('paket.web.reassign') }}" method="POST">
            @csrf
            <fieldset>
                <legend>Formulir Pengalihan Tugas PPK</legend>

                <p>
                    <label for="nomor_paket_reassign">Pilih Paket Pengadaan (*):</label><br>
                    <select id="nomor_paket_reassign" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach ($paketList as $pkt)
                            <option value="{{ $pkt->nomor_paket }}">
                                {{ $pkt->nomor_paket }} - {{ $pkt->nama_paket }} (PPK Saat Ini: {{ $pkt->ppkUser ? $pkt->ppkUser->nama : 'Belum Ditugaskan' }})
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="ppk_baru_user_id">Pilih Akun PPK Pengganti (*):</label><br>
                    <select id="ppk_baru_user_id" name="ppk_baru_user_id" required>
                        <option value="">-- Pilih 1 dari 4 Akun PPK Aktif --</option>
                        @foreach ($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}">
                                {{ $ppk->nama }} ({{ $ppk->username }})
                            </option>
                        @endforeach
                    </select><br>
                    <small>NFR 4: Penugasan presisi ke 4 akun individual PPK untuk menjaga isolasi berkas</small>
                </p>

                <p>
                    <label for="alasan_perubahan">Alasan Pengalihan Tugas (*):</label><br>
                    <textarea id="alasan_perubahan" name="alasan_perubahan" rows="3" cols="60" placeholder="Contoh: Pemerataan beban kerja PPK / Penyesuaian jenis kegiatan MAK dari Perencanaan" required></textarea>
                </p>

                <p>
                    <button type="submit">Eksekusi Alih Tugas PPK &rarr;</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Tabel Daftar Paket Pengadaan & Riwayat Penugasan -->
    <section>
        <h2>3. Daftar Paket Pengadaan (NFR Isolasi Berkas & Riwayat PPK)</h2>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Nomor Paket</th>
                    <th>Nama Paket & Jenis</th>
                    <th>Nilai HPS</th>
                    <th>Jalur Routing</th>
                    <th>PPK Penanggung Jawab</th>
                    <th>Pejabat Pengadaan (PP)</th>
                    <th>Status Paket</th>
                    <th>Riwayat Alih Tugas PPK</th>
                    <th>Unduh Dokumen Resmi (NFR 12)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paketList as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->nomor_paket }}</strong><br>
                            <small>Surat Induk: {{ $item->nomor_surat }}</small>
                        </td>
                        <td>
                            {{ $item->nama_paket }}<br>
                            <small>[{{ strtoupper(str_replace('_', ' ', $item->jenis_pengadaan)) }}]</small>
                        </td>
                        <td>
                            Rp {{ number_format($item->nilai_hps, 0, ',', '.') }}
                        </td>
                        <td>
                            <strong>{{ $item->jalur_routing }}</strong>
                        </td>
                        <td>
                            @if ($item->ppkUser)
                                {{ $item->ppkUser->nama }}<br>
                                <small>NIP: {{ $item->ppkUser->nip }}</small>
                            @else
                                <em>- Belum Ada -</em>
                            @endif
                        </td>
                        <td>
                            @if ($item->ppUser)
                                {{ $item->ppUser->nama }}
                            @else
                                <em>- Belum Ada -</em>
                            @endif
                        </td>
                        <td>
                            <strong>[{{ strtoupper($item->status_paket) }}]</strong>
                        </td>
                        <td>
                            @if ($item->riwayatPpk && $item->riwayatPpk->isNotEmpty())
                                <ul>
                                    @foreach ($item->riwayatPpk as $rPpk)
                                        <li>
                                            <small>
                                                Dialihkan ke: <strong>{{ $rPpk->ppkBaru ? $rPpk->ppkBaru->nama : $rPpk->ppk_baru_user_id }}</strong><br>
                                                Alasan: {{ $rPpk->alasan_perubahan }} ({{ $rPpk->tanggal_penugasan ? $rPpk->tanggal_penugasan->format('d/m/Y') : '-' }})
                                            </small>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <small>Penugasan awal / belum pernah dialihkan</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('unduh.pdf.web', ['jenis' => 'permohonan', 'id' => $item->nomor_surat]) }}" target="_blank">
                                [PDF Permohonan]
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" align="center">Belum ada paket pengadaan yang terdaftar dalam wewenang akun Anda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div>
            {{ $paketList->links() }}
        </div>
    </section>

</body>
</html>
