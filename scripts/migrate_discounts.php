<?php
// Jalankan hanya melalui terminal, bukan dari permintaan halaman web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config_database.php';

// Tambahkan penyimpanan rincian harga jika kolomnya belum tersedia.
if (
    !$pdo
        ->query("SHOW COLUMNS FROM tb_estimasi LIKE 'pricing_snapshot'")
        ->fetch()
) {
    $pdo->exec(
        'ALTER TABLE tb_estimasi ADD COLUMN pricing_snapshot JSON DEFAULT NULL'
    );
}
echo "Penyimpanan rincian harga dan diskon siap.\n";
