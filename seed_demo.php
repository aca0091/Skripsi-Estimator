<?php
// Jalankan lewat PHP CLI. Contoh mengikuti rumus dan tarif bawaan estimasi.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/config_database.php';

$pdo->beginTransaction();
try {
    $userId = $pdo
        ->query('SELECT id_user FROM tb_user ORDER BY id_user LIMIT 1')
        ->fetchColumn();
    if (!$userId) {
        throw new RuntimeException('Daftarkan akun melalui aplikasi terlebih dahulu.');
    }

    $carpets = [
        ['KRP-01', 'Karpet Abu Polos 4m', 4, 'Polos', 480000, 200],
        ['KRP-02', 'Karpet Biru Polos 2m', 2, 'Polos', 220000, 150],
        ['KRP-03', 'Karpet Motif Premium 4m', 4, 'Bermotif', 600000, 100],
    ];
    $carpetIds = [];
    foreach ($carpets as $carpet) {
        $find = $pdo->prepare('SELECT id_karpet FROM tb_karpet WHERE kode_karpet = ?');
        $find->execute([$carpet[0]]);
        $id = $find->fetchColumn();
        if (!$id) {
            $pdo->prepare(
                'INSERT INTO tb_karpet (kode_karpet, nama_karpet, lebar_roll, motif, harga_per_m_lari, stok_meter_lari) VALUES (?, ?, ?, ?, ?, ?)',
            )->execute($carpet);
            $id = $pdo->lastInsertId();
        }
        $carpetIds[$carpet[0]] = $id;
    }

    $findCustomer = $pdo->prepare(
        'SELECT id_customer FROM tb_customer WHERE nama_customer=? ORDER BY id_customer LIMIT 1',
    );
    $findCustomer->execute(['Kantor Nusantara']);
    $customerId = $findCustomer->fetchColumn();
    if (!$customerId) {
        $pdo->exec(
            "INSERT INTO tb_customer(nama_customer,alamat,tanggal_daftar) VALUES('Kantor Nusantara','',CURDATE())",
        );
        $customerId = $pdo->lastInsertId();
    }
    // Ruang 10 x 6 m, lekukan 1 x 2,5 m, objek 3,2 m2.
    // Luas bersih = 60 - 2,5 - 3,2 = 54,3 m2.
    // Vertikal: ceil(10 / 4) x 6 = 18 m lari; waste = 72 - 54,3.
    foreach (['Disambung', 'Permanen'] as $method) {
        $code = $method === 'Disambung' ? 'EST - 0001' : 'EST - 0002';
        $find = $pdo->prepare(
            'SELECT id_estimasi FROM tb_estimasi WHERE kode_estimasi = ?',
        );
        $find->execute([$code]);
        if ($find->fetchColumn()) {
            echo "$code sudah ada, dilewati.\n";
            continue;
        }
        $pdo->prepare(
            'INSERT INTO tb_ruangan (nama_ruangan, nama_pelanggan, bentuk_ruangan, panjang, lebar, jumlah_pilar, luas_objek, luas_kotor, luas_bersih) VALUES (?, ?, ?, 10, 6, 2, 3.2, 57.5, 54.3)',
        )->execute(['Ruang Rapat - ' . $method, 'Kantor Nusantara', 'L']);
        $roomId = $pdo->lastInsertId();
        $permanent = $method === 'Permanen';
        $seams = $permanent ? 0 : 12;
        $overlock = $permanent ? 0 : 34;
        $glue = $permanent ? 54.3 * 20000 : 0;
        $installation = $permanent ? 54.3 * 25000 : 0;
        $total = 18 * 480000 + $seams * 25000 + $overlock * 15000 + $glue + $installation;
        $pdo->prepare(
            'INSERT INTO tb_estimasi (kode_estimasi, id_ruangan, id_karpet, id_user, arah_pemasangan, metode_pemasangan, total_luas, total_meter_lari, total_sambungan, total_obras, biaya_lem, biaya_jasa_pasang, luas_waste, total_biaya, tanggal_estimasi, id_customer) VALUES (?, ?, ?, ?, ?, ?, 54.3, 18, ?, ?, ?, ?, 17.7, ?, CURDATE(), ?)',
        )->execute([
            $code,
            $roomId,
            $carpetIds['KRP-01'],
            $userId,
            'Vertikal',
            $method,
            $seams,
            $overlock,
            $glue,
            $installation,
            $total,
            $customerId,
        ]);
        echo "$code: $method, total Rp " . number_format($total, 0, ',', '.') . "\n";
    }
    $pdo->commit();
    echo "Data contoh siap.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
