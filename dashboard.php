<?php
// dashboard.php
require_once __DIR__ . '/require.php';
check_auth();

// 1. Total Estimasi yang telah dibuat
$stmtTotalEst = $pdo->query("SELECT COUNT(*) AS total FROM tb_estimasi");
$totalEstimasi = (int)($stmtTotalEst->fetch()['total'] ?? 0);

// 2. Total Data Ruangan
$stmtTotalR = $pdo->query("SELECT COUNT(*) AS total FROM tb_ruangan");
$totalRuangan = (int)($stmtTotalR->fetch()['total'] ?? 0);

// 3. Total Jenis Karpet
$stmtTotalK = $pdo->query("SELECT COUNT(*) AS total FROM tb_karpet");
$totalKarpet = (int)($stmtTotalK->fetch()['total'] ?? 0);

// 4. Total Nilai Estimasi Biaya Keseluruhan
$stmtTotalBiaya = $pdo->query("SELECT COALESCE(SUM(total_biaya), 0) AS total FROM tb_estimasi");
$totalBiayaAll = (float)($stmtTotalBiaya->fetch()['total'] ?? 0);

// 5. Ringkasan Estimasi Bulan Berjalan (Dinamis Berdasarkan Bulan & Tahun Saat Ini)
$blnIni = (int)date('m');
$thnIni = (int)date('Y');
$stmtBulanIni = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_luas), 0) AS sum_luas,
        COALESCE(SUM(total_sambungan), 0) AS sum_sambungan,
        COALESCE(SUM(total_obras), 0) AS sum_obras,
        COALESCE(SUM(total_biaya), 0) AS sum_biaya
    FROM tb_estimasi 
    WHERE MONTH(tanggal_estimasi) = ? AND YEAR(tanggal_estimasi) = ?
");
$stmtBulanIni->execute([$blnIni, $thnIni]);
$rekapBulan = $stmtBulanIni->fetch();

// Nama bulan dalam Bahasa Indonesia untuk header kartu
$namaBulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$labelBulanIni = $namaBulanIndo[$blnIni] . ' ' . $thnIni;

