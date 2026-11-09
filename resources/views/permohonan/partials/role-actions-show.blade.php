{{-- resources/views/permohonan/partials/role-actions-show.blade.php
     Panel Aksi Dinamis Berbasis Hak Akses RBAC & Permissions (Figma & BPMN Compliant).
     Mendukung 2 mode penggunaan:
     1) Mode Alpine (di permohonan/index.blade.php) -> memanfaatkan selectedItem secara reaktif
     2) Mode Server (di permohonan/show.blade.php)  -> menggunakan parameter $nomorSurat, $pagu, $statusLabel --}}
@php
    $u = auth()->user();
    $inAlpine   = !isset($nomorSurat);
    $nomorField = $inAlpine
        ? ':value="selectedItem ? (selectedItem.nomor_surat || selectedItem.nomor) : \'\'"'
        : 'value="' . e($nomorSurat) . '"';
    $paguField  = $inAlpine
        ? ':value="selectedItem ? (selectedItem.pagu || selectedItem.anggaran_diajukan || selectedItem.estimasi_total_pagu || \'\') : \'\'"'
        : 'value="' . e($pagu ?? '') . '"';

    // Evaluasi Hak Akses Fitur (RBAC Permissions)
    $canBidang2     = $u && ($u->hasRole('staff_bidang_2') || $u->hasPermission('verifikasi.bidang_2_view'));
    $canWadir2      = $u && ($u->hasRole('wadir_2') || $u->hasAnyPermission(['verifikasi.wadir_2_decide', 'verifikasi.wadir_2_view']));
    $canPerencanaan = $u && ($u->hasRole('perencanaan') || $u->hasAnyPermission(['anggaran.mak_alokasi', 'anggaran.mak_view', 'permohonan.split_mak']));
    $canPpk         = $u && ($u->hasRole('ppk') || $u->hasPermission('reviu.ppk'));
    $canPpbj        = $u && ($u->hasRole('ppbj') || $u->hasAnyPermission(['paket.create', 'paket.view_all', 'spk.create']));
    $canMonitorPp   = $u && $u->canMonitorPp();
    $canPp          = $u && ($u->hasRole('pp') || $u->hasPermission('reviu.pp'));
    $canPerlengkapan= $u && ($u->hasRole('perlengkapan') || $u->hasAnyPermission(['penerimaan.view', 'penerimaan.bast_create']));
    $canKeuangan    = $u && ($u->hasRole('keuangan') || $u->hasAnyPermission(['pembayaran.pencairan_sp2d', 'pembayaran.spp_terbitkan']));

    $field = 'w-full rounded-lg border border-slate-300 bg-white p-2.5 text-xs text-slate-700 placeholder:text-slate-400 focus:border-[#0969da] focus:ring-2 focus:ring-[#0969da]/20 outline-none transition';
    $label = 'block text-xs font-semibold text-slate-600 mb-1';
    $btn   = 'inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-lg text-white font-semibold text-xs shadow-sm transition-colors cursor-pointer';
@endphp

{{-- ============ 1. STAF BIDANG 2 ============ --}}
@if($canBidang2 && \Illuminate\Support\Facades\Route::has('verifikasi.bidang-2.teruskan'))
    <form action="{{ route('verifikasi.bidang-2.teruskan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Form Verifikasi Staf Bidang 2</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 whitespace-nowrap">Telaah Berkas</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">Nomor Memo Cek MAK (Opsional)</label>
                <input type="text" name="nomor_memo_cek_mak" placeholder="MEMO-B2/CEK-MAK/..." class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Upload Dokumen Fisik Memo Cek MAK</label>
                <input type="file" name="file_memo_cek_mak" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="{{ $field }}">
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Catatan Telaah Staf Bidang II <span class="text-rose-500">*</span></label>
            <textarea name="catatan_staff" rows="3" required placeholder="Tulis catatan kelengkapan berkas KAK & spesifikasi..." class="{{ $field }}"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit" 
                    @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                    class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                Teruskan ke Wadir 2 &amp; Perencanaan
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

