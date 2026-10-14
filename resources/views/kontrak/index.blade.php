@extends('layouts.app')

@section('title', 'Manajemen Kontrak, Reviu Dokumen & SPK - SIM-PBJ PNJ')

@section('content')
<section>
    <h2>Manajemen Penelaahan (Reviu) Dokumen & Kontrak SPK</h2>
    <p>
        @if(Auth::user()->role === 'ppk')
            <strong>Peran Pengguna: Pejabat Pembuat Komitmen (PPK).</strong><br>
            Menangani paket pengadaan komitmen strategis bernilai di atas Rp 200 Juta yang ditugaskan kepada akun Anda (<strong>{{ Auth::user()->nama }}</strong>).
            <em>Paket milik akun PPK lain diisolasi dan tidak dapat diakses atau diubah dari akun ini (Prinsip Isolasi Berkas PPK - NFR RBAC).</em>
        @else
            <strong>Peran Pengguna: Pejabat Pengadaan (PP).</strong><br>
            Menangani paket pengadaan langsung dan pemilihan rekanan bernilai sampai dengan Rp 200 Juta.
        @endif
    </p>

    {{-- 1. Panel Reviu Dokumen Teknis & HPS (FR 9) --}}
    <fieldset>
        <legend><strong>1. Penelaahan (Reviu) Dokumen Teknis & Kelayakan HPS (FR 9)</strong></legend>
        <p>
            Pejabat Pengadaan dan PPK wajib menelaah kelengkapan spesifikasi teknis, rancangan kontrak, dan rincian HPS.
            Jika berkas telah memenuhi syarat, berikan persetujuan agar paket siap diterbitkan SPK (FR 9.2).
            Jika terdapat kekurangan, kembalikan ke PPBJ dengan <strong>catatan perbaikan wajib</strong> (FR 9.3).
        </p>

        @if($paketReviu->isEmpty())
            <p><em>Saat ini tidak ada antrean paket pengadaan yang memerlukan reviu dokumen pada akun Anda.</em></p>
        @else
            <form action="{{ route('kontrak.reviu') }}" method="POST">
                @csrf
                <p>
                    <label for="nomor_paket_reviu"><strong>Pilih Paket untuk Direviu:</strong></label><br>
                    <select id="nomor_paket_reviu" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketReviu as $pktR)
                            <option value="{{ $pktR->nomor_paket }}">
                                [{{ $pktR->nomor_paket }}] {{ $pktR->nama_paket }} &bull; Nilai HPS: Rp {{ number_format($pktR->nilai_hps, 0, ',', '.') }} &bull; Status: {{ strtoupper(str_replace('_', ' ', $pktR->status_paket)) }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label><strong>Keputusan Hasil Penelaahan Dokumen (FR 9.2 / FR 9.3):</strong></label><br>
                    <input type="radio" id="reviu_setuju" name="status_reviu" value="disetujui" checked>
                    <label for="reviu_setuju"><strong>DISETUJUI (Lulus Reviu)</strong> - Dokumen teknis dan HPS lengkap, paket siap diterbitkan Kontrak SPK.</label><br>
                    <input type="radio" id="reviu_perbaikan" name="status_reviu" value="perlu_perbaikan">
                    <label for="reviu_perbaikan"><strong>PERLU PERBAIKAN (Dikembalikan ke PPBJ)</strong> - Wajib menyertakan rincian catatan revisi dokumen teknis/HPS.</label>
                </p>

                <p>
                    <label for="catatan_perbaikan"><strong>Catatan Rekomendasi / Catatan Perbaikan:</strong></label><br>
                    <textarea id="catatan_perbaikan" name="catatan_perbaikan" rows="3" cols="70" placeholder="Wajib diisi jika memilih opsi 'Perlu Perbaikan'. Tuliskan kekurangan spesifikasi teknis atau koreksi HPS."></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Kirim Keputusan Reviu Dokumen</strong></button>
                </p>
            </form>

            <hr>

            <h4>Daftar Antrean & Riwayat Penelaahan Dokumen Terkini:</h4>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Paket</th>
                        <th>Nama Paket</th>
                        <th>Nilai HPS</th>
                        <th>Status Paket</th>
                        <th>Putaran Terakhir</th>
                        <th>Hasil Reviu</th>
                        <th>Catatan Perbaikan / Telaah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paketReviu as $pr)
                        @php
                            $latestReviu = $pr->reviuDokumen->sortByDesc('putaran_ke')->first();
                        @endphp
                        <tr>
                            <td><strong>{{ $pr->nomor_paket }}</strong></td>
                            <td>{{ $pr->nama_paket }}</td>
                            <td>Rp {{ number_format($pr->nilai_hps, 0, ',', '.') }}</td>
                            <td>{{ strtoupper(str_replace('_', ' ', $pr->status_paket)) }}</td>
                            <td align="center">{{ $latestReviu ? 'Putaran Ke-'.$latestReviu->putaran_ke : 'Belum Direviu' }}</td>
                            <td>
                                @if($latestReviu)
                                    <strong>{{ strtoupper(str_replace('_', ' ', $latestReviu->status_reviu)) }}</strong>
                                @else
                                    <em>Menunggu Telaah</em>
                                @endif
                            </td>
                            <td>{{ $latestReviu->catatan_perbaikan ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>

    <br>

    {{-- 2. Formulir Penerbitan SPK Baru & Penguncian MAK --}}
    <fieldset>
        <legend><strong>2. Penerbitan Kontrak / Surat Perintah Kerja (SPK) & Penguncian MAK (FR 11 & NFR 6)</strong></legend>

        <p>
            <strong>PEMBERITAHUAN INTEGRITAS ANGGARAN (FR 11.2 & NFR 6):</strong><br>
            Penerbitan dokumen Kontrak SPK merupakan komitmen hukum definitif.
            Sistem secara otomatis akan <strong>mengunci seluruh data Mata Anggaran Kegiatan (MAK)</strong> yang mendasari paket ini (<code>is_locked = true</code>).
            Setelah diterbitkan, MAK tidak dapat diubah atau dihapus untuk menjamin akuntabilitas DIPA kampus.
        </p>

        <form action="{{ route('kontrak.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p>
                <label for="kode_paket_spk"><strong>Pilih Paket Pengadaan (Lolos Reviu):</strong></label><br>
                <select id="kode_paket_spk" name="kode_paket" required>
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList as $pkt)
                        <option value="{{ $pkt->kode_paket }}">
                            [{{ $pkt->kode_paket }}] {{ $pkt->nama_paket }} &bull; Nilai HPS: Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }} (Status: {{ $pkt->status_paket }})
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="id_vendor"><strong>Pilih Rekanan Penyedia / Vendor Terdaftar:</strong></label><br>
                <select id="id_vendor" name="id_vendor" required>
                    <option value="">-- Pilih Rekanan Vendor PNJ --</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id_vendor }}">
                            {{ $v->nama_vendor }} (NPWP: {{ $v->npwp ?? '-' }}) &bull; Badan Usaha: {{ $v->bentuk_usaha ?? 'CV/PT' }}
                        </option>
                    @endforeach
                </select>
            </p>

            <p>
                <label for="nomor_kontrak_spk"><strong>Nomor Resmi SPK / Kontrak:</strong></label><br>
                <input type="text" id="nomor_kontrak_spk" name="nomor_kontrak_spk" value="SPK/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
            </p>

            <p>
                <label for="nilai_kontrak"><strong>Nilai Kesepakatan Kontrak (Rp):</strong></label><br>
                <input type="number" id="nilai_kontrak" name="nilai_kontrak" step="0.01" min="1" required placeholder="Contoh: 145000000">
            </p>

            <p>
                <label for="tanggal_mulai"><strong>Tanggal Mulai Kontrak:</strong></label><br>
                <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ date('Y-m-d') }}" required>
            </p>

            <p>
                <label for="tanggal_selesai"><strong>Tanggal Jatuh Tempo / Selesai Kontrak:</strong></label><br>
                <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
            </p>

            <p>
                <label for="file_spk"><strong>Unggah Berkas Fisik Kontrak / SPK (PDF, Maksimal 10 MB - FR 11.1 & NFR 11):</strong></label><br>
                <input type="file" id="file_spk" name="file_spk" accept="application/pdf">
                <br>
                <small>* Format berkas wajib PDF dengan ukuran maksimal 10 MB. Jika tidak diunggah saat ini, sistem akan membuat template PDF otomatis.</small>
            </p>

            <p>
                <label for="catatan_spk"><strong>Uraian Pekerjaan / Ketentuan Kontrak:</strong></label><br>
                <textarea id="catatan_spk" name="catatan_spk" rows="2" cols="70" placeholder="Contoh: Pengadaan perangkat lab lengkap dengan garansi resmi 3 tahun dan uji fungsi."></textarea>
            </p>

            <p>
                <button type="submit"><strong>Terbitkan & Tanda Tangani Kontrak SPK (Kunci MAK)</strong></button>
            </p>
        </form>
    </fieldset>

    <br>

    {{-- 3. Daftar Kontrak SPK yang Telah Terbit --}}
    <fieldset>
        <legend><strong>3. Daftar Kontrak SPK yang Telah Diterbitkan</strong></legend>

        @if($kontrakList->isEmpty())
            <p><em>Belum ada Kontrak SPK yang diterbitkan untuk paket penugasan Anda.</em></p>
        @else
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>Nomor Kontrak SPK</th>
                        <th>Kode Paket</th>
                        <th>Penyedia / Vendor</th>
                        <th>Nilai Kontrak</th>
                        <th>Masa Berlaku</th>
                        <th>Status SPK</th>
                        <th>Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kontrakList as $k)
                        <tr>
                            <td><strong>{{ $k->nomor_kontrak_spk }}</strong></td>
                            <td>{{ $k->kode_paket }}</td>
                            <td>{{ $k->vendor->nama_vendor ?? '-' }}</td>
                            <td>Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}</td>
                            <td>{{ date('d-m-Y', strtotime($k->tanggal_mulai)) }} s.d {{ date('d-m-Y', strtotime($k->tanggal_selesai)) }}</td>
                            <td>{{ strtoupper($k->status_kontrak ?? $k->status_spk ?? 'AKTIF') }}</td>
                            <td>
                                <a href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_kontrak_spk]) }}" target="_blank">
                                    <button type="button">Unduh SPK (PDF)</button>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </fieldset>
</section>
@endsection
