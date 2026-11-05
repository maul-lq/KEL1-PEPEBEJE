<!-- OVERLAY BACKDROP & DIALOG KONFIRMASI HAPUS (FRAME 385 FIGMA) -->
<div x-show="showDeleteModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
     style="display: none;"
     x-cloak>

    <!-- CARD CONTAINER MODAL HAPUS -->
    <div @click.away="showDeleteModal = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative w-full max-w-[596px] bg-white rounded-2xl border-t-[5px] border-rose-600 shadow-2xl p-8 overflow-hidden"
         x-data="{ showPassword: false }">

        <!-- HEADER MODAL -->
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-bold text-[#213153] tracking-tight">
                    Hapus Pengajuan?
                </h2>
                <p class="text-sm text-slate-500 max-w-[430px] leading-snug">
                    Hanya dapat dihapus apabila belum disetujui oleh pihak manapun.
                </p>
            </div>
            <!-- Tombol Close (X) -->
            <button @click="showDeleteModal = false" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- FORM SUBMIT DELETE LARAVEL -->
        <form :action="selectedItem ? '{{ url('/permohonan') }}/' + (selectedItem.id || selectedItem.nomor) : '#'" method="POST" class="mt-6 space-y-6">
            @csrf
            @method('DELETE')

            <!-- FIELD KONFIRMASI PASSWORD -->
            <div class="space-y-2">
                <label for="confirm-password" class="block text-sm font-semibold text-[#213153]">
                    Konfirmasi Password <span class="text-rose-600">*</span>
                </label>
                
                <div class="relative flex items-center bg-[#f5f5ff] rounded-xl border border-slate-300 focus-within:border-[#0969da] focus-within:ring-1 focus-within:ring-[#0969da] transition-all">
                    <input :type="showPassword ? 'text' : 'password'" 
                           id="confirm-password" 
                           name="password" 
                           required 
                           placeholder="Masukkan password akun Anda"
                           class="w-full h-12 pl-4 pr-12 bg-transparent text-sm text-slate-800 placeholder-slate-400 outline-none">
                    
                    <!-- Toggle Show/Hide Password Icon -->
                    <button @click="showPassword = !showPassword" type="button" class="absolute right-4 p-1 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                        <template x-if="!showPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </template>
                        <template x-if="showPassword">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.007 10.007 0 013.682-.863c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243M3 3l18 18"/></svg>
                        </template>
                    </button>
                </div>
            </div>

            <!-- FOOTER ACTION BUTTONS -->
            <div class="flex items-center justify-end gap-3 pt-4">
                <button @click="showDeleteModal = false" type="button" 
                        class="w-36 h-12 rounded-xl border border-[#0969da] bg-white text-[#0969da] hover:bg-blue-50 font-bold text-sm transition-all cursor-pointer flex items-center justify-center">
                    Batal
                </button>
                <button type="submit" 
                        class="w-48 h-12 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm transition-all shadow-md shadow-rose-600/20 cursor-pointer flex items-center justify-center">
                    Hapus Pengajuan
                </button>
            </div>

        </form>

    </div>
</div>