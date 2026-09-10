@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-zinc-900 tracking-tight">Penerimaan Barang & BAST (Perlengkapan)</h1>
            <p class="text-sm text-zinc-600 mt-1">Pemeriksaan fisik barang, penentuan klasifikasi BMN, foto dokumentasi, dan TTD digital (FR 14.0)</p>
        </div>
        <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-4 py-2 bg-zinc-200 text-zinc-800 font-bold text-sm rounded-lg">&larr; Kembali</a>
    </div>

    <!-- DATA SPK & REKANAN -->
    <div class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-3">
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Dasar Penerimaan (SPK & Rekanan)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div><span class="text-zinc-500 text-xs block">Nomor SPK:</span><span class="font-mono font-bold">{{ $pengadaan->nomor_spk ?? '-' }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Penyedia / Vendor:</span><span class="font-bold text-zinc-900">{{ $pengadaan->vendor->nama_perusahaan ?? '-' }}</span></div>
            <div class="sm:col-span-2"><span class="text-zinc-500 text-xs block">Nama Pengadaan:</span><span class="font-bold text-zinc-900">{{ $pengadaan->nama_pengadaan }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Nilai Kontrak:</span><span class="font-bold text-base text-zinc-900">{{ $pengadaan->formatted_nilai_kontrak }}</span></div>
            <div><span class="text-zinc-500 text-xs block">Target Jadwal Selesai:</span><span class="font-semibold text-zinc-800">{{ $pengadaan->tanggal_selesai_jadwal ? $pengadaan->tanggal_selesai_jadwal->format('d F Y') : '-' }}</span></div>
        </div>
    </div>

    <!-- FORM PENERIMAAN -->
    <form method="POST" action="{{ route('perlengkapan.store-penerimaan', $pengadaan) }}" enctype="multipart/form-data" id="form-penerimaan" class="bg-white p-6 rounded-xl border border-zinc-300 shadow-xs space-y-6">
        @csrf
        <h3 class="text-base font-bold text-zinc-900 border-b border-zinc-200 pb-2">Formulir Berita Acara Serah Terima (BAST)</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">
                    Klasifikasi Jenis Belanja BMN <span class="text-zinc-500">*</span>
                </label>
                <select name="jenis_belanja" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="Belanja Barang" {{ old('jenis_belanja', $pengadaan->jenis_belanja) === 'Belanja Barang' ? 'selected' : '' }}>
                        Belanja Barang (Persediaan / Habis Pakai / Operasional)
                    </option>
                    <option value="Belanja Modal" {{ old('jenis_belanja', $pengadaan->jenis_belanja) === 'Belanja Modal' ? 'selected' : '' }}>
                        Belanja Modal (Aset Tetap / Peralatan & Mesin BMN)
                    </option>
                </select>
                <p class="text-xs text-zinc-500 mt-1">Ditentukan oleh Bagian Perlengkapan sesuai Permenkeu BMN.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">
                    Status Penerimaan Barang <span class="text-zinc-500">*</span>
                </label>
                <select name="status_penerimaan" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
                    <option value="Terima" {{ old('status_penerimaan', $pengadaan->status_penerimaan) === 'Terima' ? 'selected' : '' }}>
                        Terima (Kondisi 100% Sesuai Spesifikasi)
                    </option>
                    <option value="Pending" {{ old('status_penerimaan', $pengadaan->status_penerimaan) === 'Pending' ? 'selected' : '' }}>
                        Pending (Terdapat Ketidaksesuaian / Belum Lengkap)
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Nama Petugas Penerima <span class="text-zinc-500">*</span></label>
                <input type="text" name="nama_penerima" value="{{ old('nama_penerima', Auth::user()->name) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-bold text-zinc-800 mb-1">Unit / Bagian Penerima <span class="text-zinc-500">*</span></label>
                <input type="text" name="unit_penerima" value="{{ old('unit_penerima', Auth::user()->unit_kerja) }}" required class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-base focus:ring-2 focus:ring-zinc-900 focus:outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-bold text-zinc-800 mb-1">Catatan Hasil Pemeriksaan Fisik</label>
                <textarea name="catatan_penerimaan" rows="2" placeholder="Contoh: Barang telah diperiksa dalam kondisi tersegel, uji fungsi baik, dan jumlah lengkap 100%." class="w-full px-4 py-3 rounded-lg border border-zinc-300 text-sm focus:ring-2 focus:ring-zinc-900 focus:outline-none">{{ old('catatan_penerimaan', $pengadaan->catatan_penerimaan) }}</textarea>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-bold text-zinc-800 mb-1">Unggah Foto Dokumentasi Barang (FR 14.3)</label>
                <input type="file" name="foto_dokumentasi_barang" accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 text-sm bg-zinc-50">
                <p class="text-xs text-zinc-500 mt-1">Ambil foto fisik barang atau dokumen serah terima lapangan.</p>
            </div>
        </div>

        <!-- TANDA TANGAN DIGITAL (CANVAS HTML5) -->
        <div class="border-t border-zinc-200 pt-4 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <label class="block text-sm font-bold text-zinc-900">Tanda Tangan Digital Penerima BAST (FR 14.4) <span class="text-zinc-500">*</span></label>
                    <p class="text-xs text-zinc-500">Goreskan tanda tangan pada kotak di bawah ini menggunakan mouse atau sentuhan layar</p>
                </div>
                <div class="flex space-x-2">
                    <button type="button" id="btn-clear-sig" class="px-3 py-1.5 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 text-xs font-bold rounded">
                        Bersihkan
                    </button>
                    <button type="button" id="btn-auto-sig" class="px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold rounded">
                        Gunakan TTD Terverifikasi Otomatis
                    </button>
                </div>
            </div>

            <div class="border-2 border-dashed border-zinc-400 rounded-lg p-2 bg-zinc-50 flex items-center justify-center">
                <canvas id="sig-canvas" width="600" height="160" class="bg-white rounded border border-zinc-300 cursor-crosshair max-w-full"></canvas>
            </div>
            <input type="hidden" name="ttd_digital" id="ttd_digital" value="{{ old('ttd_digital', $pengadaan->ttd_digital) }}">
            <div id="sig-status" class="text-xs text-zinc-500 italic">Status TTD: Belum ada goresan tanda tangan.</div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-zinc-200">
            <a href="{{ route('pengadaan.show', $pengadaan) }}" class="px-5 py-3 bg-zinc-200 text-zinc-800 font-bold rounded-lg text-sm">Batal</a>
            <button type="submit" id="btn-submit" class="px-6 py-3 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg text-sm shadow-xs cursor-pointer">
                Finalisasi Penerimaan BAST &rarr;
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('sig-canvas');
        const ctx = canvas.getContext('2d');
        const inputTtd = document.getElementById('ttd_digital');
        const statusText = document.getElementById('sig-status');
        let isDrawing = false;
        let hasSigned = false;

        ctx.strokeStyle = '#18181b';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (canvas.width / rect.width),
                y: (clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        function startDrawing(e) {
            isDrawing = true;
            hasSigned = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            statusText.textContent = 'Status TTD: Sedang menandatangani...';
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                inputTtd.value = canvas.toDataURL();
                statusText.textContent = 'Status TTD: Tanda tangan digital terekam sah.';
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);

        document.getElementById('btn-clear-sig').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            inputTtd.value = '';
            hasSigned = false;
            statusText.textContent = 'Status TTD: Tanda tangan dibersihkan.';
        });

        document.getElementById('btn-auto-sig').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = 'italic bold 28px serif';
            ctx.fillStyle = '#18181b';
            ctx.fillText('{{ Auth::user()->name }}', 40, 90);
            ctx.beginPath();
            ctx.moveTo(40, 110);
            ctx.lineTo(350, 110);
            ctx.stroke();
            inputTtd.value = canvas.toDataURL();
            hasSigned = true;
            statusText.textContent = 'Status TTD: TTD Otomatis Terverifikasi Atas Nama {{ Auth::user()->name }}.';
        });

        document.getElementById('form-penerimaan').addEventListener('submit', function(e) {
            if (!inputTtd.value && !hasSigned) {
                e.preventDefault();
                alert('Mohon bubuhkan tanda tangan digital terlebih dahulu pada kotak yang disediakan.');
            }
        });
    });
</script>
@endpush
@endsection
