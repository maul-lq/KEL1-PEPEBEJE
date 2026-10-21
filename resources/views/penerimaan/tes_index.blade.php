@extends('layouts.tes_app')

@section('title', 'Pemeriksaan Barang & Penerbitan BAST - SIM-PBJ Politeknik Negeri Jakarta')

@section('content')
<article>
    <header>
        <h2>Pemeriksaan Fisik Barang & Penerbitan BAST (Unit Perlengkapan / BMN)</h2>
        <p>
            Petugas Unit Perlengkapan melakukan inspeksi fisik kiriman barang atau hasil pekerjaan rekanan penyedia,
            mengunggah foto dokumentasi fisik serta berkas serah terima pihak ketiga (tender),
            dan mengesahkan Berita Acara Serah Terima (BAST) dengan tanda tangan digital.
        </p>
    </header>

    {{-- Formulir Pemeriksaan Barang & Penerbitan BAST --}}
    <section>
        <form action="{{ route('penerimaan.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend><strong>Formulir Pemeriksaan Fisik & Pengesahan BAST</strong></legend>

                <p>
                    <label for="nomor_kontrak_spk"><strong>Pilih Kontrak SPK Aktif:</strong></label><br>
                    <select id="nomor_kontrak_spk" name="nomor_kontrak_spk" required>
                        <option value="">-- Pilih Kontrak SPK --</option>
                        @foreach($kontrakList as $k)
                            <option value="{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}">
                                [{{ $k->nomor_kontrak_spk ?? $k->nomor_spk }}] Paket: {{ $k->nomor_paket }} &bull; Nilai: Rp {{ number_format($k->nilai_kontrak, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <label for="nomor_bast"><strong>Nomor Berita Acara Serah Terima (BAST):</strong></label><br>
                    <input type="text" id="nomor_bast" name="nomor_bast" value="BAST/PNJ/{{ date('Y') }}/{{ rand(100, 999) }}" required>
                </p>

                <p>
                    <label for="tanggal_terima"><strong>Tanggal Pemeriksaan Fisik:</strong></label><br>
                    <input type="date" id="tanggal_terima" name="tanggal_terima" value="{{ date('Y-m-d') }}" required>
                </p>

                <p>
                    <label for="kondisi_barang"><strong>Status Keputusan Kondisi Fisik Barang:</strong></label><br>
                    <select id="kondisi_barang" name="kondisi_barang" required>
                        <option value="terima">Diterima Lengkap (Sesuai Spesifikasi)</option>
                        <option value="pending">Tertahan / Pending (Belum Lengkap)</option>
                        <option value="tidak_sesuai">Tidak Sesuai / Rusak (Retur)</option>
                    </select>
                </p>

                <p>
                    <label for="kategori_belanja"><strong>Penentuan Kategori Belanja (Persediaan / Aset BMN):</strong></label><br>
                    <select id="kategori_belanja" name="kategori_belanja" required>
                        <option value="belanja_barang">Belanja Barang (Dicatat sebagai Persediaan)</option>
                        <option value="belanja_modal">Belanja Modal (Dicatat sebagai Aset BMN)</option>
                    </select>
                </p>

                <p>
                    <label for="foto_barang"><strong>Unggah Foto Dokumentasi Fisik Barang (Dapat lebih dari 1 file):</strong></label><br>
                    <input type="file" id="foto_barang" name="foto_barang[]" accept="image/*,.pdf" multiple>
                    <br>
                    <small>Jumlah foto fleksibel, pastikan kondisi item barang terlihat jelas.</small>
                </p>

                <p>
                    <label for="file_penerimaan_pihak3"><strong>Unggah Dokumen Penerimaan Pihak Ketiga / Ekspedisi (Tender):</strong></label><br>
                    <input type="file" id="file_penerimaan_pihak3" name="file_penerimaan_pihak3" accept=".pdf,image/*">
                </p>

                <p>
                    <label for="catatan_pemeriksaan"><strong>Catatan Hasil Pemeriksaan Fisik:</strong></label><br>
                    <textarea id="catatan_pemeriksaan" name="catatan_pemeriksaan" rows="3" cols="60" placeholder="Pemeriksaan fisik telah selesai dilakukan, kuantitas dan kelengkapan sesuai dengan kontrak."></textarea>
                </p>

                <p>
                    <button type="submit">Sahkan BAST & Tanda Tangan Digital (HMAC)</button>
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    {{-- Daftar Dokumen BAST yang Telah Diterbitkan --}}
    <section>
        <fieldset>
            <legend><strong>Daftar Berita Acara Serah Terima (BAST) Terbit</strong></legend>

            @if($bastList->isEmpty())
                <p><em>Belum ada dokumen BAST yang diterbitkan.</em></p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Nomor BAST</th>
                            <th>Nomor Paket</th>
                            <th>Tanggal Terima</th>
                            <th>Status Fisik</th>
                            <th>Kategori Belanja</th>
                            <th>Tanda Tangan Digital (HMAC)</th>
                            <th>Salinan Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bastList as $b)
                            <tr>
                                <td>{{ $b->nomor_bast }}</td>
                                <td>{{ $b->nomor_paket }}</td>
                                <td>{{ $b->tanggal_penerimaan ? $b->tanggal_penerimaan->format('d/m/Y') : '-' }}</td>
                                <td>{{ strtoupper($b->status_fisik) }}</td>
                                <td>{{ $b->kategori_belanja === 'belanja_modal' ? 'BELANJA MODAL (BMN)' : 'BELANJA BARANG (PERSEDIAAN)' }}</td>
                                <td><code>{{ substr($b->ttd_digital_bast ?? md5($b->nomor_bast), 0, 20) }}...</code></td>
                                <td>
                                    <a href="{{ route('dokumen.pdf', ['jenis' => 'bast', 'id' => $b->nomor_bast]) }}">
                                        <button type="button">Unduh BAST (PDF)</button>
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
