@php
    $userRole = strtolower(auth()->user()->role ?? '');
@endphp

{{-- 1. ROLE WADIR 2 --}}
@if(in_array($userRole, ['wadir_2', 'wadir2', 'wadir 2']))
    <div class="flex items-center justify-between p-3.5 bg-blue-50/60 rounded-xl border border-blue-100">
        <div>
            <span class="text-xs font-bold text-[#213153] block">Aksi Persetujuan Wadir 2</span>
            <span class="text-[11px] text-slate-500">Persetujuan akhir tingkat Wakil Direktur 2</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-[#0969da] text-white font-bold text-xs rounded-lg shadow-sm hover:bg-blue-700 transition shrink-0">
            Proses Persetujuan →
        </button>
    </div>

{{-- 2. ROLE STAFF BIDANG 2 --}}
@elseif(in_array($userRole, ['bidang_2', 'staff_bidang_2', 'staff bidang 2']))
    <div class="flex items-center justify-between p-3.5 bg-blue-50/60 rounded-xl border border-blue-100">
        <div>
            <span class="text-xs font-bold text-[#213153] block">Aksi Verifikasi Staff Bidang 2</span>
            <span class="text-[11px] text-slate-500">Telaah dan teruskan pengajuan ke antrean Wadir 2</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-[#0969da] text-white font-bold text-xs rounded-lg shadow-sm hover:bg-blue-700 transition shrink-0">
            Proses Verifikasi →
        </button>
    </div>

{{-- 3. ROLE PPK --}}
@elseif(in_array($userRole, ['ppk']))
    <div class="flex items-center justify-between p-3.5 bg-blue-50/60 rounded-xl border border-blue-100">
        <div>
            <span class="text-xs font-bold text-[#213153] block">Persetujuan Pejabat Pembuat Komitmen (PPK)</span>
            <span class="text-[11px] text-slate-500">Berikan persetujuan atau catatan revisi</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-[#0969da] text-white font-bold text-xs rounded-lg shadow-sm hover:bg-blue-700 transition shrink-0">
            Proses Approval →
        </button>
    </div>

{{-- 4. ROLE PPBJ / PP --}}
@elseif(in_array($userRole, ['ppbj', 'pp']))
    <div class="flex items-center justify-between p-3.5 bg-blue-50/60 rounded-xl border border-blue-100">
        <div>
            <span class="text-xs font-bold text-[#213153] block">Verifikasi Berkas Pengajuan (PPBJ)</span>
            <span class="text-[11px] text-slate-500">Verifikasi dokumen dan penetapan level anggaran</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-[#0969da] text-white font-bold text-xs rounded-lg shadow-sm hover:bg-blue-700 transition shrink-0">
            Verifikasi Berkas →
        </button>
    </div>

{{-- 5. ROLE KEUANGAN --}}
@elseif(in_array($userRole, ['keuangan', 'finance']))
    <div class="flex items-center justify-between p-3.5 bg-emerald-50/60 rounded-xl border border-emerald-100">
        <div>
            <span class="text-xs font-bold text-emerald-900 block">Upload Bukti Pembayaran</span>
            <span class="text-[11px] text-emerald-700">Unggah bukti kuitansi / pembayaran resmi</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-emerald-600 text-white font-bold text-xs rounded-lg shadow-sm hover:bg-emerald-700 transition shrink-0">
            Upload Bukti →
        </button>
    </div>

{{-- 6. ROLE PERLENGKAPAN --}}
@elseif(in_array($userRole, ['perlengkapan', 'logistik']))
    <div class="flex items-center justify-between p-3.5 bg-blue-50/60 rounded-xl border border-blue-100">
        <div>
            <span class="text-xs font-bold text-[#213153] block">Penerimaan Barang & BAST</span>
            <span class="text-[11px] text-slate-500">Upload foto fisik barang & TTD penerimaan</span>
        </div>
        <button @click="viewMode = 'fullscreen'" type="button" class="px-3.5 py-1.5 bg-[#0969da] text-white font-bold text-xs rounded-lg shadow-sm hover:bg-blue-700 transition shrink-0">
            Proses Penerimaan →
        </button>
    </div>
@endif