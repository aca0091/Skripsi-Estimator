<?php
// Batasi migrasi agar hanya dapat dijalankan melalui terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config_database.php';

// Siapkan tabel induk untuk pelanggan, nomor, dan total penawaran.
$pdo->exec("CREATE TABLE IF NOT EXISTS tb_penawaran (
 id_penawaran INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kode_penawaran VARCHAR(100) UNIQUE DEFAULT NULL,
 id_customer INT UNSIGNED NOT NULL,
 nama_pelanggan VARCHAR(255) NOT NULL,
 tanggal_penawaran DATE NOT NULL,
 pricing_snapshot JSON NOT NULL,
 total_biaya DECIMAL(18,2) NOT NULL,
 FOREIGN KEY (id_customer) REFERENCES tb_customer(id_customer) ON DELETE RESTRICT
) ENGINE=InnoDB");
// Tambahkan relasi penawaran dan gambar tanpa mengubah estimasi lama.
foreach (
    [
        'id_penawaran' => 'INT UNSIGNED DEFAULT NULL',
        'layout_image' => 'MEDIUMTEXT DEFAULT NULL'
    ]
    as $column => $type
) {
    if (!$pdo->query("SHOW COLUMNS FROM tb_estimasi LIKE '$column'")->fetch()) {
        $pdo->exec("ALTER TABLE tb_estimasi ADD COLUMN $column $type");
    }
}

// Tambahkan indeks pencarian dan relasi agar data penawaran tetap konsisten.
if (
    !$pdo
        ->query("SHOW INDEX FROM tb_estimasi WHERE Key_name='idx_penawaran'")
        ->fetch()
) {
    $pdo->exec(
        'ALTER TABLE tb_estimasi ADD INDEX idx_penawaran (id_penawaran), ADD CONSTRAINT fk_estimasi_penawaran FOREIGN KEY (id_penawaran) REFERENCES tb_penawaran(id_penawaran) ON DELETE RESTRICT'
    );
}
echo "Penyimpanan penawaran multi-estimasi siap.\n";
