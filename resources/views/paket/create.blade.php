@extends('layouts.app')

@section('title', 'Registrasi Paket Pengadaan Baru - Koordinator PPBJ PNJ')

@section('content')
<div class="space-y-6">
    <div class="bg-white p-6 rounded-lg shadow-sm border border-slate-200">
        <div class="flex items-center justify-between border-b pb-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Registrasi Paket Pengadaan Baru</h1>
                <p class="text-sm text-slate-500">Unit Pengadaan Barang &amp; Jasa (PPBJ) Politeknik Negeri Jakarta</p>
            </div>
            <a href="{{ route('paket.index') }}" class="px-4 py-2 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 text-sm font-semibold transition">
                &larr; Kembali ke Daftar Paket
            </a>
        </div>

        <div class="p-4 bg-sky-50 border-l-4 border-sky-600 rounded text-sky-900 text-sm mb-6 space-y-1">
            <p class="font-bold">Ketentuan Alur &amp; Ambang Batas HPS (Sesuai BPMN &amp; Ketentuan PBJ):</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                <li><strong>Nilai HPS &le; Rp 50.000.000:</strong> Pengadaan Langsung (PPBJ langsung menerbitkan Kontrak/SPK &amp; Reminder &rarr; Perlengkapan).</li>
                <li><strong>Nilai HPS &gt; Rp 50.000.000 s.d &lt; Rp 200.000.000:</strong> Ditugaskan ke Pejabat Pengadaan (PP) untuk telaah &amp; negosiasi &rarr; PPK kunci MAK &rarr; PPBJ terbitkan SPK &rarr; Perlengkapan &amp; Keuangan.</li>
                <li><strong>Nilai HPS &ge; Rp 200.000.000:</strong> Ditugaskan langsung ke salah satu dari 4 akun PPK tanpa memo pengadaan PPBJ &rarr; PPK kunci MAK &rarr; PPBJ terbitkan SPK &rarr; Perlengkapan &amp; Keuangan.</li>
            </ul>
        </div>

        @if($errors->any())
            <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-700 rounded text-sm mb-6">
                <p class="font-bold">Mohon perbaiki kesalahan berikut:</p>
                <ul class="list-disc list-inside text-xs mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('paket.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="nomor_surat" class="block text-sm font-semibold text-slate-700 mb-1">
                        Pilih Usulan Permohonan Pengadaan Disetujui <span class="text-rose-500">*</span>
                    </label>
                    <select id="nomor_surat" name="nomor_surat" required class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Surat Permohonan Pengadaan --</option>
                        @foreach($permohonanDisetujui as $p)
                            <option value="{{ $p->nomor_surat }}" {{ old('nomor_surat') == $p->nomor_surat ? 'selected' : '' }}>
                                [{{ $p->nomor_surat }}] {{ $p->perihal }} &bull; Pagu: Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }} (Unit: {{ $p->unit->nama_unit ?? $p->kode_unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="kode_paket" class="block text-sm font-semibold text-slate-700 mb-1">
                        Kode Unik Paket Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="kode_paket" name="kode_paket" value="{{ old('kode_paket', 'PKT-PNJ-' . date('Ymd') . '-' . rand(100, 999)) }}" required class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500 font-mono">
                </div>

                <div>
                    <label for="nama_paket" class="block text-sm font-semibold text-slate-700 mb-1">
                        Nama Paket Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nama_paket" name="nama_paket" value="{{ old('nama_paket') }}" required placeholder="Contoh: Pengadaan Fasilitas Komputer AI Lab TIK" class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                </div>

                <div>
                    <label for="jenis_pengadaan" class="block text-sm font-semibold text-slate-700 mb-1">
                        Jenis Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <select id="jenis_pengadaan" name="jenis_pengadaan" required class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                        <option value="Barang" {{ old('jenis_pengadaan') == 'Barang' ? 'selected' : '' }}>Barang</option>
                        <option value="Jasa Lainnya" {{ old('jenis_pengadaan') == 'Jasa Lainnya' ? 'selected' : '' }}>Jasa Lainnya</option>
                        <option value="Pekerjaan Konstruksi" {{ old('jenis_pengadaan') == 'Pekerjaan Konstruksi' ? 'selected' : '' }}>Pekerjaan Konstruksi</option>
                        <option value="Jasa Konsultansi" {{ old('jenis_pengadaan') == 'Jasa Konsultansi' ? 'selected' : '' }}>Jasa Konsultansi</option>
                    </select>
                </div>

                <div>
                    <label for="metode_pengadaan" class="block text-sm font-semibold text-slate-700 mb-1">
                        Metode Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <select id="metode_pengadaan" name="metode_pengadaan" required class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                        <option value="Pengadaan Langsung">Pengadaan Langsung (&le; 50jt / non-tender)</option>
                        <option value="E-Purchasing">E-Purchasing (Katalog Elektronik / Inaproc)</option>
                        <option value="Tender">Tender Terbuka (SPSE)</option>
                        <option value="Seleksi">Seleksi Konsultansi</option>
                        <option value="Penunjukan Langsung">Penunjukan Langsung</option>
                    </select>
                </div>

                <div>
                    <label for="nilai_hps" class="block text-sm font-semibold text-slate-700 mb-1">
                        Nilai Harga Perkiraan Sendiri (HPS) dalam Rupiah <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="nilai_hps" name="nilai_hps" value="{{ old('nilai_hps') }}" step="0.01" min="1" required placeholder="Contoh: 75000000" class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500 font-mono">
                    <p class="text-xs text-slate-500 mt-1">Sistem otomatis menentukan jalur routing penugasan berdasarkan besaran HPS.</p>
                </div>

                <div>
                    <label for="nomor_memo_ppbj" class="block text-sm font-semibold text-slate-700 mb-1">
                        Nomor Memo Pengadaan PPBJ (Khusus HPS &lt; 200 Juta)
                    </label>
                    <input type="text" id="nomor_memo_ppbj" name="nomor_memo_ppbj" value="{{ old('nomor_memo_ppbj', 'MEMO-PPBJ/' . date('Y') . '/' . rand(100, 999)) }}" class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500 font-mono">
                    <p class="text-xs text-slate-500 mt-1">Untuk paket &ge; 200 Juta, memo pengadaan otomatis dikosongkan (tanpa memo PPBJ).</p>
                </div>

                <div>
                    <label for="id_user_ppk" class="block text-sm font-semibold text-slate-700 mb-1">
                        Pejabat Pembuat Komitmen (PPK) <span class="text-slate-500 text-xs">(Wajib jika HPS &ge; 200 Juta)</span>
                    </label>
                    <select id="id_user_ppk" name="id_user_ppk" class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                        <option value="">-- Pilih Akun PPK (1 dari 4 Akun) --</option>
                        @foreach($ppkUsers as $ppk)
                            <option value="{{ $ppk->id_user }}" {{ old('id_user_ppk') == $ppk->id_user ? 'selected' : '' }}>
                                {{ $ppk->nama }} ({{ $ppk->jabatan }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="id_user_pp" class="block text-sm font-semibold text-slate-700 mb-1">
                        Pejabat Pengadaan (PP) <span class="text-slate-500 text-xs">(Untuk HPS 50jt s.d 200jt)</span>
                    </label>
                    <select id="id_user_pp" name="id_user_pp" class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:ring-sky-500 focus:border-sky-500">
                        @foreach($ppUsers as $pp)
                            <option value="{{ $pp->id_user }}" {{ old('id_user_pp') == $pp->id_user ? 'selected' : '' }}>
                                {{ $pp->nama }} (Pejabat Pengadaan Aktif)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-200">
                <a href="{{ route('paket.index') }}" class="px-5 py-2.5 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 text-sm font-semibold transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-sky-700 text-white rounded text-sm font-bold hover:bg-sky-800 transition shadow">
                    Daftarkan &amp; Terbitkan Paket Pengadaan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
