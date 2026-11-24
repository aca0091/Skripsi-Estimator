<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config_database.php';
if (!$pdo->query("SHOW COLUMNS FROM tb_user LIKE 'role'")->fetch()) {
    $pdo->exec("ALTER TABLE tb_user ADD COLUMN role ENUM('karyawan', 'owner') NOT NULL DEFAULT 'karyawan'");
}
echo "Kolom role siap; akun lama menggunakan role Karyawan.\n";
