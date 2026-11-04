<!-- OVERLAY BACKDROP & MODAL DIALOG CONTAINER -->
<div x-[#213153]
     x-show="showCreateModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs overflow-y-auto"
     style="display: none;">

    <!-- MODAL CARD CONTAINER (FRAME 376 & 377 FIGMA) -->
    <div @click.away="showCreateModal = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative w-full max-w-[596px] bg-white rounded-2xl border-t-[5px] border-[#0969da] shadow-2xl overflow-hidden my-8"
         x-data="{
            fileName: '',
            fileSize: '',
            filePreviewUrl: null,
            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    this.fileName = file.name;
                    this.fileSize = (file.size / 1024).toFixed(0) + ' KB';
                    this.filePreviewUrl = URL.createObjectURL(file);
                }
            },
            removeFile() {
                this.fileName = '';
                this.fileSize = '';
                this.filePreviewUrl = null;
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            }
         }">

        <!-- HEADER MODAL -->
        <div class="px-8 pt-8 pb-4 flex items-start justify-between">
            <div class="space-y-1">
                <h2 id="new-request-title" class="text-2xl font-bold text-[#213153] tracking-tight">
                    Buat Permohonan Baru
                </h2>
                <p class="text-sm text-slate-500 max-w-[430px] leading-snug">
                    Masukkan nomor surat, judul, tujuan, serta dokumen resmi
                </p>
            </div>
            <!-- Tombol Close (X) -->
            <button @click="showCreateModal = false" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- FORM SUBMIT LARAVEL -->
        <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data" class="px-8 pb-8 space-y-6">
            @csrf

            <!-- CONTAINER SCROLLABLE FORM FIELD -->
            <div class="space-y-4 max-h-[480px] overflow-y-auto pr-1">

                <!-- 1. No Srikandi -->
                <div class="space-y-1.5">
                    <label for="nomor_surat" class="block text-sm font-semibold text-[#213153]">
                        Nomor Surat <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="nomor_surat" name="nomor_surat" required placeholder="Masukkan nomor surat"
                           class="w-full h-10 px-3.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-[#0969da] focus:border-[#0969da] outline-none transition-all">
                </div>

                <!-- 2. Judul Pengadaan -->
                <div class="space-y-1.5">
                    <label for="perihal" class="block text-sm font-semibold text-[#213153]">
                        Judul Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="perihal" name="perihal" required placeholder="Masukkan Judul Pengadaan"
                           class="w-full h-10 px-3.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-[#0969da] focus:border-[#0969da] outline-none transition-all">
                </div>

                <!-- 3. Tujuan Pengadaan -->
                <div class="space-y-1.5">
                    <label for="tujuan_pengadaan_ringkas" class="block text-sm font-semibold text-[#213153]">
                        Tujuan Pengadaan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="tujuan_pengadaan_ringkas" name="tujuan_pengadaan_ringkas" required placeholder="Masukkan Tujuan Pengadaan"
                           class="w-full h-10 px-3.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-[#0969da] focus:border-[#0969da] outline-none transition-all">
                </div>

                <!-- 4. Nominal Anggaran -->
                <div class="space-y-1.5">
                    <label for="anggaran_diajukan" class="block text-sm font-semibold text-[#213153]">
                        Nominal <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="anggaran_diajukan" name="anggaran_diajukan" required min="1" step="0.01" placeholder="Masukkan Nominal"
                           class="w-full h-10 px-3.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-[#0969da] focus:border-[#0969da] outline-none transition-all">
                </div>

                <!-- 5. Dokumen Lampiran (FRAME 376 vs 377) -->
                <div class="space-y-1.5 pt-1">
                    <label class="block text-sm font-semibold text-[#213153]">
                        Dokumen <span class="text-rose-500">*</span>
                    </label>

                    <!-- STATE 1: BILA FILE SUDAH DIPILIH (FRAME 377) -->
                    <template x-if="fileName">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-100/80 border border-slate-200">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-9 rounded-lg bg-rose-500 text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                    PDF
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-xs text-[#213153] truncate" x-text="fileName"></h4>
                                    <p class="text-[10px] text-slate-500" x-text="'PDF • ' + fileSize"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="filePreviewUrl" target="_blank" class="px-3 py-1.5 bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs rounded-md transition-colors shadow-xs">
                                    Lihat
                                </a>
                                <button @click="removeFile()" type="button" class="p-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-md transition-colors shadow-xs">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- STATE 2: UPLOAD BOX DROPZONE (FRAME 376) -->
                    <div @click="$refs.fileInput.click()" 
                         class="relative h-[115px] w-full rounded-lg border-2 border-dashed border-[#0969da]/50 hover:border-[#0969da] bg-[#ebf5ff]/60 hover:bg-[#ebf5ff] transition-all cursor-pointer flex flex-col items-center justify-center text-center p-4">
                        
                        <div class="size-10 rounded-full bg-gradient-to-br from-blue-500 to-white shadow-md flex items-center justify-center mb-2">
                            <svg class="size-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        </div>
                        <span class="font-bold text-xs text-[#213153] block" x-text="fileName ? 'Ganti file dokumen' : 'Taruh file disini'"></span>
                        <span class="text-[10px] text-slate-500 block mt-0.5">Atau klik untuk memilih file</span>

                        <input x-ref="fileInput" 
                               type="file" 
                               name="file_surat_permohonan" 
                               required 
                               accept=".pdf,.doc,.docx,.xls,.xlsx" 
                               @change="handleFileSelect($event)" 
                               class="hidden"/>
                    </div>
                </div>

            </div>

            <!-- FOOTER ACTION BUTTONS -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button @click="showCreateModal = false" type="button" 
                        class="w-36 h-11 rounded-xl border border-[#0969da] bg-white text-[#0969da] hover:bg-blue-50 font-bold text-xs transition-all cursor-pointer flex items-center justify-center">
                    Batal
                </button>
                <button type="submit" 
                        class="w-44 h-11 rounded-xl bg-[#0969da] hover:bg-blue-700 text-white font-bold text-xs transition-all shadow-md shadow-blue-500/20 cursor-pointer flex items-center justify-center">
                    Buat Pengajuan
                </button>
            </div>

        </form>

    </div>
</div>