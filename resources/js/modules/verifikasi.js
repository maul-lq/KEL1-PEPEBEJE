/**
 * Modul Verifikasi & Persetujuan Usulan SIM-PBJ (verifikasi.js)
 *
 * Mengatur formulir penentuan pos anggaran Subbagian Perencanaan,
 * keputusan PPK, Wakil Direktur II, dan persetujuan dokumen pengadaan.
 */

export function verifikasiMakForm() {
    return {
        selectedMak: '',
        paguSisa: 0,
        nominal: 0,
        keputusan: 'setuju',

        init() {
            this.selectedMak = this.$el.dataset.selectedMak || '';
            this.nominal = parseFloat(this.$el.dataset.nominal || '0') || 0;
            this.keputusan = this.$el.dataset.keputusan || 'setuju';
        },

        updateMakInfo(event) {
            const opt = event.target.selectedOptions[0];
            this.paguSisa = opt ? parseFloat(opt.dataset.sisa || 0) : 0;
        },
    };
}

export function verifikasiMakList() {
    return {
        expandedSurat: null,
    };
}

export function verifikasiKeputusanPpk() {
    return {
        keputusan: 'disetujui',

        get isMandatory() {
            return this.keputusan === 'ditolak';
        },
    };
}

export function verifikasiKeputusanWadir2() {
    return {
        keputusan: 'disetujui',

        get isMandatory() {
            return this.keputusan === 'ditolak' || this.keputusan === 'revisi';
        },
    };
}

export function ppkPersetujuanDokumen() {
    return {
        keputusan: 'disetujui',
    };
}
