<?php
// Session helpers; keep authentication independent from the database connection.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_authenticated() {
    return in_array($_SESSION['user_role'] ?? null, ['karyawan', 'owner'], true)
        && ($_SESSION['user_logged_in'] ?? false) === true
        && filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

function check_auth() {
    header('Cache-Control: no-store, private, max-age=0');
    if (!is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}

function can_access_page($page) {
    if (!is_authenticated()) return false;
    $pages = ['dashboard.php', 'laporan.php', 'cetak_laporan.php'];
    if ($_SESSION['user_role'] === 'karyawan') {
        $pages = array_merge($pages, ['data_customer.php', 'data_karpet.php', 'data_ruangan.php', 'estimasi.php',
            'riwayat_estimasi.php', 'pengaturan.php', 'cetak_estimasi.php', 'read_dimensions.php']);
    }
    return in_array($page, $pages, true);
}

function check_page_access($page) {
    check_auth();
    if (!can_access_page($page)) {
        http_response_code(403);
        echo '<!doctype html><html lang="id"><meta charset="UTF-8"><title>Akses Ditolak</title>';
        echo '<h1>Akses ditolak</h1><p>Akun Anda tidak memiliki akses ke halaman ini.</p>';
        echo '<a href="dashboard.php">Kembali ke Dashboard</a></html>';
        exit;
    }
}
