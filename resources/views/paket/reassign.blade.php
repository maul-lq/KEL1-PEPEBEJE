@extends('layouts.app')

@section('title', 'Alih Tugas Penugasan PPK - SIM-PBJ PNJ')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="rounded-2xl bg-white p-6 md:p-8 shadow-sm border border-slate-200/80">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        Feature 25 &bull; FR 13
                    </span>
                </div>
                <h1 class="text-xl font-bold text-[#213153] mt-1">Alih Tugas Penugasan PPK</h1>
                <p class="text-xs text-slate-500 mt-0.5">Pengalihan tanggung jawab berkas paket pengadaan ke akun PPK baru</p>
            </div>
            <a href="{{ route('paket.index') }}" class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('paket.reassign-ppk') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="kode_paket" class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Paket Pengadaan <span class="text-rose-500">*</span>
                </label>
                <select id="kode_paket" name="kode_paket" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 font-mono focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih Paket Pengadaan --</option>
                    @foreach($paketList as $pkt)
                        <option value="{{ $pkt->nomor_paket }}" {{ old('kode_paket') == $pkt->nomor_paket ? 'selected' : '' }}>
                            [{{ $pkt->kode_paket ?? $pkt->nomor_paket }}] {{ $pkt->nama_paket }} (PPK: {{ $pkt->ppkUser->nama ?? 'Belum Ditugaskan' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="id_user_ppk_baru" class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Akun PPK Pengganti (1 dari 4 Akun) <span class="text-rose-500">*</span>
                </label>
                <select id="id_user_ppk_baru" name="id_user_ppk_baru" required class="w-full h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">
                    <option value="">-- Pilih Akun PPK Aktif PNJ --</option>
                    @foreach($ppkUsers as $ppk)
                        <option value="{{ $ppk->id_user }}" {{ old('id_user_ppk_baru') == $ppk->id_user ? 'selected' : '' }}>
                            {{ $ppk->nama }} &bull; {{ $ppk->jabatan ?? 'PPK PNJ' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Sistem mengunci penugasan pada 4 akun PPK aktif untuk menjaga tata kelola dan isolasi berkas (NFR 4).</p>
            </div>

            <div>
                <label for="alasan_pengalihan" class="block text-xs font-bold text-slate-700 mb-1">
                    Alasan Perubahan Penugasan PPK <span class="text-rose-500">*</span>
                </label>
                <textarea id="alasan_pengalihan" name="alasan_pengalihan" rows="3" required placeholder="Tuliskan alasan resmi alih tugas penugasan..." class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20">{{ old('alasan_pengalihan') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('paket.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 text-xs font-bold text-white hover:bg-amber-700 shadow-sm transition">
                    Simpan Alih Tugas PPK
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
