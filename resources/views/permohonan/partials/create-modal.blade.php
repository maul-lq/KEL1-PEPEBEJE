<!-- OVERLAY BACKDROP & MODAL DIALOG CONTAINER -->
<div x-show="showCreateModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs overflow-y-auto"
     style="display: none;"
     x-cloak>

    <!-- MODAL CARD CONTAINER -->
    <div @click.away="showCreateModal = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative w-full max-w-[596px] bg-white rounded-2xl border-t-[5px] border-[#0969da] shadow-2xl overflow-hidden my-8"
         x-data="{
            files: [],
            maxFiles: 5,
            maxTotalSizeMB: 15,
            isDragging: false,
            errorMessage: '',

            handleFiles(selectedFiles) {
                this.errorMessage = '';
                const incomingFiles = Array.from(selectedFiles);

                // Validasi Jumlah File (Maksimal 5 Berkas)
                if (this.files.length + incomingFiles.length > this.maxFiles) {
                    this.errorMessage = `Maksimal hanya dapat mengunggah ${this.maxFiles} berkas.`;
                    return;
                }

                // Validasi Ukuran Total File (Maksimal 15 MB)
                let currentTotalBytes = this.files.reduce((acc, f) => acc + f.size, 0);
                let incomingTotalBytes = incomingFiles.reduce((acc, f) => acc + f.size, 0);
                let totalMB = (currentTotalBytes + incomingTotalBytes) / (1024 * 1024);

                if (totalMB > this.maxTotalSizeMB) {
                    this.errorMessage = `Total ukuran seluruh berkas melebihi batas ${this.maxTotalSizeMB} MB.`;
                    return;
                }

                // Pemrosesan File & Penentuan Warna Badge Format
                incomingFiles.forEach(file => {
                    let ext = file.name.split('.').pop().toUpperCase();
                    
                    let badgeColor = 'bg-[#0969da]'; // Default Biru untuk DOC/DOCX
                    if (ext === 'PDF') {
                        badgeColor = 'bg-rose-600'; // Merah khas PDF
                    } else if (['XLS', 'XLSX', 'CSV'].includes(ext)) {
                        badgeColor = 'bg-emerald-600'; // Hijau khas Excel
                    } else if (['PNG', 'JPG', 'JPEG', 'WEBP'].includes(ext)) {
                        badgeColor = 'bg-sky-500'; // Sky Blue khas Gambar
                    }

                    this.files.push({
                        raw: file,
                        name: file.name,
                        size: (file.size / 1024).toFixed(0) + ' KB',
                        type: ext,
                        colorClass: badgeColor,
                        url: URL.createObjectURL(file)
                    });
                });

                this.updateFileInput();
            },

            removeFile(index) {
                this.files.splice(index, 1);
                this.errorMessage = '';
                this.updateFileInput();
            },

            updateFileInput() {
                // Sinkronisasi data file ke input element HTML agar dapat diproses oleh controller Laravel
                const dt = new DataTransfer();
                this.files.forEach(f => dt.items.add(f.raw));
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.files = dt.files;
                }
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
            <button @click="showCreateModal = false" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- FORM SUBMIT LARAVEL -->
        <form action="{{ route('permohonan.store') }}" method="POST" enctype="multipart/form-data" class="px-8 pb-8 space-y-6">
            @csrf
            <input type="hidden" name="kode_unit" value="{{ Auth::user()->kode_unit ?? 'TIK' }}">

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

                <!-- 4. Nominal Anggaran (Live Format Rupiah + Tanpa Panah Incrementer) -->
                <div class="space-y-1.5" x-data="{ 
                    rawAnggaran: '',
                    formatRupiah(val) {
                        if (!val) return '';
                        let numberString = val.toString().replace(/[^,\d]/g, '');
                        let split = numberString.split(',');
                        let sisa = split[0].length % 3;
                        let rupiah = split[0].substr(0, sisa);
                        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
                        if (ribuan) {
                            let separator = sisa ? '.' : '';
                            rupiah += separator + ribuan.join('.');
                        }
                        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
                        return rupiah ? 'Rp ' + rupiah : '';
                    }
                }">
                    <div class="flex items-center justify-between">
                        <label for="anggaran_diajukan" class="block text-sm font-semibold text-[#213153]">
                            Nominal <span class="text-rose-500">*</span>
                        </label>
                        <span x-show="rawAnggaran" 
                              x-text="formatRupiah(rawAnggaran)" 
                              x-transition
                              class="text-xs font-extrabold text-[#0969da] bg-blue-50 px-2.5 py-0.5 rounded-md border border-blue-100 shadow-2xs">
                        </span>
                    </div>

                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400 select-none">Rp</span>
                        <input type="number" 
                               id="anggaran_diajukan" 
                               name="anggaran_diajukan" 
                               x-model="rawAnggaran"
                               required 
                               min="1" 
                               step="0.01" 
                               placeholder="Masukkan Nominal (misal: 150000000)"
                               class="w-full h-10 pl-10 pr-3.5 rounded-lg border border-slate-300 text-xs text-slate-800 placeholder-slate-400 focus:ring-1 focus:ring-[#0969da] focus:border-[#0969da] outline-none transition-all [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                    </div>
                </div>

                <!-- 5. Multiple Upload Box (Maksimal 5 File, Drag & Drop, Badges Warna Spesiﬁk) -->
                <div class="space-y-2 pt-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-semibold text-[#213153]">
                            Dokumen Lampiran <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] font-medium text-slate-400" x-text="files.length + '/5 Berkas (Maks 15 MB Total)'"></span>
                    </div>

                    <!-- PESAN ERROR VALIDASI FILE -->
                    <div x-show="errorMessage" x-text="errorMessage" class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-600 text-xs font-medium"></div>

                    <!-- DAFTAR BERKAS TERUNGGAH (DENGAN WARNA BADGE SPESIFIK TYPE) -->
                    <div x-show="files.length > 0" class="space-y-2 mb-3">
                        <template x-for="(file, index) in files" :key="index">
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 hover:border-slate-300 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <!-- Badge Warna Dinamis (PDF: Merah, DOCX: Biru, XLSX: Hijau, PNG/JPG: Sky Blue) -->
                                    <div :class="file.colorClass" class="size-8 rounded-lg text-white flex items-center justify-center font-bold text-[10px] shrink-0 shadow-xs" x-text="file.type"></div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-xs text-[#213153] truncate" x-text="file.name"></h4>
                                        <p class="text-[10px] text-slate-500" x-text="file.size"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a :href="file.url" target="_blank" class="px-2.5 py-1 bg-blue-50 text-[#0969da] hover:bg-blue-100 font-bold text-[11px] rounded-md transition-colors">
                                        Lihat
                                    </a>
                                    <button @click="removeFile(index)" type="button" class="p-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-md transition-colors cursor-pointer">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- DROPZONE DRAG & DROP -->
                    <div x-show="files.length < maxFiles"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="isDragging = false; handleFiles($event.dataTransfer.files)"
                         @click="$refs.fileInput.click()" 
                         :class="isDragging ? 'border-[#0969da] bg-blue-50 scale-[0.99]' : 'border-slate-300 hover:border-[#0969da] bg-slate-50/50 hover:bg-[#ebf5ff]/40'"
                         class="relative h-[115px] w-full rounded-xl border-2 border-dashed transition-all cursor-pointer flex flex-col items-center justify-center text-center p-4">
                        
                        <div class="size-10 rounded-full bg-gradient-to-br from-blue-500 to-white shadow-md flex items-center justify-center mb-2">
                            <svg class="size-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        </div>
                        <span class="font-bold text-xs text-[#213153] block">Tarik & Taruh file dokumen ke sini</span>
                        <span class="text-[10px] text-slate-500 block mt-0.5">Atau klik untuk memilih berkas (PDF, DOCX, XLSX, PNG, JPG)</span>

                        <!-- Input File Multiple Hidden -->
                        <input x-ref="fileInput" 
                               type="file" 
                               name="dokumen_lampiran[]" 
                               multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" 
                               @change="handleFiles($event.target.files)" 
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