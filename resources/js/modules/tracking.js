/**
 * Informasi File
 *
 * Penulis   : DendyRafi, Rizlrad Fz
 * Lisensi   : GNU General Public License v3.0 (GPL-3.0) 
 * Ringkasan : Modul ini kami bangun untuk mengelola state interaktif split view pelacakan
 *             permohonan pengadaan, filter daftar pengajuan, garis progres 8 siklus PBJ, generator
 *             timeline riwayat, serta empat modal interaktif (pratinjau dokumen, buat usulan baru,
 *             revisi multi-dokumen, dan konfirmasi penghapusan permohonan).
 */

import { parseJsonDataset } from './ui_helpers.js';

/**
 * Mengelola seluruh state split-view, penyaringan, timeline, dan modal pada halaman pelacakan permohonan.
 *
 * Tujuan: Kami menyatukan pengelolaan berkas pengajuan dan pratinjau alur 8 siklus dalam satu
 * komponen Alpine agar perpindahan antar surat berjalan instan di sisi klien tanpa reload halaman.
 * Parameter: `{Array<Object>|null} [initialItems=null]` - Daftar awal item permohonan (opsional jika lewat `data-items`).
 * Nilai kembali: Objek state, getter reaktif, dan handler modal pelacakan untuk Alpine.js.
 * Contoh pemanggilan: `<div x-data="trackingManager()" data-items="{{ Js::from($items) }}">`
 */
