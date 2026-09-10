@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Penerbitan Dokumen SPK / Kontrak (PPBJ)</h1>
            <p class="text-sm text-zinc-600 mt-1">Penerbitan Surat Perintah Kerja, pemilihan penyedia, dan konfigurasi alarm pengingat (FR 11.0 & 12.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <form method="POST" action="{{ route('ppbj.store-spk', $pengadaan) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">1. Data Penyedia / Vendor & Kontrak</h3>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Pilih Rekanan / Vendor <span class="text-zinc-500">*</span></label>
                <select name="vendor_id" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="">-- Pilih Vendor Terdaftar --</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}" {{ old('vendor_id', $pengadaan->vendor_id) == $v->id ? 'selected' : '' }}>
                            {{ $v->nama_perusahaan }} (NPWP: {{ $v->npwp ?? '-' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-zinc-500 mt-1">Belum ada vendor? <a href="{{ route('vendor.create') }}" target="_blank" class="underline font-bold text-zinc-900">Tambah Vendor Baru</a></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">Nomor SPK / Kontrak / Pesanan <span class="text-zinc-500">*</span></label>
                    <input type="text" name="nomor_spk" value="{{ old('nomor_spk', $pengadaan->nomor_spk ?? 'SPK-' . date('Ymd') . '-001') }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 font-mono text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">Tanggal Terbit SPK <span class="text-zinc-500">*</span></label>
                    <input type="date" name="tanggal_spk" value="{{ old('tanggal_spk', date('Y-m-d')) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">Nilai Kontrak Realisasi (Rp) <span class="text-zinc-500">*</span></label>
                    <input type="number" name="nilai_kontrak" min="0" value="{{ old('nilai_kontrak', $pengadaan->nilai_kontrak ?? $pengadaan->pagu_anggaran ?? $pengadaan->estimasi_anggaran) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base font-bold focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">Tanggal Mulai Pekerjaan <span class="text-zinc-500">*</span></label>
                    <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', date('Y-m-d')) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">Target Pengiriman Barang <span class="text-zinc-500">*</span></label>
                    <input type="date" name="tanggal_selesai_jadwal" value="{{ old('tanggal_selesai_jadwal', date('Y-m-d', strtotime('+14 days'))) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">
                    ID Paket / URL Transaksi LKPP-Inaproc (katalog.inaproc.id)
                </label>
                <input type="text" name="id_paket_lkpp" value="{{ old('id_paket_lkpp', $pengadaan->id_paket_lkpp) }}" 
                    placeholder="Contoh: PAKET-LKPP-982134 atau https://katalog.inaproc.id/..." 
                    class="w-full px-4 py-3 rounded-lg border border-zinc-300 font-mono text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                <p class="text-xs text-zinc-500 mt-1">Untuk pengadaan via E-Katalog, tender, atau non-tender luar sistem LKPP.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Unggah Berkas SPK / Kontrak (PDF/JPG)</label>
                <input type="file" name="file_spk" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50">
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">2. Pengaturan Alarm Pengingat & Tembusan Otomatis (FR 12.0)</h3>
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Klasifikasi Waktu Alarm / Alert</label>
                <select name="klasifikasi_alarm" class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="H-2 Jatuh Tempo">H-2 Batas Akhir Pengiriman Barang</option>
                    <option value="Hari H Pengiriman">Hari H Jadwal Pengiriman Barang</option>
                    <option value="Pembayaran Termin">Pembayaran Termin / Berkala</option>
                </select>
                <p class="text-xs text-zinc-500 mt-1">Sistem akan memunculkan popup pengingat pada dashboard PPBJ dan pihak terkait.</p>
            </div>

            <div class="bg-zinc-100 p-4 rounded-lg border border-zinc-300 text-xs text-zinc-700 space-y-1">
                <div class="font-bold">Ketentuan Tembusan Otomatis (FR 11.3):</div>
                <div>Jika anggaran Rupiah Murni (RM) > Rp50 Juta, tembusan SPK akan dikirimkan otomatis ke Bagian Perlengkapan (gudang BMN) dan Bagian Keuangan.</div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-2">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Terbitkan Dokumen SPK & Aktifkan Alarm &rarr;
            </button>
        </div>
    </form>
</div>
@endsection