// 6. Data Estimasi Terbaru (Limit 3 Proyek Terakhir)
$stmtLatest = $pdo->query("
    SELECT e.kode_estimasi, e.total_biaya, e.tanggal_estimasi, r.nama_ruangan, r.nama_pelanggan
    FROM tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    ORDER BY e.id_estimasi DESC LIMIT 3
");
$estimasiTerbaru = $stmtLatest->fetchAll();

// 7. Data Grafik Garis (Dinamis: 6 Bulan Terakhir Berjalan)
$chartLabels = [];
$chartCounts = [];

for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-$i month");
    $m = (int)date('m', $time);
    $y = (int)date('Y', $time);
    $chartLabels[] = substr($namaBulanIndo[$m], 0, 3);

    $stmtC = $pdo->prepare("SELECT COUNT(*) AS total FROM tb_estimasi WHERE MONTH(tanggal_estimasi) = ? AND YEAR(tanggal_estimasi) = ?");
    $stmtC->execute([$m, $y]);
    $chartCounts[] = (int)($stmtC->fetch()['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DASHBOARD - Carpet Estimator</title>
    <link rel="stylesheet" href="assets/css/dash.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="dashboard.php" class="nav-link active">
                    <span class="icon">🏠</span> <span>Dashboard</span>
                </a>
                <a href="data_karpet.php" class="nav-link">
                    <span class="icon">📜</span> <span>Data Karpet</span>
                </a>
                <a href="data_ruangan.php" class="nav-link">
                    <span class="icon">🚪</span> <span>Data Ruangan</span>
                </a>
                <a href="estimasi.php" class="nav-link">
                    <span class="icon">➗</span> <span>Estimasi</span>
                </a>
                <a href="riwayat_estimasi.php" class="nav-link">
                    <span class="icon">🕒</span> <span>Riwayat Estimasi</span>
                </a>
                <a href="laporan.php" class="nav-link">
                    <span class="icon">📊</span> <span>Laporan</span>
                </a>
                <a href="pengaturan.php" class="nav-link">
                    <span class="icon">⚙️</span> <span>Pengaturan</span>
                </a>
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

        <!-- MAIN CONTENT -->
        <main class="main-body">
            <div class="header-section">
                <h1 class="main-title">DASHBOARD</h1>
                <p class="breadcrumb">Beranda / Dashboard</p>
            </div>

            <!-- 4 KARTU METRIK UTAMA (SINKRON DATABASE) -->
            <div class="metric-row">
                <div class="stat-card">
                    <div class="stat-label">Total Estimasi</div>
                    <div class="stat-number"><?= $totalEstimasi ?></div>
                    <div class="stat-desc">Total estimasi yang dibuat</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Ruangan</div>
                    <div class="stat-number"><?= $totalRuangan ?></div>
                    <div class="stat-desc">Total data ruangan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Jenis Karpet</div>
                    <div class="stat-number"><?= $totalKarpet ?></div>
                    <div class="stat-desc">Total jenis karpet</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Estimasi Biaya</div>
                    <div class="stat-number cost">Rp <?= number_format($totalBiayaAll, 0, ',', '.') ?></div>
                    <div class="stat-desc">Total estimasi biaya yang telah dibuat</div>
                </div>
            </div>

            <!-- BARIS SPLIT: GRAFIK & ESTIMASI TERBARU -->
            <div class="content-split-row">
                <div class="card-box chart-box">
                    <h3 class="box-title">Grafik Estimasi (6 Bulan Terakhir)</h3>
                    <div class="chart-wrapper">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <div class="card-box recent-box">
                    <h3 class="box-title">Estimasi Terbaru</h3>
                    <div class="recent-list">
                        <?php if (empty($estimasiTerbaru)): ?>
                            <div style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">
                                Belum ada estimasi yang dibuat.
                            </div>
                        <?php else: ?>
                            <?php foreach ($estimasiTerbaru as $row): ?>
                                <div class="recent-item">
                                    <div class="left-info">
                                        <div class="est-code"><?= htmlspecialchars($row['kode_estimasi']) ?></div>
                                        <div class="est-room"><?= htmlspecialchars($row['nama_ruangan']) ?> (<?= htmlspecialchars($row['nama_pelanggan']) ?>)</div>
                                        <div class="est-date"><?= date('d F Y', strtotime($row['tanggal_estimasi'])) ?></div>
                                    </div>
                                    <div class="est-cost">
                                        Rp <?= number_format($row['total_biaya'], 0, ',', '.') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KARTU RINGKASAN BAWAH (DINAMIS BULAN BERJALAN) -->
            <div class="card-box summary-box">
                <h3 class="box-title">Ringkasan Total Estimasi per Bulan ini (<?= $labelBulanIni ?>)</h3>
                <div class="summary-columns">
                    <div class="sum-col">
                        <div class="s-label">Total Luas Bersih</div>
                        <div class="s-val"><?= number_format($rekapBulan['sum_luas'], 1) ?> m²</div>
                    </div>
                    <div class="sum-col">
                        <div class="s-label">Total Sambungan</div>
                        <div class="s-val"><?= number_format($rekapBulan['sum_sambungan'], 1) ?> m</div>
                    </div>
                    <div class="sum-col">
                        <div class="s-label">Total Obras</div>
                        <div class="s-val"><?= number_format($rekapBulan['sum_obras'], 1) ?> m</div>
                    </div>
                    <div class="sum-col">
                        <div class="s-label">Total Biaya Estimasi</div>
                        <div class="s-val s-biaya">Rp <?= number_format($rekapBulan['sum_biaya'], 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Script Render Grafik Sinkron 6 Bulan -->
    <script>
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const labelsBulan = <?= json_encode($chartLabels) ?>;
        const dataEstimasi = <?= json_encode($chartCounts) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labelsBulan,
                datasets: [{
                    label: 'Jumlah Estimasi',
                    data: dataEstimasi,
                    borderColor: '#4285F4',
                    backgroundColor: 'rgba(66, 133, 244, 0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#4285F4',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 10 } },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        ticks: { font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>