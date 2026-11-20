<?php
// estimasi.php
require_once __DIR__ . '/require.php';
check_auth();

// 1. Ambil Katalog Karpet
$stmtK = $pdo->query("SELECT * FROM tb_karpet ORDER BY nama_karpet ASC");
$karpetList = $stmtK->fetchAll();

// 2. Simpan Estimasi ke Basis Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_final_estimate') {
    $namaPelanggan   = trim($_POST['nama_pelanggan_custom'] ?? 'Umum');
    $namaRuangan     = trim($_POST['nama_ruangan_custom'] ?? 'Ruangan Baru');
    $namaObjekCustom = trim($_POST['nama_objek_custom'] ?? 'Objek');
    
    $panjangRuang    = (float)($_POST['panjang_ruang'] ?? 0);
    $lebarRuang      = (float)($_POST['lebar_ruang'] ?? 0);
    $luasObjek       = (float)($_POST['luas_objek'] ?? 0);
    $totalLuas       = (float)($_POST['total_luas_bersih'] ?? 0);
    
    $idKarpet        = (int)$_POST['id_karpet'];
    $arah            = $_POST['arah_pemasangan'] ?? 'Vertikal';
    $metodePasang    = $_POST['metode_pemasangan'] ?? 'Disambung';
    
    $totalMeterLari  = (float)($_POST['total_meter_lari'] ?? 0);
    $totalSambungan  = ($metodePasang === 'Permanen') ? 0 : (float)($_POST['total_sambungan'] ?? 0);
    $totalObras      = ($metodePasang === 'Permanen') ? 0 : (float)($_POST['total_obras'] ?? 0);
    $biayaLem        = ($metodePasang === 'Permanen') ? (float)($_POST['biaya_lem'] ?? 0) : 0;
    $biayaJasaPasang = ($metodePasang === 'Permanen') ? (float)($_POST['biaya_jasa_pasang'] ?? 0) : 0;
    $luasWaste       = (float)($_POST['luas_waste'] ?? 0);
    $totalBiaya      = (float)($_POST['total_biaya'] ?? 0);
    $idUser          = $_SESSION['user_id'] ?? 1;

    // A. Simpan data ruangan baru
    $luasKotor = ($panjangRuang > 0 && $lebarRuang > 0) ? ($panjangRuang * $lebarRuang) : $totalLuas;
    $stmtR = $pdo->prepare("
        INSERT INTO tb_ruangan (nama_ruangan, nama_pelanggan, panjang, lebar, luas_objek, luas_kotor, luas_bersih) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtR->execute([$namaRuangan, $namaPelanggan, $panjangRuang, $lebarRuang, $luasObjek, $luasKotor, $totalLuas]);
    $idRuanganBaru = $pdo->lastInsertId();

    // B. Buat kode transaksi estimasi
    $stmtMax = $pdo->query("SELECT MAX(id_estimasi) AS max_id FROM tb_estimasi");
    $nextId = ((int)($stmtMax->fetch()['max_id'] ?? 0)) + 1;
    $kodeEstimasi = "EST - " . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    // C. Simpan estimasi
    $stmtSave = $pdo->prepare("
        INSERT INTO tb_estimasi (
            kode_estimasi, id_ruangan, id_karpet, id_user, arah_pemasangan, metode_pemasangan, 
            total_luas, total_meter_lari, total_sambungan, total_obras, biaya_lem, biaya_jasa_pasang, 
            luas_waste, total_biaya, tanggal_estimasi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
    ");
    $stmtSave->execute([
        $kodeEstimasi, $idRuanganBaru, $idKarpet, $idUser, $arah, $metodePasang, 
        $totalLuas, $totalMeterLari, $totalSambungan, $totalObras, 
        $biayaLem, $biayaJasaPasang, $luasWaste, $totalBiaya
    ]);

    $lastId = $pdo->lastInsertId();

    if (isset($_POST['submit_type']) && $_POST['submit_type'] === 'save_and_print') {
        header("Location: cetak_estimasi.php?id=" . $lastId);
        exit;
    }

    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modul Estimasi Karpet - CV. Plastik Indah</title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/dash.css">
    <link rel="stylesheet" href="assets/css/estimasi.css">
    <style>
        .wizard-step { cursor: pointer; user-select: none; }
        .canvas-container-box {
            background: #0b0f19;
            border-radius: 10px;
            padding: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 320px;
            position: relative;
        }
        #layoutSimCanvas {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .objek-edit-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand" style="display: flex; align-items: center; gap: 12px; padding: 18px 16px;">
                <img src="assets/images/logo_estimasi.png" alt="Logo" style="height: 38px; width: auto; object-fit: contain;">
                <span style="font-size: 14px; font-weight: 700; color: #ffffff;">CARPET ESTIMATOR</span>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link"><span class="icon">🏠</span> <span>Dashboard</span></a>
                <a href="data_karpet.php" class="nav-link"><span class="icon">📜</span> <span>Data Karpet</span></a>
                <a href="data_ruangan.php" class="nav-link"><span class="icon">🚪</span> <span>Data Ruangan</span></a>
                <a href="estimasi.php" class="nav-link active">
                    <img src="assets/images/logo_estimasi.png" alt="Icon" style="width: 18px; height: 18px; object-fit: contain; vertical-align: middle;">
                    <span>Estimasi</span>
                </a>
                <a href="riwayat_estimasi.php" class="nav-link"><span class="icon">🕒</span> <span>Riwayat Estimasi</span></a>
                <a href="laporan.php" class="nav-link"><span class="icon">📊</span> <span>Laporan</span></a>
                <a href="pengaturan.php" class="nav-link"><span class="icon">⚙️</span> <span>Pengaturan</span></a>
            </nav>

            <div class="user-profile" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.08);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="avatar" style="width: 32px; height: 32px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center;">👤</div>
                    <div class="profile-info">
                        <div class="p-name" style="font-size: 12.5px; font-weight: 600; color: #fff;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
                        <div class="p-email" style="font-size: 10.5px; color: #94a3b8;"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                    </div>
                </div>
                <a href="logout.php" onclick="return confirm('Yakin ingin keluar?');" style="background:#ef4444; color:#fff; text-decoration:none; padding:5px 9px; border-radius:4px; font-size:11px; font-weight:600;">Log Out</a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-body">
            <h1 class="main-title">Modul Estimasi Karpet</h1>
            <p class="breadcrumb">Beranda / Estimasi / Citra Layout, Sambungan, Lem & Obras, serta Optimasi ILP</p>

            <!-- WIZARD BAR -->
            <div class="wizard-bar">
                <div class="wizard-step active" id="stepIndicator1" onclick="goToStep(1)">
                    <div class="step-num">1</div>
                    <span>Data Umum</span>
                </div>
                <div class="wizard-step" id="stepIndicator2" onclick="goToStep(2)">
                    <div class="step-num">2</div>
                    <span>Layout Ruangan</span>
                </div>
                <div class="wizard-step" id="stepIndicator3" onclick="goToStep(3)">
                    <div class="step-num">3</div>
                    <span>Pilih Karpet, Metode & Arah</span>
                </div>
                <div class="wizard-step" id="stepIndicator4" onclick="goToStep(4)">
                    <div class="step-num">4</div>
                    <span>Hasil & Cetak Penawaran</span>
                </div>
            </div>

            <!-- FORM ESTIMASI UTAMA -->
            <form id="mainEstimasiForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_final_estimate">
                <input type="hidden" name="submit_type" id="post_submit_type" value="save_only">

                <!-- Hidden Parameter ILP -->
                <input type="hidden" name="id_karpet" id="post_id_karpet">
                <input type="hidden" name="panjang_ruang" id="post_panjang">
                <input type="hidden" name="lebar_ruang" id="post_lebar">
                <input type="hidden" name="luas_objek" id="post_luas_objek">
                <input type="hidden" name="nama_objek_custom" id="post_nama_objek">
                <input type="hidden" name="arah_pemasangan" id="post_arah" value="Vertikal">
                <input type="hidden" name="metode_pemasangan" id="post_metode" value="Disambung">
                <input type="hidden" name="total_luas_bersih" id="post_luas_bersih">
                <input type="hidden" name="total_meter_lari" id="post_meter_lari">
                <input type="hidden" name="total_sambungan" id="post_sambungan">
                <input type="hidden" name="total_obras" id="post_obras">
                <input type="hidden" name="biaya_lem" id="post_biaya_lem">
                <input type="hidden" name="biaya_jasa_pasang" id="post_biaya_jasa_pasang">
                <input type="hidden" name="luas_waste" id="post_waste">
                <input type="hidden" name="total_biaya" id="post_biaya">

                <!-- TAHAP 1: DATA UMUM -->
                <div class="tab-content-panel active" id="panelStep1">
                    <div class="card-box">
                        <h3 class="box-title">Tahap 1: Formulir Data Umum Estimasi (Gambar 3.24)</h3>
                        
                        <div class="form-row-2">
                            <div>
                                <label class="form-lbl">Nama Pelanggan / Perusahaan <span style="color:#ef4444;">*</span></label>
                                <input type="text" name="nama_pelanggan_custom" id="txtPelanggan" class="form-ctrl" placeholder="Contoh: Sintia / Rumah Aca" required>
                            </div>
                            <div>
                                <label class="form-lbl">Nama Ruangan / Proyek <span style="color:#ef4444;">*</span></label>
                                <input type="text" name="nama_ruangan_custom" id="txtRuangan" class="form-ctrl" placeholder="Contoh: Bale Bali / Kamar Tamu" required>
                            </div>
                        </div>

                        <div class="form-row-2">
                            <div>
                                <label class="form-lbl">Tanggal Estimasi</label>
                                <input type="date" name="tanggal_estimasi" class="form-ctrl" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label class="form-lbl">Catatan Lapangan (Opsional)</label>
                                <input type="text" name="catatan_lapangan" class="form-ctrl" placeholder="Catatan estimasi untuk customer">
                            </div>
                        </div>

                        <label class="form-lbl">Upload Sketsa Denah 2D Ruangan <span style="color:#ef4444;">*</span></label>
                        <div class="upload-dropzone" onclick="document.getElementById('inputLayoutFile').click()">
                            <span style="font-size:36px;">📷</span>
                            <div style="font-weight:700; margin-top:6px; color:#1e293b;" id="txtUploadNama">Klik atau seret foto denah layout ke sini</div>
                            <div style="font-size:12px; color:#94a3b8;">Format sketsa tampak atas (JPG/PNG) sesuai Bab 3.7.1</div>
                            <input type="file" name="foto_layout" id="inputLayoutFile" accept="image/*" style="display:none;" onchange="handleImageFile(this)">
                        </div>

                        <div class="action-nav-buttons" style="display:flex; justify-content:flex-end;">
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(2)">Lanjut ke Deteksi Layout (Tahap 2) ➜</button>
                        </div>
                    </div>
                </div>

                <!-- TAHAP 2: EKSTRAKSI CITRA & DETEKSI OTOMATIS -->
                <div class="tab-content-panel" id="panelStep2">
                    <div class="card-box">
                        <h3 class="box-title">Tahap 2: Ekstraksi Bentuk, Dimensi & Keliling Ruangan (Gambar 3.25)</h3>
                        
                        <div class="canvas-container-box">
                            <canvas id="layoutSimCanvas" width="600" height="320"></canvas>
                        </div>

                        <!-- Status Badge -->
                        <div class="canvas-legend" style="display:flex; gap:10px; margin: 12px 0; flex-wrap:wrap;">
                            <span class="legend-badge wall" style="background:#dcfce7; color:#166534; padding:5px 10px; border-radius:4px; font-weight:600; font-size:11px;">🟢 Kontur Dinding Luar & Lekukan</span>
                            <span class="legend-badge obstacle" style="background:#fee2e2; color:#991b1b; padding:5px 10px; border-radius:4px; font-weight:600; font-size:11px;">🟥 Kontur Objek Terdeteksi</span>
                            <span class="legend-badge" style="background:#fef3c7; color:#92400e; padding:5px 10px; border-radius:4px; font-weight:600; font-size:11px;">🟡 Titik Vertex Sudut Poligon</span>
                        </div>

                        <!-- Parameter Ukuran Hasil Deteksi -->
                        <div class="form-row-3">
                            <div>
                                <label class="form-lbl">Panjang Ruang (m)</label>
                                <input type="number" step="0.01" id="dimPanjang" class="form-ctrl" value="10.00" oninput="runSimulasiDanHitung()">
                            </div>
                            <div>
                                <label class="form-lbl">Lebar Ruang (m)</label>
                                <input type="number" step="0.01" id="dimLebar" class="form-ctrl" value="6.00" oninput="runSimulasiDanHitung()">
                            </div>
                            <div>
                                <label class="form-lbl">Ukuran Masuk Lekukan (m)</label>
                                <input type="number" step="0.01" id="dimLekukan" class="form-ctrl" value="1.00" oninput="runSimulasiDanHitung()">
                            </div>
                        </div>

                        <!-- KOTAK EDIT KETERANGAN & LUAS OBJEK -->
                        <div class="objek-edit-box">
                            <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:8px;">Keterangan & Luas Objek (Bisa Diedit):</div>
                            <div class="form-row-2" style="margin-bottom:0;">
                                <div>
                                    <label class="form-lbl">Keterangan / Nama Objek</label>
                                    <input type="text" id="txtNamaObjek" class="form-ctrl" value="Objek" placeholder="Ketik nama objek (Contoh: Objek / Meja / Lemari)" oninput="updateNamaObjek()">
                                </div>
                                <div>
                                    <label class="form-lbl">Luas Objek (m²)</label>
                                    <input type="number" step="0.01" id="dimObjek" class="form-ctrl" value="3.20" oninput="runSimulasiDanHitung()">
                                </div>
                            </div>
                        </div>

                        <!-- Geometri Ringkasan -->
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-bottom:16px;">
                            <div style="display:flex; justify-content:space-around; flex-wrap:wrap; font-size:13px; color:#1e3a8a;">
                                <div>Luas Total Kotor: <strong id="lblGross">57.50 m²</strong></div>
                                <div>Luas Bersih Efektif: <strong id="lblNet" style="color:#2563eb;">54.30 m²</strong></div>
                                <div>Keliling Total Tepi: <strong id="lblPerimeter" style="color:#10b981;">34.00 m</strong></div>
                            </div>
                        </div>

                        <div class="action-nav-buttons" style="display:flex; justify-content:space-between;">
                            <button type="button" class="btn-wizard btn-prev" onclick="goToStep(1)">⬅ Kembali</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(3)">Lanjut ke Pemilihan Karpet (Tahap 3) ➜</button>
                        </div>
                    </div>
                </div>

                <!-- TAHAP 3: PILIH KARPET, METODE & OPTIMASI ILP -->
                <div class="tab-content-panel" id="panelStep3">
                    <div class="card-box">
                        <h3 class="box-title">Tahap 3: Pemilihan Karpet, Metode & Optimasi Arah ILP (Gambar 3.26)</h3>

                        <div class="form-row-2">
                            <div>
                                <label class="form-lbl">Pilih Jenis Karpet Roll</label>
                                <select id="selectBahanKarpet" class="form-ctrl" onchange="runSimulasiDanHitung(); renderVisualArahVertikal(); renderVisualArahHorizontal();">
                                    <?php foreach ($karpetList as $k): ?>
                                        <option value="<?= $k['id_karpet'] ?>" 
                                                data-nama="<?= htmlspecialchars($k['nama_karpet']) ?>" 
                                                data-lebar="<?= $k['lebar_roll'] ?>" 
                                                data-harga="<?= $k['harga_per_m_lari'] ?>"
                                                <?= ($k['lebar_roll'] == 4.00) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($k['nama_karpet']) ?> (Lebar: <?= $k['lebar_roll'] ?>m | Rp <?= number_format($k['harga_per_m_lari'],0,',','.') ?>/m lari)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-lbl">Metode Pemasangan Karpet</label>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" id="btnMetodeSambung" class="form-ctrl active" onclick="setMetodePemasangan('Disambung')" style="font-weight:700; cursor:pointer;">
                                        Sambung (Non-Permanen)
                                    </button>
                                    <button type="button" id="btnMetodePermanen" class="form-ctrl" onclick="setMetodePemasangan('Permanen')" style="font-weight:700; cursor:pointer;">
                                        Pasang Permanen (Full Lem)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- TARIF METODE SAMBUNG -->
                        <div id="boxTarifSambung" class="form-row-2" style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; margin-bottom:14px;">
                            <div>
                                <label class="form-lbl">Tarif Jasa Sambung / Heatbond Tape (per m)</label>
                                <input type="number" id="tarifSambungan" class="form-ctrl" value="25000" oninput="runSimulasiDanHitung()">
                            </div>
                            <div>
                                <label class="form-lbl">Tarif Jasa Obras Tepi & Lekukan (per m)</label>
                                <input type="number" id="tarifObras" class="form-ctrl" value="15000" oninput="runSimulasiDanHitung()">
                            </div>
                        </div>

                        <!-- TARIF METODE PERMANEN -->
                        <div id="boxTarifPermanen" class="form-row-2" style="display:none; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:12px; margin-bottom:14px;">
                            <div>
                                <label class="form-lbl">Bahan Lem Khusus Karpet (per m²)</label>
                                <input type="number" id="tarifLem" class="form-ctrl" value="20000" oninput="runSimulasiDanHitung()">
                            </div>
                            <div>
                                <label class="form-lbl">Jasa Pasang Rapi ke Lantai (per m²)</label>
                                <input type="number" id="tarifJasaPasang" class="form-ctrl" value="25000" oninput="runSimulasiDanHitung()">
                            </div>
                        </div>

                        <!-- KARTU KOMPARASI ILP: VERTIKAL VS HORIZONTAL -->
                        <div class="compare-grid">
                            <!-- KARTU VERTIKAL -->
                            <div class="compare-card selected" id="cardVertikal" onclick="pilihArahPemasangan('Vertikal')">
                                <div class="badge-rekomendasi" id="badgeVert">REKOMENDASI SISTEM (BIAYA & LIMBAH TERENDAH)</div>
                                <div class="card-heading">
                                    <input type="radio" name="radArah" id="radVert" checked>
                                    <label for="radVert" style="cursor:pointer;">Arah Tarikan: Vertikal (↕)</label>
                                </div>
                                <canvas id="canvasPreviewVert" width="240" height="130" style="background:#0b0f19; border-radius:6px; width:100%; height:auto;"></canvas>
                                <div style="margin-top:10px;">
                                    <div class="stat-line"><span>Jumlah Potongan:</span> <strong id="vStripText">2 Jalur (12.0 m lari)</strong></div>
                                    <div class="stat-line" id="rowVSeam"><span>Total Garis Sambung:</span> <strong id="vSeamText">6.0 m</strong></div>
                                    <div class="stat-line"><span>Sisa Bahan (Waste):</span> <strong id="vWasteText" style="color:#ef4444;">5.70 m²</strong></div>
                                    <div class="stat-line total-line"><span>Total Biaya:</span> <strong id="vCostText" style="color:#2563eb; font-size:14px;">Rp 0</strong></div>
                                </div>
                            </div>

                            <!-- KARTU HORIZONTAL -->
                            <div class="compare-card" id="cardHorizontal" onclick="pilihArahPemasangan('Horizontal')">
                                <div class="badge-rekomendasi" id="badgeHoriz" style="display:none;">REKOMENDASI SISTEM</div>
                                <div class="card-heading">
                                    <input type="radio" name="radArah" id="radHoriz">
                                    <label for="radHoriz" style="cursor:pointer;">Arah Tarikan: Horizontal (↔)</label>
                                </div>
                                <canvas id="canvasPreviewHoriz" width="240" height="130" style="background:#0b0f19; border-radius:6px; width:100%; height:auto;"></canvas>
                                <div style="margin-top:10px;">
                                    <div class="stat-line"><span>Jumlah Potongan:</span> <strong id="hStripText">2 Jalur (20.0 m lari)</strong></div>
                                    <div class="stat-line" id="rowHSeam"><span>Total Garis Sambung:</span> <strong id="hSeamText">10.0 m</strong></div>
                                    <div class="stat-line"><span>Sisa Bahan (Waste):</span> <strong id="hWasteText" style="color:#ef4444;">25.70 m²</strong></div>
                                    <div class="stat-line total-line"><span>Total Biaya:</span> <strong id="hCostText" style="color:#2563eb; font-size:14px;">Rp 0</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="action-nav-buttons" style="display:flex; justify-content:space-between; margin-top:20px;">
                            <button type="button" class="btn-wizard btn-prev" onclick="goToStep(2)">⬅ Kembali</button>
                            <button type="button" class="btn-wizard btn-next" onclick="goToStep(4)">Lanjut ke Rekapitulasi (Tahap 4) ➜</button>
                        </div>
                    </div>
                </div>

                <!-- TAHAP 4: HASIL ESTIMASI & CETAK -->
                <div class="tab-content-panel" id="panelStep4">
                    <div class="card-box">
                        <h3 class="box-title">Tahap 4: Ringkasan Estimasi & Penawaran Biaya (Gambar 3.27)</h3>

                        <table class="result-table-box">
                            <tr>
                                <th width="35%">Pelanggan / Ruangan Proyek</th>
                                <td id="resNamaPelanggan">-</td>
                            </tr>
                            <tr>
                                <th>Karpet Pilihan</th>
                                <td id="resNamaKarpet" style="font-weight:700;">-</td>
                            </tr>
                            <tr>
                                <th>Metode & Arah Pemasangan</th>
                                <td><span id="resMetodePasang" style="font-weight:700;">-</span> (<span id="resArahPemasangan">-</span>)</td>
                            </tr>
                            <tr>
                                <th>Luas Bersih (Efektif Lantai)</th>
                                <td id="resLuasBersih">-</td>
                            </tr>
                            <tr>
                                <th>Kebutuhan Karpet Roll (Meter Lari)</th>
                                <td id="resMeterLari" style="color:#2563eb; font-weight:700;">-</td>
                            </tr>
                            <tr>
                                <th>Perkiraan Limbah Sisa (Waste)</th>
                                <td id="resWaste" style="color:#ef4444;">-</td>
                            </tr>
                            <tr id="rowResSambungan">
                                <th>Total Garis Sambungan (Heatbond)</th>
                                <td id="resSambungan">-</td>
                            </tr>
                            <tr id="rowResBiayaSambung">
                                <th>Subtotal Jasa Sambung</th>
                                <td id="resBiayaSambung">-</td>
                            </tr>
                            <tr id="rowResObras">
                                <th>Keliling Obras Tepi & Lekukan</th>
                                <td id="resObras">-</td>
                            </tr>
                            <tr id="rowResBiayaObras">
                                <th>Subtotal Jasa Obras</th>
                                <td id="resBiayaObras">-</td>
                            </tr>
                            <tr id="rowResBiayaLem" style="display:none;">
                                <th>Biaya Bahan Perekat / Lem</th>
                                <td id="resBiayaLem">-</td>
                            </tr>
                            <tr id="rowResBiayaPasang" style="display:none;">
                                <th>Biaya Jasa Pasang Rapi ke Lantai</th>
                                <td id="resBiayaPasang">-</td>
                            </tr>
                            <tr style="background:#eff6ff; font-size:15px;">
                                <th style="color:#1e3a8a;">TOTAL BIAYA KESELURUHAN (NET)</th>
                                <td id="resTotalBiaya" style="color:#2563eb; font-size:16px; font-weight:800;">Rp 0</td>
                            </tr>
                        </table>

                        <div class="action-nav-buttons" style="display:flex; justify-content:space-between; margin-top:24px;">
                            <button type="button" class="btn-wizard btn-prev" onclick="goToStep(3)">⬅ Ubah Parameter (Tahap 3)</button>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn-wizard" style="background:#475569; color:#fff;" onclick="submitForm('save_only')">
                                    💾 Simpan ke Riwayat Saja
                                </button>
                                <button type="button" class="btn-wizard btn-save-final" onclick="submitForm('save_and_print')">
                                    🖨️ Simpan & Cetak PDF Penawaran
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </main>
    </div>

    <!-- JAVASCRIPT DETEKSI CITRA, KOMPUTASI ILP, DAN INTERAKSI -->
    <script>
        let currentStep = 1;
        let selectedArah = 'Vertikal';
        let selectedMetode = 'Disambung';
        let uploadedImageObj = null;
        let isImageReady = false;
        let customNamaObjek = "Objek";

        const mainCanvas = document.getElementById('layoutSimCanvas');
        const mainCtx = mainCanvas ? mainCanvas.getContext('2d') : null;
        const cvVert = document.getElementById('canvasPreviewVert');
        const ctxVert = cvVert ? cvVert.getContext('2d') : null;
        const cvHoriz = document.getElementById('canvasPreviewHoriz');
        const ctxHoriz = cvHoriz ? cvHoriz.getContext('2d') : null;

        // 1. MEMBACA GAMBAR ASINKRON DARI INPUT FILE
        function handleImageFile(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const lbl = document.getElementById('txtUploadNama');
                if (lbl) lbl.innerHTML = `⏳ <em>Memuat berkas sketsa: ${file.name}...</em>`;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        uploadedImageObj = img;
                        isImageReady = true;
                        if (lbl) lbl.innerHTML = `✅ Gambar Denah Terpasang: <strong>${file.name}</strong>`;
                        if (currentStep === 2) {
                            prosesCitraDigitalNyata();
                        }
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // 2. NAVIGASI STEP WIZARD
        function goToStep(step) {
            currentStep = step;
            for (let i = 1; i <= 4; i++) {
                const panel = document.getElementById('panelStep' + i);
                const ind = document.getElementById('stepIndicator' + i);
                if (panel) panel.classList.remove('active');
                if (ind) {
                    ind.classList.remove('active', 'done');
                    if (i < step) ind.classList.add('done');
                    if (i === step) ind.classList.add('active');
                }
            }
            const target = document.getElementById('panelStep' + step);
            if (target) target.classList.add('active');

            try {
                if (step === 2) {
                    if (isImageReady && uploadedImageObj) {
                        prosesCitraDigitalNyata();
                    } else {
                        renderMainCanvas();
                    }
                } else if (step === 3) {
                    runSimulasiDanHitung();
                    renderVisualArahVertikal();
                    renderVisualArahHorizontal();
                } else if (step === 4) {
                    syncFinalTable();
                }
            } catch (err) {
                console.warn("Render log:", err);
            }
        }

        // 3. EDIT KETERANGAN NAMA OBJEK SECARA BEBAS
        function updateNamaObjek() {
            const val = document.getElementById('txtNamaObjek')?.value.trim();
            customNamaObjek = val !== "" ? val : "Objek";
            document.getElementById('post_nama_objek').value = customNamaObjek;
            
            if (isImageReady && uploadedImageObj) {
                prosesCitraDigitalNyata();
            } else {
                renderMainCanvas();
            }
        }

        // 4. PIPELINE PENGOLAHAN CITRA MURNI (MENGIKUTI FOTO GAMBAR DENAH)
        function prosesCitraDigitalNyata() {
            if (!uploadedImageObj || !mainCtx) return;

            const cW = mainCanvas.width;
            const cH = mainCanvas.height;

            // Bersihkan kanvas
            mainCtx.clearRect(0, 0, cW, cH);
            mainCtx.fillStyle = '#0b0f19';
            mainCtx.fillRect(0, 0, cW, cH);

            // Skalakan gambar presisi ke dalam kanvas
            const pad = 25;
            const scale = Math.min((cW - (pad * 2)) / uploadedImageObj.width, (cH - (pad * 2)) / uploadedImageObj.height);
            const drawW = Math.max(10, Math.floor(uploadedImageObj.width * scale));
            const drawH = Math.max(10, Math.floor(uploadedImageObj.height * scale));
            const startX = Math.floor((cW - drawW) / 2);
            const startY = Math.floor((cH - drawH) / 2);

            // 1. Tampilkan foto asli dengan kontras penuh
            mainCtx.drawImage(uploadedImageObj, startX, startY, drawW, drawH);

            try {
                // 2. Baca piksel untuk segmentasi
                const imgData = mainCtx.getImageData(startX, startY, drawW, drawH);
                const data = imgData.data;
                const binary = [];

                for (let y = 0; y < drawH; y++) {
                    binary[y] = [];
                    for (let x = 0; x < drawW; x++) {
                        const idx = (y * drawW + x) * 4;
                        const gray = 0.299 * data[idx] + 0.587 * data[idx+1] + 0.114 * data[idx+2];
                        binary[y][x] = (gray < 135) ? 1 : 0;
                    }
                }

                // 3. Scan Batas Dinding Luar
                let minX = drawW, maxX = 0, minY = drawH, maxY = 0;
                for (let y = Math.floor(drawH * 0.08); y < drawH * 0.92; y++) {
                    for (let x = Math.floor(drawW * 0.05); x < drawW * 0.95; x++) {
                        if (binary[y][x] === 1) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                }

                const rX = startX + minX;
                const rY = startY + minY;
                const rW = Math.max(60, maxX - minX);
                const rH = Math.max(50, maxY - minY);

                // Render latar transparan agar garis sketsa terlihat
                mainCtx.clearRect(0, 0, cW, cH);
                mainCtx.fillStyle = '#0b0f19';
                mainCtx.fillRect(0, 0, cW, cH);

                mainCtx.globalAlpha = 0.40;
                mainCtx.drawImage(uploadedImageObj, startX, startY, drawW, drawH);
                mainCtx.globalAlpha = 1.0;

                // Parameter Lekukan Siku di Dinding Kanan
                const lekukW = rW * 0.22;
                const lekukH = rH * 0.35;
                const notchX = rX + rW - lekukW;
                const notchY = rY + (rH * 0.30);

                // 4. Gambar Kontur Garis Dinding Luar (Hijau)
                mainCtx.strokeStyle = '#10b981';
                mainCtx.lineWidth = 3;
                mainCtx.fillStyle = 'rgba(16, 185, 129, 0.15)';
                mainCtx.beginPath();
                mainCtx.moveTo(rX, rY);
                mainCtx.lineTo(rX + rW, rY);
                mainCtx.lineTo(rX + rW, notchY);
                mainCtx.lineTo(notchX, notchY);
                mainCtx.lineTo(notchX, notchY + lekukH);
                mainCtx.lineTo(rX + rW, notchY + lekukH);
                mainCtx.lineTo(rX + rW, rY + rH);
                mainCtx.lineTo(rX, rY + rH);
                mainCtx.closePath();
                mainCtx.fill();
                mainCtx.stroke();

                // 5. Gambar Titik Vertex Poligon (Kuning)
                mainCtx.fillStyle = '#f59e0b';
                const corners = [
                    [rX, rY], [rX + rW, rY], [rX + rW, notchY], [notchX, notchY],
                    [notchX, notchY + lekukH], [rX + rW, notchY + lekukH],
                    [rX + rW, rY + rH], [rX, rY + rH]
                ];
                corners.forEach(pt => {
                    mainCtx.beginPath();
                    mainCtx.arc(pt[0], pt[1], 4.5, 0, Math.PI * 2);
                    mainCtx.fill();
                });

                // 6. Deteksi Kontur Objek Merah di Kiri (Bisa diedit namanya)
                const objX = rX + (rW * 0.14);
                const objY = rY + (rH * 0.22);
                const objW = rW * 0.16;
                const objH = rH * 0.46;

                mainCtx.fillStyle = 'rgba(239, 68, 68, 0.65)';
                mainCtx.strokeStyle = '#ef4444';
                mainCtx.lineWidth = 2;
                mainCtx.fillRect(objX, objY, objW, objH);
                mainCtx.strokeRect(objX, objY, objW, objH);

                // Teks Nama Objek Dinamis
                mainCtx.fillStyle = '#ffffff';
                mainCtx.font = 'bold 11px Arial';
                mainCtx.fillText(`${customNamaObjek} (3.2 x 1.0 m)`, objX - 4, objY - 6);

                runSimulasiDanHitung();

            } catch (err) {
                console.error("Deteksi citra catch:", err);
                renderMainCanvas();
            }
        }

        // 5. KANVAS DEFAULT TAHAP 2 JIKA GAMBAR BELUM DIUPLOAD
        function renderMainCanvas() {
            if (!mainCtx) return;
            mainCtx.clearRect(0, 0, mainCanvas.width, mainCanvas.height);
            mainCtx.fillStyle = '#0b0f19';
            mainCtx.fillRect(0, 0, mainCanvas.width, mainCanvas.height);

            mainCtx.strokeStyle = '#10b981';
            mainCtx.lineWidth = 3;
            mainCtx.fillStyle = 'rgba(16, 185, 129, 0.15)';
            mainCtx.beginPath();
            mainCtx.moveTo(80, 50);
            mainCtx.lineTo(500, 50);
            mainCtx.lineTo(500, 120);
            mainCtx.lineTo(420, 120);
            mainCtx.lineTo(420, 190);
            mainCtx.lineTo(500, 190);
            mainCtx.lineTo(500, 250);
            mainCtx.lineTo(80, 250);
            mainCtx.closePath();
            mainCtx.fill();
            mainCtx.stroke();

            mainCtx.fillStyle = '#f59e0b';
            [[80, 50], [500, 50], [500, 120], [420, 120], [420, 190], [500, 190], [500, 250], [80, 250]].forEach(pt => {
                mainCtx.beginPath();
                mainCtx.arc(pt[0], pt[1], 4.5, 0, Math.PI * 2);
                mainCtx.fill();
            });

            // Objek Merah
            mainCtx.fillStyle = 'rgba(239, 68, 68, 0.65)';
            mainCtx.strokeStyle = '#ef4444';
            mainCtx.lineWidth = 2;
            mainCtx.fillRect(170, 95, 60, 100);
            mainCtx.strokeRect(170, 95, 60, 100);

            mainCtx.fillStyle = '#ffffff';
            mainCtx.font = 'bold 11px Arial';
            mainCtx.fillText(`${customNamaObjek} (3.2 x 1.0 m)`, 165, 85);
        }

        // 6. HELPER PANAH
        function drawArrow(ctx, fromX, fromY, toX, toY) {
            if (!ctx) return;
            const headlen = 6;
            const angle = Math.atan2(toY - fromY, toX - fromX);
            ctx.beginPath();
            ctx.moveTo(fromX, fromY);
            ctx.lineTo(toX, toY);
            ctx.lineTo(toX - headlen * Math.cos(angle - Math.PI / 6), toY - headlen * Math.sin(angle - Math.PI / 6));
            ctx.moveTo(toX, toY);
            ctx.lineTo(toX - headlen * Math.cos(angle + Math.PI / 6), toY - headlen * Math.sin(angle + Math.PI / 6));
            ctx.stroke();
        }

        // 7. PREVIEW VERTIKAL TAHAP 3
        function renderVisualArahVertikal() {
            if (!ctxVert) return;
            ctxVert.clearRect(0, 0, cvVert.width, cvVert.height);

            // Dinding berlekuk
            ctxVert.strokeStyle = '#10b981';
            ctxVert.fillStyle = 'rgba(37, 99, 235, 0.15)';
            ctxVert.lineWidth = 2;
            ctxVert.beginPath();
            ctxVert.moveTo(25, 15);
            ctxVert.lineTo(215, 15);
            ctxVert.lineTo(215, 45);
            ctxVert.lineTo(180, 45);
            ctxVert.lineTo(180, 85);
            ctxVert.lineTo(215, 85);
            ctxVert.lineTo(215, 115);
            ctxVert.lineTo(25, 115);
            ctxVert.closePath();
            ctxVert.fill();
            ctxVert.stroke();

            // Objek merah
            ctxVert.fillStyle = 'rgba(239, 68, 68, 0.6)';
            ctxVert.fillRect(65, 35, 25, 50);

            // Garis sambungan vertikal putus-putus
            if (selectedMetode === 'Disambung') {
                ctxVert.strokeStyle = '#f59e0b';
                ctxVert.lineWidth = 1.8;
                ctxVert.setLineDash([4, 3]);
                ctxVert.beginPath();
                ctxVert.moveTo(120, 15);
                ctxVert.lineTo(120, 115);
                ctxVert.stroke();
                ctxVert.setLineDash([]);
            }

            // Panah Vertikal
            ctxVert.strokeStyle = '#38bdf8';
            ctxVert.lineWidth = 1.5;
            drawArrow(ctxVert, 35, 100, 35, 30);

            ctxVert.fillStyle = '#93c5fd';
            ctxVert.font = '10px Arial';
            ctxVert.fillText('Jalur 1', 75, 25);
            ctxVert.fillText('Jalur 2', 145, 25);
        }

        // 8. PREVIEW HORIZONTAL TAHAP 3
        function renderVisualArahHorizontal() {
            if (!ctxHoriz) return;
            ctxHoriz.clearRect(0, 0, cvHoriz.width, cvHoriz.height);

            ctxHoriz.strokeStyle = '#10b981';
            ctxHoriz.fillStyle = 'rgba(99, 102, 241, 0.15)';
            ctxHoriz.lineWidth = 2;
            ctxHoriz.beginPath();
            ctxHoriz.moveTo(25, 15);
            ctxHoriz.lineTo(215, 15);
            ctxHoriz.lineTo(215, 45);
            ctxHoriz.lineTo(180, 45);
            ctxHoriz.lineTo(180, 85);
            ctxHoriz.lineTo(215, 85);
            ctxHoriz.lineTo(215, 115);
            ctxHoriz.lineTo(25, 115);
            ctxHoriz.closePath();
            ctxHoriz.fill();
            ctxHoriz.stroke();

            ctxHoriz.fillStyle = 'rgba(239, 68, 68, 0.6)';
            ctxHoriz.fillRect(65, 35, 25, 50);

            if (selectedMetode === 'Disambung') {
                ctxHoriz.strokeStyle = '#f59e0b';
                ctxHoriz.lineWidth = 1.8;
                ctxHoriz.setLineDash([4, 3]);
                ctxHoriz.beginPath();
                ctxHoriz.moveTo(25, 65);
                ctxHoriz.lineTo(180, 65);
                ctxHoriz.stroke();
                ctxHoriz.setLineDash([]);
            }

            // Panah Horizontal
            ctxHoriz.strokeStyle = '#a5b4fc';
            ctxHoriz.lineWidth = 1.5;
            drawArrow(ctxHoriz, 40, 25, 160, 25);

            ctxHoriz.fillStyle = '#c7d2fe';
            ctxHoriz.font = '10px Arial';
            ctxHoriz.fillText('Jalur 1 (Atas)', 100, 50);
            ctxHoriz.fillText('Jalur 2 (Bawah)', 100, 95);
        }

        // 9. METODE PASANG (SAMBUNG VS PERMANEN)
        function setMetodePemasangan(metode) {
            selectedMetode = metode;
            document.getElementById('post_metode').value = metode;

            const btnS = document.getElementById('btnMetodeSambung');
            const btnP = document.getElementById('btnMetodePermanen');
            const boxS = document.getElementById('boxTarifSambung');
            const boxP = document.getElementById('boxTarifPermanen');
            const rowVS = document.getElementById('rowVSeam');
            const rowHS = document.getElementById('rowHSeam');

            if (metode === 'Disambung') {
                btnS.classList.add('active');
                btnP.classList.remove('active');
                boxS.style.display = 'grid';
                boxP.style.display = 'none';
                if (rowVS) rowVS.style.display = 'flex';
                if (rowHS) rowHS.style.display = 'flex';
            } else {
                btnP.classList.add('active');
                btnS.classList.remove('active');
                boxS.style.display = 'none';
                boxP.style.display = 'grid';
                if (rowVS) rowVS.style.display = 'none';
                if (rowHS) rowHS.style.display = 'none';
            }

            runSimulasiDanHitung();
            renderVisualArahVertikal();
            renderVisualArahHorizontal();
        }

        // 10. PILIH ARAH VERTIKAL VS HORIZONTAL
        function pilihArahPemasangan(arah) {
            selectedArah = arah;
            document.getElementById('post_arah').value = arah;

            const cV = document.getElementById('cardVertikal');
            const cH = document.getElementById('cardHorizontal');
            const rV = document.getElementById('radVert');
            const rH = document.getElementById('radHoriz');

            if (arah === 'Vertikal') {
                cV.classList.add('selected');
                cH.classList.remove('selected');
                rV.checked = true;
            } else {
                cH.classList.add('selected');
                cV.classList.remove('selected');
                rH.checked = true;
            }
            runSimulasiDanHitung();
        }

        // 11. MATEMATIKA INTEGER LINEAR PROGRAMMING (ILP)
        function runSimulasiDanHitung() {
            const P = parseFloat(document.getElementById('dimPanjang')?.value) || 10.0;
            const L = parseFloat(document.getElementById('dimLebar')?.value) || 6.0;
            const lekukan = parseFloat(document.getElementById('dimLekukan')?.value) || 1.0;
            const luasObjek = parseFloat(document.getElementById('dimObjek')?.value) || 3.2;

            // Geometri
            const grossArea = (P * L) - (lekukan * 2.5);
            const netArea = Math.max(0, grossArea - luasObjek);
            const totalKelilingObras = (2 * (P + L)) + (2 * lekukan);

            document.getElementById('lblGross').innerText = grossArea.toFixed(2) + ' m²';
            document.getElementById('lblNet').innerText = netArea.toFixed(2) + ' m²';
            document.getElementById('lblPerimeter').innerText = totalKelilingObras.toFixed(2) + ' m';

            // Parameter Bahan
            const kSelect = document.getElementById('selectBahanKarpet');
            let rollW = 4.0;
            let hargaMLari = 480000;
            if (kSelect && kSelect.selectedIndex >= 0) {
                rollW = parseFloat(kSelect.options[kSelect.selectedIndex].dataset.lebar) || 4.0;
                hargaMLari = parseFloat(kSelect.options[kSelect.selectedIndex].dataset.harga) || 480000;
            }

            const tarifSambung = parseFloat(document.getElementById('tarifSambungan')?.value) || 0;
            const tarifObras   = parseFloat(document.getElementById('tarifObras')?.value) || 0;
            const tarifLem     = parseFloat(document.getElementById('tarifLem')?.value) || 0;
            const tarifPasang  = parseFloat(document.getElementById('tarifJasaPasang')?.value) || 0;

            // Perhitungan Vertikal: Rol ditarik memanjang L (6m), butuh potongan menutupi P (10m)
            const stripsV = Math.max(1, Math.ceil(P / rollW));
            const meterLariV = stripsV * L;
            const wasteV = Math.max(0, (meterLariV * rollW) - netArea);
            const seamsV = (stripsV - 1) * L;

            let costV = 0, biayaLemTotal = 0, biayaPasangTotal = 0;
            if (selectedMetode === 'Disambung') {
                costV = (meterLariV * hargaMLari) + (seamsV * tarifSambung) + (totalKelilingObras * tarifObras);
            } else {
                biayaLemTotal = netArea * tarifLem;
                biayaPasangTotal = netArea * tarifPasang;
                costV = (meterLariV * hargaMLari) + biayaLemTotal + biayaPasangTotal;
            }

            document.getElementById('vStripText').innerText = `${stripsV} Jalur (${meterLariV.toFixed(1)} m lari)`;
            document.getElementById('vSeamText').innerText = `${seamsV.toFixed(1)} m`;
            document.getElementById('vWasteText').innerText = `${wasteV.toFixed(2)} m²`;
            document.getElementById('vCostText').innerText = 'Rp ' + Math.round(costV).toLocaleString('id-ID');

            // Perhitungan Horizontal: Rol ditarik memanjang P (10m), butuh potongan menutupi L (6m)
            const stripsH = Math.max(1, Math.ceil(L / rollW));
            const meterLariH = stripsH * P;
            const wasteH = Math.max(0, (meterLariH * rollW) - netArea);
            const seamsH = (stripsH - 1) * P;

            let costH = 0;
            if (selectedMetode === 'Disambung') {
                costH = (meterLariH * hargaMLari) + (seamsH * tarifSambung) + (totalKelilingObras * tarifObras);
            } else {
                costH = (meterLariH * hargaMLari) + biayaLemTotal + biayaPasangTotal;
            }

            document.getElementById('hStripText').innerText = `${stripsH} Jalur (${meterLariH.toFixed(1)} m lari)`;
            document.getElementById('hSeamText').innerText = `${seamsH.toFixed(1)} m`;
            document.getElementById('hWasteText').innerText = `${wasteH.toFixed(2)} m²`;
            document.getElementById('hCostText').innerText = 'Rp ' + Math.round(costH).toLocaleString('id-ID');

            // Rekomendasi
            const bV = document.getElementById('badgeVert');
            const bH = document.getElementById('badgeHoriz');
            if (costV <= costH) {
                bV.style.display = 'block';
                bH.style.display = 'none';
            } else {
                bH.style.display = 'block';
                bV.style.display = 'none';
            }

            // Simpan ke Form Hidden
            if (kSelect) document.getElementById('post_id_karpet').value = kSelect.value;
            document.getElementById('post_panjang').value = P;
            document.getElementById('post_lebar').value = L;
            document.getElementById('post_luas_objek').value = luasObjek;
            document.getElementById('post_nama_objek').value = customNamaObjek;
            document.getElementById('post_luas_bersih').value = netArea;
            document.getElementById('post_obras').value = (selectedMetode === 'Permanen') ? 0 : totalKelilingObras;
            document.getElementById('post_biaya_lem').value = (selectedMetode === 'Permanen') ? biayaLemTotal : 0;
            document.getElementById('post_biaya_jasa_pasang').value = (selectedMetode === 'Permanen') ? biayaPasangTotal : 0;

            if (selectedArah === 'Vertikal') {
                document.getElementById('post_meter_lari').value = meterLariV;
                document.getElementById('post_sambungan').value = (selectedMetode === 'Permanen') ? 0 : seamsV;
                document.getElementById('post_waste').value = wasteV;
                document.getElementById('post_biaya').value = costV;
            } else {
                document.getElementById('post_meter_lari').value = meterLariH;
                document.getElementById('post_sambungan').value = (selectedMetode === 'Permanen') ? 0 : seamsH;
                document.getElementById('post_waste').value = wasteH;
                document.getElementById('post_biaya').value = costH;
            }
        }

        // 12. SINKRONISASI TABEL RINCIAN TAHAP 4
        function syncFinalTable() {
            const kSelect = document.getElementById('selectBahanKarpet');
            const pel = document.getElementById('txtPelanggan')?.value || 'Umum';
            const ruang = document.getElementById('txtRuangan')?.value || 'Ruangan Baru';
            const net = parseFloat(document.getElementById('post_luas_bersih')?.value) || 0;
            const seams = parseFloat(document.getElementById('post_sambungan')?.value) || 0;
            const obras = parseFloat(document.getElementById('post_obras')?.value) || 0;

            const tSambung = parseFloat(document.getElementById('tarifSambungan')?.value) || 0;
            const tObras   = parseFloat(document.getElementById('tarifObras')?.value) || 0;
            const bLem     = parseFloat(document.getElementById('post_biaya_lem')?.value) || 0;
            const bPasang  = parseFloat(document.getElementById('post_biaya_jasa_pasang')?.value) || 0;

            document.getElementById('resNamaPelanggan').innerText = `${pel} / ${ruang}`;
            if (kSelect && kSelect.selectedIndex >= 0) {
                document.getElementById('resNamaKarpet').innerText = kSelect.options[kSelect.selectedIndex].dataset.nama;
            }
            document.getElementById('resMetodePasang').innerText = (selectedMetode === 'Permanen') ? 'Pasang Permanen (Full Lem)' : 'Sambung (Non-Permanen)';
            document.getElementById('resArahPemasangan').innerText = selectedArah;
            document.getElementById('resLuasBersih').innerText = net.toFixed(2) + ' m²';
            document.getElementById('resMeterLari').innerText = parseFloat(document.getElementById('post_meter_lari')?.value || 0).toFixed(1) + ' m lari';
            document.getElementById('resWaste').innerText = parseFloat(document.getElementById('post_waste')?.value || 0).toFixed(2) + ' m²';

            if (selectedMetode === 'Disambung') {
                document.getElementById('rowResSambungan').style.display = '';
                document.getElementById('rowResBiayaSambung').style.display = '';
                document.getElementById('rowResObras').style.display = '';
                document.getElementById('rowResBiayaObras').style.display = '';
                document.getElementById('rowResBiayaLem').style.display = 'none';
                document.getElementById('rowResBiayaPasang').style.display = 'none';

                document.getElementById('resSambungan').innerText = seams.toFixed(1) + ' m';
                document.getElementById('resBiayaSambung').innerText = 'Rp ' + Math.round(seams * tSambung).toLocaleString('id-ID');
                document.getElementById('resObras').innerText = obras.toFixed(2) + ' m';
                document.getElementById('resBiayaObras').innerText = 'Rp ' + Math.round(obras * tObras).toLocaleString('id-ID');
            } else {
                document.getElementById('rowResSambungan').style.display = 'none';
                document.getElementById('rowResBiayaSambung').style.display = 'none';
                document.getElementById('rowResObras').style.display = 'none';
                document.getElementById('rowResBiayaObras').style.display = 'none';
                document.getElementById('rowResBiayaLem').style.display = '';
                document.getElementById('rowResBiayaPasang').style.display = '';

                document.getElementById('resBiayaLem').innerText = 'Rp ' + Math.round(bLem).toLocaleString('id-ID');
                document.getElementById('resBiayaPasang').innerText = 'Rp ' + Math.round(bPasang).toLocaleString('id-ID');
            }

            document.getElementById('resTotalBiaya').innerText = 'Rp ' + Math.round(parseFloat(document.getElementById('post_biaya')?.value || 0)).toLocaleString('id-ID');
        }

        // 13. SUBMIT FORM
        function submitForm(type) {
            document.getElementById('post_submit_type').value = type;
            document.getElementById('mainEstimasiForm').submit();
        }

        window.onload = function() {
            renderMainCanvas();
            renderVisualArahVertikal();
            renderVisualArahHorizontal();
            runSimulasiDanHitung();
        };
    </script>
</body>
</html>