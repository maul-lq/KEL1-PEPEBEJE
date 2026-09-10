@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Registrasi Penyedia / Rekanan Vendor</h1>
            <p class="text-sm text-zinc-600 mt-1">Unggah legalitas penyedia barang dan jasa kampus (FR 10.0)</p>
        </div>
        <a href="{{ route('vendor.index') }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <form method="POST" action="{{ route('vendor.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Nama Perusahaan / Rekanan <span class="text-zinc-500">*</span></label>
            <input type="text" name="nama_perusahaan" required placeholder="Contoh: PT Dharma Santosa Sejahtera" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor Pokok Wajib Pajak (NPWP)</label>
                <input type="text" name="npwp" placeholder="Contoh: 01.234.567.8-012.000" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor Induk Berusaha (NIB)</label>
                <input type="text" name="nib" placeholder="Contoh: 9120001234567" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Alamat Kantor Perusahaan</label>
            <textarea name="alamat" rows="2" placeholder="Alamat lengkap domisili penyedia..." class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nama Narahubung / PIC</label>
                <input type="text" name="nama_kontak" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Telepon / WhatsApp</label>
                <input type="text" name="telepon" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Alamat Email</label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nama Bank</label>
                <input type="text" name="nama_bank" placeholder="Bank Mandiri / BNI / BRI" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor Rekening</label>
                <input type="text" name="nomor_rekening" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base font-mono focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nama Pemilik Rekening</label>
                <input type="text" name="nama_rekening" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-zinc-800 mb-1">Unggah Dokumen Legalitas (Akta, NIB, NPWP - PDF)</label>
            <input type="file" name="file_legalitas" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50">
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3">
            <a href="{{ route('vendor.index') }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Simpan Data Rekanan &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
