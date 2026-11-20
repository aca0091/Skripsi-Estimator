<?php
// laporan.php
require_once __DIR__ . '/require.php';
check_auth();

// Filter parameter
$tglMulai     = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tglSelesai   = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$filterKarpet = isset($_GET['id_karpet']) ? (int)$_GET['id_karpet'] : 0;
$filterMetode = isset($_GET['metode']) ? $_GET['metode'] : '';

// Data dropdown karpet
$stmtKarpet = $pdo->query("SELECT * FROM tb_karpet ORDER BY nama_karpet ASC");
$karpets = $stmtKarpet->fetchAll();

// Query laporan agregasi
$sql = "
    SELECT e.*, r.nama_ruangan, r.nama_pelanggan, k.nama_karpet, k.lebar_roll, k.harga_per_m_lari, u.nama_lengkap AS nama_admin
    FROM tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    JOIN tb_karpet k ON e.id_karpet = k.id_karpet
    JOIN tb_user u ON e.id_user = u.id_user
    WHERE e.tanggal_estimasi BETWEEN ? AND ?
";
$params = [$tglMulai, $tglSelesai];

if ($filterKarpet > 0) {
    $sql .= " AND e.id_karpet = ?";
    $params[] = $filterKarpet;
}

if ($filterMetode !== '') {
    $sql .= " AND e.metode_pemasangan = ?";
    $params[] = $filterMetode;
}

$sql .= " ORDER BY e.tanggal_estimasi DESC, e.id_estimasi DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dataLaporan = $stmt->fetchAll();

// Hitung total akumulasi
$totProyek     = count($dataLaporan);
$totMeterLari  = 0;
$totLuasBersih = 0;
$totWaste      = 0;
$totBiaya      = 0;

