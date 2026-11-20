<?php
// data_ruangan.php
require_once __DIR__ . '/require.php';
check_auth();

// Handle Tambah Data Ruangan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_ruangan'])) {
    $namaPelanggan = trim($_POST['nama_pelanggan']);
    $namaRuangan   = trim($_POST['nama_ruangan']);
    $bentukRuangan = $_POST['bentuk_ruangan'];
    $panjang       = (float)$_POST['panjang'];
    $lebar         = (float)$_POST['lebar'];
    $jumlahPilar   = (int)$_POST['jumlah_pilar'];
    $luasObjek     = (float)$_POST['luas_objek'];

    // Perhitungan Geometris Otomatis
    $luasKotor  = $panjang * $lebar;
    $luasBersih = max(0, $luasKotor - $luasObjek);

    // Upload Sketsa Layout (Opsional)
    $fileName = null;
    if (isset($_FILES['foto_layout']) && $_FILES['foto_layout']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['foto_layout']['name'], PATHINFO_EXTENSION);
        $fileName = 'layout_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['foto_layout']['tmp_name'], $uploadDir . $fileName);
    }

    $stmt = $pdo->prepare("
        INSERT INTO tb_ruangan (nama_ruangan, nama_pelanggan, bentuk_ruangan, panjang, lebar, jumlah_pilar, luas_objek, luas_kotor, luas_bersih, file_layout) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$namaRuangan, $namaPelanggan, $bentukRuangan, $panjang, $lebar, $jumlahPilar, $luasObjek, $luasKotor, $luasBersih, $fileName]);

    header('Location: data_ruangan.php');
    exit;
}

// Handle Hapus Ruangan
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtDel = $pdo->prepare("DELETE FROM tb_ruangan WHERE id_ruangan = ?");
    $stmtDel->execute([$idHapus]);
    header('Location: data_ruangan.php');
    exit;
}

