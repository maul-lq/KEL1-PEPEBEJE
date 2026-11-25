{{--
  Informasi File

  Penulis   : DendyRafi, Rizlrad Fz
  Lisensi   : GNU General Public License v3.0 (GPL-3.0)
  Ringkasan : Partial Blade ini menyusun modal konfirmasi hapus pengajuan permohonan mengikuti
              desain Figma node 1005:13414 dan menghubungkannya dengan modalDialog serta state
              trackingManager.
--}}
{{--
  Partial Modal Konfirmasi Hapus Pengajuan (`permohonan.partials.delete-modal`).

  Tujuan: menampilkan dialog konfirmasi penghapusan usulan permohonan dengan tata letak bersih
          berbasis desain Figma 1005:13414, URL aksi DELETE yang aman terhadap karakter `/`
          pada nomor surat, serta penanganan buka/tutup modal via `modalDialog`.
  Parameter (Inherited Alpine Scope): `hapusTarget`, `hapusPassword`, `showHapusPassword`, `toggleHapusPassword()` dari `trackingManager`.
  Nilai kembali: Fragmen HTML dialog modal formulir DELETE ke endpoint `/permohonan/{id}`.
  Contoh pemanggilan: `@include('permohonan.partials.delete-modal')`
--}}
<div x-data="modalDialog"
     data-show="0"
     data-focusable="1"
     x-on:open-modal.window="onWindowOpen($event.detail)"
     x-on:close-modal.window="onWindowClose($event.detail)"
     x-on:close.stop="close()"
     x-on:keydown.escape.window="close()"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     id="modal-delete-permohonan"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-title-modal-delete-permohonan"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
     x-cloak>

    <!-- CARD CONTAINER MODAL HAPUS (Figma 1005:13414 / 1005:13415) -->
    <div @click.away="close()"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative w-full max-w-[596px] rounded-[12px] border border-[#dfe6ef] bg-white px-7 py-8 sm:px-[56px] sm:pt-[39px] sm:pb-[52px] shadow-2xl overflow-hidden">

        <!-- HEADER MODAL (Figma 1005:13422, 1005:13421, 1005:13423) -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="modal-title-modal-delete-permohonan" class="text-[24px] font-semibold leading-snug text-[#172b4d]">
                    Hapus Pengajuan?
                </h2>
                <p class="mt-2 text-[17px] font-normal leading-snug text-[#475569] max-w-[490px]">
                    Hanya dapat dihapus saat status Diajukan (sebelum penetapan pos anggaran) atau setelah Selesai.
                </p>
            </div>
            <!-- Tombol Close (X) -->
            <button @click="close()" type="button" class="mt-1 p-1 text-[#172b4d] hover:text-slate-600 rounded-lg transition-colors cursor-pointer shrink-0" title="Tutup">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- FORM SUBMIT DELETE LARAVEL -->
        <form :action="'{{ url('permohonan') }}/' + String(hapusTarget?.nomor_surat || hapusTarget?.nomor || hapusTarget?.id || '').split('/').map(seg => encodeURIComponent(seg)).join('/')"
              method="POST"
              class="mt-7">
            @csrf
            @method('DELETE')

            <!-- FIELD KONFIRMASI PASSWORD (Figma 1005:13416, 1005:13417, 1005:13418) -->
            <div>
                <label for="confirm-password" class="block text-[18px] font-medium text-black mb-2.5">
                    Konfirmasi Password <span class="text-[#ff0000]">*</span>
                </label>

                <div class="relative flex items-center h-[60px] w-full rounded-[5px] border border-[#d2d2d2] bg-[#f4f7fb] focus-within:border-[#3b82f6] focus-within:ring-1 focus-within:ring-[#3b82f6] transition-all">
                    <input :type="showHapusPassword ? 'text' : 'password'"
                           id="confirm-password"
                           name="password"
                           x-model="hapusPassword"
                           required
                           placeholder="Masukkan password akun Anda"
                           class="w-full h-full pl-[28px] pr-12 bg-transparent text-[18px] text-black placeholder:text-[#aaaaaa] outline-none">

                    <!-- Toggle Show/Hide Password Icon (Figma 1005:8611) -->
                    <button @click="toggleHapusPassword()" type="button" class="absolute right-5 p-1 text-[#475569] hover:text-[#172b4d] transition-colors cursor-pointer" title="Tampilkan / Sembunyikan Password">
                        <template x-if="!showHapusPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </template>
                        <template x-if="showHapusPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.007 10.007 0 013.682-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243M3 3l18 18"/></svg>
                        </template>
                    </button>
                </div>
            </div>

            <!-- FOOTER ACTION BUTTONS (Figma 1005:13424, 1005:13425, 1005:13426, 1005:13427) -->
            <div class="mt-[42px] flex flex-wrap sm:flex-nowrap items-center justify-between gap-4">
                <button @click="close()" type="button"
                        class="w-full sm:w-[185px] h-[48px] rounded-[10px] border border-[rgba(59,130,246,0.85)] bg-white text-[#2563eb] hover:bg-blue-50 font-medium text-[18px] transition-colors cursor-pointer flex items-center justify-center">
                    Batal
                </button>
                <button type="submit"
                        class="w-full sm:w-[248px] h-[48px] rounded-[10px] bg-[#dc2626] hover:bg-red-700 text-white font-medium text-[18px] transition-colors cursor-pointer flex items-center justify-center">
                    Hapus Pengajuan
                </button>
            </div>

        </form>

    </div>
</div>
