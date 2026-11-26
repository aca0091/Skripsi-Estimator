<?php
// Database SQLite di memori: tidak membuka koneksi ke database aplikasi.
require __DIR__ . '/../quotation_helpers.php';
require __DIR__ . '/../room_geometry.php';
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
$pdo->exec(
    'CREATE TABLE tb_penawaran (id_penawaran INTEGER PRIMARY KEY AUTOINCREMENT,kode_penawaran TEXT,id_customer INTEGER,nama_pelanggan TEXT,tanggal_penawaran TEXT,pricing_snapshot TEXT,total_biaya NUMERIC)'
);
$pdo->exec(
    'CREATE TABLE tb_ruangan (id_ruangan INTEGER PRIMARY KEY AUTOINCREMENT,nama_ruangan TEXT,nama_pelanggan TEXT,panjang NUMERIC,lebar NUMERIC,luas_objek NUMERIC,luas_kotor NUMERIC,luas_bersih NUMERIC)'
);
$pdo->exec(
    'CREATE TABLE tb_karpet (id_karpet INTEGER PRIMARY KEY,kode_karpet TEXT,nama_karpet TEXT,lebar_roll NUMERIC,motif TEXT,harga_per_m_lari NUMERIC)'
);
$pdo->exec(
    'CREATE TABLE tb_estimasi (id_estimasi INTEGER PRIMARY KEY AUTOINCREMENT,kode_estimasi TEXT,id_ruangan INTEGER,id_karpet INTEGER,id_user INTEGER,arah_pemasangan TEXT,metode_pemasangan TEXT,total_luas NUMERIC,total_meter_lari NUMERIC,total_sambungan NUMERIC,total_obras NUMERIC,biaya_lem NUMERIC,biaya_jasa_pasang NUMERIC,luas_waste NUMERIC,total_biaya NUMERIC,tanggal_estimasi TEXT,layout_geometry TEXT,id_customer INTEGER,pricing_snapshot TEXT,id_penawaran INTEGER,layout_image TEXT)'
);
$pdo->exec(
    "INSERT INTO tb_karpet (id_karpet,kode_karpet,nama_karpet,lebar_roll,motif,harga_per_m_lari) VALUES (1,'TEST-A','Karpet A',4,'Polos',200000),(2,'TEST-B','Karpet B',2,'Polos',150000)"
);
$_SESSION = ['user_id' => 1];
$geometry = measure_room_geometry('{"points":[[0,0],[4,0],[4,3],[0,3]]}');
$row = [
    'name' => 'Ruang tamu',
    'carpet' => 1,
    'geometry' => $geometry,
    'object_area' => 0,
    'net_area' => 12,
    'direction' => 'Vertikal',
    'method' => 'Disambung',
    'meters' => 4,
    'seams' => 3,
    'binding' => 14,
    'waste' => 4,
    'image' => '',
    'pricing' => calculate_pricing(
        [
            'karpet' => ['quantity' => 4, 'rate' => 200000],
            'obras' => ['quantity' => 14, 'rate' => 15000]
        ],
        ['karpet' => ['value' => 10]]
    )
];
$second = $row;
$second['name'] = 'Kamar tidur';
$second['carpet'] = 2;
$second['pricing'] = calculate_pricing(
    ['karpet' => ['quantity' => 4, 'rate' => 150000]],
    []
);
$draft = [
    'customer' => 1,
    'customer_name' => 'Customer Test',
    'date' => '2026-11-26',
    'rows' => [$row, $second]
];
$quotationId = save_quotation($pdo, $draft, ['value' => 10]);
$quote = $pdo->query('SELECT * FROM tb_penawaran')->fetch();
$rows = $pdo
    ->query('SELECT * FROM tb_estimasi ORDER BY id_estimasi')
    ->fetchAll();
if (
    count($rows) !== 2 ||
    $rows[0]['id_penawaran'] != $rows[1]['id_penawaran'] ||
    array_sum(array_column($rows, 'total_biaya')) != $quote['total_biaya']
) {
    throw new RuntimeException('Penyimpanan grup atau total gagal');
}
ob_start();
require __DIR__ . '/../includes/print_quotation.php';
$html = ob_get_clean();
foreach (
    [
        'Ruang tamu',
        'Kamar tidur',
        'Karpet A',
        'Karpet B',
        $quote['kode_penawaran']
    ]
    as $text
) {
    if (!str_contains($html, $text)) {
        throw new RuntimeException('Cetakan kehilangan: ' . $text);
    }
}
file_put_contents('/tmp/quotation-preview.html', $html);
// Paksa kegagalan insert anak untuk memeriksa rollback seluruh penawaran.
$pdo->exec('ALTER TABLE tb_estimasi DROP COLUMN id_user');
try {
    save_quotation($pdo, $draft, []);
    throw new RuntimeException('Kegagalan insert tidak terjadi');
} catch (PDOException $expected) {
}
if (
    (int) $pdo->query('SELECT COUNT(*) FROM tb_penawaran')->fetchColumn() !==
        1 ||
    (int) $pdo->query('SELECT COUNT(*) FROM tb_ruangan')->fetchColumn() !== 2
) {
    throw new RuntimeException('Rollback tidak lengkap');
}
echo "Penyimpanan dua bahan, cetakan gabungan, total laporan, dan rollback lulus (SQLite di memori).\n";
