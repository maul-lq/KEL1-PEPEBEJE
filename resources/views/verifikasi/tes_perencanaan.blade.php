@extends('layouts.tes_app')

@section('title', 'Cek MAK, Kunci/Buka Kunci MAK, & Memo Ketersediaan MAK - Subbagian Perencanaan PNJ')

@section('content')
<section>
    <h2>Pemeriksaan Ketersediaan MAK, Pembuatan MAK, &amp; Kontrol Kunci MAK (Subbagian Perencanaan)</h2>
    <p>
        Bagian Perencanaan menerima <strong>Memo Cek MAK</strong> dari Bidang II, membuat serta memeriksa ketersediaan kode MAK DIPA,
        memegang <strong>otoritas tunggal untuk mengunci (Lock) dan membuka kunci (Unlock) MAK</strong>, memilih 1 dari 4 akun PPK,
        dan mengirimkan <strong>Memo Ketersediaan MAK</strong> kembali ke Bidang II (Wadir II) serta PPK terpilih untuk disetujui.
    </p>

    {{-- 1. Antrean Permohonan dari Memo Cek MAK Bidang II --}}
    <fieldset>
        <legend><strong>Antrean Memo Cek MAK dari Bidang II (Menunggu Pemeriksaan Ketersediaan MAK)</strong></legend>

        @if($permohonanAntrean->isEmpty())
            <p><em>Tidak ada usulan permohonan yang menunggu pemeriksaan ketersediaan MAK saat ini.</em></p>
        @else
            @foreach($permohonanAntrean as $p)
                <article>
                    <fieldset>
                        <legend><strong>Surat: {{ $p->nomor_surat }}</strong></legend>
                        <p>
                            <strong>Unit Pengusul:</strong> {{ $p->unit->nama_unit ?? $p->kode_unit }} &bull;
                            <strong>Perihal:</strong> {{ $p->perihal }} &bull;
                            <strong>Estimasi Anggaran Diajukan:</strong> Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}
                        </p>
                        <p>
                            <strong>Nomor Memo Cek MAK (Bidang II):</strong> {{ $p->nomor_memo_cek_mak ?? '-' }} &bull;
                            <strong>Catatan Bidang II:</strong> {{ $p->catatan_memo_cek_mak ?? '-' }}
                        </p>

                        <form action="{{ route('verifikasi.perencanaan.mak') }}" method="POST">
                            @csrf
                            <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                            <p>
                                <label for="kode_mak_{{ $loop->index }}"><strong>Pilih Mata Anggaran Kegiatan (MAK) DIPA:</strong></label><br>
                                <select id="kode_mak_{{ $loop->index }}" name="kode_mak" required>
                                    <option value="">-- Pilih MAK yang Tersedia --</option>
                                    @foreach($makList as $mak)
                                        <option value="{{ $mak->kode_mak }}" {{ $mak->is_locked ? 'disabled' : '' }}>
                                            [{{ $mak->kode_mak }}] {{ $mak->uraian_mak }} &bull; Sisa Pagu: Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }} ({{ $mak->sumber_dana }}) {{ $mak->is_locked ? '[TERKUNCI]' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </p>

                            <p>
                                <label for="nominal_alokasi_{{ $loop->index }}"><strong>Nominal Alokasi Anggaran (Rp):</strong></label><br>
                                <input type="number" id="nominal_alokasi_{{ $loop->index }}" name="nominal_alokasi" value="{{ $p->anggaran_diajukan }}" step="0.01" min="1" required>
                            </p>

                            <p>
                                <label for="id_user_ppk_awal_{{ $loop->index }}"><strong>Pilih 1 dari 4 Akun PPK Penerima Memo Ketersediaan MAK:</strong></label><br>
                                <select id="id_user_ppk_awal_{{ $loop->index }}" name="id_user_ppk_awal">
                                    <option value="">-- Pilih Pejabat Pembuat Komitmen (PPK) --</option>
                                    @foreach($ppkUsers ?? [] as $ppk)
                                        <option value="{{ $ppk->id_user }}" {{ (int) $p->id_user_ppk_awal === (int) $ppk->id_user ? 'selected' : '' }}>
                                            {{ $ppk->nama }} ({{ $ppk->jabatan }})
                                        </option>
                                    @endforeach
                                </select>
                            </p>

                            <p>
                                <label for="nomor_memo_ketersediaan_mak_{{ $loop->index }}"><strong>Nomor Memo Ketersediaan MAK (Perencanaan ke Bidang II &amp; PPK):</strong></label><br>
                                <input type="text" id="nomor_memo_ketersediaan_mak_{{ $loop->index }}" name="nomor_memo_ketersediaan_mak" value="{{ $p->nomor_memo_ketersediaan_mak ?? ('MEMO-PRC/MAK/' . date('Y') . '/' . rand(100, 999)) }}" size="45">
                            </p>

                            <p>
                                <label>
                                    <input type="radio" name="keputusan_anggaran" value="setuju" checked>
                                    <strong>MAK Tersedia &mdash; Kirim Memo Ketersediaan MAK ke Bidang II (Wadir II) &amp; PPK</strong>
                                </label>
                                &nbsp;&nbsp;
                                <label>
                                    <input type="radio" name="keputusan_anggaran" value="tahan">
                                    <strong>Tahan / Hold (Pagu MAK Belum Tersedia)</strong>
                                </label>
                            </p>

                            <p>
                                <label for="catatan_anggaran_{{ $loop->index }}"><strong>Catatan Memo Ketersediaan MAK (Opsional):</strong></label><br>
                                <textarea id="catatan_anggaran_{{ $loop->index }}" name="catatan_anggaran" rows="2" cols="60" placeholder="Contoh: Pagu MAK tersedia pada BOPTN {{ date('Y') }}, mohon persetujuan kegiatan oleh Wadir II dan PPK."></textarea>
                            </p>

                            <p>
                                <button type="submit"><strong>Simpan Ketersediaan MAK &amp; Kirim Memo ke Bidang II + PPK</strong></button>
                            </p>
                        </fieldset>
                    </form>
                </article>
                <br>
            @endforeach
        @endif
    </fieldset>

    <hr>

    {{-- 2. Formulir Pemecahan (Split) Permohonan Pengadaan Berdasarkan Anggaran MAK --}}
    <fieldset>
        <legend><strong>Pemecahan (Split) Usulan Permohonan Berdasarkan Pos Anggaran MAK</strong></legend>
        <p>
            Jika terdapat usulan permohonan yang perlu dipisahkan pembebanan anggarannya ke mata anggaran (MAK) yang berbeda,
            Perencanaan dapat memecah berkas usulan menjadi nomor surat pecahan baru.
        </p>

        <form action="{{ route('verifikasi.perencanaan.split') }}" method="POST">
            @csrf
            <p>
                <label for="nomor_surat_induk"><strong>Pilih Usulan Induk yang Akan Dipecah:</strong></label><br>
                <select id="nomor_surat_induk" name="nomor_surat_induk" required>
                    <option value="">-- Pilih Surat Permohonan Induk --</option>
                    @foreach($permohonanAntrean as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} (Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_surat_baru"><strong>Nomor Surat Pecahan Baru:</strong></label><br>
                <input type="text" id="nomor_surat_baru" name="nomor_surat_baru" value="B/SPLIT/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="judul_pengadaan_split"><strong>Judul / Item Pengadaan Pecahan:</strong></label><br>
                <input type="text" id="judul_pengadaan_split" name="judul_pengadaan" placeholder="Contoh: Pengadaan Komputer Bagian Lab A" required size="50">
            </p>

            <p>
                <label for="kode_mak_split"><strong>Alokasikan ke Kode MAK:</strong></label><br>
                <select id="kode_mak_split" name="kode_mak" required>
                    <option value="">-- Pilih MAK Alokasi --</option>
                    @foreach($makList as $mak)
                        <option value="{{ $mak->kode_mak }}" {{ $mak->is_locked ? 'disabled' : '' }}>
                            [{{ $mak->kode_mak }}] {{ $mak->uraian_mak }} &bull; Sisa: Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="estimasi_total_pagu"><strong>Estimasi Alokasi Pagu Pecahan (Rp):</strong></label><br>
                <input type="number" id="estimasi_total_pagu" name="estimasi_total_pagu" min="1" step="0.01" required placeholder="Contoh: 50000000">
            </p>

            <p>
                <label for="alasan_pemecahan"><strong>Alasan Pemecahan Anggaran MAK:</strong></label><br>
                <textarea id="alasan_pemecahan" name="alasan_pemecahan" rows="2" cols="60" required placeholder="Contoh: Pemisahan item belanja modal dan operasional ke pos MAK terpisah"></textarea>
            </p>

            <p>
                <button type="submit"><strong>Proses Pemecahan (Split) Berkas Permohonan</strong></button>
            </p>
        </form>
    </fieldset>

    <hr>

    {{-- 3. Formulir Pendaftaran Rekening MAK DIPA Baru --}}
    <fieldset>
        <legend><strong>+ Buat Master Rekening MAK DIPA Baru (Subbagian Perencanaan)</strong></legend>
        <p>Gunakan formulir ini untuk mendaftarkan pos akun rekening DIPA anggaran baru institusi Politeknik Negeri Jakarta.</p>

        <form action="{{ route('verifikasi.perencanaan.mak.store') }}" method="POST">
            @csrf
            <p>
                <label for="input_kode_mak"><strong>Kode Rekening MAK:</strong></label><br>
                <input type="text" id="input_kode_mak" name="kode_mak" required placeholder="Contoh: MAK-TIK-2026-004 atau 023.17.WA.521211" size="40">
            </p>

            <p>
                <label for="input_uraian_mak"><strong>Uraian / Nama Kegiatan MAK:</strong></label><br>
                <input type="text" id="input_uraian_mak" name="uraian_mak" required placeholder="Contoh: Belanja Bahan Penunjang Laboratorium Komputer" size="60">
            </p>

            <p>
                <label for="input_sumber_dana"><strong>Sumber Pendanaan:</strong></label><br>
                <select id="input_sumber_dana" name="sumber_dana" required>
                    <option value="BOPTN">BOPTN (Bantuan Operasional PTN)</option>
                    <option value="PNBP">PNBP (Pendapatan Negara Bukan Pajak)</option>
                    <option value="Rupiah Murni">Rupiah Murni (APBN RM)</option>
                    <option value="Hibah / Kerjasama">Hibah / Kerjasama</option>
                </select>
            </p>

            <p>
                <label for="input_tahun_anggaran"><strong>Tahun Anggaran DIPA:</strong></label><br>
                <input type="number" id="input_tahun_anggaran" name="tahun_anggaran" value="{{ date('Y') }}" min="2020" max="2035" required>
            </p>

            <p>
                <label for="input_pagu_anggaran"><strong>Pagu Awal Anggaran (Rp):</strong></label><br>
                <input type="number" id="input_pagu_anggaran" name="pagu_anggaran" min="1" step="0.01" required placeholder="Contoh: 150000000">
            </p>

            <p>
                <label for="input_catatan_mak"><strong>Catatan Rekening (Opsional):</strong></label><br>
                <textarea id="input_catatan_mak" name="catatan_mak" rows="2" cols="60" placeholder="Keterangan alokasi pagu DIPA atau revisi DIPA..."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Simpan &amp; Terbitkan Rekening MAK</strong></button>
            </p>
        </form>
    </fieldset>

    <hr>

    {{-- 4. Daftar Master MAK DIPA & Otoritas Tunggal Kunci / Buka Kunci MAK --}}
    <fieldset>
        <legend><strong>Daftar Mata Anggaran Kegiatan (MAK) DIPA &amp; Kontrol Kunci / Buka Kunci MAK (Eksklusif Perencanaan)</strong></legend>
        <p><em>Hanya Subbagian Perencanaan yang berwenang mengunci dan membuka kunci pagu MAK.</em></p>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>Kode MAK</th>
                    <th>Uraian MAK</th>
                    <th>Sumber Dana</th>
                    <th>Tahun</th>
                    <th>Pagu Awal</th>
                    <th>Sisa Pagu Aktif</th>
                    <th>Status Kunci</th>
                    <th>Kontrol Kunci Perencanaan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($makList as $mak)
                    <tr>
                        <td><strong>{{ $mak->kode_mak }}</strong></td>
                        <td>{{ $mak->uraian_mak }}</td>
                        <td>{{ $mak->sumber_dana }}</td>
                        <td align="center">{{ $mak->tahun_anggaran }}</td>
                        <td>Rp {{ number_format($mak->pagu_anggaran, 0, ',', '.') }}</td>
                        <td><strong>Rp {{ number_format($mak->sisa_pagu_mak, 0, ',', '.') }}</strong></td>
                        <td align="center">
                            @if($mak->is_locked)
                                <strong>TERKUNCI</strong>
                            @else
                                <em>Terbuka</em>
                            @endif
                        </td>
                        <td align="center">
                            @if($mak->is_locked)
                                <form action="{{ route('verifikasi.perencanaan.mak.unlock', $mak->kode_mak) }}" method="POST">
                                    @csrf
                                    <button type="submit">Buka Kunci MAK</button>
                                </form>
                            @else
                                <form action="{{ route('verifikasi.perencanaan.mak.lock', $mak->kode_mak) }}" method="POST">
                                    @csrf
                                    <button type="submit">Kunci MAK</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </fieldset>
</section>
@endsection
