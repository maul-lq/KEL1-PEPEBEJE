@extends('layouts.tes_app')

@section('title', 'Notifikasi dan Reminder - SIM-PBJ')

@section('content')
<article>
    <header>
        <h2>Notifikasi dan Reminder</h2>
        <p>
            Pusat pengingat jadwal, alarm batas waktu SPK &amp; termin, serta tugas verifikasi seluruh alur pengadaan barang dan jasa khusus akun Anda (<strong>{{ Auth::user()->nama }}</strong>).
        </p>

        @if($tabCounts['belum_dibaca'] > 0)
            <form action="{{ route('notifikasi.mark-all-read') }}" method="POST">
                @csrf
                <p>
                    <button type="submit"><strong>Tandai Semua Dibaca ({{ $tabCounts['belum_dibaca'] }})</strong></button>
                </p>
            </form>
        @endif
    </header>

    <hr>

    {{-- 1. Tab Navigasi Semantik --}}
    <nav>
        <strong>Kategori Tab Notifikasi:</strong>
        <ul>
            <li>
                @if($currentTab === 'semua')
                    <strong><u>Semua ({{ $tabCounts['semua'] }})</u></strong>
                @else
                    <a href="{{ route('notifikasi.index', ['tab' => 'semua', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}">Semua ({{ $tabCounts['semua'] }})</a>
                @endif
            </li>
            <li>
                @if($currentTab === 'belum_dibaca')
                    <strong><u>Belum dibaca ({{ $tabCounts['belum_dibaca'] }})</u></strong>
                @else
                    <a href="{{ route('notifikasi.index', ['tab' => 'belum_dibaca', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}">Belum dibaca ({{ $tabCounts['belum_dibaca'] }})</a>
                @endif
            </li>
            <li>
                @if($currentTab === 'perlu_tindakan')
                    <strong><u>Perlu tindakan ({{ $tabCounts['perlu_tindakan'] }})</u></strong>
                @else
                    <a href="{{ route('notifikasi.index', ['tab' => 'perlu_tindakan', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}">Perlu tindakan ({{ $tabCounts['perlu_tindakan'] }})</a>
                @endif
            </li>
            <li>
                @if($currentTab === 'pengingat')
                    <strong><u>Pengingat ({{ $tabCounts['pengingat'] }})</u></strong>
                @else
                    <a href="{{ route('notifikasi.index', ['tab' => 'pengingat', 'q' => request('q'), 'jenis' => request('jenis'), 'periode' => request('periode')]) }}">Pengingat ({{ $tabCounts['pengingat'] }})</a>
                @endif
            </li>
        </ul>
    </nav>

    <hr>

    {{-- 2. Formulir Filter & Pencarian Semantik --}}
    <section>
        <form action="{{ route('notifikasi.index') }}" method="GET">
            <fieldset>
                <legend><strong>Filter &amp; Pencarian Notifikasi</strong></legend>
                <input type="hidden" name="tab" value="{{ $currentTab }}">

                <p>
                    <label for="pencarian_q"><strong>Cari no. surat / judul:</strong></label><br>
                    <input type="text" id="pencarian_q" name="q" value="{{ request('q') }}" placeholder="Ketik no. surat, paket, atau kata kunci judul...">
                </p>

                <p>
                    <label for="filter_jenis"><strong>Jenis Notifikasi:</strong></label><br>
                    <select id="filter_jenis" name="jenis">
                        <option value="semua" {{ request('jenis', 'semua') === 'semua' ? 'selected' : '' }}>Semua Jenis</option>
                        <option value="pengingat" {{ request('jenis') === 'pengingat' ? 'selected' : '' }}>Pengingat Jadwal</option>
                        <option value="perlu_tindakan" {{ request('jenis') === 'perlu_tindakan' ? 'selected' : '' }}>Perlu Tindakan</option>
                        <option value="verifikasi" {{ request('jenis') === 'verifikasi' ? 'selected' : '' }}>Verifikasi Usulan</option>
                        <option value="pembayaran" {{ request('jenis') === 'pembayaran' ? 'selected' : '' }}>Pembayaran &amp; SP2D</option>
                        <option value="pengiriman" {{ request('jenis') === 'pengiriman' ? 'selected' : '' }}>Pengiriman Barang</option>
                        <option value="spk" {{ request('jenis') === 'spk' ? 'selected' : '' }}>Tembusan SPK</option>
                    </select>
                </p>

                <p>
                    <label for="filter_periode"><strong>Periode:</strong></label><br>
                    <select id="filter_periode" name="periode">
                        <option value="semua" {{ request('periode', 'semua') === 'semua' ? 'selected' : '' }}>Semua Periode</option>
                        <option value="hari_ini" {{ request('periode') === 'hari_ini' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="7_hari" {{ request('periode') === '7_hari' ? 'selected' : '' }}>7 Hari Terakhir</option>
                        <option value="30_hari" {{ request('periode') === '30_hari' ? 'selected' : '' }}>30 Hari Terakhir</option>
                        <option value="bulan_ini" {{ request('periode') === 'bulan_ini' ? 'selected' : '' }}>Bulan Ini</option>
                    </select>
                </p>

                <p>
                    <button type="submit">Terapkan Filter &amp; Cari</button>
                    @if(request('q') || (request('jenis') && request('jenis') !== 'semua') || (request('periode') && request('periode') !== 'semua') || ($currentTab && $currentTab !== 'semua'))
                        &nbsp;
                        <a href="{{ route('notifikasi.index') }}">Reset Filter</a>
                    @endif
                </p>
            </fieldset>
        </form>
    </section>

    <hr>

    {{-- 3. Daftar Notifikasi dalam Tabel Semantik --}}
    <section>
        <fieldset>
            <legend><strong>Daftar Pemberitahuan &amp; Pengingat Masuk</strong></legend>

            @if($notifikasiList->isEmpty())
                <p><em>Tidak ada notifikasi atau pengingat yang cocok dengan kriteria pencarian akun Anda.</em></p>
            @else
                <table border="1" cellpadding="6" cellspacing="0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Status</th>
                            <th>Kategori</th>
                            <th>Judul &amp; Uraian Pemberitahuan</th>
                            <th>Referensi Berkas Dinas</th>
                            <th>Waktu Diterima</th>
                            <th>Tindak Lanjut Alur</th>
                            <th>Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifikasiList as $index => $notif)
                            <tr>
                                <td align="center">{{ $notifikasiList->firstItem() + $index }}</td>
                                <td align="center">
                                    @if(!$notif->is_read)
                                        <strong>[BELUM DIBACA]</strong>
                                    @else
                                        <small>[Sudah Dibaca]</small>
                                    @endif
                                </td>
                                <td>
                                    <u>{{ $notif->jenis_badge }}</u>
                                </td>
                                <td>
                                    <strong>{{ $notif->judul }}</strong>
                                    <p>{{ $notif->pesan }}</p>
                                </td>
                                <td>
                                    <code>{{ $notif->referensi_teks }}</code>
                                </td>
                                <td align="center">
                                    {{ $notif->created_at ? $notif->created_at->format('d/m/Y H:i') . ' WIB' : '-' }}
                                </td>
                                <td align="center">
                                    <a href="{{ $notif->action_url }}">
                                        <button type="button">{{ $notif->action_label }}</button>
                                    </a>
                                </td>
                                <td align="center">
                                    @if(!$notif->is_read)
                                        <form action="{{ route('notifikasi.read', $notif->id) }}" method="POST">
                                            @csrf
                                            <button type="submit">Tandai Dibaca</button>
                                        </form>
                                    @else
                                        <small>Selesai</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div>
                    {{ $notifikasiList->links() }}
                </div>
            @endif
        </fieldset>
    </section>
</article>
@endsection