export function trackingManager(initialItems = null) {
    const isArray = Array.isArray(initialItems);
    const itemsList = isArray ? initialItems : [];

    return {
        viewMode: 'split', // 'split' | 'fullscreen'
        selectedItem: null,
        revisiTarget: null,
        hapusTarget: null,
        currentUserId: 0,
        canViewAll: false,
        searchQuery: '',
        filterStatus: isArray ? 'Semua' : '',
        filterJenis: 'Semua',
        filterTanggal: '',
        items: itemsList,

        get filteredList() {
            return (this.items || []).filter(item => {
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase().trim();
                    const no = String(item.nomor_surat || item.nomor || '').toLowerCase();
                    const judul = String(item.judul || item.perihal || item.judul_pengadaan || '').toLowerCase();
                    const pemohon = String(item.pemohon || '').toLowerCase();
                    if (!no.includes(q) && !judul.includes(q) && !pemohon.includes(q)) {
                        return false;
                    }
                }

                if (this.filterStatus && this.filterStatus !== 'Semua') {
                    const s = String(item.statusRaw || item.status_permohonan || item.status || '').toLowerCase();
                    const f = String(this.filterStatus).toLowerCase();
                    if (f === 'reviu_paralel') {
                        if (!['reviu_paralel', 'menunggu_bidang_2', 'cek_mak_perencanaan', 'menunggu_acc_wadir_ppk', 'pending_anggaran', 'hold'].includes(s)) return false;
                    } else if (f === 'disetujui') {
                        if (!['disetujui', 'diproses_ppbj', 'disetujui_wadir2_dan_ppk', 'disetujui_ppk_siap_belanja_pp'].includes(s)) return false;
                    } else if (f === 'perlu_revisi' || f === 'revisi') {
                        if (!['perlu_revisi', 'revisi'].includes(s)) return false;
                    } else if (s !== f) {
                        return false;
                    }
                }

                if (this.filterJenis && this.filterJenis !== 'Semua') {
                    const j = String(item.jenis || item.jenis_pengadaan || '').toLowerCase();
                    if (!j.includes(String(this.filterJenis).toLowerCase())) {
                        return false;
                    }
                }

                if (this.filterTanggal) {
                    const t = String(item.tanggal_pengajuan || item.tanggal || '');
                    if (!t.startsWith(this.filterTanggal)) {
                        return false;
                    }
                }

                return true;
            });
        },

        resetFilters() {
            this.searchQuery = '';
            this.filterStatus = 'Semua';
            this.filterJenis = 'Semua';
            this.filterTanggal = '';
        },

        getStatusBadgeClass(statusRaw) {
            return this.statusMeta(statusRaw);
        },

        // ==================== MODAL 1: PRATINJAU DOKUMEN (715:6528 & 744:7446) ====================
        showPreviewModal: false,
        showModalDoc: false,
        activePreviewPage: 1, // 1: Halaman 1 (Surat Pengantar), 2: Halaman 2 (Lampiran Rincian)
        previewDocData: {
            isRevision: false,
            nomor: '',
            title: 'Surat Permohonan Pengadaan.pdf',
            note: '',
            pages: 2,
            size: '245 KB',
            unit: 'Jurusan Teknik Informatika dan Komputer',
            judul: 'Pengadaan Laptop untuk Laboratorium TI',
            tujuan: 'Mendukung praktikum dan proses belajar mengajar mahasiswa',
            nominal: 150000000,
            nominalFormatted: 'Rp 150.000.000',
            pemohon: 'Unit Pengusul',
            tanggal: '16 Januari 2026',
            url: '#',
            downloadUrl: '#',
            target: null,
        },

        // ==================== MODAL 2: BUAT PERMOHONAN BARU (737:7343 & 737:7339) ====================
        createFiles: [], // Multi Dokumen: array of { id, name, size, formattedSize, file, pageCount }
        createNominal: '',
        isDraggingCreate: false,
        createFileError: '',
        maxCreateFiles: 5,
        maxCreateTotalSizeMB: 15,

        // ==================== MODAL 3: REVISI PERMOHONAN (744:7706 & 744:7705) ====================
        revisiExistingFiles: [], // Multi Dokumen Berkas Lama: array of { id, name, formattedSize, pageCount, isExisting }
        revisiNewFiles: [], // Multi Dokumen Berkas Baru: array of { id, name, size, formattedSize, file }
        revisiNominal: '',

        // ==================== MODAL 4: HAPUS PENGAJUAN (744:7604) ====================
        hapusPassword: '',
        showHapusPassword: false,

        tahapanList: [
            'Pengajuan',
            'Pemeriksaan & Persetujuan Awal',
            'Pembuatan Paket Pengadaan',
            'Reviu & Persetujuan Paket',
            'Kontrak & Pembelian',
            'Penerimaan Barang/Jasa',
            'Pembayaran & Pencairan',
            'Selesai'
        ],

        init() {
            const dataset = this.$el?.dataset || {};
            if (!initialItems && dataset.items) {
                const parsed = parseJsonDataset(this.$el, 'items', []);
                if (Array.isArray(parsed)) {
                    this.items = parsed;
                }
            }

            if (dataset.currentUserId !== undefined) {
                this.currentUserId = Number(dataset.currentUserId || 0) || 0;
            }
            if (dataset.canViewAll !== undefined) {
                this.canViewAll = dataset.canViewAll === 'true' || dataset.canViewAll === '1';
            }

            // memulihkan nilai input filter server-side ke state Alpine agar ketikan pencarian & tanggal tidak terhapus saat hydrate.
            const urlParams = new URLSearchParams(window.location.search);
            const initialQ = dataset.initialQ ?? urlParams.get('q') ?? urlParams.get('keyword') ?? '';
            if (initialQ !== '') {
                this.searchQuery = initialQ;
            }

            const initialStatus = dataset.initialStatus ?? urlParams.get('status') ?? dataset.defaultStatus ?? '';
            if (initialStatus !== '') {
                this.filterStatus = initialStatus;
            }

            const initialJenis = dataset.initialJenis ?? urlParams.get('jenis') ?? urlParams.get('type') ?? 'Semua';
            if (initialJenis !== '') {
                this.filterJenis = initialJenis;
            }

            const initialTanggal = dataset.initialTanggal ?? urlParams.get('tanggal') ?? '';
            if (initialTanggal !== '') {
                this.filterTanggal = initialTanggal;
            }

            // memeriksa apakah terdapat query parameter `selected` di URL.
            const selectedParam = urlParams.get('selected');
            if (selectedParam) {
                const found = this.items.find(i => (i.nomor_surat || i.nomor || String(i.id)) === selectedParam);
                if (found) {
                    this.selectItem(found);
                }
            }
            // CATATAN: By default jangan otomatis select item agar Empty State
            // 'Pilih Pengajuan untuk Melihat Detail' tampil kepada pengguna ketika belum memilih berkas.
        },

        /**
         * Menentukan apakah tombol revisi boleh tampil untuk item terpilih berdasarkan kepemilikan & status revisi.
         *
         * @param  {Object|null} item  Data permohonan pengadaan aktif.
         * @return {boolean} True jika item berstatus ditolak/perlu revisi dan dimiliki pengguna atau punya view_all.
         *
         * Contoh pemanggilan: `x-show="canReviseItem(selectedItem)"`
         */
        canReviseItem(item) {
            if (!item) return false;
            const st = String(item.statusRaw || item.status_permohonan || item.status || '').toLowerCase().trim();
            if (!['ditolak', 'revisi', 'perlu_revisi'].includes(st)) return false;
            return this.canViewAll || (this.currentUserId > 0 && Number(item.id_user || 0) === this.currentUserId);
        },

        /**
         * Menentukan apakah pengguna berhak membatalkan sendiri permohonan berstatus awal atau ditolak.
         *
         * @param  {Object|null} item  Data permohonan pengadaan aktif.
         * @return {boolean} True jika item berada pada status yang masih dapat dibatalkan oleh pembuatnya.
         *
         * Contoh pemanggilan: `x-if="canCancelOwnItem(selectedItem)"`
         */
        canCancelOwnItem(item) {
            if (!item) return false;
            const st = String(item.statusRaw || item.status_permohonan || item.status || '').toLowerCase().trim();
            if (!['draft', 'diajukan', 'ditolak', 'perlu_revisi'].includes(st)) return false;
            return this.canViewAll || (this.currentUserId > 0 && Number(item.id_user || 0) === this.currentUserId);
        },

        /**
         * Merakit URL halaman detail permohonan secara aman tanpa merusak pemisah `/` pada nomor surat.
         *
         * @param  {Object|null} item  Data permohonan pengadaan target.
         * @return {string} URL rute detail permohonan yang aman di-encode per segmen.
         *
         * Contoh pemanggilan: `:href="detailUrl(selectedItem)"`
         */
        detailUrl(item) {
            if (!item) return '#';
            const nomor = String(item.nomor_surat || item.nomor || item.id || '');
            if (!nomor) return '#';
            return '/permohonan/' + nomor.split('/').map(seg => encodeURIComponent(seg)).join('/');
        },

        // Helper format ukuran berkas
        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 KB';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        /**
         * Memetakan status mentah permohonan/paket dari database menjadi nomor langkah 1..8 pada siklus PBJ.
         *
         * Tujuan: Kami menyatukan variasi status historis dan paralel ke 8 tonggak siklus baku agar
         * progress stepper serta timeline konsisten terbaca oleh pengusul maupun pimpinan.
         * Parameter: `{string} statusRaw` - Kode status aktif permohonan atau paket.
         * Nilai kembali: `{number}` Nomor tahap integer antara 1 sampai 8.
         * Contoh pemanggilan: `const step = this.getStepNumber(item.status_permohonan);`
         */
        getStepNumber(statusRaw) {
            const s = String(statusRaw || '').toLowerCase().trim();
            if (['diajukan', 'draft', 'pending'].includes(s)) return 1;
            if (['reviu_paralel', 'verifikasi', 'telaah', 'pending_anggaran', 'hold', 'perlu_revisi', 'revisi', 'ditolak', 'menunggu_bidang_2', 'cek_mak_perencanaan', 'menunggu_acc_wadir_ppk'].includes(s)) return 2;
            if (['penyusunan_dokumen', 'anggaran', 'disetujui', 'diproses_ppbj', 'paket_terdaftar', 'disetujui_wadir2_dan_ppk', 'terdaftar'].includes(s)) return 3;
            if (['persetujuan_2', 'review_direktur', 'reviu_dokumen', 'reviu_dokumen_disetujui', 'menunggu_approval_ppk', 'menunggu_reviu_pp', 'menunggu_reviu_ppk', 'perlu_perbaikan'].includes(s)) return 4;
            if (['pembelian', 'surat_pesanan', 'kontrak', 'spk_terbit', 'belanja_langsung', 'belanja_langsung_selesai', 'surat_pesanan_terbit', 'surat_kontrak_terbit', 'disetujui_ppk_siap_belanja_pp', 'siap_upload_dokumen_ppbj'].includes(s)) return 5;
            if (['penerimaan', 'penerimaan_pending', 'bapp', 'bast', 'pengingat_h2', 'pengiriman', 'pemeriksaan_fisik', 'proses_penerimaan'].includes(s)) return 6;
            if (['pembayaran', 'spm', 'sp2d', 'bast_terbit', 'penerimaan_selesai', 'pengajuan_pembayaran', 'reviu_memo', 'perintah_bayar', 'spp_terbit', 'menunggu_pembayaran', 'menunggu_memo'].includes(s)) return 7;
            if (['selesai', 'sp2d_lunas', 'lunas', 'cair', 'pencairan'].includes(s)) return 8;
            return 1;
        },

        /**
         * Memilih satu berkas permohonan ke panel detail kanan dan menormalisasi metadata tampilannya.
         *
         * Tujuan: Kami mereset pilihan menjadi null bila item kosong agar UI menampilkan empty state,
         * serta menyeragamkan variasi nama atribut objek (nomor vs nomor_surat, perihal vs judul).
         * Parameter: `{Object|null} item` - Objek berkas permohonan atau null untuk membersihkan detail.
         * Nilai kembali: Tidak mengembalikan nilai (memperbarui `this.selectedItem`).
         * Contoh pemanggilan: `@click="selectItem(item)"`
         */
        selectItem(item) {
            if (!item) {
                this.selectedItem = null;
                return;
            }
            const status = item.status_permohonan || item.status || item.statusRaw || 'diajukan';
            const determinedTahap = item.currentTahap || item.current_tahap || this.getStepNumber(status);
            this.selectedItem = Object.assign({}, item, {
                id: item.id,
                nomor: item.nomor_surat || item.nomor,
                nomor_surat: item.nomor_surat || item.nomor,
                judul: item.perihal || item.judul_pengadaan || item.judul,
                perihal: item.perihal || item.judul_pengadaan || item.judul,
                tujuan_pengadaan_ringkas: item.tujuan_pengadaan_ringkas || item.perihal || item.judul_pengadaan,
                statusRaw: status,
                pemohon: item.pemohon || (item.unit ? item.unit.nama_unit : (item.kode_unit || 'Unit Pengusul')),
                tanggal: item.tanggal || (item.tanggal_pengajuan ? String(item.tanggal_pengajuan).substring(0, 10) : '-'),
                currentTahap: determinedTahap,
                current_tahap: determinedTahap,
                fileName: item.fileName || (item.file_surat_permohonan ? String(item.file_surat_permohonan).split('/').pop() : 'Surat_Permohonan_Pengadaan.pdf'),
                id_user_ppk_awal: item.id_user_ppk_awal ?? null,
                revisi_ke: item.revisi_ke || 0,
                alasan_revisi: item.alasan_revisi || '',
            });
        },

        /**
         * Menghitung persentase posisi garis progres aktif pada stepper 8 siklus pengadaan.
         *
         * Tujuan: Kami membagi langkah berjalan `(step - 1) / 7 * 100` dengan rentang aman 0..100%
         * karena 8 titik node membentuk 7 segmen penghubung di antara tahap 1 dan tahap 8.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: `{number}` Persentase lebar progres antara 0 dan 100.
         * Contoh pemanggilan: `:style="`width: ${stepPercent()}%`"`
         */
        stepPercent() {
            if (!this.selectedItem) return 0;
            const step = this.selectedItem.currentTahap || 1;
            // Tujuh segmen berada di antara delapan node tahap sehingga pembaginya 7, bukan 8.
            return Math.max(0, Math.min(100, ((step - 1) / 7) * 100));
        },

        detailStepPercent() {
            return this.stepPercent();
        },

        // Metadata Style Status (Figma Tone)
        statusMeta(raw) {
            const key = String(raw || '').toLowerCase().trim().replace(/[\s-]+/g, '_');
            const tone = {
                sky:     { circle: 'bg-sky-100 text-sky-600',     pill: 'bg-sky-100 text-sky-700',     icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' },
                amber:   { circle: 'bg-amber-100 text-amber-600', pill: 'bg-amber-100 text-amber-700', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
                emerald: { circle: 'bg-emerald-100 text-emerald-600', pill: 'bg-emerald-100 text-emerald-700', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' },
                orange:  { circle: 'bg-orange-100 text-orange-600',   pill: 'bg-orange-100 text-orange-700',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>' },
                rose:    { circle: 'bg-rose-100 text-rose-600',       pill: 'bg-rose-100 text-rose-700',       icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' },
            };
            const map = {
                diajukan:                { label: 'Diajukan',                     t: 'sky' },
                menunggu_bidang_2:       { label: 'Usulan Masuk',                 t: 'amber' },
                cek_mak_perencanaan:     { label: 'Cek Anggaran Perencanaan',     t: 'amber' },
                menunggu_acc_wadir_ppk:  { label: 'Menunggu Persetujuan',         t: 'amber' },
                reviu_paralel:           { label: 'Pemeriksaan Paralel',          t: 'amber' },
                diproses_ppbj:           { label: 'Menunggu Unit Pengadaan',      t: 'amber' },
                paket_terdaftar:         { label: 'Paket Terdaftar',              t: 'amber' },
                diproses:                { label: 'Dalam Proses',                 t: 'amber' },
                disetujui:               { label: 'Disetujui',                    t: 'emerald' },
                selesai:                 { label: 'Selesai',                      t: 'emerald' },
                revisi:                  { label: 'Perlu Revisi',                 t: 'orange' },
                perlu_revisi:            { label: 'Perlu Revisi',                 t: 'orange' },
                ditolak:                 { label: 'Ditolak',                      t: 'rose' },
            };
            const m = map[key] || {
                label: key ? key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'Diajukan',
                t: 'sky'
            };
            return Object.assign({ label: m.label }, tone[m.t]);
        },

        // Helper Icon & Dot Timeline
        timelineDot(type) {
            return ({
                warning: 'bg-amber-500 text-white',
                danger:  'bg-rose-500 text-white',
                info:    'bg-[#0969da] text-white',
                success: 'bg-emerald-500 text-white',
                gray:    'bg-slate-400 text-white'
            })[type] || 'bg-[#0969da] text-white';
        },

        timelineIcon(type) {
            if (type === 'danger')  return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
            if (type === 'warning') return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
            if (type === 'success') return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
            return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>';
        },

        badgeClass(type) {
            return ({
                warning: 'bg-amber-100 text-amber-700 border border-amber-200',
                danger:  'bg-rose-100 text-rose-700 border border-rose-200',
                info:    'bg-blue-100 text-[#0969da] border border-blue-200',
                success: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                gray:    'bg-slate-100 text-slate-700 border border-slate-200'
            })[type] || 'bg-slate-100 text-slate-700 border border-slate-200';
        },

        /**
         * Menyusun daftar node timeline riwayat pengajuan berdasarkan mode revisi atau progres normal.
         *
         * Tujuan: Kami menampilkan alur terbaru-ke-terlama yang langsung menyorot catatan revisi atau
         * tahap pencairan berjalan tanpa membuat pengusul menebak di posisi mana berkasnya tertahan.
         * Parameter: `{Object|null} item` - Berkas permohonan yang sedang dipilih di panel detail.
         * Nilai kembali: `{Array<Object>}` Array entri timeline siap render dengan warna dot dan lencana.
         * Contoh pemanggilan: `<template x-for="node in generateTimeline(selectedItem)">`
         */
        generateTimeline(item) {
            if (!item) return [];
            const list = [];
            const statusKey = String(item.statusRaw || item.status_permohonan || item.status || '').toLowerCase();
            const revNum = item.revisi_ke || 0;
            const tanggalStr = item.tanggal || '16 Jan 2026';
            const pengusulNama = item.user?.nama || item.user?.name || item.pemohon || 'Unit Pengusul';
            const judulStr = item.perihal || item.judul_pengadaan || item.judul || 'Pengadaan Barang/Jasa';

            const isRevisiState = ['ditolak', 'perlu_revisi', 'revisi'].includes(statusKey);

            if (isRevisiState) {
                // ==========================================
                // TIMELINE REVISI (Figma 506:1026 & 506:1335)
                // ==========================================
                // 1. Form perlu direvisi
                list.push({
                    id: 1,
                    time: tanggalStr,
                    title: 'Form perlu direvisi',
                    subtitle: item.alasan_revisi ? 'Catatan: ' + item.alasan_revisi : 'Catatan: file tidak dapat dilihat',
                    badge: 'Perlu Revisi',
                    badge_type: 'warning',
                    type: 'warning',
                    revisi: 'Revisi ' + revNum
                });

                // 2. Perencanaan menolak form pengajuan
                list.push({
                    id: 2,
                    time: tanggalStr + ' • 15:00',
                    title: 'Perencanaan menolak form pengajuan',
                    subtitle: 'Pengajuan ditolak',
                    badge: 'Ditolak',
                    badge_type: 'danger',
                    type: 'danger',
                    revisi: 'Revisi ' + revNum
                });

                // 3. Sedang memeriksa
                list.push({
                    id: 3,
                    time: tanggalStr + ' • 11:00',
                    title: 'Bagian Perencanaan sedang memeriksa pos anggaran usulan',
                    subtitle: 'Pemeriksaan kelengkapan berkas, pos anggaran, dan penetapan PPK',
                    badge: 'Informasi',
                    badge_type: 'info',
                    type: 'info',
                    revisi: 'Revisi ' + revNum
                });

                // 4. Form berhasil disampaikan
                list.push({
                    id: 4,
                    time: tanggalStr + ' • 11:00',
                    title: 'Form berhasil disampaikan',
                    subtitle: 'Bagian Perencanaan berhasil menerima notifikasi pengajuan',
                    badge: 'Sistem',
                    badge_type: 'info',
                    type: 'info',
                    revisi: 'Revisi ' + revNum
                });

                // 5. Pengusul melakukan pengajuan
                list.push({
                    id: 5,
                    time: tanggalStr + ' • 11:00',
                    title: pengusulNama + ' melakukan pengajuan',
                    subtitle: 'Pengajuan ' + judulStr,
                    badge: 'Informasi',
                    badge_type: 'info',
                    type: 'info',
                    revisi: 'Revisi ' + revNum
                });

            } else {
                // ==========================================
                // TIMELINE BUKAN REVISI (Figma 498:4973 & 465:8991)
                // ==========================================
                const currentStep = Number(item.currentTahap || item.current_tahap || this.getStepNumber(statusKey));

                // Tahap 8: Selesai / Pembayaran
                if (currentStep >= 8) {
                    list.push({
                        id: 8,
                        time: tanggalStr + ' • 16:30',
                        title: 'Pencairan dana Pembayaran oleh Bagian Keuangan',
                        subtitle: 'Pengadaan telah selesai tuntas seluruh siklus',
                        badge: 'Selesai',
                        badge_type: 'success',
                        type: 'success',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 7: Pembayaran & Serah Terima
                if (currentStep >= 7) {
                    list.push({
                        id: 7,
                        time: tanggalStr + ' • 16:00',
                        title: 'Bukti serah terima diterbitkan dan verifikasi tagihan oleh Keuangan',
                        subtitle: 'Penerbitan bukti terima oleh Unit Perlengkapan',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 6: Penerimaan Barang/Jasa
                if (currentStep >= 6) {
                    list.push({
                        id: 6,
                        time: tanggalStr + ' • 15:30',
                        title: 'Pemeriksaan fisik barang dan serah terima oleh Perlengkapan',
                        subtitle: 'Verifikasi fisik barang di lokasi pengiriman',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 5: Pembelian & Kontrak
                if (currentStep >= 5) {
                    list.push({
                        id: 5,
                        time: tanggalStr + ' • 15:15',
                        title: 'Dokumen kontrak atau kwitansi resmi diterbitkan',
                        subtitle: 'Penerbitan dokumen pembelian oleh Unit Pengadaan',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 4: Reviu Dokumen & Perkiraan Harga
                if (currentStep >= 4) {
                    list.push({
                        id: 4,
                        time: tanggalStr + ' • 15:10',
                        title: 'Pemeriksaan berkas teknis dan perkiraan harga oleh Pejabat Pengadaan / PPK',
                        subtitle: 'Evaluasi kesesuaian harga dan spesifikasi teknis',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 3: Unit Pengadaan Penyusunan Paket
                if (currentStep >= 3) {
                    list.push({
                        id: 3,
                        time: tanggalStr + ' • 15:00',
                        title: 'Pembuatan paket dan penentuan jalur pengadaan',
                        subtitle: 'Pengajuan ' + judulStr,
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 2: Persetujuan 1 Disetujui
                if (['disetujui', 'selesai', 'diproses_ppbj', 'paket_terdaftar'].includes(statusKey) || currentStep >= 3) {
                    list.push({
                        id: 2,
                        time: tanggalStr + ' • 15:00',
                        title: 'Perencanaan, Wadir II, dan PPK menyetujui usulan',
                        subtitle: 'Persetujuan lengkap; berkas diteruskan ke Unit Pengadaan',
                        badge: 'Disetujui',
                        badge_type: 'success',
                        type: 'success',
                        revisi: 'Revisi ' + revNum
                    });
                } else if (statusKey === 'menunggu_acc_wadir_ppk') {
                    list.push({
                        id: 202,
                        time: tanggalStr + ' • 14:00',
                        title: 'Cek anggaran selesai, menunggu persetujuan Wadir II & PPK',
                        subtitle: 'Memo ketersediaan anggaran telah diterbitkan oleh Perencanaan',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 2: Sedang memeriksa
                if (currentStep >= 2) {
                    list.push({
                        id: 201,
                        time: tanggalStr + ' • 11:00',
                        title: 'Bagian Perencanaan sedang memeriksa usulan & pos anggaran',
                        subtitle: 'Pemeriksaan kelengkapan berkas, ketersediaan anggaran, dan penetapan PPK',
                        badge: 'Informasi',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 1: Form berhasil disampaikan
                list.push({
                    id: 101,
                    time: tanggalStr + ' • 11:00',
                    title: 'Form berhasil disampaikan',
                    subtitle: 'Bagian Perencanaan berhasil menerima notifikasi pengajuan',
                    badge: 'Sistem',
                    badge_type: 'info',
                    type: 'info',
                    revisi: 'Revisi ' + revNum
                });

                // Tahap 1: Pengusul melakukan pengajuan
                list.push({
                    id: 100,
                    time: tanggalStr + ' • 11:00',
                    title: pengusulNama + ' melakukan pengajuan',
                    subtitle: 'Pengajuan ' + judulStr,
                    badge: 'Informasi',
                    badge_type: 'info',
                    type: 'info',
                    revisi: 'Revisi ' + revNum
                });
            }

            return list;
        },

        /**
         * Membentuk URL pratinjau dokumen PDF surat permohonan melalui rute `/dokumen-pdf/permohonan/{id}`.
         *
         * Tujuan: Kami mengembalikan tanda slash asli (`%2F` -> `/`) setelah `encodeURIComponent` agar
         * nomor surat dinas PNJ yang mengandung slash tetap cocok dengan parameter wildcard rute Laravel.
         * Parameter: `{Object|null} item` - Objek berkas permohonan sumber nomor surat.
         * Nilai kembali: `{string}` URL relatif menuju dokumen PDF atau `#` jika item kosong.
         * Contoh pemanggilan: `:href="pdfUrl(selectedItem)"`
         */
        pdfUrl(item) {
            if (!item) return '#';
            // Nomor surat memakai '/', sehingga %2F kembalikan ke '/' untuk wildcard route Laravel.
            const id = encodeURIComponent(item.nomor_surat || item.nomor || item.id).replace(/%2F/gi, '/');
            return '/dokumen-pdf/permohonan/' + id;
        },

        /**
         * Memicu modal pratinjau dokumen PDF utama untuk item tertentu atau item terpilih saat ini.
         *
         * Tujuan: Kami melempar CustomEvent `open-preview-doc` agar modal PDF global di root layout
         * menampilkan dokumen terkait secara seragam.
         * Parameter: `{Object|null} [item]` - Objek berkas opsional; default ke `selectedItem`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openPreview(selectedItem)"`
         */
        openPreview(item) {
            const target = item || this.selectedItem;
            if (!target) return;
            const url = this.pdfUrl(target);
            const title = target.fileName || (target.file_surat_permohonan ? String(target.file_surat_permohonan).split('/').pop() : 'Surat_Permohonan.pdf');
            window.dispatchEvent(new CustomEvent('open-preview-doc', {
                detail: {
                    title: title,
                    url: url,
                    size: target.fileSize || 'PDF'
                }
            }));
        },

        openDocPreview(doc) {
            if (!doc) return;
            const docUrl = doc.url || (doc.path ? '/storage/' + doc.path : '#');
            window.dispatchEvent(new CustomEvent('open-preview-doc', {
                detail: {
                    title: doc.name || 'Dokumen.pdf',
                    url: docUrl,
                    size: doc.size || 'PDF'
                }
            }));
        },

        closePreview() {
            window.dispatchEvent(new CustomEvent('close-preview-doc'));
        },

        triggerRevisiFromPreview() {
            const target = this.selectedItem;
            this.closePreview();
            this.openRevisiModal(target);
        },

        // ==================== MODAL 2: BUAT PERMOHONAN BARU (737:7343 & 737:7339) ====================
        openModalCreate() {
            this.openCreateModal();
        },

        /**
         * Membuka modal pengajuan permohonan baru dan membersihkan state berkas unggahan sebelumnya.
         *
         * Tujuan: Kami mereset antrean `createFiles`, nominal, dan input DOM sebelum mengirim sinyal
         * buka modal agar sisa berkas percobaan sebelumnya tidak ikut terbawa saat pengajuan baru.
         * Parameter: Tidak menerima argumen.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openCreateModal()"`
         */
        openCreateModal() {
            this.createFiles = [];
            this.createNominal = '';
            this.createFileError = '';
            this.isDraggingCreate = false;
            const input = document.getElementById('input-file-create');
            if (input) input.value = '';
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-create-permohonan' }));
        },

        handleCreateFilesDrop(event) {
            const files = event.dataTransfer?.files;
            if (files && files.length > 0) {
                this.processIncomingCreateFiles(files);
            }
        },

        onCreateFilesChange(event) {
            if (event.target.files && event.target.files.length > 0) {
                this.processIncomingCreateFiles(event.target.files);
            }
        },

        /**
         * Memvalidasi batch file unggahan baru (maksimal 5 file, total 15 MB) dan menyinkronkannya ke input.
         *
         * Tujuan: Kami menegakkan batasan kapasitas di sisi klien agar pengusul langsung menerima pesan
         * kesalahan bila lampiran terlalu banyak atau terlalu besar sebelum form dikirim ke server.
         * Parameter: `{FileList|Array<File>} incomingList` - Kumpulan objek File pilihan/drop pengguna.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `this.processIncomingCreateFiles(files);`
         */
        processIncomingCreateFiles(incomingList) {
            this.createFileError = '';
            const incomingFiles = Array.from(incomingList);

            if (this.createFiles.length + incomingFiles.length > this.maxCreateFiles) {
                this.createFileError = `Maksimal hanya dapat mengunggah ${this.maxCreateFiles} berkas.`;
                return;
            }

            const currentTotalBytes = this.createFiles.reduce((acc, f) => acc + (f.size || 0), 0);
            const incomingTotalBytes = incomingFiles.reduce((acc, f) => acc + (f.size || 0), 0);
            const totalMB = (currentTotalBytes + incomingTotalBytes) / (1024 * 1024);

            if (totalMB > this.maxCreateTotalSizeMB) {
                this.createFileError = `Total ukuran seluruh berkas melebihi batas ${this.maxCreateTotalSizeMB} MB.`;
                return;
            }

            incomingFiles.forEach((file, idx) => {
                this.createFiles.push({
                    id: Date.now() + '_' + idx + '_' + Math.random().toString(36).substring(2, 7),
                    name: file.name,
                    size: file.size,
                    formattedSize: this.formatFileSize(file.size),
                    file: file,
                    pageCount: 2,
                });
            });

            this.syncCreateFileInput();
        },

        syncCreateFileInput() {
            const input = document.getElementById('input-file-create');
            if (input) {
                const dt = new DataTransfer();
                this.createFiles.forEach(f => {
                    if (f.file) dt.items.add(f.file);
                });
                input.files = dt.files;
            }
        },

        removeCreateFile(index) {
            this.createFiles.splice(index, 1);
            this.createFileError = '';
            this.syncCreateFileInput();
        },

        previewLocalCreateFile(fileItem) {
            if (!fileItem) return;
            const ext = (fileItem.type || fileItem.name.split('.').pop() || '').toUpperCase();
            if (ext !== 'PDF') {
                return;
            }
            const blobUrl = fileItem.file ? URL.createObjectURL(fileItem.file) : (fileItem.url || '#');
            window.dispatchEvent(new CustomEvent('open-preview-doc', {
                detail: {
                    title: fileItem.name,
                    url: blobUrl,
                    size: fileItem.formattedSize || 'PDF'
                }
            }));
        },

        /**
         * Membuka modal revisi permohonan sekaligus memuat daftar dokumen eksisting milik berkas target.
         *
         * Tujuan: Kami menyalin metadata berkas surat dan KAK sebelumnya serta nominal awal pengajuan
         * agar pengusul dapat meninjau dokumen lama sambil menambahkan berkas revisi baru.
         * Parameter: `{Object|null} [item]` - Berkas target revisi; default ke `selectedItem`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openRevisiModal(selectedItem)"`
         */
        openRevisiModal(item) {
            this.revisiTarget = item || this.selectedItem;
            this.revisiNewFiles = [];
            this.revisiExistingFiles = [];

            if (this.revisiTarget) {
                const fn = this.revisiTarget.fileName || 'Surat Permohonan Pengadaan.pdf';
                this.revisiExistingFiles.push({
                    id: 'existing_1',
                    name: fn,
                    formattedSize: '245 KB',
                    pageCount: 2,
                    isExisting: true,
                });
                if (this.revisiTarget.file_kak) {
                    this.revisiExistingFiles.push({
                        id: 'existing_kak',
                        name: String(this.revisiTarget.file_kak).split('/').pop(),
                        formattedSize: '410 KB',
                        pageCount: 3,
                        isExisting: true,
                    });
                }
                this.revisiNominal = this.revisiTarget.anggaran_diajukan || this.revisiTarget.estimasi_total_pagu || '';
            }
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-revisi-permohonan' }));
        },

        removeExistingRevisiFile(index) {
            this.revisiExistingFiles.splice(index, 1);
        },

        onRevisiFilesChange(event) {
            if (event.target.files && event.target.files.length > 0) {
                Array.from(event.target.files).forEach((file, idx) => {
                    this.revisiNewFiles.push({
                        id: Date.now() + '_' + idx + '_' + Math.random().toString(36).substring(2, 7),
                        name: file.name,
                        size: file.size,
                        formattedSize: this.formatFileSize(file.size),
                        file: file,
                        pageCount: 2,
                    });
                });
            }
        },

        removeRevisiNewFile(index) {
            this.revisiNewFiles.splice(index, 1);
            if (this.revisiNewFiles.length === 0) {
                const input = document.getElementById('input-file-revisi');
                if (input) input.value = '';
            }
        },

        previewRevisiExistingFile(fileItem) {
            if (!fileItem) return;
            const url = this.revisiTarget ? this.pdfUrl(this.revisiTarget) : '#';
            window.dispatchEvent(new CustomEvent('open-preview-doc', {
                detail: {
                    title: fileItem.name || 'Dokumen.pdf',
                    url: url,
                    size: fileItem.formattedSize || 'PDF'
                }
            }));
        },

        /**
         * Membuka modal konfirmasi penghapusan pengajuan sambil mereset input kata sandi konfirmasi.
         *
         * Tujuan: Kami membersihkan `hapusPassword` setiap dialog hapus dibuka agar sandi sebelumnya
         * tidak tersisa di memori input modal.
         * Parameter: `{Object|null} [item]` - Berkas target penghapusan; default ke `selectedItem`.
         * Nilai kembali: Tidak mengembalikan nilai.
         * Contoh pemanggilan: `@click="openHapusModal(selectedItem)"`
         */
        openHapusModal(item) {
            this.hapusTarget = item || this.selectedItem;
            this.hapusPassword = '';
            this.showHapusPassword = false;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-delete-permohonan' }));
        },

        toggleHapusPassword() {
            this.showHapusPassword = !this.showHapusPassword;
        }
    };
}