// Ambil daftar ruangan
$ruanganList = $pdo->query("SELECT * FROM tb_ruangan ORDER BY id_ruangan DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Ruangan - Carpet Estimator</title>
    <link rel="stylesheet" href="assets/css/dash.css">
    <link rel="stylesheet" href="assets/css/data_ruangan.css">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR LENGKAP -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">📐</div>
                <div class="brand-title">CARPET ESTIMATOR</div>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <span class="icon">🏠</span> <span>Dashboard</span>
                </a>
                <a href="data_karpet.php" class="nav-link">
                    <span class="icon">📜</span> <span>Data Karpet</span>
                </a>
                <a href="data_ruangan.php" class="nav-link active">
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

        <!-- MAIN DATA RUANGAN -->
        <main class="main-body">
            <h1 class="main-title">Data Ruangan Pelanggan</h1>
            <p class="breadcrumb">Beranda / Master Data / Data Ruangan Proyek</p>

            <div class="ruangan-layout-grid">
                <!-- FORM INPUT RUANGAN -->
                <div class="card-box">
                    <h3 class="box-title">Tambah Data Ruangan</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="tambah_ruangan" value="1">

                        <div class="form-field">
                            <label>Nama Pelanggan / Perusahaan</label>
                            <input type="text" name="nama_pelanggan" placeholder="Contoh: PT. Coco Mandiri" required>
                        </div>

                        <div class="form-field">
                            <label>Nama Ruangan</label>
                            <input type="text" name="nama_ruangan" placeholder="Contoh: Ruang Meeting Lantai 2" required>
                        </div>

                        <div class="form-field">
                            <label>Bentuk Ruangan</label>
                            <select name="bentuk_ruangan" id="inBentuk">
                                <option value="Persegi Panjang">Persegi Panjang (Standar)</option>
                                <option value="L-Shape (Berlekuk)">Bentuk L (Memiliki Lekukan)</option>
                                <option value="Tidak Beraturan">Bentuk Poligon / Tidak Beraturan</option>
                            </select>
                        </div>

                        <div class="form-field-row">
                            <div class="form-field">
                                <label>Panjang (m)</label>
                                <input type="number" step="0.01" name="panjang" id="inPanjang" value="8.00" required oninput="calcPreview()">
                            </div>
                            <div class="form-field">
                                <label>Lebar (m)</label>
                                <input type="number" step="0.01" name="lebar" id="inLebar" value="5.00" required oninput="calcPreview()">
                            </div>
                        </div>

                        <div class="form-field-row">
                            <div class="form-field">
                                <label>Jumlah Pilar</label>
                                <input type="number" name="jumlah_pilar" id="inPilar" value="0" min="0" oninput="calcPreview()">
                            </div>
                            <div class="form-field">
                                <label>Luas Objek Kosong (m²)</label>
                                <input type="number" step="0.01" name="luas_objek" id="inObjek" value="0.00" oninput="calcPreview()">
                            </div>
                        </div>

                        <!-- LIVE PREVIEW KALKULASI LUAS -->
                        <div class="live-calc-box">
                            <div>
                                <div class="item">Luas Kotor Total:</div>
                                <div class="val" id="prevKotor">40.00 m²</div>
                            </div>
                            <div>
                                <div class="item">Luas Bersih Efektif:</div>
                                <div class="val" style="color:#2563eb;" id="prevBersih">40.00 m²</div>
                            </div>
                        </div>

                        <!-- UPLOAD FOTO DENAH -->
                        <div class="form-field">
                            <label>Upload Sketsa / Citra Layout 2D (Tampak Atas)</label>
                            <div class="upload-box-small" onclick="document.getElementById('fileInp').click()">
                                <span style="font-size:24px;">📐</span>
                                <div style="font-size:12px; font-weight:600; color:#334155;" id="lblUpload">Klik untuk pilih sketsa layout 2D</div>
                                <input type="file" name="foto_layout" id="fileInp" accept="image/*" style="display:none;" onchange="showFileName(this)">
                            </div>
                        </div>

                        <button type="submit" class="btn-primary-ruang">Simpan Data Ruangan</button>
                    </form>
                </div>

                <!-- DAFTAR TABEL RUANGAN -->
                <div class="card-box">
                    <h3 class="box-title">Daftar Ruangan Proyek Terdaftar</h3>
                    <div class="table-responsive">
                        <table class="table-custom-ruang">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Ruangan</th>
                                    <th>Bentuk</th>
                                    <th>Dimensi (P × L)</th>
                                    <th>Luas Bersih</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ruanganList)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:#94a3b8; padding:20px;">Belum ada data ruangan tersimpan.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ruanganList as $r): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($r['nama_pelanggan']) ?></strong></td>
                                            <td><?= htmlspecialchars($r['nama_ruangan']) ?></td>
                                            <td><span class="shape-badge"><?= htmlspecialchars($r['bentuk_ruangan'] ?? 'Persegi Panjang') ?></span></td>
                                            <td><?= number_format($r['panjang'], 2) ?> × <?= number_format($r['lebar'], 2) ?> m</td>
                                            <td style="color:#2563eb; font-weight:700;"><?= number_format($r['luas_bersih'], 2) ?> m²</td>
                                            <td>
                                                <a href="carpet.php?id_ruangan=<?= $r['id_ruangan'] ?>" class="btn-act-est" title="Hitung Estimasi ILP">Estimasi</a>
                                                <a href="data_ruangan.php?hapus=<?= $r['id_ruangan'] ?>" class="btn-del" onclick="return confirm('Yakin ingin menghapus data ruangan ini?')" title="Hapus">🗑️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function calcPreview() {
            const p = parseFloat(document.getElementById('inPanjang').value) || 0;
            const l = parseFloat(document.getElementById('inLebar').value) || 0;
            const obj = parseFloat(document.getElementById('inObjek').value) || 0;

            const kotor = p * l;
            const bersih = Math.max(0, kotor - obj);

            document.getElementById('prevKotor').innerText = kotor.toFixed(2) + ' m²';
            document.getElementById('prevBersih').innerText = bersih.toFixed(2) + ' m²';
        }

        function showFileName(input) {
            if (input.files && input.files[0]) {
                document.getElementById('lblUpload').innerText = "File terpilih: " + input.files[0].name;
            }
        }
    </script>
</body>
</html>