foreach ($dataLaporan as $row) {
    $totMeterLari  += $row['total_meter_lari'];
    $totLuasBersih += $row['total_luas'];
    $totWaste      += $row['luas_waste'];
    $totBiaya      += $row['total_biaya'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Estimasi - Carpet Estimator</title>
    <link rel="stylesheet" href="assets/css/dash.css">
    <link rel="stylesheet" href="assets/css/laporan.css">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">📐</div>
                <div class="brand-title">CARPET ESTIMATOR</div>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link"><span class="icon">🏠</span> <span>Dashboard</span></a>
                <a href="data_karpet.php" class="nav-link"><span class="icon">📜</span> <span>Data Karpet</span></a>
                <a href="data_ruangan.php" class="nav-link"><span class="icon">🚪</span> <span>Data Ruangan</span></a>
                <a href="estimasi.php" class="nav-link"><span class="icon">➗</span> <span>Estimasi</span></a>
                <a href="riwayat_estimasi.php" class="nav-link"><span class="icon">🕒</span> <span>Riwayat Estimasi</span></a>
                <a href="laporan.php" class="nav-link active"><span class="icon">📊</span> <span>Laporan</span></a>
                <a href="pengaturan.php" class="nav-link"><span class="icon">⚙️</span> <span>Pengaturan</span></a>
            </nav>
            <div class="user-profile">
                <div class="avatar">👤</div>
                <div class="profile-info">
                    <div class="p-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin 1') ?></div>
                    <div class="p-email"><?= htmlspecialchars($_SESSION['user_email'] ?? 'admin1@gmail.com') ?></div>
                </div>
            </div>
            <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');" title="Keluar / Logout" style="background: #ef4444; color: #fff; text-decoration: none; padding: 6px 12px; border - radius: 4px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
            <span>🚪</span> Log Out </a>
        </aside>

        <!-- MAIN BODY -->
        <main class="main-body">
            <h1 class="main-title">Laporan Estimasi & Optimasi Material</h1>
            <p class="breadcrumb">Beranda / Laporan / Rekapitulasi Proyek CV. Plastik Indah</p>

            <!-- FORM FILTER PERIODE & KRITERIA -->
            <div class="report-filter-card">
                <form method="GET" class="filter-form-grid">
                    <div class="filter-item">
                        <label>Periode Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tglMulai) ?>" required>
                    </div>
                    <div class="filter-item">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tglSelesai) ?>" required>
                    </div>
                    <div class="filter-item">
                        <label>Katalog Karpet</label>
                        <select name="id_karpet">
                            <option value="0">-- Semua Karpet --</option>
                            <?php foreach ($karpets as $k): ?>
                                <option value="<?= $k['id_karpet'] ?>" <?= ($filterKarpet == $k['id_karpet']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_karpet']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label>Metode Pemasangan</label>
                        <select name="metode">
                            <option value="">-- Semua Metode --</option>
                            <option value="Disambung" <?= ($filterMetode === 'Disambung') ? 'selected' : '' ?>>Di-Sambung Saja</option>
                            <option value="Permanen" <?= ($filterMetode === 'Permanen') ? 'selected' : '' ?>>Pasang Permanen</option>
                        </select>
                    </div>
                    <div class="btn-filter-group">
                        <button type="submit" class="btn-filter btn-filter-submit">Filter Data</button>
                        <a href="laporan.php" class="btn-filter btn-filter-reset">Reset</a>
                    </div>
                </form>
            </div>

            <!-- METRIK RINGKASAN REKAP -->
            <div class="rekap-summary-grid">
                <div class="rekap-card">
                    <div class="rekap-label">Total Proyek Estimasi</div>
                    <div class="rekap-val"><?= number_format($totProyek) ?> Proyek</div>
                    <div class="rekap-sub">Tercatat dalam periode terpilih</div>
                </div>
                <div class="rekap-card">
                    <div class="rekap-label">Kebutuhan Bahan Roll</div>
                    <div class="rekap-val" style="color: #2563eb;"><?= number_format($totMeterLari, 1) ?> m</div>
                    <div class="rekap-sub">Total meter lari pemotongan roll</div>
                </div>
                <div class="rekap-card">
                    <div class="rekap-label">Total Luas Bersih</div>
                    <div class="rekap-val" style="color: #10b981;"><?= number_format($totLuasBersih, 1) ?> m²</div>
                    <div class="rekap-sub">Sisa bahan (waste): <?= number_format($totWaste, 1) ?> m²</div>
                </div>
                <div class="rekap-card">
                    <div class="rekap-label">Akumulasi Nilai Estimasi</div>
                    <div class="rekap-val" style="color: #a31d24;">Rp <?= number_format($totBiaya, 0, ',', '.') ?></div>
                    <div class="rekap-sub">Total estimasi biaya neto</div>
                </div>
            </div>

            <!-- TABEL DATA LAPORAN -->
            <div class="card-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h3 class="box-title" style="margin-bottom: 0;">Rincian Transaksi Estimasi</h3>
                    <a href="cetak_laporan.php?tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>&id_karpet=<?= $filterKarpet ?>&metode=<?= urlencode($filterMetode) ?>" target="_blank" class="btn-print-report">
                        🖨️ Cetak Rekapitulasi Laporan (PDF)
                    </a>
                </div>

                <div class="table-report-wrapper">
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>No. Estimasi</th>
                                <th>Tanggal</th>
                                <th>Pelanggan / Proyek</th>
                                <th>Ruangan</th>
                                <th>Karpet Roll</th>
                                <th>Arah & Metode</th>
                                <th>Kebutuhan Roll</th>
                                <th>Sambung / Obras</th>
                                <th>Sisa (Waste)</th>
                                <th style="text-align: right;">Total Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataLaporan)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; color: #94a3b8; padding: 30px;">
                                        Tidak ditemukan data estimasi pada rentang periode yang dipilih.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($dataLaporan as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($row['kode_estimasi']) ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal_estimasi'])) ?></td>
                                        <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_ruangan']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_karpet']) ?></td>
                                        <td>
                                            <span class="badge-tag <?= ($row['arah_pemasangan'] === 'Vertikal') ? 'vert' : 'horiz' ?>">
                                                <?= htmlspecialchars($row['arah_pemasangan']) ?>
                                            </span>
                                            <span class="badge-tag <?= ($row['metode_pemasangan'] === 'Permanen') ? 'perm' : 'seam' ?>">
                                                <?= htmlspecialchars($row['metode_pemasangan']) ?>
                                            </span>
                                        </td>
                                        <td><strong><?= number_format($row['total_meter_lari'], 1) ?> m lari</strong></td>
                                        <td>
                                            <?php if ($row['metode_pemasangan'] === 'Disambung'): ?>
                                                <?= number_format($row['total_sambungan'], 1) ?> m / <?= number_format($row['total_obras'], 1) ?> m
                                            <?php else: ?>
                                                <em style="color:#64748b;">Full Lem</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><span style="color: #ef4444;"><?= number_format($row['luas_waste'], 1) ?> m²</span></td>
                                        <td style="text-align: right; font-weight: 700;">
                                            Rp <?= number_format($row['total_biaya'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" style="text-align: right;">TOTAL KESELURUHAN:</td>
                                <td><?= number_format($totMeterLari, 1) ?> m lari</td>
                                <td>-</td>
                                <td><?= number_format($totWaste, 1) ?> m²</td>
                                <td style="text-align: right; color: #a31d24;">Rp <?= number_format($totBiaya, 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>