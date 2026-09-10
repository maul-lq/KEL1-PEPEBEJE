@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Formulir Pengajuan Permohonan Pengadaan</h1>
            <p class="text-sm text-zinc-600 mt-1">Lengkapi data surat permohonan dan rincian barang/jasa yang dibutuhkan</p>
        </div>
        <a href="{{ route('pengadaan.index') }}" class="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 font-bold text-sm rounded-lg">
            &larr; Kembali
        </a>
    </div>

    <form method="POST" action="{{ route('pengadaan.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- BAGIAN 1: INFORMASI SURAT PERMOHONAN -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-5">
            <h3 class="text-lg font-bold text-zinc-900 border-b border-zinc-200 pb-3">1. Informasi Surat Permohonan User</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Nama / Judul Kegiatan Pengadaan <span class="text-zinc-500">*</span>
                    </label>
                    <input type="text" name="nama_pengadaan" value="{{ old('nama_pengadaan') }}" required 
                        placeholder="Contoh: Pengadaan Bahan Habis Pakai Praktikum Mahasiswa TIK Tahun 2026"
                        class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Jenis Pengadaan <span class="text-zinc-500">*</span>
                    </label>
                    <select name="jenis_pengadaan" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                        <option value="Barang">Barang</option>
                        <option value="Jasa Lainnya">Jasa Lainnya</option>
                        <option value="Pekerjaan Konstruksi">Pekerjaan Konstruksi</option>
                        <option value="Jasa Konsultansi">Jasa Konsultansi</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Referensi Draft Web Srikandi (FR 3.3)
                    </label>
                    <input type="text" name="no_draft_srikandi" value="{{ old('no_draft_srikandi') }}" 
                        placeholder="Contoh: SRIKANDI-2026-TIK-00892 (srikandi.arsip.go.id)"
                        class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Nomor Surat Permohonan Unit <span class="text-zinc-500">*</span>
                    </label>
                    <input type="text" name="nomor_surat_user" value="{{ old('nomor_surat_user') }}" required 
                        placeholder="Contoh: 181/PL/TIK/BHP/2026"
                        class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Tanggal Surat Permohonan <span class="text-zinc-500">*</span>
                    </label>
                    <input type="date" name="tanggal_surat_user" value="{{ old('tanggal_surat_user', date('Y-m-d')) }}" required 
                        class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Latar Belakang / Keperluan Pengadaan
                    </label>
                    <textarea name="latar_belakang" rows="3" placeholder="Jelaskan secara singkat urgensi dan tujuan pengadaan barang/jasa ini..."
                        class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">{{ old('latar_belakang') }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-zinc-800 mb-1">
                        Unggah Berkas Surat Permohonan Resmi (PDF / JPG, Maks 10MB)
                    </label>
                    <input type="file" name="file_surat_permohonan" accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-bold file:bg-zinc-900 file:text-white hover:file:bg-zinc-800 cursor-pointer">
                </div>
            </div>
        </div>

        <!-- BAGIAN 2: RINCIAN DAFTAR BARANG / JASA -->
        <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-200 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-zinc-900">2. Rincian Kebutuhan Barang / Jasa</h3>
                    <p class="text-xs text-zinc-500">Estimasi total otomatis dihitung untuk menentukan jalur pengadaan (FR 8.0)</p>
                </div>
                <button type="button" id="btn-add-item" class="px-3.5 py-2 bg-zinc-800 hover:bg-zinc-700 text-white rounded-lg text-xs font-bold cursor-pointer">
                    + Tambah Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm" id="table-items">
                    <thead>
                        <tr class="bg-zinc-100 text-zinc-700 font-bold border-b border-zinc-300 text-xs uppercase">
                            <th class="py-2.5 px-3">Nama Barang / Jasa</th>
                            <th class="py-2.5 px-3">Spesifikasi Singkat</th>
                            <th class="py-2.5 px-3 w-24">Jumlah</th>
                            <th class="py-2.5 px-3 w-28">Satuan</th>
                            <th class="py-2.5 px-3 w-40">Harga Satuan (Rp)</th>
                            <th class="py-2.5 px-3 w-40">Subtotal (Rp)</th>
                            <th class="py-2.5 px-3 w-16 text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="items-container" class="divide-y divide-zinc-200">
                        <tr class="item-row">
                            <td class="py-2.5 px-3">
                                <input type="text" name="items[0][nama_barang]" required placeholder="Nama item" 
                                    class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                            </td>
                            <td class="py-2.5 px-3">
                                <input type="text" name="items[0][spesifikasi]" placeholder="Spesifikasi/merek" 
                                    class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                            </td>
                            <td class="py-2.5 px-3">
                                <input type="number" name="items[0][volume]" min="1" value="1" required 
                                    class="item-qty w-full px-3 py-2 border border-zinc-300 rounded text-sm text-center">
                            </td>
                            <td class="py-2.5 px-3">
                                <input type="text" name="items[0][satuan]" value="Unit" required 
                                    class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                            </td>
                            <td class="py-2.5 px-3">
                                <input type="number" name="items[0][harga_satuan]" min="0" value="0" required 
                                    class="item-price w-full px-3 py-2 border border-zinc-300 rounded text-sm text-right">
                            </td>
                            <td class="py-2.5 px-3 font-bold text-right text-zinc-900 item-subtotal">
                                Rp 0
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <button type="button" class="btn-remove-row text-zinc-400 hover:text-zinc-900 font-bold text-lg">&times;</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-zinc-100 font-bold border-t-2 border-zinc-400">
                            <td colspan="5" class="py-3 px-3 text-right uppercase text-xs text-zinc-700">Total Estimasi Anggaran:</td>
                            <td class="py-3 px-3 text-right text-base text-zinc-900" id="grand-total">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="bg-zinc-100 p-4 rounded-lg border border-zinc-300 text-xs text-zinc-700 space-y-1">
                <div class="font-bold">Ketentuan Otomatis Jalur Pengadaan (FR 8.0):</div>
                <div>• Total < Rp50.000.000 : Diarahkan ke <strong>Pengadaan Langsung</strong></div>
                <div>• Total Rp50.000.000 s.d. Rp200.000.000 : Diarahkan ke <strong>Pejabat Pengadaan (PP)</strong></div>
                <div>• Total > Rp200.000.000 : Diarahkan ke <strong>Pejabat Pembuat Komitmen (PPK)</strong></div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-4">
            <a href="{{ route('pengadaan.index') }}" class="px-6 py-3.5 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 font-bold text-base rounded-lg">
                Batal
            </a>
            <button type="submit" class="px-8 py-3.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-base rounded-lg shadow-sm cursor-pointer">
                Ajukan ke Wadir 2 &rarr;
            </button>
        </div>

    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let itemIndex = 1;
        const container = document.getElementById('items-container');
        const btnAdd = document.getElementById('btn-add-item');
        const grandTotalElem = document.getElementById('grand-total');

        function formatRupiah(num) {
            return 'Rp ' + Number(num).toLocaleString('id-ID');
        }

        function recalc() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const sub = qty * price;
                row.querySelector('.item-subtotal').textContent = formatRupiah(sub);
                total += sub;
            });
            grandTotalElem.textContent = formatRupiah(total);
        }

        btnAdd.addEventListener('click', function() {
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td class="py-2.5 px-3">
                    <input type="text" name="items[${itemIndex}][nama_barang]" required placeholder="Nama item" 
                        class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                </td>
                <td class="py-2.5 px-3">
                    <input type="text" name="items[${itemIndex}][spesifikasi]" placeholder="Spesifikasi/merek" 
                        class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                </td>
                <td class="py-2.5 px-3">
                    <input type="number" name="items[${itemIndex}][volume]" min="1" value="1" required 
                        class="item-qty w-full px-3 py-2 border border-zinc-300 rounded text-sm text-center">
                </td>
                <td class="py-2.5 px-3">
                    <input type="text" name="items[${itemIndex}][satuan]" value="Unit" required 
                        class="w-full px-3 py-2 border border-zinc-300 rounded text-sm">
                </td>
                <td class="py-2.5 px-3">
                    <input type="number" name="items[${itemIndex}][harga_satuan]" min="0" value="0" required 
                        class="item-price w-full px-3 py-2 border border-zinc-300 rounded text-sm text-right">
                </td>
                <td class="py-2.5 px-3 font-bold text-right text-zinc-900 item-subtotal">
                    Rp 0
                </td>
                <td class="py-2.5 px-3 text-center">
                    <button type="button" class="btn-remove-row text-zinc-400 hover:text-zinc-900 font-bold text-lg">&times;</button>
                </td>
            `;
            container.appendChild(tr);
            itemIndex++;
            recalc();
        });

        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
                recalc();
            }
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-row')) {
                if (document.querySelectorAll('.item-row').length > 1) {
                    e.target.closest('tr').remove();
                    recalc();
                } else {
                    alert('Minimal harus ada 1 baris item kebutuhan.');
                }
            }
        });

        recalc();
    });
</script>
@endpush
@endsection
