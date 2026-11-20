<?php
// data_karpet.php
require_once __DIR__ . '/require.php';
check_auth();

// Tambah Karpet Baru (Harga & Stok dalam Meter Panjang / Meter Lari)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_karpet'])) {
    $kode   = trim($_POST['kode_karpet']);
    $nama   = trim($_POST['nama_karpet']);
    $lebar  = (float)$_POST['lebar_roll'];
    $motif  = $_POST['motif'];
    $harga  = (float)$_POST['harga_per_m_lari'];
    $stok   = (float)$_POST['stok_meter_lari'];

    $stmt = $pdo->prepare("INSERT INTO tb_karpet (kode_karpet, nama_karpet, lebar_roll, motif, harga_per_m_lari, stok_meter_lari) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$kode, $nama, $lebar, $motif, $harga, $stok]);
    header('Location: data_karpet.php');
    exit;
}

$karpetList = $pdo->query("SELECT * FROM tb_karpet ORDER BY id_karpet DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Karpet - CV. Plastik Indah</title>
    <link rel="stylesheet" href="assets/css/dash.css">
    <link rel="stylesheet" href="assets/css/data_karpet.css">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">📐</div>
                <div class="brand-title">CARPET ESTIMATOR</div>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <span class="icon">🏠</span> <span>Dashboard</span>
                </a>
                <a href="data_karpet.php" class="nav-link active">
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

        <main class="main-body">
            <h1 class="main-title">Data Master Karpet</h1>
            <p class="breadcrumb">Beranda / Master Data / Data Karpet Roll</p>

            <div class="karpet-grid">
                <!-- Form Tambah -->
                <div class="card-box">
                    <h3 class="box-title">Tambah Jenis Karpet Roll</h3>
                    <form method="POST">
                        <input type="hidden" name="tambah_karpet" value="1">
                        <div class="form-group-k">
                            <label>Kode Karpet</label>
                            <input type="text" name="kode_karpet" placeholder="Misal: KRP-05" required>
                        </div>
                        <div class="form-group-k">
                            <label>Nama Karpet</label>
                            <input type="text" name="nama_karpet" placeholder="Misal: Santana Blue Pattern" required>
                        </div>
                        <div class="form-group-k">
                            <label>Lebar Roll (Meter)</label>
                            <input type="number" step="0.1" name="lebar_roll" value="4.0" required>
                        </div>
                        <div class="form-group-k">
                            <label>Motif Karpet</label>
                            <select name="motif" required>
                                <option value="Polos">Polos</option>
                                <option value="Bermotif">Bermotif</option>
                            </select>
                        </div>
                        <div class="form-group-k">
                            <label>Harga per Meter Panjang / Meter Lari (Rp)</label>
                            <input type="number" name="harga_per_m_lari" placeholder="Contoh: 480000" required>
                        </div>
                        <div class="form-group-k">
                            <label>Stok Meter Lari Tersedia (m)</label>
                            <input type="number" step="0.1" name="stok_meter_lari" value="200" required>
                        </div>
                        <button type="submit" class="btn-add">Simpan Data Karpet</button>
                    </form>
                </div>

                <!-- Tabel Data -->
                <div class="card-box">
                    <h3 class="box-title">Daftar Karpet CV. Plastik Indah</h3>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Karpet</th>
                                <th>Lebar</th>
                                <th>Motif</th>
                                <th>Harga / Meter Lari</th>
                                <th>Stok Tersedia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($karpetList as $k): ?>
                                <?php 
                                // Mendukung nama kolom baru maupun lama agar tidak error
                                $hargaLari = $k['harga_per_m_lari'] ?? $k['harga_per_m2'] ?? 0;
                                $stokLari  = $k['stok_meter_lari']  ?? $k['stok_m2'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($k['kode_karpet']) ?></strong></td>
                                <td><?= htmlspecialchars($k['nama_karpet']) ?></td>
                                <td><?= number_format($k['lebar_roll'], 2) ?> m</td>
                                <td><span class="badge-m <?= strtolower($k['motif']) ?>"><?= htmlspecialchars($k['motif']) ?></span></td>
                                <td>Rp <?= number_format($hargaLari, 0, ',', '.') ?> / m</td>
                                <td><strong><?= number_format($stokLari, 1) ?> m lari</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>