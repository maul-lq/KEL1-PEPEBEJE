<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Simulasi Pengajuan Permohonan & Revisi Srikandi (FR 3)</title>
</head>
<body>

    <header>
        <h1>Simulasi Pengajuan Permohonan & Revisi Srikandi (FR 3)</h1>
        <p>SIM-PBJ Politeknik Negeri Jakarta &bull; Khusus Pengujian & Simulasi Backend</p>
        <p>
            Login: <strong>{{ $user ? $user->nama : 'Guest' }}</strong> ({{ $user ? strtoupper($user->role) : '-' }}) |
            <a href="{{ route('home') }}">&larr; Beranda</a> |
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

    <!-- Formulir Pengajuan Permohonan Baru (FR 3.1 & FR 3.2) -->
    <section>
        <h2>1. Ajukan Usulan Permohonan Srikandi (FR 3.1 - FR 3.3)</h2>
        <form action="{{ route('permohonan.web.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend>Data Usulan Pengadaan Srikandi</legend>

                <p>
                    <label for="nomor_surat">Nomor Surat Srikandi (*):</label><br>
                    <input type="text" id="nomor_surat" name="nomor_surat" value="{{ old('nomor_surat') }}" placeholder="Contoh: B/101/PL3.TIK/PG/2026" required size="50"><br>
                    <small>Format resmi surat dinas keluar Srikandi (Primary Key unik)</small>
                </p>

                <p>
                    <label for="judul_pengadaan">Judul Pengadaan (*):</label><br>
                    <input type="text" id="judul_pengadaan" name="judul_pengadaan" value="{{ old('judul_pengadaan') }}" placeholder="Contoh: Pengadaan Komputer Desktop Lab TIK" required size="60">
                </p>

                <p>
                    <label for="estimasi_total_pagu">Estimasi Pagu Anggaran (Rp):</label><br>
                    <input type="number" id="estimasi_total_pagu" name="estimasi_total_pagu" value="{{ old('estimasi_total_pagu') }}" placeholder="Contoh: 150000000" min="0">
                </p>

                <p>
                    <label for="tujuan_pengadaan_ringkas">Tujuan Pengadaan Ringkas (KAK) (*):</label><br>
                    <textarea id="tujuan_pengadaan_ringkas" name="tujuan_pengadaan_ringkas" rows="4" cols="70" placeholder="Deskripsikan kebutuhan pengadaan secara ringkas.." required>{{ old('tujuan_pengadaan_ringkas') }}</textarea>
                </p>

                <p>
                    <label for="file_permohonan">Berkas Lampiran Srikandi (PDF, Maks. 10MB) (*):</label><br>
                    <input type="file" id="file_permohonan" name="file_permohonan" accept="application/pdf" required><br>
                    <small>NFR 3 & 11: Validasi format PDF dan ukuran &le;10MB</small>
                </p>

                <p>
                    <button type="submit">Kirim Permohonan &rarr; Paralel Verifikasi</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Formulir Pengajuan Revisi atas Surat Ditolak (FR 3.4) -->
    <section>
        <h2>2. Pengajuan Ulang / Revisi Surat Ditolak (FR 3.4)</h2>
        <form action="{{ route('permohonan.web.revisi') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend>Penerbitan Surat Revisi Baru (Unary Relation)</legend>

                <p>
                    <label for="nomor_surat_lama">Pilih Surat Ditolak yang Direvisi (*):</label><br>
                    <select id="nomor_surat_lama" name="nomor_surat_lama" required>
                        <option value="">-- Pilih Nomor Surat Ditolak --</option>
                        @foreach (($permohonanDitolak ?? []) as $itemDitolak)
                            <option value="{{ $itemDitolak->nomor_surat }}">
                                {{ $itemDitolak->nomor_surat }} - {{ $itemDitolak->judul_pengadaan }} (Revisi ke-{{ $itemDitolak->revisi_ke }})
                            </option>
                        @endforeach
                    </select><br>
                    <small>Membentuk relasi unary nomor_surat_revisi ke surat induk ditolak</small>
                </p>

                <p>
                    <label for="nomor_surat_baru">Nomor Surat Baru (Revisi) (*):</label><br>
                    <input type="text" id="nomor_surat_baru" name="nomor_surat_baru" placeholder="Contoh: B/101.REV1/PL3.TIK/PG/2026" required size="50">
                </p>

                <p>
                    <label for="judul_pengadaan_rev">Judul Pengadaan (Revisi) (*):</label><br>
                    <input type="text" id="judul_pengadaan_rev" name="judul_pengadaan" placeholder="Judul usulan yang disesuaikan.." required size="60">
                </p>

                <p>
                    <label for="estimasi_total_pagu_rev">Estimasi Pagu yang Disesuaikan (Rp):</label><br>
                    <input type="number" id="estimasi_total_pagu_rev" name="estimasi_total_pagu" min="0">
                </p>

                <p>
                    <label for="alasan_revisi">Catatan / Alasan Penyesuaian Revisi (*):</label><br>
                    <textarea id="alasan_revisi" name="alasan_revisi" rows="3" cols="70" placeholder="Jelaskan penyesuaian yang telah dilakukan sesuai catatan penolakan.." required></textarea>
                </p>

                <p>
                    <label for="tujuan_pengadaan_ringkas_rev">Tujuan Pengadaan Ringkas (*):</label><br>
                    <textarea id="tujuan_pengadaan_ringkas_rev" name="tujuan_pengadaan_ringkas" rows="3" cols="70" placeholder="Rincian kebutuhan.." required></textarea>
                </p>

                <p>
                    <label for="file_permohonan_rev">Berkas Lampiran Hasil Revisi (PDF, Maks. 10MB) (*):</label><br>
                    <input type="file" id="file_permohonan_rev" name="file_permohonan" accept="application/pdf" required>
                </p>

                <p>
                    <button type="submit">Terbitkan Surat Revisi Baru &rarr;</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    <!-- Tabel Daftar Permohonan & Riwayat Revisi -->
    <section>
        <h2>3. Riwayat Permohonan Pengadaan Unit (Real-time DB)</h2>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Nomor Surat Srikandi</th>
                    <th>Judul Pengadaan</th>
                    <th>Unit Pengaju</th>
                    <th>Estimasi Pagu</th>
                    <th>Status Usulan</th>
                    <th>Revisi Ke</th>
                    <th>Terkait Revisi Induk</th>
                    <th>Aksi / Tracking</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($permohonanList as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->nomor_surat }}</strong><br>
                            <small>{{ $item->tanggal_pengajuan ? $item->tanggal_pengajuan->format('d/m/Y H:i') : '-' }}</small>
                        </td>
                        <td>{{ $item->judul_pengadaan }}</td>
                        <td>{{ $item->unit ? $item->unit->nama_unit : $item->kode_unit }}</td>
                        <td>Rp {{ number_format($item->estimasi_total_pagu, 0, ',', '.') }}</td>
                        <td>
                            <strong>[{{ strtoupper($item->status_permohonan) }}]</strong>
                        </td>
                        <td align="center">{{ $item->revisi_ke }}</td>
                        <td>
                            @if ($item->nomor_surat_revisi)
                                Revisi dari: <em>{{ $item->nomor_surat_revisi }}</em>
                            @else
                                <small>Usulan Induk Asli</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('monitoring.web', ['nomor_surat' => $item->nomor_surat]) }}">
                                Lacak Posisi &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" align="center">Belum ada berkas permohonan yang diajukan oleh unit Anda.</td>
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