{{-- ============ 2. WADIR 2 (Persetujuan Eksekutif Kegiatan) ============ --}}
@elseif($canWadir2 && \Illuminate\Support\Facades\Route::has('verifikasi.wadir-2.keputusan'))
    <form action="{{ route('verifikasi.wadir-2.keputusan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Form Persetujuan Wadir 2</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-[#0969da] whitespace-nowrap">Keputusan Eksekutif</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">Nomor Memo Disposisi Wadir II</label>
                <input type="text" name="nomor_memo" placeholder="025/PL3.WDIR2/PG/..." class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">Upload Dokumen Fisik Memo Disposisi</label>
                <input type="file" name="file_memo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="{{ $field }}">
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Catatan / Alasan Keputusan Wadir 2</label>
            <textarea name="catatan_alasan" rows="3" placeholder="Isi arahan disposisi, catatan efisiensi, atau alasan jika menolak/minta revisi..." class="{{ $field }}"></textarea>
            <p class="mt-1 text-[11px] text-slate-400">Wajib diisi jika menolak atau meminta revisi usulan (FR 5.3).</p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" name="keputusan" value="ditolak" 
                    @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                    class="{{ $btn }} bg-amber-500 hover:bg-amber-600">
                Tolak / Minta Revisi
            </button>
            <button type="submit" name="keputusan" value="disetujui" 
                    @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                    class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Setujui Pengajuan (ACC Wadir 2)
            </button>
        </div>
    </form>

