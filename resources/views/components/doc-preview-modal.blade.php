{{--
  Informasi File

  Penulis   : Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
  Ringkasan : Komponen Blade ini buat untuk menampilkan modal global pratinjau dokumen PDF
              berbasis iframe lengkap dengan metadata ukuran dan tombol unduh langsung.
--}}
{{--
  Komentar Deskriptif: Komponen Modal Pratinjau Dokumen PDF Global (`<x-doc-preview-modal>`).

  Tujuan: mendengarkan CustomEvent window `open-preview-doc` dan `close-preview-doc` agar
          setiap modul di aplikasi dapat membuka PDF di dalam aplikasi tanpa berpindah tab.
  Parameter: Tidak menerima @props (dikendalikan lewat detail event window `docPreviewModal`).
  Nilai kembali: Overlay dialog HTML modal pratinjau dokumen PDF yang siap dirender di root layout.
  Contoh pemanggilan: `<x-doc-preview-modal />`
--}}
{{-- pasang x-cloak agar overlay dialog tidak sempat berkedip saat Alpine belum terinisialisasi. --}}
<div x-data="docPreviewModal"
x-on:open-preview-doc.window="openPreview($event.detail)"
x-on:close-preview-doc.window="close()"
x-show="open"
x-cloak
class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
role="dialog"
aria-modal="true">
    <div @click.stop class="bg-white rounded-2xl w-full max-w-[1100px] shadow-2xl flex flex-col max-h-[94vh] overflow-hidden border border-slate-200">
        
        {{-- Bagian Header Modal Pratinjau Dokumen --}}
        <div class="h-16 border-b border-slate-200 px-6 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="size-9 rounded-lg bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0 font-bold text-xs">
                    PDF
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-base text-[#213153] truncate" x-text="doc.title || 'Pratinjau Dokumen'"></h3>
                    <p class="text-xs text-slate-500 truncate" x-text="doc.size ? 'Ukuran: ' + doc.size : 'Format Dokumen PDF'"></p>
                </div>
            </div>
            <button @click="open = false" type="button" class="text-slate-400 hover:text-slate-600 rounded-lg p-2 transition-colors cursor-pointer" title="Tutup">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Bagian Viewer Area: Iframe hanya dimount ketika open && doc.url bernilai true agar PDF tidak dimuat di latar belakang. --}}
        <div class="flex-1 min-h-[500px] bg-slate-100 p-3 sm:p-4 flex flex-col">
            <template x-if="open && doc.url">
                <iframe :src="doc.url" class="w-full h-[68vh] rounded-xl border border-slate-200 bg-white shadow-inner"></iframe>
            </template>
            <template x-if="!doc.url">
                <div class="w-full h-[68vh] rounded-xl border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center text-slate-400 gap-2">
                    <svg class="size-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm font-medium">Pratinjau dokumen tidak tersedia.</p>
                </div>
            </template>
        </div>

        {{-- Bagian Footer Tombol Unduh dan Tutup --}}
        <div class="h-16 bg-white border-t border-slate-200 px-6 flex items-center justify-end gap-3 shrink-0">
            <a :href="doc.url" download
               class="h-10 px-5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-bold text-xs inline-flex items-center justify-center gap-2 transition-colors shadow-xs">
                <svg class="size-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh Berkas</span>
            </a>

            <button @click="open = false" type="button"
                    class="h-10 px-6 bg-[#0969da] hover:bg-blue-700 text-white rounded-xl font-bold text-xs inline-flex items-center justify-center transition-colors cursor-pointer shadow-xs">
                Tutup
            </button>
        </div>

    </div>
</div>
