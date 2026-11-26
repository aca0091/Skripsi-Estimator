<?php
// riwayat_estimasi.php
require_once __DIR__ . '/auth.php';
check_page_access('riwayat_estimasi.php');
require_once __DIR__ . '/require.php';

require_once __DIR__ . '/quotation_helpers.php';
// Penghapusan satu penawaran beserta seluruh estimasinya dilakukan atomik.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    quotation_check_token();
    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT id_penawaran FROM tb_estimasi WHERE id_estimasi=? FOR UPDATE');
        $find->execute([(int) $_POST['hapus']]);
        $parent = $find->fetchColumn();
        if ($parent) {
            $pdo->prepare('DELETE FROM tb_estimasi WHERE id_penawaran=?')->execute([$parent]);
            $pdo->prepare('DELETE FROM tb_penawaran WHERE id_penawaran=?')->execute([$parent]);
        } else {
            $pdo->prepare('DELETE FROM tb_estimasi WHERE id_estimasi=?')->execute([(int) $_POST['hapus']]);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    header('Location: riwayat_estimasi.php'); exit;
}

// Logika Pencarian & Filter Tanggal / Pelanggan
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$bulanFilter = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';

$sql = "
    SELECT e.*, r.nama_ruangan, r.nama_pelanggan, k.nama_karpet, q.kode_penawaran, q.total_biaya AS total_penawaran, g.rooms, g.carpets, g.room_count, g.area_sum, g.waste_sum
    FROM tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    JOIN tb_karpet k ON e.id_karpet = k.id_karpet
    LEFT JOIN tb_penawaran q ON q.id_penawaran=e.id_penawaran
    LEFT JOIN (
        SELECT x.id_penawaran, GROUP_CONCAT(r2.nama_ruangan ORDER BY x.id_estimasi SEPARATOR ', ') rooms,
        GROUP_CONCAT(DISTINCT k2.nama_karpet ORDER BY k2.nama_karpet SEPARATOR ', ') carpets,
        COUNT(*) room_count, SUM(x.total_luas) area_sum, SUM(x.luas_waste) waste_sum
        FROM tb_estimasi x JOIN tb_ruangan r2 ON r2.id_ruangan=x.id_ruangan JOIN tb_karpet k2 ON k2.id_karpet=x.id_karpet
        WHERE x.id_penawaran IS NOT NULL GROUP BY x.id_penawaran
    ) g ON g.id_penawaran=e.id_penawaran
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .=
        ' AND (e.kode_estimasi LIKE ? OR r.nama_pelanggan LIKE ? OR r.nama_ruangan LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

if ($bulanFilter !== '') {
    $sql .= " AND DATE_FORMAT(e.tanggal_estimasi, '%Y-%m') = ?";
    array_push($params, $bulanFilter);
}

