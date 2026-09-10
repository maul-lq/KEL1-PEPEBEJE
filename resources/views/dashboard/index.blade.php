@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- WELCOME BANNER -->
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-wider font-extrabold text-zinc-500 mb-1">
                Portal Manajemen Pengadaan Barang & Jasa
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-zinc-900 tracking-tight">
                Selamat Datang, {{ Auth::user()->name }}
            </h1>
            <p class="text-sm text-zinc-600 mt-1">
                Peran: <strong class="text-zinc-900">{{ Auth::user()->role_label }}</strong> • Unit: {{ Auth::user()->unit_kerja }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if(Auth::user()->isRole('user', 'ppbj'))
                <a href="{{ route('pengadaan.create') }}" class="px-5 py-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-xs transition inline-flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>Ajukan Permohonan Baru</span>
                </a>
            @endif

            <a href="{{ route('monitoring') }}" class="px-5 py-3 rounded-lg bg-zinc-200 hover:bg-zinc-300 text-zinc-900 font-bold text-sm border border-zinc-300 shadow-xs transition inline-flex items-center space-x-2">
                <svg class="w-5 h-5 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Monitoring Real-Time</span>
            </a>
        </div>
    </div>

    <!-- REMINDER / ALARM POPUP NOTICE (FR 12.0) -->
    @if ($activeReminders->isNotEmpty())
        <div class="bg-zinc-900 text-white p-5 rounded-xl border border-zinc-800 shadow-md">
            <div class="flex items-start justify-between">
                <div class="flex items-start space-x-3">
                    <div class="p-2 bg-zinc-800 rounded-lg text-white mt-0.5">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-base text-white">Peringatan Jadwal & Pengingat Aktif ({{ $activeReminders->count() }})</h4>
                        <div class="mt-2 space-y-2">
                            @foreach ($activeReminders as $reminder)
                                <div class="bg-zinc-800 p-3 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-2 border border-zinc-700">
                                    <div>
                                        <div class="font-semibold text-sm text-zinc-100">{{ $reminder->judul }}</div>
                                        <div class="text-xs text-zinc-300 mt-0.5">{{ $reminder->pesan }}</div>
                                        @if($reminder->tanggal_ingat)
                                            <div class="text-[11px] text-zinc-400 mt-1">Tanggal: {{ \Carbon\Carbon::parse($reminder->tanggal_ingat)->format('d F Y') }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center space-x-2 shrink-0">
                                        <a href="{{ route('pengadaan.show', $reminder->pengadaan_id) }}" class="px-3 py-1.5 bg-white text-zinc-900 rounded text-xs font-bold hover:bg-zinc-200">
                                            Lihat Berkas
                                        </a>
                                        <form method="POST" action="{{ route('reminders.dismiss', $reminder) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-zinc-700 text-zinc-300 rounded text-xs font-medium hover:bg-zinc-600">
                                                Tutup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- STATISTIK KARTU BESAR -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Perlu Tindakan Saya</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $pendingTasksCount }}</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded bg-zinc-900 text-white">Prioritas</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Menunggu disposisi/aksi role saat ini</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Sedang Berjalan</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $totalBerjalan }}</span>
                <span class="text-xs font-medium text-zinc-500">Berkas Aktif</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Dalam tahap proses pengadaan</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Selesai / Lunas</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $totalSelesai }}</span>
                <span class="text-xs font-medium text-zinc-500">Tuntas</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Pembayaran telah dicairkan</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-zinc-300 shadow-xs">
            <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Total Seluruh Pengadaan</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900">{{ $totalPengadaan }}</span>
                <span class="text-xs font-medium text-zinc-500">Pengajuan</span>
            </div>
            <p class="text-xs text-zinc-500 mt-2">Tercatat dalam sistem PBJ</p>
        </div>
    </div>

    <!-- TUGAS YANG MEMERLUKAN AKSI ROLE INI -->
    @if ($pendingTasks->isNotEmpty())
        <div class="bg-white rounded-xl border-2 border-zinc-900 shadow-sm overflow-hidden">
            <div class="bg-zinc-900 text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">Daftar Berkas Memerlukan Tindakan Anda</h3>
                    <p class="text-xs text-zinc-300">Segera tinjau atau lengkapi data berikut untuk melanjutkan proses</p>
                </div>
                <span class="bg-white text-zinc-900 text-xs font-extrabold px-3 py-1 rounded-full">
                    {{ $pendingTasks->count() }} Berkas
                </span>
            </div>

            <div class="divide-y divide-zinc-200">
                @foreach ($pendingTasks as $item)
                    <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-zinc-50 transition">
                        <div class="space-y-1">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-mono font-bold text-zinc-600 bg-zinc-200 px-2 py-0.5 rounded">{{ $item->nomor_pengadaan }}</span>
                                <x-badge :status="$item->status" :label="$item->status_label" />
                                <span class="text-xs text-zinc-500">• Jalur: <strong>{{ $item->jalur_label }}</strong></span>
                            </div>
                            <h4 class="text-base font-bold text-zinc-900">{{ $item->nama_pengadaan }}</h4>
                            <div class="text-xs text-zinc-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span>Pemohon: <strong>{{ $item->user->name }}</strong> ({{ $item->user->unit_kerja }})</span>
                                <span>Estimasi: <strong>{{ $item->formatted_estimasi_anggaran }}</strong></span>
                                @if($item->nomor_mak)
                                    <span>MAK: <strong>{{ $item->nomor_mak }}</strong></span>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center space-x-2">
                            <!-- TOMBOL AKSI CEPAT SESUAI ROLE -->
                            @if(Auth::user()->role === 'wadir2' && $item->status === 'diajukan')
                                <a href="{{ route('wadir2.review', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Tinjau & Setujui
                                </a>
                            @elseif(Auth::user()->role === 'wadir2' && $item->status === 'memo_pembayaran_ppk')
                                <a href="{{ route('pengadaan.show', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    ACC Pembayaran Wadir 2
                                </a>
                            @elseif(Auth::user()->role === 'perencanaan' && $item->status === 'disetujui_wadir2')
                                <a href="{{ route('perencanaan.input-mak', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Input Nomor MAK
                                </a>
                            @elseif(Auth::user()->role === 'ppbj' && $item->status === 'mak_ditetapkan')
                                <a href="{{ route('ppbj.register', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Registrasi Pengadaan
                                </a>
                            @elseif(Auth::user()->role === 'ppbj' && in_array($item->status, ['disetujui_ppk', 'revisi_ppk']))
                                <a href="{{ route('ppbj.create-spk', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Terbitkan SPK/Kontrak
                                </a>
                            @elseif(Auth::user()->role === 'ppbj' && $item->status === 'barang_diterima')
                                <a href="{{ route('ppbj.ajukan-pembayaran', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Unggah Berkas Bayar
                                </a>
                            @elseif(Auth::user()->role === 'ppk_pp' && $item->status === 'teregistrasi_ppbj')
                                <a href="{{ route('ppk.review', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Reviu Dokumen PPK
                                </a>
                            @elseif(Auth::user()->role === 'perlengkapan' && in_array($item->status, ['spk_diterbitkan', 'barang_pending']))
                                <a href="{{ route('perlengkapan.penerimaan', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Pemeriksaan BAST & Foto
                                </a>
                            @elseif(Auth::user()->role === 'keuangan' && $item->status === 'sp_pembayaran_terbit')
                                <a href="{{ route('keuangan.pencairan', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Pencairan & Bukti Bayar
                                </a>
                            @else
                                <a href="{{ route('pengadaan.show', $item) }}" class="px-4 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm rounded-lg shadow-xs">
                                    Buka Berkas
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- TABEL REKAP TERBARU -->
    <div class="bg-white rounded-xl border border-zinc-300 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-zinc-900">Riwayat Pengadaan Terakhir</h3>
                <p class="text-xs text-zinc-500">Daftar permohonan pengadaan barang dan jasa terkini</p>
            </div>
            <a href="{{ route('pengadaan.index') }}" class="text-xs font-bold text-zinc-900 hover:underline">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-zinc-100 text-zinc-700 font-bold border-b border-zinc-300 text-xs">
                        <th class="py-3 px-4">No. Pengadaan</th>
                        <th class="py-3 px-4">Nama Pengadaan</th>
                        <th class="py-3 px-4">Pemohon</th>
                        <th class="py-3 px-4">Jalur Pengadaan</th>
                        <th class="py-3 px-4">Estimasi / Kontrak</th>
                        <th class="py-3 px-4">Status Terkini</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($recentPengadaans as $pengadaan)
                        <tr class="hover:bg-zinc-50">
                            <td class="py-3.5 px-4 font-mono font-bold text-xs text-zinc-800">
                                {{ $pengadaan->nomor_pengadaan }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</div>
                                <div class="text-xs text-zinc-500">{{ $pengadaan->jenis_pengadaan }} • Surat: {{ $pengadaan->nomor_surat_user ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-medium text-zinc-900">{{ $pengadaan->user->name }}</div>
                                <div class="text-zinc-500">{{ $pengadaan->user->unit_kerja }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-medium text-zinc-700">
                                {{ $pengadaan->jalur_label }}
                            </td>
                            <td class="py-3.5 px-4 text-xs font-bold text-zinc-900">
                                @if($pengadaan->nilai_kontrak)
                                    <div>{{ $pengadaan->formatted_nilai_kontrak }}</div>
                                    <div class="text-[10px] text-zinc-500 font-normal">Kontrak SPK</div>
                                @else
                                    <div>{{ $pengadaan->formatted_estimasi_anggaran }}</div>
                                    <div class="text-[10px] text-zinc-500 font-normal">Estimasi User</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <x-badge :status="$pengadaan->status" :label="$pengadaan->status_label" />
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 border border-zinc-300 text-zinc-900 font-bold text-xs rounded transition">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500 text-sm">
                                Belum ada berkas pengadaan yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
