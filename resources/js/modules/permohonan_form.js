/**
 * Modul Formulir Permohonan Pengadaan SIM-PBJ (permohonan_form.js)
 *
 * Mengatur unggah seret-dan-lepas pada halaman buat usulan, modal unggah multi-berkas,
 * pemformatan nominal Rupiah, serta modal konfirmasi hapus usulan.
 */

import { formatRupiahInput } from './ui_helpers.js';

export function permohonanCreatePage() {
    return {
        nominal: '',
        fileNameSurat: '',
        fileNameKak: '',
        fileNameSpek: '',
        isDraggingSurat: false,
        isDraggingKak: false,
        isDraggingSpek: false,

        init() {
            this.nominal = this.$el.dataset.initialNominal || '';
        },

        handleDrop(event, field) {
            const file = event.dataTransfer?.files?.[0];
            if (!file) {
                return;
            }
            const input = document.getElementById(field);
            if (input) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    };
}

export function permohonanCreateModalUpload() {
    return {
        files: [],
        maxFiles: 5,
        maxTotalSizeMB: 15,
        isDragging: false,
        errorMessage: '',

        handleFiles(selectedFiles) {
            this.errorMessage = '';
            const incomingFiles = Array.from(selectedFiles);

            if (this.files.length + incomingFiles.length > this.maxFiles) {
                this.errorMessage = `Maksimal hanya dapat mengunggah ${this.maxFiles} berkas.`;
                return;
            }

            const currentTotalBytes = this.files.reduce((acc, f) => acc + f.size, 0);
            const incomingTotalBytes = incomingFiles.reduce((acc, f) => acc + f.size, 0);
            const totalMB = (currentTotalBytes + incomingTotalBytes) / (1024 * 1024);

            if (totalMB > this.maxTotalSizeMB) {
                this.errorMessage = `Total ukuran seluruh berkas melebihi batas ${this.maxTotalSizeMB} MB.`;
                return;
            }

            incomingFiles.forEach((file) => {
                const ext = file.name.split('.').pop().toUpperCase();

                let badgeColor = 'bg-[#0969da]';
                if (ext === 'PDF') {
                    badgeColor = 'bg-rose-600';
                } else if (['XLS', 'XLSX', 'CSV'].includes(ext)) {
                    badgeColor = 'bg-emerald-600';
                } else if (['PNG', 'JPG', 'JPEG', 'WEBP'].includes(ext)) {
                    badgeColor = 'bg-sky-500';
                }

                this.files.push({
                    raw: file,
                    name: file.name,
                    size: `${(file.size / 1024).toFixed(0)} KB`,
                    type: ext,
                    colorClass: badgeColor,
                    url: URL.createObjectURL(file),
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
            const dt = new DataTransfer();
            this.files.forEach((f) => dt.items.add(f.raw));
            if (this.$refs.fileInput) {
                this.$refs.fileInput.files = dt.files;
            }
        },
    };
}

export function rupiahField() {
    return {
        rawAnggaran: '',

        formatRupiah(val) {
            return formatRupiahInput(val);
        },
    };
}

export function permohonanDeleteModal() {
    return {
        showPassword: false,
    };
}

export function permohonanQuickModal() {
    return {
        nominalModal: '',
    };
}
