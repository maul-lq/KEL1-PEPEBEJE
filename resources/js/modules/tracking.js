/**
 * Tracking Manager Module (resources/js/modules/tracking.js)
 *
 * Mengelola state interaktif split view pelacakan permohonan pengadaan,
 * pemilihan berkas aktif, dan pembukaan modal interaktif (Revisi, Hapus, Buat).
 */

export function trackingManager() {
    return {
        selectedItem: null,
        revisiTarget: null,
        hapusTarget: null,

        selectPermohonan(item) {
            this.selectedItem = item;
        },

        openCreateModal() {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-create-permohonan' }));
        },

        openRevisiModal(item) {
            this.revisiTarget = item || this.selectedItem;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-revisi-permohonan' }));
        },

        openHapusModal(item) {
            this.hapusTarget = item || this.selectedItem;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-delete-permohonan' }));
        },

        getStepNumber(status) {
            const st = (status || '').toLowerCase();
            if (['draft', 'diajukan'].includes(st)) return 1;
            if (['reviu_paralel', 'pending_anggaran', 'hold', 'perlu_revisi'].includes(st)) return 2;
            if (['disetujui', 'diproses_ppbj'].includes(st)) return 3;
            if (['reviu_dokumen'].includes(st)) return 4;
            if (['spk_terbit', 'kontrak'].includes(st)) return 5;
            if (['pengingat_h2', 'pengiriman'].includes(st)) return 6;
            if (['pemeriksaan_fisik', 'bast_terbit'].includes(st)) return 7;
            if (['selesai', 'sp2d_lunas'].includes(st)) return 8;
            return 1;
        },

        getStepTitle(step) {
            const titles = [
                'Diajukan',
                'Verifikasi Paralel',
                'Registrasi Paket',
                'Reviu Dokumen',
                'Kontrak SPK',
                'Pengiriman',
                'Penerimaan BAST',
                'SP2D Lunas'
            ];
            return titles[step - 1] || 'Siklus';
        },

        getStepColor(step) {
            if (!this.selectedItem) return 'bg-slate-100 text-slate-400';

            const st = (this.selectedItem.status_permohonan || this.selectedItem.status || '').toLowerCase();
            const curr = this.getStepNumber(st);

            if (step < curr) {
                return 'bg-emerald-500 text-white shadow-emerald-500/20';
            } else if (step === curr) {
                if (['ditolak', 'batal'].includes(st)) {
                    return 'bg-rose-500 text-white shadow-rose-500/20';
                }
                if (['revisi', 'perlu_revisi', 'hold', 'pending_anggaran'].includes(st)) {
                    return 'bg-amber-500 text-white shadow-amber-500/20';
                }
                return 'bg-[#0969da] text-white shadow-blue-500/20';
            } else {
                return 'bg-slate-100 text-slate-400';
            }
        }
    };
}
