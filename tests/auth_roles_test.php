<?php
require_once __DIR__ . '/../auth.php';
// Memastikan hasil pemeriksaan akses sesuai harapan.
function expect_access($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$pages = [
    'data_customer.php',
    'dashboard.php',
    'laporan.php',
    'cetak_laporan.php',
    'data_karpet.php',
    'data_ruangan.php',
    'estimasi.php',
    'riwayat_estimasi.php',
    'pengaturan.php',
    'cetak_estimasi.php',
    'read_dimensions.php',
];
foreach (['karyawan', 'owner', 'invalid', null] as $role) {
    $_SESSION = ['user_logged_in' => true, 'user_id' => 1, 'user_role' => $role];
    foreach ($pages as $page) {
        $expected =
            $role === 'karyawan' ||
            ($role === 'owner' &&
                in_array(
                    $page,
                    ['dashboard.php', 'laporan.php', 'cetak_laporan.php'],
                    true,
                ));
        expect_access(can_access_page($page) === $expected, "$role: $page");
    }
    expect_access(!can_access_page('unknown.php'), 'Unknown route must be denied');
    $activePage = 'dashboard';
    ob_start();
    include __DIR__ . '/../includes/sidebar.php';
    $html = ob_get_clean();
    foreach (
        array_diff($pages, [
            'data_ruangan.php',
            'cetak_laporan.php',
            'cetak_estimasi.php',
            'read_dimensions.php',
        ])
        as $page
    ) {
        expect_access(
            (strpos($html, 'href="' . $page . '"') !== false) === can_access_page($page),
            'Sidebar: ' . $page,
        );
    }
}
$_SESSION = [];
foreach ($pages as $page) {
    expect_access(!can_access_page($page), 'Guest: ' . $page);
}
echo "Role access matrix and sidebar visibility passed.\n";
