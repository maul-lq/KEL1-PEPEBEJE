/**
 * Split MAK Manager Module (resources/js/modules/split_mak.js)
 *
 * Mengelola antarmuka dinamis pemecahan (Split) berkas usulan permohonan pengadaan
 * berdasarkan pos rekening MAK DIPA Kampus PNJ (FR 6.3).
 */

export function splitMakManager(initialData = {}) {
    return {
        permohonanList: initialData.permohonanList || [],
        makList: initialData.makList || [],
        selectedIndukNomor: '',
        parentTotalPagu: 0,
        alasanPemecahan: '',
        subBerkas: [
            { nomor_surat_pecahan: '', judul_pengadaan: '', kode_mak: '', estimasi_pagu_pecahan: 0 },
            { nomor_surat_pecahan: '', judul_pengadaan: '', kode_mak: '', estimasi_pagu_pecahan: 0 }
        ],

        init() {
            if (this.selectedIndukNomor) {
                this.onSelectInduk();
            }
        },

        onSelectInduk() {
            const induk = this.permohonanList.find(p => p.nomor_surat === this.selectedIndukNomor);
            if (induk) {
                this.parentTotalPagu = parseFloat(induk.estimasi_total_pagu || induk.anggaran_diajukan || 0);
                this.subBerkas.forEach((row, idx) => {
                    if (!row.nomor_surat_pecahan || row.nomor_surat_pecahan.includes('.')) {
                        row.nomor_surat_pecahan = `${this.selectedIndukNomor}.${idx + 1}`;
                    }
                    if (!row.judul_pengadaan || row.judul_pengadaan.includes('(Bagian ')) {
                        row.judul_pengadaan = `${induk.perihal || induk.judul_pengadaan} (Bagian ${idx + 1})`;
                    }
                });
            } else {
                this.parentTotalPagu = 0;
            }
        },

        addRow() {
            const nextIdx = this.subBerkas.length + 1;
            this.subBerkas.push({
                nomor_surat_pecahan: this.selectedIndukNomor ? `${this.selectedIndukNomor}.${nextIdx}` : '',
                judul_pengadaan: '',
                kode_mak: '',
                estimasi_pagu_pecahan: 0
            });
        },

        removeRow(index) {
            if (this.subBerkas.length > 2) {
                this.subBerkas.splice(index, 1);
            }
        },

        get totalAllocated() {
            return this.subBerkas.reduce((acc, row) => acc + (parseFloat(row.estimasi_pagu_pecahan) || 0), 0);
        },

        get remainingPagu() {
            return this.parentTotalPagu - this.totalAllocated;
        },

        get isExceeding() {
            return this.totalAllocated > this.parentTotalPagu;
        },

        get isValid() {
            return this.selectedIndukNomor !== '' &&
                this.subBerkas.length >= 2 &&
                this.totalAllocated > 0 &&
                this.totalAllocated <= this.parentTotalPagu &&
                this.subBerkas.every(row => row.nomor_surat_pecahan && row.judul_pengadaan && row.kode_mak && parseFloat(row.estimasi_pagu_pecahan) > 0);
        }
    };
}
