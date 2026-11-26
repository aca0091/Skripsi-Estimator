-- Skema awal berdasarkan query aplikasi; tidak berisi data contoh.
CREATE DATABASE IF NOT EXISTS db_estimator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_estimator;

CREATE TABLE IF NOT EXISTS tb_user (
    id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('karyawan', 'owner') NOT NULL DEFAULT 'karyawan'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_karpet (
    id_karpet INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_karpet VARCHAR(100) NOT NULL,
    nama_karpet VARCHAR(255) NOT NULL,
    lebar_roll DECIMAL(12,4) NOT NULL,
    motif VARCHAR(100) NOT NULL,
    harga_per_m_lari DECIMAL(18,2) NOT NULL DEFAULT 0,
    stok_meter_lari DECIMAL(14,4) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_ruangan (
    id_ruangan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_ruangan VARCHAR(255) NOT NULL,
    nama_pelanggan VARCHAR(255) NOT NULL,
    bentuk_ruangan VARCHAR(100) NOT NULL DEFAULT 'Persegi Panjang',
    panjang DECIMAL(14,4) NOT NULL DEFAULT 0,
    lebar DECIMAL(14,4) NOT NULL DEFAULT 0,
    jumlah_pilar INT NOT NULL DEFAULT 0,
    luas_objek DECIMAL(14,4) NOT NULL DEFAULT 0,
    luas_kotor DECIMAL(14,4) NOT NULL DEFAULT 0,
    luas_bersih DECIMAL(14,4) NOT NULL DEFAULT 0,
    file_layout VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_customer (
    id_customer INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_customer VARCHAR(255) NOT NULL,
    kontak_person VARCHAR(255) NOT NULL DEFAULT '',
    telepon VARCHAR(50) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    alamat TEXT NOT NULL,
    tanggal_daftar DATE NOT NULL,
    INDEX(nama_customer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_penawaran (
 id_penawaran INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kode_penawaran VARCHAR(100) UNIQUE DEFAULT NULL,
 id_customer INT UNSIGNED NOT NULL,
 nama_pelanggan VARCHAR(255) NOT NULL,
 tanggal_penawaran DATE NOT NULL,
 pricing_snapshot JSON NOT NULL,
 total_biaya DECIMAL(18,2) NOT NULL,
 FOREIGN KEY (id_customer) REFERENCES tb_customer(id_customer) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tb_estimasi (
    id_estimasi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_estimasi VARCHAR(100) NOT NULL,
    id_customer INT UNSIGNED NOT NULL,
    status_proyek ENUM('Estimasi','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Estimasi',
    id_ruangan INT UNSIGNED NOT NULL,
    id_karpet INT UNSIGNED NOT NULL,
    id_user INT UNSIGNED NOT NULL,
    arah_pemasangan VARCHAR(50) NOT NULL,
    metode_pemasangan VARCHAR(50) NOT NULL,
    total_luas DECIMAL(14,4) NOT NULL DEFAULT 0,
    total_meter_lari DECIMAL(14,4) NOT NULL DEFAULT 0,
    total_sambungan DECIMAL(14,4) NOT NULL DEFAULT 0,
    total_obras DECIMAL(14,4) NOT NULL DEFAULT 0,
    biaya_lem DECIMAL(18,2) NOT NULL DEFAULT 0,
    biaya_jasa_pasang DECIMAL(18,2) NOT NULL DEFAULT 0,
    luas_waste DECIMAL(14,4) NOT NULL DEFAULT 0,
    total_biaya DECIMAL(18,2) NOT NULL DEFAULT 0,
    tanggal_estimasi DATE NOT NULL,
    layout_geometry JSON DEFAULT NULL,
    pricing_snapshot JSON DEFAULT NULL,
    layout_image MEDIUMTEXT DEFAULT NULL,
    id_penawaran INT UNSIGNED DEFAULT NULL,
    INDEX idx_penawaran (id_penawaran),
    CONSTRAINT fk_estimasi_penawaran FOREIGN KEY (id_penawaran) REFERENCES tb_penawaran(id_penawaran) ON DELETE RESTRICT,
    INDEX (id_ruangan),
    INDEX (id_karpet),
    INDEX (id_user),
    CONSTRAINT fk_estimasi_customer FOREIGN KEY (id_customer) REFERENCES tb_customer(id_customer) ON DELETE RESTRICT,
    INDEX (tanggal_estimasi)
) ENGINE=InnoDB;
