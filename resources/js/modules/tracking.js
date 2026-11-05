/**
 * Tracking Manager Module (resources/js/modules/tracking.js)
 *
 * Mengelola state interaktif split view pelacakan permohonan pengadaan,
 * pemilihan berkas aktif, mode layar penuh (fullscreen detail), timeline 8 siklus,
 * dan 4 modal interaktif berbasis 12 desain Figma:
 * 1. Modal Pratinjau Dokumen (715:6528 & 744:7446)
 * 2. Modal Revisi Permohonan Multi-Dokumen (744:7706 & 744:7705)
 * 3. Modal Buat Permohonan Baru Multi-Dokumen (737:7343 & 737:7339)
 * 4. Modal Hapus Pengajuan (744:7604)
 */

export function trackingManager(initialItems = null) {
    const isArray = Array.isArray(initialItems);
    const itemsList = isArray ? initialItems : [];

    return {
        viewMode: 'split', // 'split' | 'fullscreen'
        selectedItem: null,
        revisiTarget: null,
        hapusTarget: null,
        searchQuery: '',
        filterStatus: isArray ? 'Semua' : '',
        filterJenis: 'Semua',
        filterTanggal: '',
        items: itemsList,

        // ==================== MODAL 1: PRATINJAU DOKUMEN (715:6528 & 744:7446) ====================
        showPreviewModal: false,
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
            pemohon: 'Riyan Sohibul Anam',
            tanggal: '16 Januari 2026',
            url: '#',
            downloadUrl: '#',
            target: null,
        },

        // ==================== MODAL 2: BUAT PERMOHONAN BARU (737:7343 & 737:7339) ====================
        createFiles: [], // Multi Dokumen: array of { id, name, size, formattedSize, file, pageCount }
        createNominal: '',

        // ==================== MODAL 3: REVISI PERMOHONAN (744:7706 & 744:7705) ====================
        revisiExistingFiles: [], // Multi Dokumen Berkas Lama: array of { id, name, formattedSize, pageCount, isExisting }
        revisiNewFiles: [], // Multi Dokumen Berkas Baru: array of { id, name, size, formattedSize, file }
        revisiNominal: '',

        // ==================== MODAL 4: HAPUS PENGAJUAN (744:7604) ====================
        hapusPassword: '',
        showHapusPassword: false,

        tahapanList: [
            'Pengajuan',
            'Persetujuan 1',
            'Penyusunan Dokumen dan Penetapan Level Anggaran',
            'Persetujuan 2 (Kondisional)',
            'Pembelian dan Pembuatan Surat Kontrak',
            'Penerimaan Barang/Jasa',
            'Pembayaran',
            'Selesai'
        ],

        init() {
            // Periksa apakah terdapat query parameter `selected` di URL
            const urlParams = new URLSearchParams(window.location.search);
            const selectedParam = urlParams.get('selected');
            if (selectedParam) {
                const found = this.items.find(i => (i.nomor_surat || i.nomor || String(i.id)) === selectedParam);
                if (found) {
                    this.selectItem(found);
                }
            }
            // CATATAN: By default jangan otomatis select item agar Empty State (Figma 455:6666)
            // 'Pilih Pengajuan untuk Melihat Detail' tampil kepada pengguna ketika belum memilih berkas.
        },

        // Helper format ukuran berkas
        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 KB';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        // Pemetaan Siklus 8 Tahap dari Database Status
        getStepNumber(statusRaw) {
            const s = String(statusRaw || '').toLowerCase().trim();
            if (['diajukan', 'draft', 'pending'].includes(s)) return 1;
            if (['reviu_paralel', 'verifikasi', 'telaah', 'pending_anggaran', 'hold', 'perlu_revisi', 'ditolak', 'menunggu_bidang_2', 'cek_mak_perencanaan', 'menunggu_acc_wadir_ppk'].includes(s)) return 2;
            if (['penyusunan_dokumen', 'anggaran', 'disetujui', 'diproses_ppbj', 'paket_terdaftar', 'disetujui_wadir2_dan_ppk'].includes(s)) return 3;
            if (['persetujuan_2', 'review_direktur', 'reviu_dokumen', 'menunggu_approval_ppk', 'disetujui_ppk_siap_belanja_pp', 'siap_upload_dokumen_ppbj', 'menunggu_reviu_pp', 'menunggu_reviu_ppk'].includes(s)) return 4;
            if (['pembelian', 'surat_pesanan', 'kontrak', 'spk_terbit', 'belanja_langsung', 'belanja_langsung_selesai', 'surat_pesanan_terbit', 'surat_kontrak_terbit'].includes(s)) return 5;
            if (['penerimaan', 'bapp', 'bast', 'pengingat_h2', 'pengiriman', 'pemeriksaan_fisik', 'proses_penerimaan'].includes(s)) return 6;
            if (['pembayaran', 'spm', 'sp2d', 'bast_terbit', 'menunggu_pembayaran'].includes(s)) return 7;
            if (['selesai', 'sp2d_lunas', 'cair'].includes(s)) return 8;
            return 1;
        },

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
                revisi_ke: item.revisi_ke || 0,
                alasan_revisi: item.alasan_revisi || '',
            });
        },

        stepPercent() {
            if (!this.selectedItem) return 0;
            const step = this.selectedItem.currentTahap || 1;
            return Math.max(0, Math.min(100, ((step - 1) / 7) * 100));
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
                diajukan:            { label: 'Diajukan',             t: 'sky' },
                reviu_paralel:       { label: 'Dalam Proses',         t: 'amber' },
                diproses:            { label: 'Dalam Proses',         t: 'amber' },
                disetujui:           { label: 'Disetujui',            t: 'emerald' },
                selesai:             { label: 'Selesai',              t: 'emerald' },
                revisi:              { label: 'Perlu Revisi',         t: 'orange' },
                perlu_revisi:        { label: 'Perlu Revisi',         t: 'orange' },
                ditolak:             { label: 'Ditolak',              t: 'rose' },
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

        // Generator Timeline Sesuai Figma 506:1026 (Revisi) dan 498:4973 (Bukan Revisi)
        generateTimeline(item) {
            if (!item) return [];
            const list = [];
            const statusKey = String(item.statusRaw || item.status_permohonan || item.status || '').toLowerCase();
            const revNum = item.revisi_ke || 0;
            const tanggalStr = item.tanggal || '16 Jan 2026';
            const pengusulNama = item.user?.nama || item.user?.name || item.pemohon || 'Riyan Sohibul Anam';
            const judulStr = item.perihal || item.judul_pengadaan || item.judul || 'pengadaan laptop';

            const isRevisiState = ['ditolak', 'perlu_revisi', 'revisi'].includes(statusKey);

            if (isRevisiState) {
                // ==========================================
                // TIMELINE REVISI (Figma 506:1026 & 506:1335)
                // ==========================================
                // 1. Form perlu direvisi
                list.push({
                    id: 1,
                    time: tanggalStr + ' • 15:00 | Batas terakhir: 19 Jan 2026 • 15:00',
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
                    title: 'Perencanaan dan Bidang 2 sedang memeriksa form pengajuan',
                    subtitle: 'Pemeriksaan berkas telaah dan alokasi pagu',
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
                    subtitle: 'Pihak Perencanaan dan Bidang 2 berhasil menerima notifikasi',
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

                // Tahap 8: Selesai / SP2D Lunas
                if (currentStep >= 8) {
                    list.push({
                        id: 8,
                        time: tanggalStr + ' • 16:30',
                        title: 'Pencairan dana SP2D lunas oleh Bagian Keuangan',
                        subtitle: 'Pengadaan telah selesai tuntas seluruh siklus',
                        badge: 'Selesai',
                        badge_type: 'success',
                        type: 'success',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 7: Pembayaran & BAST
                if (currentStep >= 7) {
                    list.push({
                        id: 7,
                        time: tanggalStr + ' • 16:00',
                        title: 'BAST resmi diterbitkan dan verifikasi tagihan oleh Keuangan',
                        subtitle: 'Penerbitan BAST oleh Unit Perlengkapan & BMN',
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

                // Tahap 5: Pembelian & Kontrak SPK
                if (currentStep >= 5) {
                    list.push({
                        id: 5,
                        time: tanggalStr + ' • 15:15',
                        title: 'Dokumen perikatan SPK / Kontrak / Kwitansi resmi diterbitkan',
                        subtitle: 'Penerbitan perikatan pengadaan oleh PPBJ',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 4: Reviu Dokumen & HPS PP/PPK
                if (currentStep >= 4) {
                    list.push({
                        id: 4,
                        time: tanggalStr + ' • 15:10',
                        title: 'Penelaahan berkas teknis dan evaluasi HPS oleh PP/PPK',
                        subtitle: 'Evaluasi kesesuaian harga dan spesifikasi teknis',
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 3: PPBJ Penyusunan Dokumen & MAK
                if (currentStep >= 3) {
                    list.push({
                        id: 3,
                        time: tanggalStr + ' • 15:00',
                        title: 'Penyusunan dokumen dan penetapan level anggaran oleh PPBJ',
                        subtitle: 'Pengajuan ' + judulStr,
                        badge: 'Dalam Proses',
                        badge_type: 'info',
                        type: 'info',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 2: Persetujuan 1 Disetujui
                if (['disetujui', 'selesai'].includes(statusKey) || currentStep >= 3) {
                    list.push({
                        id: 2,
                        time: tanggalStr + ' • 15:00',
                        title: 'Perencanaan dan Bidang 2 menyetujui form pengajuan',
                        subtitle: 'Persetujuan pimpinan telah lengkap',
                        badge: 'Disetujui',
                        badge_type: 'success',
                        type: 'success',
                        revisi: 'Revisi ' + revNum
                    });
                }

                // Tahap 2: Sedang memeriksa
                if (currentStep >= 2) {
                    list.push({
                        id: 201,
                        time: tanggalStr + ' • 11:00',
                        title: 'Perencanaan dan Bidang 2 sedang memeriksa form pengajuan',
                        subtitle: 'Pemeriksaan berkas telaah dan alokasi pagu',
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
                    subtitle: 'Pihak Perencanaan dan Bidang 2 berhasil menerima notifikasi',
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

        // Helper URL PDF (Route resmi Laravel /dokumen-pdf/{jenis}/{id})
        pdfUrl(item) {
            if (!item) return '#';
            const id = encodeURIComponent(item.nomor_surat || item.nomor || item.id).replace(/%2F/gi, '/');
            return '/dokumen-pdf/permohonan/' + id;
        },

        // ==================== MODAL 1: PRATINJAU DOKUMEN (715:6528 & 744:7446) ====================
        openPreview(item, isRevision = false, noteOverride = null) {
            const target = item || this.selectedItem;
            if (!target) return;

            const isRev = isRevision || ['ditolak', 'perlu_revisi', 'revisi'].includes(String(target.statusRaw || target.status_permohonan || target.status || '').toLowerCase());
            const nom = Number(target.anggaran_diajukan || target.estimasi_total_pagu || 150000000);
            const nomorSurat = target.nomor_surat || target.nomor || 'PBJ-2026-0017';

            this.activePreviewPage = 1;
            this.previewDocData = {
                isRevision: isRev,
                nomor: nomorSurat,
                title: target.fileName || (target.file_surat_permohonan ? String(target.file_surat_permohonan).split('/').pop() : 'Surat Permohonan Pengadaan.pdf'),
                note: noteOverride || target.alasan_revisi || 'Catatan revisi: Halaman terpotong, tidak terbaca',
                pages: 2,
                size: target.fileSize || '245 KB',
                unit: (target.unit && target.unit.nama_unit) ? target.unit.nama_unit : (target.pemohon || 'Jurusan Teknik Informatika dan Komputer'),
                judul: target.perihal || target.judul_pengadaan || target.judul || 'Pengadaan Laptop untuk Laboratorium TI',
                tujuan: target.tujuan_pengadaan_ringkas || 'Mendukung praktikum dan proses belajar mengajar mahasiswa',
                nominal: nom,
                nominalFormatted: nom ? 'Rp ' + nom.toLocaleString('id-ID') : 'Rp 150.000.000',
                pemohon: target.user?.nama || target.user?.name || target.pemohon || 'Riyan Sohibul Anam',
                tanggal: target.tanggal || '16 Januari 2026',
                url: this.pdfUrl(target),
                downloadUrl: this.pdfUrl(target) + '?download=1',
                target: target
            };
            this.showPreviewModal = true;
        },

        setPreviewPage(pageNum) {
            this.activePreviewPage = pageNum;
        },

        closePreview() {
            this.showPreviewModal = false;
        },

        triggerRevisiFromPreview() {
            const target = this.previewDocData.target || this.selectedItem;
            this.closePreview();
            this.openRevisiModal(target);
        },

        // ==================== MODAL 2: BUAT PERMOHONAN BARU (737:7343 & 737:7339) ====================
        openModalCreate() {
            this.openCreateModal();
        },

        openCreateModal() {
            this.createFiles = [];
            this.createNominal = '';
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modal-create-permohonan' }));
        },

        onCreateFilesChange(event) {
            if (event.target.files && event.target.files.length > 0) {
                Array.from(event.target.files).forEach((file, idx) => {
                    this.createFiles.push({
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

        removeCreateFile(index) {
            this.createFiles.splice(index, 1);
            if (this.createFiles.length === 0) {
                const input = document.getElementById('input-file-create');
                if (input) input.value = '';
            }
        },

        previewLocalCreateFile(fileItem) {
            this.openPreview({
                nomor_surat: 'DRAFT-' + Date.now(),
                perihal: 'Draft Usulan Baru (' + fileItem.name + ')',
                fileName: fileItem.name,
                fileSize: fileItem.formattedSize,
                statusRaw: 'diajukan',
            }, false);
        },

        // ==================== MODAL 3: REVISI PERMOHONAN (744:7706 & 744:7705) ====================
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
            this.openPreview(this.revisiTarget, true, this.revisiTarget?.alasan_revisi);
        },

        // ==================== MODAL 4: HAPUS PENGAJUAN (744:7604) ====================
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