$sql .= ' ORDER BY e.id_estimasi DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayatList = [];
$seen = [];
foreach ($stmt->fetchAll() as $row) {
    if ($row['id_penawaran']) {
        if (isset($seen[$row['id_penawaran']])) continue;
        $seen[$row['id_penawaran']] = true;
        $row['kode_estimasi'] = $row['kode_penawaran'];
        $row['nama_ruangan'] = $row['rooms'];
        $row['nama_karpet'] = $row['carpets'];
        $row['total_biaya'] = $row['total_penawaran'];
        $row['total_luas'] = $row['area_sum'];
        $row['luas_waste'] = $row['waste_sum'];
        $row['arah_pemasangan'] = $row['room_count'] . ' estimasi';
        $row['metode_pemasangan'] = 'Lihat rincian';
    }
    $riwayatList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Estimasi - Carpet Estimator</title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/dash.css?v=20261123-sidebar2">
    <link rel="stylesheet" href="assets/css/riwayat.css">
    <link rel="stylesheet" href="assets/css/app-theme.css?v=20261123-1">
    <link rel="stylesheet" href="assets/css/form-polish.css?v=20261124-1">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <?php $activePage = 'riwayat_estimasi'; require __DIR__ . '/includes/sidebar.php'; ?>

        <!-- MAIN BODY -->
        <main class="main-body">
            <?php require __DIR__ . '/includes/app_toolbar.php'; ?>
            <h1 class="main-title">Riwayat Estimasi Pemasangan</h1>
            <p class="breadcrumb">Beranda / Riwayat Estimasi / Arsip Kalkulasi Optimasi
                ILP</p>

            <!-- BAR FILTER & PENCARIAN -->
            <form method="GET" class="filter-bar">
                <div class="filter-group">
                    <input type="text" name="search" class="filter-inp"
                        placeholder="Cari No. Est, Pelanggan, Ruangan..."
                        value="<?= htmlspecialchars( $search ) ?>" style="width: 260px;">
                    <input type="month" name="bulan" class="filter-inp"
                        value="<?= htmlspecialchars( $bulanFilter ) ?>">
                    <button type="submit" class="btn-wizard btn-next"
                        style="padding: 8px 16px; font-size:12px;">Filter</button>
                    <?php if ($search !== '' || $bulanFilter !== ''): ?>
                    <a href="riwayat_estimasi.php"
                        style="font-size: 12px; color: #dc2626; text-decoration:none;">Reset</a>
                    <?php endif; ?>
                </div>
                <div style="font-size: 12px; color: #64748b;">
                    Total Arsip: <strong><?= count($riwayatList) ?> data</strong>
                </div>
            </form>

            <!-- KARTU TABEL RIWAYAT -->
            <div class="card-box">
                <h3 class="box-title">Arsip Perhitungan Material & Biaya</h3>
                <div class="table-responsive">
                    <table class="table-riwayat">
                        <thead>
                            <tr>
                                <th>No. Ref</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Ruangan</th>
                                <th>Jenis Karpet</th>
                                <th>Arah & Metode</th>
                                <th>Luas & Waste</th>
                                <th>Total Biaya</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($riwayatList)): ?>
                            <tr>
                                <td colspan="9"
                                    style="text-align: center; color: #94a3b8; padding: 30px;">
                                    Belum ada riwayat estimasi yang tersimpan sesuai
                                    filter.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($riwayatList as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars( $row['kode_estimasi'] ) ?></strong></td>
                                <td><?= date( 'd/m/Y', strtotime($row['tanggal_estimasi']) ) ?></td>
                                <td><?= htmlspecialchars( $row['nama_pelanggan'] ) ?></td>
                                <td><?= htmlspecialchars( $row['nama_ruangan'] ) ?></td>
                                <td><?= htmlspecialchars( $row['nama_karpet'] ) ?></td>
                                <td>
                                    <?= htmlspecialchars( $row['arah_pemasangan'] ) ?><br>
                                    <span class="badge-status <?= strtolower( $row['metode_pemasangan'] ?? 'disambung' ) ?>">
                                        <?= htmlspecialchars( $row['metode_pemasangan'] ?? 'Disambung' ) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= number_format( $row['total_luas'], 1 ) ?> m²<br>
                                    <small style="color: #ef4444;">Waste:
                                        <?= number_format( $row['luas_waste'], 1 ) ?> m²</small>
                                </td>
                                <td><strong style="color: #0f172a;">Rp
                                        <?= number_format( $row['total_biaya'], 0, ',', '.' ) ?></strong></td>
                                <td>
                                    <div class="action-group">
                                        <a href="cetak_estimasi.php?id=<?= $row[ 'id_estimasi' ] ?>"
                                            target="_blank"
                                            class="btn-icon-action btn-pdf"
                                            title="Cetak / Unduh PDF Penawaran"><svg
                                                class="ui-icon" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor"
                                                stroke-width="1.7" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true">
                                                <path
                                                    d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5" />
                                            </svg> PDF</a>
                                        <form method="post" onsubmit="return confirm('Hapus penawaran ini beserta seluruh estimasinya?')">
                                            <input type="hidden" name="csrf" value="<?= quotation_token() ?>">
                                            <button type="submit" name="hapus" value="<?= (int) $row['id_estimasi'] ?>" class="btn-icon-action btn-del-history" title="Hapus Arsip"><svg class="ui-icon"
                                                viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="1.7"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true">
                                                <path
                                                    d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" />
                                            </svg></button></form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div>Menampilkan data terverifikasi sistem Integer Linear Programming
                        CV. Plastik Indah.</div>
                    <div>Status Data: <span class="badge-status optimal">Optimal / Sinkron
                            Database</span></div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
