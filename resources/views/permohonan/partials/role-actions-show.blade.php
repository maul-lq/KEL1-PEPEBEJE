@php
    $userRole = strtolower(auth()->user()->role ?? '');
@endphp

{{-- 1. ROLE WADIR 2 --}}
@if(in_array($userRole, ['wadir_2', 'wadir2', 'wadir 2']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Form Persetujuan Wadir 2</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-[#0969da]">Persetujuan Level Wadir 2</span>
        </div>
        
        <form action="{{ route('permohonan.index') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Persetujuan / Catatan Revisi</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-700 focus:border-[#0969da] focus:ring-1 focus:ring-[#0969da] outline-none" placeholder="Masukkan instruksi atau catatan Wadir 2..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="submit" name="action" value="revisi" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition-colors">
                    Kembalikan / Revisi
                </button>
                <button type="submit" name="action" value="setujui" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Setujui Pengajuan ✓
                </button>
            </div>
        </form>
    </div>

{{-- 2. ROLE STAFF BIDANG 2 --}}
@elseif(in_array($userRole, ['bidang_2', 'staff_bidang_2', 'staff bidang 2']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Form Verifikasi Staff Bidang 2</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700">Telaah Berkas</span>
        </div>
        
        <form action="{{ route('permohonan.index') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan / Alasan (Opsional)</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-700 focus:border-[#0969da] outline-none" placeholder="Masukkan catatan telaah berkas..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="submit" name="action" value="revisi" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition-colors">
                    Minta Revisi
                </button>
                <button type="submit" name="action" value="teruskan" class="px-4 py-2 rounded-lg bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Teruskan ke Wadir 2 →
                </button>
            </div>
        </form>
    </div>

{{-- 3. ROLE PPK --}}
@elseif(in_array($userRole, ['ppk']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Persetujuan PPK</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-[#0969da]">Verifikasi Komitmen</span>
        </div>
        
        <form action="{{ route('permohonan.index') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Persetujuan PPK</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-700 focus:border-[#0969da] outline-none" placeholder="Masukkan catatan PPK..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="submit" name="action" value="tolak" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition-colors">
                    Tolak / Revisi
                </button>
                <button type="submit" name="action" value="setujui" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Setujui Pengajuan ✓
                </button>
            </div>
        </form>
    </div>

{{-- 4. ROLE PPBJ / PANITIA PENGADAAN (PP) --}}
@elseif(in_array($userRole, ['ppbj', 'pp']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Verifikasi Berkas & Level Anggaran (PPBJ/PP)</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-[#0969da]">Verifikasi Dokumen</span>
        </div>

        <form action="{{ route('permohonan.index') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Hasil Pemeriksaan Dokumen</label>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-700 focus:border-[#0969da] outline-none" placeholder="Catatan kelengkapan dokumen pengadaan..."></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="submit" name="action" value="revisi" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition-colors">
                    Minta Kelengkapan Berkas
                </button>
                <button type="submit" name="action" value="verifikasi" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Verifikasi & Lanjutkan ✓
                </button>
            </div>
        </form>
    </div>

{{-- 5. ROLE KEUANGAN / FINANCE --}}
@elseif(in_array($userRole, ['keuangan', 'finance']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Upload Bukti Pembayaran</h3>
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">Tahap Pembayaran</span>
        </div>

        <form action="{{ route('permohonan.index') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">
                    Upload File Bukti Pembayaran / Kwitansi <span class="text-rose-500">*</span>
                </label>
                <input type="file" name="bukti_pembayaran" required class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#0969da] hover:file:bg-blue-100 cursor-pointer"/>
            </div>
            <div class="flex justify-end pt-1">
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Upload & Selesaikan Pembayaran
                </button>
            </div>
        </form>
    </div>

{{-- 6. ROLE PERLENGKAPAN --}}
@elseif(in_array($userRole, ['perlengkapan', 'logistik']))
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-base text-[#213153]">Penerimaan Barang & BAST</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-[#0969da]">Tahap Penerimaan</span>
        </div>

        <form action="{{ route('permohonan.index') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Foto Bukti Fisik Barang <span class="text-rose-500">*</span></label>
                <input type="file" name="foto_barang" required class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 text-xs"/>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Tanda Tangan Penerima (TTD/BAST) <span class="text-rose-500">*</span></label>
                <input type="file" name="ttd_penerima" required class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 text-xs"/>
            </div>
            <div class="flex justify-end pt-1">
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-colors shadow-sm">
                    Simpan BAST Penerimaan
                </button>
            </div>
        </form>
    </div>

{{-- 7. ROLE PERENCANAAN / USER --}}
@else
    <div class="flex items-center justify-between p-3.5 rounded-xl bg-blue-50/70 border border-blue-100">
        <div class="flex items-center gap-2.5">
            <div class="size-8 rounded-full bg-[#0969da] text-white flex items-center justify-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h4 class="font-bold text-xs text-[#213153]">Status Pengajuan Saat Ini</h4>
                <p class="text-[11px] text-slate-500">Berkas sedang diproses oleh verifikator terkait.</p>
            </div>
        </div>
        <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-[#0969da] border border-blue-200 shadow-sm" x-text="selectedItem ? selectedItem.status : 'Diproses'"></span>
    </div>
@endif