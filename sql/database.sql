-- ============================================================================
-- SISTEM INFORMASI MANAJEMEN PENGADAAN BARANG DAN JASA (SIM-PBJ)
-- SUMBER TRUTH: 1Jorking (ERD_ver2.erdplus, FR-NFR Kel 1 v1.2, Sequence, & BPMN)
-- DIALECT: PostgreSQL 14+
-- ============================================================================

-- DROP EXISTING TABLES IN REVERSE DEPENDENCY ORDER
DROP TABLE IF EXISTS audit_log CASCADE;
DROP TABLE IF EXISTS notifikasi_user CASCADE;
DROP TABLE IF EXISTS reminder CASCADE;
DROP TABLE IF EXISTS transaksi_pencairan CASCADE;
DROP TABLE IF EXISTS memo_bayar CASCADE;
DROP TABLE IF EXISTS pembayaran CASCADE;
DROP TABLE IF EXISTS penerimaan_barang_foto_dokumentasi CASCADE;
DROP TABLE IF EXISTS penerimaan_barang CASCADE;
DROP TABLE IF EXISTS kontrak_spk CASCADE;
DROP TABLE IF EXISTS riwayat_penugasan_ppk CASCADE;
DROP TABLE IF EXISTS reviu_dokumen CASCADE;
DROP TABLE IF EXISTS paket_pengadaan CASCADE;
DROP TABLE IF EXISTS alokasi_mak CASCADE;
DROP TABLE IF EXISTS verifikasi_paralel CASCADE;
DROP TABLE IF EXISTS permohonan_pengadaan CASCADE;
DROP TABLE IF EXISTS anggaran_mak CASCADE;
DROP TABLE IF EXISTS vendor_file_legalitas CASCADE;
DROP TABLE IF EXISTS vendors CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS units CASCADE;

