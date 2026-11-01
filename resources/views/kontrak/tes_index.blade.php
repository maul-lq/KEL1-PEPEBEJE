@extends('layouts.tes_app')

@section('title', 'Review PP, Approval PPK, Belanja, & Dokumen Perikatan - SIM-PBJ PNJ')

@section('content')
<article>
    <header>
        <h2>Review Kelengkapan PP, Approval PPK, Eksekusi Belanja, &amp; Dokumen Perikatan (&gt; Rp 50 Juta)</h2>
        <p>
            @if(Auth::user()->role === 'pp')
                <strong>Pejabat Pengadaan (PP):</strong>
                Terlibat pada paket &gt; Rp 50.000.000 s.d. Rp 200.000.000 (baik Tender maupun Non-Tender).
                PP hanya mereview kelengkapan berkas serta menginput <strong>Nominal Harga Perkiraan</strong>, <strong>Nominal Harga Akhir</strong>, dan <strong>Vendor/Pemenang Lelang</strong>.
                <em>PP tidak memiliki wewenang klik Approval (persetujuan tetap oleh PPK) dan tidak menerbitkan BAST. Setelah disetujui PPK, PP memiliki kewenangan mengeksekusi belanja.</em>
            @elseif(Auth::user()->role === 'ppk')
                <strong>Pejabat Pembuat Komitmen (PPK &mdash; {{ Auth::user()->nama }}):</strong>
                Memberikan <strong>Approval</strong> pada paket komitmen Anda (baik hasil koordinasi review PP pada paket 50&ndash;200 juta maupun review langsung &amp; eksekusi belanja pada paket &gt; 200 juta).
                <em>Paket milik akun PPK lain terisolasi dan tidak dapat diakses dari akun ini. Penguncian MAK berada pada Bagian Perencanaan.</em>
            @else
                <strong>Koordinator Pengadaan Barang dan Jasa (PPBJ):</strong>
                Setelah belanja dieksekusi oleh PP (50&ndash;200 juta) atau PPK (&gt; 200 juta), PPBJ mengunggah <strong>salah satu</strong> dokumen perikatan
                (<strong>Surat Pesanan</strong>, <strong>SPK</strong>, atau <strong>Surat Kontrak</strong> &mdash; bukan diunggah keduanya) beserta jadwal pengingat (Reminder).
            @endif
        </p>
    </header>

    {{-- 1. Panel Review Kelengkapan PP & Approval PPK --}}
    <section>
        <fieldset>
            <legend><strong>1. Review Kelengkapan PP (50&ndash;200 Juta) &amp; Keputusan Approval PPK</strong></legend>

            @if($paketReviu->isEmpty())
                <p><em>Saat ini tidak ada paket &gt; Rp 50 Juta yang menunggu proses review atau approval pada akun Anda.</em></p>
            @else
                @if(in_array(Auth::user()->role, ['pp', 'ppk', 'admin']))
                    <form action="{{ route('kontrak.reviu') }}" method="POST">
                        @csrf
                        <p>
                            <label for="nomor_paket_reviu"><strong>Pilih Paket Pengadaan (&gt; Rp 50 Juta):</strong></label><br>
                            <select id="nomor_paket_reviu" name="nomor_paket" required>
                                <option value="">-- Pilih Paket Pengadaan --</option>
                                @foreach($paketReviu as $pktR)
                                    <option value="{{ $pktR->nomor_paket }}">
                                        [{{ $pktR->nomor_paket }}] {{ $pktR->nama_paket }} &bull; HPS: Rp {{ number_format($pktR->nilai_hps, 0, ',', '.') }} &bull; Status: {{ strtoupper(str_replace('_', ' ', $pktR->status_paket)) }}
                                    </option>
                                @endforeach
                            </select>
                        </p>

                        @if(Auth::user()->role === 'pp')
                            <fieldset>
                                <legend>Input Hasil Review PP (Wajib saat Verifikasi Kelengkapan Selesai)</legend>
                                <p>
                                    <label for="nominal_harga_perkiraan"><strong>Nominal Harga Perkiraan (Rp):</strong></label><br>
                                    <input type="number" id="nominal_harga_perkiraan" name="nominal_harga_perkiraan" step="0.01" min="1" placeholder="Contoh: 120000000">
                                </p>
                                <p>
                                    <label for="nominal_harga_akhir"><strong>Nominal Harga Akhir / Negosiasi (Rp):</strong></label><br>
                                    <input type="number" id="nominal_harga_akhir" name="nominal_harga_akhir" step="0.01" min="1" placeholder="Contoh: 115000000">
                                </p>
                                <p>
                                    <label for="npwp_vendor_reviu"><strong>Vendor / Pemenang Pengadaan:</strong></label><br>
                                    <select id="npwp_vendor_reviu" name="npwp_vendor">
                                        <option value="">-- Pilih Vendor / Pemenang --</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->npwp }}">{{ $v->nama_vendor }} (NPWP: {{ $v->npwp }})</option>
                                        @endforeach
                                    </select>
                                </p>
                            </fieldset>

                            <p>
                                <strong>Tindakan Review PP (PP Tidak Dapat Klik Approval Final):</strong><br>
                                <label>
                                    <input type="radio" name="status_reviu" value="terverifikasi_pp" checked>
                                    <strong>Verifikasi Kelengkapan Selesai</strong> &mdash; Simpan Harga Perkiraan, Harga Akhir, &amp; Vendor lalu minta Approval PPK.
                                </label><br>
                                <label>
                                    <input type="radio" name="status_reviu" value="perlu_perbaikan">
                                    <strong>Perlu Perbaikan</strong> &mdash; Kembalikan berkas ke PPBJ disertai catatan revisi.
                                </label>
                            </p>
                        @else
                            <p>
                                <strong>Keputusan Approval Pejabat Pembuat Komitmen (PPK):</strong><br>
                                <label>
                                    <input type="radio" id="reviu_setuju" name="status_reviu" value="disetujui" checked>
                                    <strong>Klik Approval (Disetujui PPK)</strong> &mdash; Untuk 50&ndash;200 juta memberi wewenang belanja ke PP; untuk &gt; 200 juta menyetujui pengadaan PPK.
                                </label><br>
                                <label>
                                    <input type="radio" id="reviu_perbaikan" name="status_reviu" value="perlu_perbaikan">
                                    <strong>Perlu Perbaikan</strong> &mdash; Kembalikan berkas disertai rincian catatan revisi.
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="eksekusi_belanja_sekaligus" value="1" checked>
                                    Khusus Paket &gt; Rp 200 Juta: Sekaligus catat eksekusi kewenangan belanja oleh PPK.
                                </label>
                            </p>
                        @endif

                        <p>
                            <label for="catatan_perbaikan"><strong>Catatan Review / Koordinasi / Perbaikan:</strong></label><br>
                            <textarea id="catatan_perbaikan" name="catatan_perbaikan" rows="3" cols="70" placeholder="Wajib diisi jika memilih Perlu Perbaikan; opsional untuk catatan koordinasi."></textarea>
                        </p>

                        <p>
                            <button type="submit"><strong>Simpan Hasil Review / Approval</strong></button>
                        </p>
                    </form>
                    <hr>
                @endif

                <h4>Daftar Paket &amp; Status Koordinasi PP &ndash; PPK:</h4>
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor Paket</th>
                            <th>Nama Paket &amp; Metode</th>
                            <th>Harga Perkiraan</th>
                            <th>Harga Akhir</th>
                            <th>Vendor / Pemenang</th>
                            <th>Status Paket</th>
                            <th>Putaran &amp; Catatan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paketReviu as $pr)
                            @php
                                $latestReviu = $pr->reviuDokumen->sortByDesc('putaran_ke')->first();
                            @endphp
                            <tr>
                                <td>{{ $pr->nomor_paket }}</td>
                                <td>{{ $pr->nama_paket }}<br><small>{{ strtoupper(str_replace('_', ' ', $pr->metode_pengadaan)) }}</small></td>
                                <td>Rp {{ number_format($pr->nominal_harga_perkiraan ?? $pr->nilai_hps, 0, ',', '.') }}</td>
                                <td>{{ $pr->nominal_harga_akhir ? 'Rp ' . number_format($pr->nominal_harga_akhir, 0, ',', '.') : '-' }}</td>
                                <td>{{ $pr->vendor->nama_vendor ?? ($pr->npwp_vendor ?? '-') }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $pr->status_paket)) }}</td>
                                <td>
                                    @if($latestReviu)
                                        Ke-{{ $latestReviu->putaran_ke }} ({{ strtoupper($latestReviu->role_reviewer) }}: {{ $latestReviu->status_reviu }})<br>
                                        <small>{{ $latestReviu->catatan_perbaikan ?? '-' }}</small>
                                    @else
                                        <em>Menunggu Review</em>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>

    <hr>

    {{-- 2. Panel Eksekusi Kewenangan Belanja oleh PP (50-200 Juta pasca-Approval PPK) atau PPK (> 200 Juta) --}}
    @if(in_array(Auth::user()->role, ['pp', 'ppk']))
    <section>
        <fieldset>
            <legend><strong>2. Eksekusi Kewenangan Belanja ({{ Auth::user()->role === 'pp' ? 'Pejabat Pengadaan: 50 s.d. 200 Juta Pasca-Approval PPK' : 'PPK: > 200 Juta' }})</strong></legend>
            <p>
                Pada paket 50&ndash;200 juta yang telah mendapat <strong>Approval PPK</strong>, kewenangan eksekusi belanja berada di tangan <strong>PP</strong> (bukan kembali ke PPBJ).
                Pada paket di atas 200 juta, eksekusi belanja dilakukan oleh <strong>PPK</strong>.
            </p>

            <form action="{{ route('kontrak.eksekusi-belanja') }}" method="POST">
                @csrf
                <p>
                    <label for="nomor_paket_belanja"><strong>Pilih Paket Siap Eksekusi Belanja:</strong></label><br>
                    <select id="nomor_paket_belanja" name="nomor_paket" required>
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketList as $pktB)
                            <option value="{{ $pktB->nomor_paket }}">
                                [{{ $pktB->nomor_paket }}] {{ $pktB->nama_paket }} &bull; Status: {{ strtoupper(str_replace('_', ' ', $pktB->status_paket)) }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="catatan_belanja"><strong>Catatan Pelaksanaan Belanja / Pemesanan (Opsional):</strong></label><br>
                    <input type="text" id="catatan_belanja" name="catatan_belanja" size="65" placeholder="Contoh: Pemesanan barang melalui E-Katalog Inaproc telah dieksekusi sesuai Approval PPK.">
                </p>

                <p>
                    <button type="submit"><strong>Eksekusi Kewenangan Belanja</strong></button>
                </p>
            </form>
        </fieldset>
    </section>
    <hr>
    @endif

    {{-- 3. Formulir Upload Salah Satu Dokumen Perikatan Terpilih (> Rp 50 Juta) & Reminder --}}
    <section>
        <fieldset>
            <legend><strong>3. Unggah Salah Satu Dokumen Perikatan Terpilih (Surat Pesanan / SPK / Surat Kontrak) &amp; Set Reminder</strong></legend>
            <p>
                Khusus paket pengadaan <strong>di atas Rp 50.000.000</strong>, pilih dan unggah <strong>hanya salah satu</strong> dokumen perikatan berikut
                (bukan diunggah keduanya):
                <strong>Surat Pesanan</strong> atau <strong>SPK</strong> (umumnya E-Katalog / Inaproc), atau <strong>Surat Kontrak</strong> (umumnya Non-Tender / Tender pihak ketiga).
            </p>

            <form action="{{ route('kontrak.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <p>
                    <label for="kode_paket_spk"><strong>Pilih Paket Pengadaan (&gt; Rp 50 Juta, Belum Memiliki Dokumen Perikatan):</strong></label><br>
                    <select id="kode_paket_spk" name="kode_paket" required>
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketList as $pkt)
                            @if(!$pkt->kontrakSpk)
                                <option value="{{ $pkt->nomor_paket }}">
                                    [{{ $pkt->nomor_paket }}] {{ $pkt->nama_paket }} &bull; Harga Akhir/HPS: Rp {{ number_format($pkt->nominal_harga_akhir ?? $pkt->nilai_hps, 0, ',', '.') }} ({{ $pkt->status_paket }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="tipe_dokumen"><strong>Pilih Jenis Dokumen Perikatan (Pilih Salah Satu):</strong></label><br>
                    <select id="tipe_dokumen" name="tipe_dokumen" required>
                        <option value="spk">SPK &mdash; Surat Perintah Kerja (E-Katalog / Inaproc)</option>
                        <option value="surat_pesanan">Surat Pesanan (E-Katalog / Inaproc)</option>
                        <option value="surat_kontrak">Surat Kontrak (Pihak Ketiga: Tender / Non-Tender)</option>
                    </select>
                </p>

                <p>
                    <label for="id_vendor"><strong>Pilih Rekanan Penyedia / Vendor Pemenang:</strong></label><br>
                    <select id="id_vendor" name="id_vendor">
                        <option value="">-- Gunakan Vendor Hasil Review / Pilih Vendor --</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->npwp }}">
                                {{ $v->nama_vendor }} (NPWP: {{ $v->npwp }}) &bull; {{ $v->kategori_vendor ?? 'Rekanan' }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_kontrak_spk"><strong>Nomor Resmi Dokumen (Surat Pesanan / SPK / Kontrak):</strong></label><br>
                    <input type="text" id="nomor_kontrak_spk" name="nomor_kontrak_spk" value="DOC/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="nilai_kontrak"><strong>Nilai Perikatan Final (Rp):</strong></label><br>
                    <input type="number" id="nilai_kontrak" name="nilai_kontrak" step="0.01" min="1" required placeholder="Contoh: 145000000">
                </p>

                <p>
                    <label for="tanggal_mulai"><strong>Tanggal Mulai Perikatan:</strong></label><br>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ date('Y-m-d') }}" required>
                </p>

                <p>
                    <label for="tanggal_selesai"><strong>Target Pengiriman / Jatuh Tempo Selesai (Reminder Otomatis):</strong></label><br>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                </p>

                <p>
                    <label for="file_spk"><strong>Unggah Berkas Fisik Dokumen Terpilih (PDF, Maksimal 10 MB):</strong></label><br>
                    <input type="file" id="file_spk" name="file_spk" accept="application/pdf">
                </p>

                <p>
                    <label for="catatan_spk"><strong>Uraian Pekerjaan / Ketentuan Pengiriman:</strong></label><br>
                    <textarea id="catatan_spk" name="catatan_spk" rows="2" cols="70" placeholder="Rincian pengiriman perangkat dan uji fungsi sebelum BAST di Unit Perlengkapan."></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Unggah Dokumen Perikatan Terpilih &amp; Aktifkan Reminder</strong></button>
                </p>
            </form>
        </fieldset>
    </section>

    <hr>

    {{-- 4. Daftar Dokumen Perikatan Tunggal yang Telah Diterbitkan --}}
    <section>
        <fieldset>
            <legend><strong>4. Daftar Dokumen Perikatan Terbit (Surat Pesanan / SPK / Surat Kontrak)</strong></legend>

            @if($kontrakList->isEmpty())
                <p><em>Belum ada dokumen perikatan yang diunggah.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nomor Dokumen</th>
                            <th>Jenis Dokumen Terpilih</th>
                            <th>Nomor Paket</th>
                            <th>Penyedia / Vendor</th>
                            <th>Nilai Perikatan</th>
                            <th>Masa Berlaku &amp; Reminder</th>
                            <th>Salinan PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kontrakList as $k)
                            <tr>
                                <td><strong>{{ $k->nomor_spk }}</strong></td>
                                <td><u>{{ strtoupper(str_replace('_', ' ', $k->tipe_dokumen ?? 'spk')) }}</u></td>
                                <td>{{ $k->nomor_paket }}</td>
                                <td>{{ $k->paket->vendor->nama_vendor ?? ($k->vendor->nama_vendor ?? '-') }}</td>
                                <td>Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}</td>
                                <td>{{ $k->tanggal_mulai ? date('d-m-Y', strtotime($k->tanggal_mulai)) : '-' }} s.d. {{ $k->tanggal_selesai ? date('d-m-Y', strtotime($k->tanggal_selesai)) : '-' }}</td>
                                <td>
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'spk', 'id' => $k->nomor_spk]) }}">
                                        <button type="button">Unduh Dokumen (PDF)</button>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </fieldset>
    </section>
</article>
@endsection
