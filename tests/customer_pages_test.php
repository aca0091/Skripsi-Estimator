<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config_database.php';
$customerId = (int) $pdo
    ->query('SELECT id_customer FROM tb_customer ORDER BY id_customer LIMIT 1')
    ->fetchColumn();
$userId = (int) $pdo->query('SELECT id_user FROM tb_user LIMIT 1')->fetchColumn();
$root = dirname(__DIR__);
$cases = [
    ['data_customer.php', 'karyawan', [], [], 'Tambah Customer'],
    [
        'data_customer.php',
        'karyawan',
        ['id' => $customerId],
        [],
        'Riwayat Proyek Customer',
    ],
    [
        'estimasi.php',
        'karyawan',
        ['id_customer' => $customerId],
        [],
        'Pilih customer terdaftar',
    ],
    ['laporan.php', 'owner', [], [], 'Rekap Customer'],
    ['cetak_laporan.php', 'owner', [], [], 'Rekap Customer'],
    ['data_customer.php', 'owner', [], [], 'STATUS:403'],
    ['data_customer.php', 'owner', [], ['action' => 'save'], 'STATUS:403'],
    [
        'data_customer.php',
        'owner',
        [],
        ['action' => 'delete', 'id_customer' => $customerId],
        'STATUS:403',
    ],
    [
        'estimasi.php',
        'karyawan',
        [],
        ['action' => 'save_final_estimate', 'id_customer' => 0],
        'STATUS:422',
    ],
    [
        'data_customer.php',
        'karyawan',
        [],
        ['action' => 'save', 'nama_customer' => 'Tidak boleh tersimpan'],
        'STATUS:403',
    ],
];
foreach ($cases as [$page, $role, $get, $post, $expected]) {
    $session = [
        'user_logged_in' => true,
        'user_id' => $userId,
        'user_role' => $role,
        'user_name' => 'Test',
    ];
    $code =
        'session_start(); $_SESSION=' .
        var_export($session, true) .
        '; $_GET=' .
        var_export($get, true) .
        '; $_POST=' .
        var_export($post, true) .
        '; $_SERVER["REQUEST_METHOD"]="' .
        ($post ? 'POST' : 'GET') .
        '"; register_shutdown_function(function(){echo "STATUS:".http_response_code();}); include ' .
        var_export($root . '/' . $page, true) .
        ';';
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=1', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
    );
    $html = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if (
        $exit !== 0 ||
        $errors !== '' ||
        strpos($html, $expected) === false ||
        preg_match('/Warning:|Fatal error:|Koneksi database gagal/', $html)
    ) {
        throw new RuntimeException("$page ($role): $errors; expected $expected");
    }
}
echo "Page render/access/invalid-request checks passed.\n";