-- ============================================================================
-- 1. MASTER UNIT & ORGANISASI
-- ============================================================================
CREATE TABLE units (
    kode_unit VARCHAR(20) PRIMARY KEY,
    nama_unit VARCHAR(150) NOT NULL,
    jenis_unit VARCHAR(50) NOT NULL, -- jurusan, unit, bagian, bidang
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 2. PENGGUNA SISTEM (RBAC: 9 ROLES)
-- ============================================================================
CREATE TABLE users (
    id_user BIGSERIAL PRIMARY KEY,
    nip VARCHAR(30) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    nama VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL, -- user, staff_bidang_2, wadir_2, perencanaan, ppbj, pp, ppk, perlengkapan, keuangan
    status_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    no_hp VARCHAR(20) NOT NULL,
    no_telp_kantor VARCHAR(20),
    kode_unit VARCHAR(20),
    remember_token VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_unit FOREIGN KEY (kode_unit) REFERENCES units (kode_unit) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 3. VENDOR / REKANAN & DOKUMEN LEGALITAS (MULTI-VALUED ATTRIBUTES)
-- ============================================================================
CREATE TABLE vendors (
    npwp VARCHAR(30) PRIMARY KEY,
    nib VARCHAR(30) NOT NULL UNIQUE,
    nama_perusahaan VARCHAR(200) NOT NULL,
    alamat TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    nama_pic VARCHAR(100) NOT NULL,
    telepon_pic VARCHAR(20) NOT NULL,
    email_pic VARCHAR(100) NOT NULL,
    nama_bank VARCHAR(100) NOT NULL,
    nomor_rekening VARCHAR(50) NOT NULL,
    atas_nama VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vendor_file_legalitas (
    id BIGSERIAL PRIMARY KEY,
    npwp VARCHAR(30) NOT NULL,
    nama_dokumen VARCHAR(150) NOT NULL,
    file_legalitas VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_legalitas_vendor FOREIGN KEY (npwp) REFERENCES vendors (npwp) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 4. ANGGARAN MAK (IMMUTABLE LOGIC SAAT SPK TERBIT)
-- ============================================================================
CREATE TABLE anggaran_mak (
    kode_mak VARCHAR(50) PRIMARY KEY,
    uraian_mak VARCHAR(255) NOT NULL,
    pagu_anggaran NUMERIC(15, 2) NOT NULL,
    sisa_pagu_mak NUMERIC(15, 2) NOT NULL DEFAULT 0,
    sumber_dana VARCHAR(100) NOT NULL, -- RM, BLU, BOPTN
    tahun_anggaran INT NOT NULL,
    catatan_mak TEXT,
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    tanggal_kunci TIMESTAMP,
    id_user BIGINT, -- Petugas Perencanaan Penetap MAK
    tanggal_penetapan TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mak_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 5. PERMOHONAN PENGADAAN (UNARY SPLIT & REVISI, METADATA SRIKANDI)
-- ============================================================================
CREATE TABLE permohonan_pengadaan (
    nomor_surat VARCHAR(100) PRIMARY KEY,
    judul_pengadaan VARCHAR(255) NOT NULL,
    tujuan_pengadaan_ringkas TEXT NOT NULL,
    file_permohonan VARCHAR(255) NOT NULL,
    status_permohonan VARCHAR(50) NOT NULL DEFAULT 'diajukan', -- diajukan, reviu_paralel, pending_anggaran, disetujui, ditolak, diproses_ppbj, selesai
    status_anggaran VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, hold, disetujui, ditolak
    revisi_ke INT NOT NULL DEFAULT 0,
    estimasi_total_pagu NUMERIC(15, 2),
    tanggal_pengajuan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kode_unit VARCHAR(20) NOT NULL,
    id_user BIGINT NOT NULL, -- User Pengaju
    nomor_surat_induk_split VARCHAR(100), -- Unary Split Permohonan
    tanggal_pemecahan TIMESTAMP,
    alasan_pemecahan TEXT,
    nomor_surat_revisi VARCHAR(100), -- Unary Revisi Permohonan (FR 3.4)
    tanggal_revisi TIMESTAMP,
    alasan_revisi TEXT,
    kode_mak VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_permohonan_unit FOREIGN KEY (kode_unit) REFERENCES units (kode_unit) ON UPDATE CASCADE,
    CONSTRAINT fk_permohonan_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_permohonan_mak FOREIGN KEY (kode_mak) REFERENCES anggaran_mak (kode_mak) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_permohonan_split FOREIGN KEY (nomor_surat_induk_split) REFERENCES permohonan_pengadaan (nomor_surat) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_permohonan_revisi FOREIGN KEY (nomor_surat_revisi) REFERENCES permohonan_pengadaan (nomor_surat) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 6. VERIFIKASI PARALEL (PERENCANAAN & WADIR 2)
-- ============================================================================
CREATE TABLE verifikasi_paralel (
    id BIGSERIAL PRIMARY KEY,
    id_user BIGINT NOT NULL,
    nomor_surat VARCHAR(100) NOT NULL,
    role_verifikator VARCHAR(50) NOT NULL, -- perencanaan, staff_bidang_2, wadir_2
    status_keputusan VARCHAR(50) NOT NULL DEFAULT 'menunggu', -- menunggu, disetujui, ditolak, revisi, pending_anggaran
    catatan_alasan TEXT, -- Wajib isi alasan jika ditolak/revisi
    tanggal_keputusan TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_verifikasi_user_surat_role UNIQUE (id_user, nomor_surat, role_verifikator),
    CONSTRAINT fk_verif_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_verif_surat FOREIGN KEY (nomor_surat) REFERENCES permohonan_pengadaan (nomor_surat) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 7. ALOKASI MAK
-- ============================================================================
CREATE TABLE alokasi_mak (
    id BIGSERIAL PRIMARY KEY,
    nomor_surat VARCHAR(100) NOT NULL,
    kode_mak VARCHAR(50) NOT NULL,
    nominal_alokasi NUMERIC(15, 2) NOT NULL,
    tanggal_alokasi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alokasi_surat FOREIGN KEY (nomor_surat) REFERENCES permohonan_pengadaan (nomor_surat) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_alokasi_mak FOREIGN KEY (kode_mak) REFERENCES anggaran_mak (kode_mak) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 8. PAKET PENGADAAN (ROUTING 3 JALUR: <50JT, 50-200JT, >200JT)
-- ============================================================================
CREATE TABLE paket_pengadaan (
    nomor_paket VARCHAR(100) PRIMARY KEY,
    nama_paket VARCHAR(255) NOT NULL,
    jenis_pengadaan VARCHAR(50) NOT NULL, -- barang, jasa_lainnya, konstruksi, jasa_konsultansi
    platform_pengadaan VARCHAR(50) NOT NULL DEFAULT 'spse', -- spse, inaproc, bela_pengadaan, e_katalog, toko_daring, non_spse
    mekanisme_pengadaan VARCHAR(50) NOT NULL DEFAULT 'pengadaan_langsung', -- pengadaan_langsung, tender, seleksi, penunjukan_langsung, e_purchasing
    jalur_routing VARCHAR(50) NOT NULL DEFAULT 'langsung_sd_50jt', -- langsung_sd_50jt, pp_50jt_sd_200jt, ppk_diatas_200jt
    nilai_hps NUMERIC(15, 2) NOT NULL,
    alokasi_pagu_paket NUMERIC(15, 2) NOT NULL,
    file_dokumen_pengadaan VARCHAR(255),
    status_paket VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, reviu_dokumen, spk_terbit, pengiriman, penerimaan, pembayaran, selesai, dibatalkan
    id_paket_lkpp VARCHAR(100),
    nomor_surat VARCHAR(100) NOT NULL,
    tanggal_registrasi_paket TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nomor_memo_ppbj VARCHAR(100),
    id_user_ppbj BIGINT NOT NULL, -- Staf PPBJ
    id_user_ppk BIGINT, -- Salah 1 dari 4 akun PPK
    tanggal_penugasan_ppk TIMESTAMP,
    status_penugasan_ppk VARCHAR(50),
    alasan_pengalihan_ppk TEXT,
    id_user_pp BIGINT, -- 1 akun PP
    tanggal_penugasan_pp TIMESTAMP,
    nilai_hasil_negosiasi NUMERIC(15, 2),
    catatan_verifikasi_pp TEXT,
    npwp_vendor VARCHAR(30),
    tanggal_penunjukan_vendor TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paket_surat FOREIGN KEY (nomor_surat) REFERENCES permohonan_pengadaan (nomor_surat) ON UPDATE CASCADE,
    CONSTRAINT fk_paket_ppbj FOREIGN KEY (id_user_ppbj) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_paket_ppk FOREIGN KEY (id_user_ppk) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_paket_pp FOREIGN KEY (id_user_pp) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_paket_vendor FOREIGN KEY (npwp_vendor) REFERENCES vendors (npwp) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 9. REVIU DOKUMEN OLEH PP / PPK
-- ============================================================================
CREATE TABLE reviu_dokumen (
    id_reviu BIGSERIAL PRIMARY KEY,
    nomor_paket VARCHAR(100) NOT NULL,
    id_user BIGINT NOT NULL,
    putaran_ke INT NOT NULL DEFAULT 1,
    role_reviewer VARCHAR(50) NOT NULL, -- pp, ppk
    status_reviu VARCHAR(50) NOT NULL, -- disetujui, perlu_perbaikan, ditolak
    catatan_perbaikan TEXT,
    tanggal_reviu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviu_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reviu_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE
);

-- ============================================================================
-- 10. RIWAYAT PENUGASAN PPK (AUDIT TRAIL ALIH TUGAS)
-- ============================================================================
CREATE TABLE riwayat_penugasan_ppk (
    id_riwayat BIGSERIAL PRIMARY KEY,
    nomor_paket VARCHAR(100) NOT NULL,
    ppk_lama_user_id BIGINT,
    ppk_baru_user_id BIGINT NOT NULL,
    diubah_oleh_user_id BIGINT NOT NULL,
    alasan_perubahan TEXT,
    tanggal_penugasan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_riwayat_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_riwayat_ppk_lama FOREIGN KEY (ppk_lama_user_id) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_riwayat_ppk_baru FOREIGN KEY (ppk_baru_user_id) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_riwayat_diubah FOREIGN KEY (diubah_oleh_user_id) REFERENCES users (id_user) ON UPDATE CASCADE
);

-- ============================================================================
-- 11. KONTRAK & SPK (MEMICU KUNCI PERMANEN MAK)
-- ============================================================================
CREATE TABLE kontrak_spk (
    nomor_spk VARCHAR(100) PRIMARY KEY,
    nomor_paket VARCHAR(100) NOT NULL,
    id_user BIGINT, -- PPBJ Pembuat SPK
    tipe_dokumen VARCHAR(50) NOT NULL DEFAULT 'spk', -- spk, surat_kontrak, surat_pesanan
    sifat_kontrak VARCHAR(50) NOT NULL DEFAULT 'sekali_selesai', -- sekali_selesai, lumpsum, harga_satuan, termin_bulanan, kontrak_tahunan
    status_spk VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, terbit, aktif, selesai
    tanggal_spk DATE NOT NULL,
    tanggal_mulai DATE,
    tanggal_selesai DATE,
    jadwal_pengiriman DATE NOT NULL,
    nilai_kontrak NUMERIC(15, 2) NOT NULL,
    file_spk VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_spk_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_spk_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 12. PENERIMAAN BARANG, BAST & FOTO DOKUMENTASI (PERLENGKAPAN)
-- ============================================================================
CREATE TABLE penerimaan_barang (
    id_penerimaan BIGSERIAL PRIMARY KEY,
    nomor_bast VARCHAR(100) UNIQUE,
    nomor_paket VARCHAR(100) NOT NULL,
    id_user BIGINT NOT NULL, -- Petugas Perlengkapan
    tanggal_penerimaan DATE NOT NULL,
    status_fisik VARCHAR(50) NOT NULL DEFAULT 'terima', -- terima, pending, tidak_sesuai
    ttd_digital_bast TEXT,
    file_bast_signed VARCHAR(255),
    file_penerimaan_pihak3 VARCHAR(255),
    catatan_pemeriksaan TEXT,
    waktu_selesai_inspeksi TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_penerimaan_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_penerimaan_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE
);

CREATE TABLE penerimaan_barang_foto_dokumentasi (
    id_foto BIGSERIAL PRIMARY KEY,
    id_penerimaan BIGINT NOT NULL,
    foto_dokumentasi VARCHAR(255) NOT NULL,
    keterangan_foto VARCHAR(255),
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_foto_penerimaan FOREIGN KEY (id_penerimaan) REFERENCES penerimaan_barang (id_penerimaan) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 13. PENGAJUAN PEMBAYARAN
-- ============================================================================
CREATE TABLE pembayaran (
    nomor_pembayaran VARCHAR(100) PRIMARY KEY,
    nomor_paket VARCHAR(100) NOT NULL,
    id_user_pengaju BIGINT NOT NULL, -- PPBJ
    tahap_termin INT NOT NULL DEFAULT 1,
    nominal_pengajuan NUMERIC(15, 2) NOT NULL,
    tanggal_pengajuan DATE NOT NULL,
    jatuh_tempo DATE NOT NULL,
    status_pembayaran VARCHAR(50) NOT NULL DEFAULT 'diajukan', -- diajukan, reviu_memo, disetujui_memo, perintah_bayar, lunas, ditolak
    file_tagihan_vendor VARCHAR(255),
    file_dokumen_penunjang VARCHAR(255),
    nomor_memo VARCHAR(100),
    file_memo VARCHAR(255),
    id_user_reviu BIGINT, -- PPBJ Reviu Akhir
    tanggal_perintah_bayar TIMESTAMP,
    status_reviu_akhir VARCHAR(50),
    catatan_reviu_akhir TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bayar_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_bayar_pengaju FOREIGN KEY (id_user_pengaju) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_bayar_reviu FOREIGN KEY (id_user_reviu) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- 14. MEMO PEMBAYARAN BERTINGKAT (PPK & WADIR 2)
-- ============================================================================
CREATE TABLE memo_bayar (
    id BIGSERIAL PRIMARY KEY,
    id_user BIGINT NOT NULL,
    nomor_pembayaran VARCHAR(100) NOT NULL,
    role_approver VARCHAR(50) NOT NULL, -- ppk, wadir_2
    urutan_level INT NOT NULL DEFAULT 1, -- 1: PPK, 2: Wadir 2
    status_memo VARCHAR(50) NOT NULL DEFAULT 'menunggu', -- menunggu, disetujui, ditolak
    catatan_memo TEXT,
    tanggal_acc TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_memo_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE,
    CONSTRAINT fk_memo_bayar FOREIGN KEY (nomor_pembayaran) REFERENCES pembayaran (nomor_pembayaran) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 15. TRANSAKSI PENCAIRAN KEUANGAN (FINALISASI STATUS SELESAI)
-- ============================================================================
CREATE TABLE transaksi_pencairan (
    nomor_transaksi_bank VARCHAR(100) PRIMARY KEY,
    nomor_pembayaran VARCHAR(100) NOT NULL,
    id_user BIGINT NOT NULL, -- Staf Keuangan
    tanggal_transfer TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nominal_transfer NUMERIC(15, 2) NOT NULL,
    file_bukti_transfer VARCHAR(255) NOT NULL,
    catatan_keuangan TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cair_bayar FOREIGN KEY (nomor_pembayaran) REFERENCES pembayaran (nomor_pembayaran) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cair_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE
);

-- ============================================================================
-- 16. REMINDER & NOTIFIKASI PENGGUNA
-- ============================================================================
CREATE TABLE reminder (
    id_reminder BIGSERIAL PRIMARY KEY,
    nomor_paket VARCHAR(100) NOT NULL,
    tipe_reminder VARCHAR(50) NOT NULL, -- pengiriman, h_minus_2_termin, bulanan_tahunan
    waktu_trigger TIMESTAMP NOT NULL,
    is_recurring BOOLEAN NOT NULL DEFAULT FALSE,
    interval_bulan INT,
    judul_alert VARCHAR(150) NOT NULL,
    pesan_alert TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reminder_paket FOREIGN KEY (nomor_paket) REFERENCES paket_pengadaan (nomor_paket) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE notifikasi_user (
    id BIGSERIAL PRIMARY KEY,
    id_user BIGINT NOT NULL,
    id_reminder BIGINT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    is_dismissed BOOLEAN NOT NULL DEFAULT FALSE,
    waktu_dibaca TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_notif_user_reminder UNIQUE (id_user, id_reminder),
    CONSTRAINT fk_notif_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notif_reminder FOREIGN KEY (id_reminder) REFERENCES reminder (id_reminder) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ============================================================================
-- 17. AUDIT LOG TRANSAKSI PBJ
-- ============================================================================
CREATE TABLE audit_log (
    id_log BIGSERIAL PRIMARY KEY,
    id_user BIGINT,
    waktu_log TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    jenis_aksi VARCHAR(50) NOT NULL, -- revisi_surat, penetapan_mak, kunci_mak, split_permohonan, penugasan_ppk, penerbitan_spk, ttd_bast, memo_bayar, transfer_pencairan
    nama_entitas VARCHAR(100) NOT NULL,
    id_entitas VARCHAR(100) NOT NULL,
    nilai_lama TEXT,
    nilai_baru TEXT,
    alasan TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================================
-- DATABASE TRIGGERS UNTUK INTEGRITAS DAN PENGUNCIAN MAK
-- ============================================================================

-- Trigger 1: Cegah pengubahan data MAK jika is_locked = true
CREATE OR REPLACE FUNCTION fn_check_mak_immutability()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.is_locked = TRUE AND NEW.is_locked = TRUE THEN
        IF OLD.uraian_mak <> NEW.uraian_mak OR OLD.pagu_anggaran <> NEW.pagu_anggaran OR OLD.sumber_dana <> NEW.sumber_dana THEN
            RAISE EXCEPTION 'Data MAK dengan kode % telah dikunci permanen karena SPK telah terbit dan tidak dapat diubah!', OLD.kode_mak;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_check_mak_immutability ON anggaran_mak;
CREATE TRIGGER trg_check_mak_immutability
BEFORE UPDATE ON anggaran_mak
FOR EACH ROW EXECUTE FUNCTION fn_check_mak_immutability();

-- Trigger 2: Kunci MAK secara otomatis saat Kontrak SPK berstatus 'terbit'
CREATE OR REPLACE FUNCTION fn_lock_mak_on_spk()
RETURNS TRIGGER AS $$
DECLARE
    v_kode_mak VARCHAR(50);
BEGIN
    IF NEW.status_spk IN ('terbit', 'aktif', 'selesai') THEN
        -- Ambil kode_mak dari permohonan melalui paket
        SELECT p.kode_mak INTO v_kode_mak
        FROM paket_pengadaan pkt
        JOIN permohonan_pengadaan p ON pkt.nomor_surat = p.nomor_surat
        WHERE pkt.nomor_paket = NEW.nomor_paket;

        IF v_kode_mak IS NOT NULL THEN
            UPDATE anggaran_mak
            SET is_locked = TRUE, tanggal_kunci = CURRENT_TIMESTAMP
            WHERE kode_mak = v_kode_mak AND is_locked = FALSE;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_lock_mak_on_spk ON kontrak_spk;
CREATE TRIGGER trg_lock_mak_on_spk
AFTER INSERT OR UPDATE ON kontrak_spk
FOR EACH ROW EXECUTE FUNCTION fn_lock_mak_on_spk();