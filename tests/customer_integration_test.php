<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config_database.php';
require __DIR__ . '/../customer_helpers.php';
// Memastikan hasil pengujian customer sesuai harapan.
function verify_customer($ok, $message)
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$pdo->beginTransaction();
try {
    $base = $pdo->query('SELECT * FROM tb_estimasi LIMIT 1')->fetch();
    if (!$base) {
        throw new RuntimeException(
            'Test needs one existing project as fixture template.',
        );
    }
    $name = 'Customer test ' . bin2hex(random_bytes(6));
    $pdo->prepare(
        'INSERT INTO tb_customer(nama_customer,alamat,tanggal_daftar) VALUES(?,?,?)',
    )->execute([$name, 'Alamat pengujian', '2026-01-01']);
    $id = (int) $pdo->lastInsertId();
    unset($base['id_estimasi']);
    $base['id_customer'] = $id;
    $insert = $pdo->prepare(
        'INSERT INTO tb_estimasi (`' .
            implode('`,`', array_keys($base)) .
            '`) VALUES(' .
            implode(',', array_fill(0, count($base), '?')) .
            ')',
    );
    foreach (
        [
            ['2026-01-10', 100, 'Selesai'],
            ['2026-02-10', 200, 'Diproses'],
            ['2026-03-10', 300, 'Dibatalkan'],
        ]
        as [$date, $value, $status]
    ) {
        $base['tanggal_estimasi'] = $date;
        $base['total_biaya'] = $value;
        $base['status_proyek'] = $status;
        $base['kode_estimasi'] = 'TEST-' . bin2hex(random_bytes(6));
        $insert->execute(array_values($base));
    }
    $lookup = function ($report) use ($id) {
        foreach ($report['rows'] as $row) {
            if ((int) $row['id_customer'] === $id) {
                return $row;
            }
        }
        throw new RuntimeException('Missing test customer');
    };
    $jan = $lookup(customer_report($pdo, '2026-01-01', '2026-01-31'));
    verify_customer(
        (int) $jan['jumlah_total'] === 1 && (int) $jan['jumlah_periode'] === 1,
        'January must not include future projects',
    );
    $feb = $lookup(customer_report($pdo, '2026-02-01', '2026-02-28'));
    verify_customer(
        (int) $feb['jumlah_total'] === 2 &&
            (int) $feb['jumlah_periode'] === 1 &&
            (float) $feb['nilai_periode'] === 200.0,
        'February repeat counts/value',
    );
    $all = $lookup(
        customer_report(
            $pdo,
            '2026-01-01',
            '2026-03-31',
            (int) $base['id_karpet'],
            $base['metode_pemasangan'],
        ),
    );
    verify_customer(
        (int) $all['jumlah_total'] === 3 &&
            (int) $all['jumlah_periode'] === 3 &&
            (float) $all['nilai_periode'] === 600.0,
        'All statuses and filter totals',
    );
    $empty = $lookup(customer_report($pdo, '2026-01-01', '2026-03-31', 0, 'NO_MATCH'));
    verify_customer(
        (int) $empty['jumlah_periode'] === 0 && (int) $empty['jumlah_total'] === 3,
        'Filter must not erase lifetime count',
    );
    $pdo->prepare('UPDATE tb_customer SET tanggal_daftar=? WHERE id_customer=?')->execute(
        ['2026-04-01', $id],
    );
    $backdated = $lookup(customer_report($pdo, '2026-01-01', '2026-01-31'));
    verify_customer(
        (int) $backdated['jumlah_periode'] === 1,
        'Backdated project must remain visible for a newly registered customer',
    );
    $rejected = false;
    try {
        $pdo->prepare('DELETE FROM tb_customer WHERE id_customer=?')->execute([$id]);
    } catch (PDOException $e) {
        $rejected = $e->getCode() === '23000';
    }
    verify_customer($rejected, 'Customer with projects cannot be removed');
    verify_customer(
        (int) $pdo
            ->query(
                'SELECT COUNT(*) FROM tb_estimasi e LEFT JOIN tb_customer c ON c.id_customer=e.id_customer WHERE c.id_customer IS NULL',
            )
            ->fetchColumn() === 0,
        'Every project linked',
    );
    echo "Customer reports, date boundaries, filters, repeat counts and foreign key passed. Test data rolled back.\n";
} finally {
    $pdo->rollBack();
}
