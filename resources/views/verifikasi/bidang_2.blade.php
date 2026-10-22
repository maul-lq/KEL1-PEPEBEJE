@extends('layouts.app')

@section('title', 'Telaah Berkas Permohonan Masuk - Staf Bidang II PNJ')

@section('content')
<div class="mx-auto max-w-[1650px] space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#1E3156]">Antrean Telaah Kelengkapan Berkas Administrasi</h1>
            <p class="text-sm text-slate-500 mt-1">
                Staf Tata Usaha Bidang II bertugas menelaah dan memeriksa kelengkapan fisik/digital naskah dinas Srikandi, KAK, dan Spesifikasi Teknis sebelum diteruskan kepada Wakil Direktur II.
            </p>
        </div>
        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
            Peran: Staf Bidang II (Telaah &amp; Teruskan)
        </span>
    </div>

    @if($permohonanMasuk->isEmpty())
        <div class="rounded-2xl bg-white p-12 text-center shadow-sm border border-slate-100">
            <svg class="mx-auto size-12 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="mt-2 text-base font-medium text-slate-800">Tidak Ada Antrean Berkas</h3>
            <p class="mt-1 text-sm text-slate-400">Seluruh berkas usulan permohonan baru telah ditelaah dan diteruskan ke Wadir II.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($permohonanMasuk as $p)
                <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <div>
                            <span class="font-mono text-sm font-bold text-slate-900">{{ $p->nomor_surat }}</span>
                            <span class="ml-2 text-xs text-slate-500">Unit Pengusul: <strong>{{ $p->unit->nama_unit ?? $p->kode_unit }}</strong> ({{ $p->kode_unit }})</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400">Tanggal Pengajuan: {{ $p->tanggal_pengajuan ? date('d/m/Y', strtotime($p->tanggal_pengajuan)) : '-' }}</span>
                            <div class="text-sm font-bold text-[#0088E8]">Rp {{ number_format($p->anggaran_diajukan, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h2 class="text-lg font-semibold text-slate-800">{{ $p->perihal }}</h2>
                        <p class="text-xs text-slate-600 mt-1">{{ $p->tujuan_pengadaan_ringkas ?? 'Pengadaan barang/jasa institusi PNJ' }}</p>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <a href="{{ route('dokumen.pdf', ['jenis' => 'permohonan', 'id' => $p->nomor_surat]) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors">
                            <svg class="size-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Lihat Berkas Surat Srikandi (PDF)
                        </a>
                        <a href="{{ route('permohonan.show', $p->nomor_surat) }}" class="text-xs text-blue-600 hover:underline">
                            Detail &amp; Lacak Permohonan &rarr;
                        </a>
                    </div>

                    {{-- Form Telaah Staf Bidang 2 --}}
                    <form action="{{ route('verifikasi.bidang-2.teruskan') }}" method="POST" class="mt-6 rounded-xl bg-slate-50 p-5 border border-slate-200/70 space-y-4">
                        @csrf
                        <input type="hidden" name="nomor_surat" value="{{ $p->nomor_surat }}">

                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Checklist Pemeriksaan Administrasi</div>

                        <div class="space-y-2">
                            <label class="flex items-start gap-2.5 text-xs text-slate-700">
                                <input type="checkbox" name="is_kak_valid" value="1" required class="mt-0.5 rounded text-blue-600">
                                <span>Dokumen Kerangka Acuan Kerja (KAK) telah diperiksa, lengkap, dan bermeterai/tervalidasi.</span>
                            </label>
                            <label class="flex items-start gap-2.5 text-xs text-slate-700">
                                <input type="checkbox" name="is_spesifikasi_valid" value="1" required class="mt-0.5 rounded text-blue-600">
                                <span>Dokumen Spesifikasi Teknis dan rincian estimasi harga wajar pasar terlampir jelas.</span>
                            </label>
                            <label class="flex items-start gap-2.5 text-xs text-slate-700">
                                <input type="checkbox" name="is_srikandi_valid" value="1" required class="mt-0.5 rounded text-blue-600">
                                <span>Nomor naskah dinas resmi terdaftar pada tata naskah persuratan Srikandi PNJ.</span>
                            </label>
                        </div>

                        <div>
                            <label for="catatan_staff_{{ $p->nomor_surat }}" class="block text-xs font-semibold text-slate-700 mb-1">
                                Catatan Hasil Telaah Staf Bidang II untuk Wadir II (Rekomendasi Disposisi)
                            </label>
                            <textarea id="catatan_staff_{{ $p->nomor_surat }}" name="catatan_staff" rows="2"
                                      placeholder="Contoh: Berkas telah diverifikasi lengkap, spesifikasi server sesuai standar lab TIK. Direkomendasikan untuk disetujui."
                                      class="w-full rounded-lg border border-slate-300 p-2.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[#0088E8] px-5 py-2 text-xs font-semibold text-white shadow hover:bg-blue-600 transition-colors">
                                Teruskan Telaah Berkas ke Wadir 2 &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
