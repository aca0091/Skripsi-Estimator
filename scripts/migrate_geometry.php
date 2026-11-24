<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config_database.php';
if (!$pdo->query("SHOW COLUMNS FROM tb_estimasi LIKE 'layout_geometry'")->fetch()) {
    $pdo->exec('ALTER TABLE tb_estimasi ADD COLUMN layout_geometry JSON DEFAULT NULL');
}
echo "Penyimpanan kontur ruangan siap.\n";
