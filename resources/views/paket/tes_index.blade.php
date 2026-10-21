@extends('layouts.tes_app')

@section('title', 'Daftar Paket Pengadaan & Alih Tugas PPK - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Manajemen Paket Pengadaan & Alih Penugasan PPK (Koordinator PPBJ)</h2>
    <p>
        Halaman ini menampilkan seluruh paket pengadaan di lingkungan Politeknik Negeri Jakarta,
        jalur routing ambang batas anggaran yang ditentukan, serta fitur pengalihan tugas PPK (Reassign PPK).
    </p>

    <p>
        <a href="{{ route('paket.create') }}"><button type="button"><strong>+ Registrasi Paket Pengadaan Baru</strong></button></a>
    </p>

    {{-- 1. Tabel Daftar Paket Pengadaan --}}
    <fieldset>
        <legend><strong>Daftar Paket Pengadaan Aktif</strong></legend>

        @if($paketList->isEmpty())
            <p><em>Belum ada paket pengadaan yang terdaftar.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Kode Paket</th>
                        <th>Nama Paket</th>
                        <th>Jenis & Metode</th>
                        <th>Nilai HPS</th>
                        <th>Jalur Routing</th>
                        <th>Memo Pengadaan PPBJ</th>
                        <th>Pejabat Pengadaan</th>
                        <th>Pejabat Pembuat Komitmen (PPK)</th>
                        <th>Status Paket</th>
                        <th>Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paketList as $pkt)
                        <tr>
                            <td><strong>{{ $pkt->kode_paket }}</strong></td>
                            <td>{{ $pkt->nama_paket }}</td>
                            <td>{{ $pkt->jenis_pengadaan }} &bull; {{ $pkt->metode_pengadaan }}</td>
                            <td><strong>Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}</strong></td>
                            <td>
                                <u>{{ strtoupper(str_replace('_', ' ', $pkt->jalur_routing)) }}</u>
                            </td>
                            <td>
                                @if($pkt->jalur_routing === 'ppk_diatas_200jt')
                                    <small><em>Tanpa Memo (&ge; 200 Juta)</em></small>
                                @else
                                    {{ $pkt->nomor_memo_ppbj ?? '-' }}
                                @endif
                            </td>
                            <td>{{ $pkt->ppUser->nama ?? '-' }}</td>
                            <td>
                                @if(in_array($pkt->jalur_routing, ['ppk_diatas_200jt', 'pp_50jt_sd_200jt']))
                                    <strong>{{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }}</strong>
                                @else
                                    <small><em>(Pengadaan Langsung PPBJ)</em></small>
                                @endif
                            </td>
                            <td>{{ strtoupper(str_replace('_', ' ', $pkt->status_paket)) }}</td>
                            <td>
                                @if(in_array(Auth::user()->role, ['pp', 'ppk', 'ppbj', 'admin']))
                                    <a href="{{ route('kontrak.index') }}">
                                        <button type="button">Reviu / SPK</button>
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>

    <hr>

    {{-- 1B. Formulir Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan oleh PPBJ (FR 7.2) --}}
    <fieldset>
        <legend><strong>Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan Barang / Jasa / Konstruksi (FR 7.2)</strong></legend>
        <p>
            Jika satu usulan pengadaan yang telah disetujui menggabungkan lebih dari satu jenis pengadaan (misalnya Barang dan Jasa/Konstruksi),
            PPBJ dapat memecah berkas tersebut berdasarkan jenis pengadaan sebelum diregistrasikan menjadi paket pengadaan terpisah.
        </p>

        <form action="{{ route('paket.split-jenis') }}" method="POST">
            @csrf
            <p>
                <label for="nomor_surat_induk_ppbj"><strong>Pilih Usulan Disetujui yang Akan Dipecah:</strong></label><br>
                <select id="nomor_surat_induk_ppbj" name="nomor_surat_induk" required>
                    <option value="">-- Pilih Surat Permohonan Disetujui --</option>
                    @foreach($permohonanDisetujui ?? [] as $p)
                        <option value="{{ $p->nomor_surat }}">
                            [{{ $p->nomor_surat }}] {{ $p->perihal }} (Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_surat_baru_ppbj"><strong>Nomor Surat Pecahan Baru:</strong></label><br>
                <input type="text" id="nomor_surat_baru_ppbj" name="nomor_surat_baru" value="B/SPLIT-PBJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="judul_pengadaan_ppbj"><strong>Judul / Item Pengadaan Pecahan:</strong></label><br>
                <input type="text" id="judul_pengadaan_ppbj" name="judul_pengadaan" placeholder="Contoh: Pekerjaan Instalasi Jaringan Lab TIK" required size="50">
            </p>

            <p>
                <label for="jenis_pengadaan_split"><strong>Jenis Pengadaan Pecahan:</strong></label><br>
                <select id="jenis_pengadaan_split" name="jenis_pengadaan_split" required>
                    <option value="barang">Pengadaan Barang</option>
                    <option value="jasa">Pengadaan Jasa Lainnya / Konsultansi</option>
                    <option value="konstruksi">Pekerjaan Konstruksi</option>
                </select>
            </p>

            <p>
                <label for="estimasi_total_pagu_ppbj"><strong>Alokasi Pagu Pecahan (Rp):</strong></label><br>
                <input type="number" id="estimasi_total_pagu_ppbj" name="estimasi_total_pagu" min="1" step="0.01" required placeholder="Contoh: 45000000">
            </p>

            <p>
                <label for="alasan_pemecahan_ppbj"><strong>Alasan Pemecahan Jenis Pengadaan:</strong></label><br>
                <textarea id="alasan_pemecahan_ppbj" name="alasan_pemecahan" rows="2" cols="60" required placeholder="Contoh: Pemisahan paket pengadaan barang perangkat keras dan jasa instalasi konstruksi jaringan."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Proses Pemecahan Berkas Berdasarkan Jenis Pengadaan</strong></button>
            </p>
        </form>
    </fieldset>

    <hr>

    {{-- 2. Formulir Alih Tugas (Reassign) PPK --}}
    <fieldset>
        <legend><strong>Alih Tugas (Reassign) Pejabat Pembuat Komitmen (PPK)</strong></legend>
        <p>
            Jika terjadi beban kerja berlebih atau penyesuaian bidang/MAK, PPBJ berhak mengalihkan penugasan paket bernilai &gt; Rp 200 Juta
            ke salah satu akun PPK lainnya. Mutasi penugasan akan otomatis dicatat pada riwayat sistem.
        </p>

        <form action="{{ route('paket.reassign-ppk') }}" method="POST">
            @csrf
            <p>
                <label for="kode_paket_reassign"><strong>Pilih Paket Pengadaan (Jalur PPK &gt; 200 Juta):</strong></label><br>
                <select id="kode_paket_reassign" name="kode_paket" required>
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList->where('jalur_routing', 'ppk_diatas_200jt') as $pktPpk)
                        <option value="{{ $pktPpk->kode_paket }}">
                            [{{ $pktPpk->kode_paket }}] {{ $pktPpk->nama_paket }} &bull; PPK Saat Ini: {{ $pktPpk->ppkUser->nama ?? 'Kosong' }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="id_user_ppk_baru"><strong>Alihkan ke PPK Baru:</strong></label><br>
                <select id="id_user_ppk_baru" name="id_user_ppk_baru" required>
                    <option value="">-- Pilih PPK Penerima Tugas --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}">{{ $ppk->nama }} ({{ $ppk->jabatan }})</option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="alasan_pengalihan"><strong>Alasan Resmi Alih Tugas / Mutasi PPK:</strong></label><br>
                <textarea id="alasan_pengalihan" name="alasan_pengalihan" rows="2" cols="60" required placeholder="Contoh: Pemerataan beban kerja PPK dan penyesuaian MAK Sarana Kampus."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Simpan & Eksekusi Alih Tugas PPK</strong></button>
            </p>
        </form>
    </fieldset>

    <hr>

    {{-- 3. Tabel Riwayat Penugasan PPK --}}
    <fieldset>
        <legend><strong>Jejak Riwayat Mutasi Penugasan PPK</strong></legend>
        @if($riwayatPenugasan->isEmpty())
            <p><em>Belum ada riwayat alih tugas PPK yang tercatat.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Waktu Mutasi</th>
                        <th>Kode Paket</th>
                        <th>PPK Sebelumnya</th>
                        <th>PPK Baru</th>
                        <th>Alasan Pengalihan</th>
                        <th>Ditetapkan Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riwayatPenugasan as $rw)
                        <tr>
                            <td>{{ date('d-m-Y H:i', strtotime($rw->created_at)) }}</td>
                            <td>{{ $rw->kode_paket }}</td>
                            <td>{{ $rw->ppkLama->nama ?? '-' }}</td>
                            <td><strong>{{ $rw->ppkBaru->nama ?? '-' }}</strong></td>
                            <td>{{ $rw->alasan_pengalihan }}</td>
                            <td>{{ $rw->userPenetap->nama ?? 'Sistem' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>
</section>
@endsection
