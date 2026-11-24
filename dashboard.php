<?php
// dashboard.php
require_once __DIR__ . '/auth.php';
check_page_access('dashboard.php');
require_once __DIR__ . '/require.php';

// Gunakan relasi yang sama dengan laporan agar data tidak lengkap tidak dihitung.
$estimasiFrom = 'tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    JOIN tb_karpet k ON e.id_karpet = k.id_karpet
    JOIN tb_user u ON e.id_user = u.id_user';

// 1. Total Estimasi yang telah dibuat
$stmtTotalEst = $pdo->query("SELECT COUNT(*) AS total FROM $estimasiFrom");
$totalEstimasi = (int)($stmtTotalEst->fetch()['total'] ?? 0);

// 2. Total Data Ruangan
$stmtTotalR = $pdo->query("SELECT COUNT(DISTINCT e.id_ruangan) AS total FROM $estimasiFrom");
$totalRuangan = (int)($stmtTotalR->fetch()['total'] ?? 0);

// 3. Total Jenis Karpet
$stmtTotalK = $pdo->query("SELECT COUNT(*) AS total FROM tb_karpet");
$totalKarpet = (int)($stmtTotalK->fetch()['total'] ?? 0);

// 4. Total Nilai Estimasi Biaya Keseluruhan
$stmtTotalBiaya = $pdo->query("SELECT COALESCE(SUM(total_biaya), 0) AS total FROM $estimasiFrom");
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
    FROM $estimasiFrom
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
    FROM $estimasiFrom
    ORDER BY e.id_estimasi DESC LIMIT 3
");
$estimasiTerbaru = $stmtLatest->fetchAll();

// 7. Data Grafik Garis (Dinamis: 6 Bulan Terakhir Berjalan)
$chartLabels = [];
$chartCounts = [];

for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("first day of -$i month");
    $m = (int)date('m', $time);
    $y = (int)date('Y', $time);
    $chartLabels[] = substr($namaBulanIndo[$m], 0, 3);

    $stmtC = $pdo->prepare("SELECT COUNT(*) AS total FROM $estimasiFrom WHERE MONTH(tanggal_estimasi) = ? AND YEAR(tanggal_estimasi) = ?");
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
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/dash.css?v=20261123-sidebar2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/app-theme.css?v=20261123-1">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <?php $activePage = 'dashboard'; require __DIR__ . '/includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-body">
<?php require __DIR__ . '/includes/app_toolbar.php'; ?>
            <div class="header-section">
                <h1 class="main-title">Dashboard</h1>
                <p class="breadcrumb">Ringkasan aktivitas proyek dan performa estimasi perusahaan.</p>
            </div>

            <!-- 4 KARTU METRIK UTAMA (SINKRON DATABASE) -->
            <div class="metric-row">
                <div class="stat-card">
                    <div class="metric-icon"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5"/></svg></div><div class="stat-label">Total Estimasi</div>
                    <div class="stat-number"><?= $totalEstimasi ?></div>
                    <div class="stat-desc">Total estimasi yang dibuat</div>
                </div>
                <div class="stat-card">
                    <div class="metric-icon"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h1m6 0h1M8 11h1m6 0h1"/></svg></div><div class="stat-label">Total Ruangan</div>
                    <div class="stat-number"><?= $totalRuangan ?></div>
                    <div class="stat-desc">Total data ruangan</div>
                </div>
                <div class="stat-card">
                    <div class="metric-icon"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></div><div class="stat-label">Jenis Karpet</div>
                    <div class="stat-number"><?= $totalKarpet ?></div>
                    <div class="stat-desc">Total jenis karpet</div>
                </div>
                <div class="stat-card">
                    <div class="metric-icon"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 8V4l14-2v3M21 10h-7v5h7M17 12.5h.01"/></svg></div><div class="stat-label">Total Estimasi Biaya</div>
                    <div class="stat-number cost">Rp <?= number_format($totalBiayaAll, 0, ',', '.') ?></div>
                    <div class="stat-desc">Total estimasi biaya yang telah dibuat</div>
                </div>
            </div>

            <!-- BARIS SPLIT: GRAFIK & ESTIMASI TERBARU -->
            <div class="content-split-row">
                <div class="card-box chart-box">
                    <h3 class="box-title">Aktivitas Estimasi</h3>
                    <div class="chart-wrapper">
                        <?php if (array_sum($chartCounts) > 0): ?>
                            <canvas id="monthlyChart"></canvas>
                        <?php else: ?>
                            <div style="height:100%;min-height:180px;display:flex;align-items:center;justify-content:center;color:#64748b;text-align:center;">Belum ada estimasi tersimpan dalam 6 bulan terakhir.</div>
                        <?php endif; ?>
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
                <h3 class="box-title">Ringkasan Bulanan · <?= $labelBulanIni ?></h3>
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
        const chartCanvas = document.getElementById('monthlyChart');
        if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
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
        }
    </script>
</body>
</html>