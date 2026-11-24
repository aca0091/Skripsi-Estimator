<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config_database.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS tb_customer (
 id_customer INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_customer VARCHAR(255) NOT NULL,
 kontak_person VARCHAR(255) NOT NULL DEFAULT '',
 telepon VARCHAR(50) NOT NULL DEFAULT '',
 email VARCHAR(255) NOT NULL DEFAULT '',
 alamat TEXT NOT NULL,
 tanggal_daftar DATE NOT NULL,
 INDEX(nama_customer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if (!$pdo->query("SHOW COLUMNS FROM tb_estimasi LIKE 'id_customer'")->fetch()) {
 $pdo->exec('ALTER TABLE tb_estimasi ADD id_customer INT UNSIGNED NULL, ADD INDEX(id_customer)');
}
if (!$pdo->query("SHOW COLUMNS FROM tb_estimasi LIKE 'status_proyek'")->fetch()) {
 $pdo->exec("ALTER TABLE tb_estimasi ADD status_proyek ENUM('Estimasi','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Estimasi'");
}
$pdo->beginTransaction();
try {
 $rows = $pdo->query('SELECT e.id_estimasi, e.tanggal_estimasi, r.nama_pelanggan FROM tb_estimasi e LEFT JOIN tb_ruangan r ON r.id_ruangan=e.id_ruangan WHERE e.id_customer IS NULL ORDER BY e.tanggal_estimasi, e.id_estimasi')->fetchAll();
 $find=$pdo->prepare('SELECT id_customer FROM tb_customer WHERE BINARY nama_customer = BINARY ? ORDER BY id_customer LIMIT 1');
 $insert=$pdo->prepare('INSERT INTO tb_customer(nama_customer, alamat, tanggal_daftar) VALUES (?, ?, ?)');
 $update=$pdo->prepare('UPDATE tb_estimasi SET id_customer=? WHERE id_estimasi=?');
 foreach ($rows as $row) {
  $name=trim($row['nama_pelanggan'] ?? '');
  if ($name==='') $name='Customer proyek lama #'.$row['id_estimasi'];
  $find->execute([$name]); $id=$find->fetchColumn();
  if (!$id) { $insert->execute([$name,'',$row['tanggal_estimasi']]); $id=$pdo->lastInsertId(); }
  $update->execute([$id,$row['id_estimasi']]);
 }
 $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
$pdo->exec('ALTER TABLE tb_estimasi MODIFY id_customer INT UNSIGNED NOT NULL');
$fk=$pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tb_estimasi' AND COLUMN_NAME='id_customer' AND REFERENCED_TABLE_NAME='tb_customer'")->fetchColumn();
if (!$fk) $pdo->exec('ALTER TABLE tb_estimasi ADD CONSTRAINT fk_estimasi_customer FOREIGN KEY(id_customer) REFERENCES tb_customer(id_customer) ON DELETE RESTRICT');
echo 'Migrasi customer selesai. Proyek lama terhubung: '.count($rows)."\n";
