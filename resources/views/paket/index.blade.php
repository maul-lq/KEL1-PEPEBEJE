@extends('layouts.app')

@section('title', 'Daftar Paket Pengadaan & Alih Tugas PPK - SIM-PBJ PNJ')

@section('content')
<div class="space-y-8">
    {{-- Header & Registrasi Baru --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b pb-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manajemen Paket Pengadaan &amp; Penugasan PPK</h1>
                <p class="text-sm text-slate-500">Unit Pengadaan Barang dan Jasa (PPBJ) Politeknik Negeri Jakarta</p>
            </div>
            <a href="{{ route('paket.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-sky-700 text-white rounded text-sm font-semibold hover:bg-sky-800 transition shadow">
                + Registrasi Paket Pengadaan Baru
            </a>
        </div>
        <p class="text-sm text-slate-600">
            Halaman ini menampilkan seluruh paket pengadaan di lingkungan Politeknik Negeri Jakarta,
            alur penugasan berdasarkan ambang batas HPS, pemecahan berkas per jenis pengadaan (FR 7.2), serta fitur alih tugas PPK (FR 7.3).
        </p>
    </div>

    {{-- 1. Tabel Daftar Paket Pengadaan --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
            <span>Daftar Paket Pengadaan Aktif</span>
            <span class="text-xs font-normal text-slate-500">Total: {{ $paketList->total() }} paket</span>
        </h2>

        @if($paketList->isEmpty())
            <div class="text-center py-10 bg-slate-50 rounded border border-dashed border-slate-200">
                <p class="text-sm text-slate-500">Belum ada paket pengadaan yang terdaftar.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold uppercase tracking-wider">
                            <th class="p-3 border-b">Kode Paket</th>
                            <th class="p-3 border-b">Nama Paket</th>
                            <th class="p-3 border-b">Jenis &amp; Metode</th>
                            <th class="p-3 border-b">Nilai HPS</th>
                            <th class="p-3 border-b">Jalur Routing</th>
                            <th class="p-3 border-b">Memo PPBJ</th>
                            <th class="p-3 border-b">Pejabat Pengadaan</th>
                            <th class="p-3 border-b">PPK Penanggung Jawab</th>
                            <th class="p-3 border-b">Status</th>
                            <th class="p-3 border-b text-center">Aksi Dokumen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($paketList as $pkt)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono font-bold text-sky-800">{{ $pkt->kode_paket }}</td>
                                <td class="p-3 font-semibold text-slate-800">{{ $pkt->nama_paket }}</td>
                                <td class="p-3 text-slate-600">{{ $pkt->jenis_pengadaan }} &bull; {{ $pkt->metode_pengadaan }}</td>
                                <td class="p-3 font-mono font-bold text-emerald-700">Rp {{ number_format($pkt->nilai_hps, 0, ',', '.') }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold 
                                        {{ $pkt->jalur_routing === 'ppk_diatas_200jt' ? 'bg-purple-100 text-purple-800' : ($pkt->jalur_routing === 'pp_50jt_sd_200jt' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                                        {{ strtoupper(str_replace('_', ' ', $pkt->jalur_routing)) }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-600">
                                    @if($pkt->jalur_routing === 'ppk_diatas_200jt')
                                        <span class="text-slate-400 italic">Tanpa Memo (&ge; 200jt)</span>
                                    @else
                                        <span class="font-mono text-xs">{{ $pkt->nomor_memo_ppbj ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-slate-700">{{ $pkt->ppUser->nama ?? '-' }}</td>
                                <td class="p-3 text-slate-700">
                                    @if(in_array($pkt->jalur_routing, ['ppk_diatas_200jt', 'pp_50jt_sd_200jt']))
                                        <strong class="text-slate-900">{{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }}</strong>
                                    @else
                                        <span class="text-slate-400 italic">(Pengadaan Langsung PPBJ)</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                                        {{ strtoupper(str_replace('_', ' ', $pkt->status_paket)) }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    @if(in_array(Auth::user()->role, ['pp', 'ppk', 'ppbj', 'admin']))
                                        <a href="{{ route('kontrak.index') }}" class="inline-block px-3 py-1 bg-slate-700 text-white rounded hover:bg-slate-800 transition font-semibold text-xs">
                                            Reviu / SPK
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $paketList->links() }}
            </div>
        @endif
    </div>

    {{-- 2. Formulir Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan (FR 7.2) --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-2 pb-2 border-b border-slate-100">
            Pemecahan (Split) Berkas Berdasarkan Jenis Pengadaan (FR 7.2)
        </h2>
        <p class="text-sm text-slate-600 mb-4">
            Jika satu usulan permohonan yang telah disetujui menggabungkan beberapa jenis pengadaan (misalnya Barang dan Jasa/Konstruksi),
            PPBJ dapat memecah berkas usulan menjadi surat pecahan baru sesuai jenisnya sebelum diregistrasikan ke paket pengadaan.
        </p>

        <form action="{{ route('paket.split-jenis') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="nomor_surat_induk_ppbj" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Usulan Disetujui yang Akan Dipecah <span class="text-rose-500">*</span>
                    </label>
                    <select id="nomor_surat_induk_ppbj" name="nomor_surat_induk" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Surat Permohonan Disetujui --</option>
                        @foreach($permohonanDisetujui ?? [] as $p)
                            <option value="{{ $p->nomor_surat }}">
                                [{{ $p->nomor_surat }}] {{ $p->perihal }} (Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="nomor_surat_baru_ppbj" class="block text-xs font-semibold text-slate-700 mb-1">
                        Nomor Surat Pecahan Baru <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nomor_surat_baru_ppbj" name="nomor_surat_baru" value="B/SPLIT-PBJ/{{ date('Y') }}/{{ rand(100, 999) }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="judul_pengadaan_ppbj" class="block text-xs font-semibold text-slate-700 mb-1">
                        Judul / Uraian Pengadaan Pecahan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="judul_pengadaan_ppbj" name="judul_pengadaan" placeholder="Contoh: Pekerjaan Instalasi Jaringan Lab TIK" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="jenis_pengadaan_split" class="block text-xs font-semibold text-slate-700 mb-1">
                        Jenis Pengadaan Pecahan <span class="text-rose-500">*</span>
                    </label>
                    <select id="jenis_pengadaan_split" name="jenis_pengadaan_split" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="barang">Pengadaan Barang</option>
                        <option value="jasa">Pengadaan Jasa Lainnya / Konsultansi</option>
                        <option value="konstruksi">Pekerjaan Konstruksi</option>
                    </select>
                </div>

                <div>
                    <label for="estimasi_total_pagu_ppbj" class="block text-xs font-semibold text-slate-700 mb-1">
                        Alokasi Pagu Pecahan (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="estimasi_total_pagu_ppbj" name="estimasi_total_pagu" min="1" step="0.01" required placeholder="Contoh: 45000000" class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="alasan_pemecahan_ppbj" class="block text-xs font-semibold text-slate-700 mb-1">
                        Alasan Pemecahan Jenis Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="alasan_pemecahan_ppbj" name="alasan_pemecahan" rows="2" required placeholder="Contoh: Pemisahan paket barang perangkat keras dan jasa konstruksi instalasi jaringan." class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2 bg-indigo-700 text-white rounded text-xs font-bold hover:bg-indigo-800 transition shadow">
                    Proses Pemecahan Berkas Berdasarkan Jenis Pengadaan
                </button>
            </div>
        </form>
    </div>

    {{-- 3. Formulir Alih Tugas (Reassign) PPK (FR 7.3) --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-2 pb-2 border-b border-slate-100">
            Alih Tugas (Reassign) Pejabat Pembuat Komitmen (PPK) (FR 7.3)
        </h2>
        <p class="text-sm text-slate-600 mb-4">
            Jika terjadi beban kerja berlebih atau penyesuaian bidang/MAK, PPBJ berhak mengalihkan penugasan paket bernilai &gt; Rp 200 Juta
            ke salah satu akun PPK lainnya. Jejak mutasi otomatis tercatat pada riwayat sistem.
        </p>

        <form action="{{ route('paket.reassign-ppk') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="kode_paket_reassign" class="block text-xs font-semibold text-slate-700 mb-1">
                        Pilih Paket Pengadaan (Jalur PPK &gt; 200 Juta) <span class="text-rose-500">*</span>
                    </label>
                    <select id="kode_paket_reassign" name="kode_paket" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Paket Pengadaan --</option>
                        @foreach($paketList->where('jalur_routing', 'ppk_diatas_200jt') as $pktPpk)
                            <option value="{{ $pktPpk->kode_paket }}">
                                [{{ $pktPpk->kode_paket }}] {{ $pktPpk->nama_paket }} &bull; PPK Saat Ini: {{ $pktPpk->ppkUser->nama ?? 'Kosong' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="id_user_ppk_baru" class="block text-xs font-semibold text-slate-700 mb-1">
                        Alihkan ke PPK Penerima Tugas Baru <span class="text-rose-500">*</span>
                    </label>
                    <select id="id_user_ppk_baru" name="id_user_ppk_baru" required class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Akun PPK Penerima --</option>
                        @foreach($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}">{{ $ppk->nama }} ({{ $ppk->jabatan }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="alasan_pengalihan" class="block text-xs font-semibold text-slate-700 mb-1">
                        Alasan Resmi Alih Tugas / Mutasi PPK <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="alasan_pengalihan" name="alasan_pengalihan" rows="2" required placeholder="Contoh: Pemerataan beban kerja PPK dan penyesuaian MAK Sarana Kampus." class="w-full px-3 py-2 border border-slate-300 rounded text-xs focus:ring-sky-500 focus:border-sky-500"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2 bg-amber-600 text-white rounded text-xs font-bold hover:bg-amber-700 transition shadow">
                    Simpan &amp; Eksekusi Alih Tugas PPK
                </button>
            </div>
        </form>
    </div>

    {{-- 4. Tabel Riwayat Penugasan & Alih Tugas PPK --}}
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100">
            Jejak Riwayat Mutasi Penugasan PPK (Audit Trail)
        </h2>

        @if($riwayatPenugasan->isEmpty())
            <div class="text-center py-6 bg-slate-50 rounded border border-dashed border-slate-200">
                <p class="text-sm text-slate-500">Belum ada riwayat alih tugas PPK yang tercatat.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold uppercase tracking-wider">
                            <th class="p-3 border-b">Waktu Mutasi</th>
                            <th class="p-3 border-b">Kode Paket</th>
                            <th class="p-3 border-b">PPK Sebelumnya</th>
                            <th class="p-3 border-b">PPK Baru</th>
                            <th class="p-3 border-b">Alasan Pengalihan</th>
                            <th class="p-3 border-b">Ditetapkan Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($riwayatPenugasan as $rw)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono text-slate-600">{{ date('d-m-Y H:i', strtotime($rw->created_at)) }}</td>
                                <td class="p-3 font-mono font-bold text-sky-800">{{ $rw->kode_paket }}</td>
                                <td class="p-3 text-slate-600">{{ $rw->ppkLama->nama ?? '-' }}</td>
                                <td class="p-3 font-bold text-slate-900">{{ $rw->ppkBaru->nama ?? '-' }}</td>
                                <td class="p-3 text-slate-700">{{ $rw->alasan_pengalihan }}</td>
                                <td class="p-3 text-slate-600">{{ $rw->userPenetap->nama ?? 'Sistem' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