{{-- ============ 3. PERENCANAAN (Verifikasi MAK & Pagu DIPA) ============ --}}
@elseif($canPerencanaan && \Illuminate\Support\Facades\Route::has('verifikasi.perencanaan.mak'))
    <form action="{{ route('verifikasi.perencanaan.mak') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Alokasi MAK &amp; Anggaran DIPA</h3>
            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700 whitespace-nowrap">Perencanaan</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="{{ $label }}">Kode MAK (Mata Anggaran) <span class="text-rose-500">*</span></label>
                <select name="kode_mak" required class="{{ $field }}">
                    <option value="">-- Pilih Rekening MAK DIPA --</option>
                    @php $listMakGlobal = \App\Models\AnggaranMAK::orderBy('kode_mak')->get(); @endphp
                    @foreach($listMakGlobal as $makItem)
                        <option value="{{ $makItem->kode_mak }}" {{ $makItem->is_locked ? 'disabled' : '' }}>
                            [{{ $makItem->kode_mak }}] {{ $makItem->uraian_mak }} (Sisa: Rp {{ number_format($makItem->sisa_pagu_mak, 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $label }}">Nominal Alokasi (Rp) <span class="text-rose-500">*</span></label>
                <input type="number" name="nominal_alokasi" required step="0.01" {!! $paguField !!} class="{{ $field }}">
            </div>
        </div>

        <div>
            <label class="{{ $label }}">Pilih PPK yang Ditugaskan (1 dari 4 Akun PPK)</label>
            <select name="id_user_ppk_awal" class="{{ $field }}">
                <option value="">-- Tetapkan Akun PPK Awal --</option>
                @php $ppkList = \App\Models\User::where('role', 'ppk')->where('status_aktif', true)->get(); @endphp
                @foreach($ppkList as $ppkOpt)
                    <option value="{{ $ppkOpt->id_user }}">{{ $ppkOpt->nama }} ({{ $ppkOpt->jabatan ?? 'PPK' }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $label }}">Catatan Ketersediaan MAK</label>
            <textarea name="catatan_anggaran" rows="2" placeholder="Catatan ketersediaan pagu dan sumber dana DIPA..." class="{{ $field }}"></textarea>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="submit" name="keputusan_anggaran" value="tahan" 
                    @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                    class="{{ $btn }} bg-amber-500 hover:bg-amber-600">
                Tahan (Anggaran Belum Siap)
            </button>
            <button type="submit" name="keputusan_anggaran" value="setuju" 
                    @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                    class="{{ $btn }} bg-indigo-600 hover:bg-indigo-700">
                Setujui &amp; Terbitkan Memo MAK
            </button>
        </div>
    </form>

{{-- ============ 4. PPK (Persetujuan Awal Usulan Berdasarkan Memo MAK) ============ --}}
@elseif($canPpk && \Illuminate\Support\Facades\Route::has('verifikasi.ppk.keputusan'))
    @if(isset($permohonan) && empty($permohonan->id_user_ppk_awal))
        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 space-y-2">
            <div class="flex items-center gap-2 text-amber-800 font-bold text-xs">
                <svg class="size-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Menunggu Penetapan MAK &amp; Penugasan Akun PPK
            </div>
            <p class="text-xs text-amber-700 leading-relaxed">
                Usulan ini belum dialokasikan ke akun PPK Anda karena Subbagian Perencanaan belum menetapkan nomor rekening MAK DIPA dan belum memilih 1 dari 4 akun PPK yang bertugas.
            </p>
        </div>
    @elseif(isset($permohonan) && (int) $permohonan->id_user_ppk_awal !== (int) $u->id_user)
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-2">
            <div class="flex items-center gap-2 text-slate-700 font-bold text-xs">
                <svg class="size-4 text-[#0969da]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Ditugaskan ke Akun PPK Lain
            </div>
            <p class="text-xs text-slate-600 leading-relaxed">
                Usulan ini ditugaskan oleh Perencanaan kepada <strong>{{ $permohonan->ppkAwal->nama ?? 'PPK Terpilih' }}</strong>. Anda saat ini masuk sebagai <strong>{{ $u->nama }}</strong>, sehingga verifikasi persetujuan awal hanya dapat diproses oleh PPK terkait.
            </p>
        </div>
    @else
        <form action="{{ route('verifikasi.ppk.keputusan') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="nomor_surat" {!! $nomorField !!}>

            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h3 class="font-semibold text-base text-[#213153]">Persetujuan Awal PPK</h3>
                <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800 whitespace-nowrap">Pejabat Pembuat Komitmen</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="{{ $label }}">Nomor Memo Persetujuan PPK</label>
                    <input type="text" name="nomor_memo" placeholder="MEMO-PPK/{{ date('Y') }}/..." class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $label }}">Upload Dokumen Fisik Memo</label>
                    <input type="file" name="file_memo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="{{ $field }}">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">Catatan / Rekomendasi PPK</label>
                <textarea name="catatan_alasan" rows="3" placeholder="Tuliskan catatan persetujuan usulan atau alasan bila menolak..." class="{{ $field }}"></textarea>
                <p class="mt-1 text-[11px] text-slate-400">Persetujuan awal PPK diperlukan sebelum usulan diteruskan ke PPBJ.</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="submit" name="keputusan" value="ditolak" 
                        @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                        class="{{ $btn }} bg-rose-600 hover:bg-rose-700">
                    Tolak Usulan
                </button>
                <button type="submit" name="keputusan" value="disetujui" 
                        @if($inAlpine) :disabled="!selectedItem || (!selectedItem.nomor && !selectedItem.nomor_surat)" @endif
                        class="{{ $btn }} bg-cyan-700 hover:bg-cyan-800">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Setujui Usulan (ACC PPK)
                </button>
            </div>
        </form>
    @endif

{{-- ============ 5. PPBJ (Registrasi Paket Pengadaan) ============ --}}
@elseif($canPpbj)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Paket Pengadaan &amp; Routing PPBJ</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-[#0969da] whitespace-nowrap">PPBJ Kampus</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
            Menerima Form Pengadaan, MAK, dan Memo permohonan yang telah disetujui pimpinan untuk diregistrasikan menjadi paket pengadaan, penetapan HPS, dan routing penugasan.
        </p>

        <div class="flex flex-wrap items-center justify-end gap-2">
            @if($inAlpine)
                <a :href="'/paket-pengadaan/create?selected=' + encodeURIComponent(selectedItem ? (selectedItem.nomor_surat || selectedItem.nomor) : '')"
                   class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                    Buka Registrasi Paket Baru
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            @else
                <a href="{{ route('paket.create', ['selected' => $nomorSurat ?? '']) }}" class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                    Buka Registrasi Paket Baru
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            @endif
        </div>
    </div>

{{-- ============ 6. PEMANTAU PP (lihat_pp / pp.monitoring_aktivitas) ============ --}}
@elseif($canMonitorPp)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Pemantauan Aktivitas PP</h3>
            <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700 whitespace-nowrap">Monitoring PP</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
            Wewenang memantau proses verifikasi teknis, negosiasi harga rekanan, dan progres belanja Pejabat Pengadaan (PP).
        </p>

        <div class="flex justify-end">
            <a href="{{ route('kontrak.index', ['tab' => 'reviu']) }}" class="{{ $btn }} bg-purple-700 hover:bg-purple-800">
                Buka Monitor Aktivitas PP
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 7. PP (Reviu Teknis & Negosiasi Harga) ============ --}}
@elseif($canPp)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Reviu Teknis &amp; Negosiasi Harga</h3>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 whitespace-nowrap">Pejabat Pengadaan</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
            Lakukan telaah spesifikasi teknis, evaluasi rekanan penyedia, dan negosiasi harga paket pengadaan &le; 200 Juta.
        </p>

        <div class="flex justify-end">
            <a href="{{ route('kontrak.index', ['tab' => 'reviu']) }}" class="{{ $btn }} bg-amber-600 hover:bg-amber-700">
                Buka Reviu Pengadaan PP
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 8. PERLENGKAPAN (Penerimaan Barang & BAST) ============ --}}
@elseif($canPerlengkapan)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Penerimaan Barang &amp; BAST</h3>
            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-[#0969da] whitespace-nowrap">Bagian Perlengkapan</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
            Periksa kesesuaian fisik kiriman barang dari penyedia, unggah bukti multi-foto, dan terbitkan Berita Acara Serah Terima (BAST).
        </p>

        <div class="flex justify-end">
            <a href="{{ route('penerimaan.index') }}" class="{{ $btn }} bg-[#0969da] hover:bg-blue-700">
                Buka Modul Penerimaan
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 9. KEUANGAN (Tagihan & Pembayaran SP2D) ============ --}}
@elseif($canKeuangan)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <h3 class="font-semibold text-base text-[#213153]">Tagihan &amp; Pembayaran SP2D</h3>
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 whitespace-nowrap">Bagian Keuangan</span>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed">
            Verifikasi kelengkapan berkas BAST, terbitkan SPP/SPM, dan catat realisasi pencairan dana SP2D anti-duplicate bayar.
        </p>

        <div class="flex justify-end">
            <a href="{{ route('pembayaran.index') }}" class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">
                Buka Modul Pembayaran
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>

{{-- ============ 10. PEMOHON / UMUM: INFO STATUS ============ --}}
@else
    <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-blue-50/70 border border-blue-100">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="size-8 rounded-full bg-[#0969da] text-white flex items-center justify-center shrink-0">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h4 class="font-semibold text-xs text-[#213153]">Status Pengajuan Saat Ini</h4>
                <p class="text-[11px] text-slate-500">Berkas sedang dalam pemrosesan siklus pengadaan resmi PNJ.</p>
            </div>
        </div>

        @if($inAlpine)
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#0969da] border border-blue-200 shadow-sm whitespace-nowrap"
                  x-text="selectedItem ? statusMeta(selectedItem.statusRaw).label : 'Diproses'"></span>
        @else
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#0969da] border border-blue-200 shadow-sm whitespace-nowrap">
                {{ $statusLabel ?? 'Diproses' }}
            </span>
        @endif
    </div>
@endif
