<?php
// estimasi.php
require_once __DIR__ . '/auth.php';
check_page_access('estimasi.php');
require_once __DIR__ . '/require.php';

// 1. Ambil Katalog Karpet
$stmtK = $pdo->query("SELECT * FROM tb_karpet ORDER BY nama_karpet ASC");
$karpetList = $stmtK->fetchAll();
$customerList = $pdo->query('SELECT id_customer,nama_customer FROM tb_customer ORDER BY nama_customer,id_customer')->fetchAll();

// 2. Simpan Estimasi ke Basis Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_final_estimate') {
    $idCustomer = filter_var($_POST['id_customer'] ?? null, FILTER_VALIDATE_INT);
    $stmtCustomer = $pdo->prepare('SELECT nama_customer FROM tb_customer WHERE id_customer=?');
    $stmtCustomer->execute([$idCustomer ?: 0]);
    $namaPelanggan = $stmtCustomer->fetchColumn();
    if ($namaPelanggan === false) { http_response_code(422); exit('Pilih customer yang terdaftar sebelum menyimpan proyek.'); }
    $tanggalEstimasi = $_POST['tanggal_estimasi'] ?? '';
    $date = is_string($tanggalEstimasi) ? DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalEstimasi) : false;
    if (!$date || $date->format('Y-m-d') !== $tanggalEstimasi) { http_response_code(422); exit('Tanggal estimasi tidak valid.'); }
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

    require_once __DIR__ . '/room_geometry.php';
    try {
        $geometry = measure_room_geometry($_POST['layout_geometry'] ?? '');
        if (!is_finite($luasObjek) || $luasObjek < 0 || $luasObjek >= $geometry['area']) {
            throw new InvalidArgumentException('Luas objek harus lebih kecil dari luas ruangan.');
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        exit(htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    $panjangRuang = $geometry['width'];
    $lebarRuang = $geometry['height'];
    $luasKotor = $geometry['area'];
    $totalLuas = $luasKotor - $luasObjek;
    $totalObras = $metodePasang === 'Permanen' ? 0 : $geometry['perimeter'];
    $pdo->beginTransaction();
    // Simpan batas ruangan bersama estimasi agar cetak memakai bentuk yang sama.
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
            luas_waste, total_biaya, tanggal_estimasi, layout_geometry, id_customer
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtSave->execute([
        $kodeEstimasi, $idRuanganBaru, $idKarpet, $idUser, $arah, $metodePasang,
        $totalLuas, $totalMeterLari, $totalSambungan, $totalObras,
        $biayaLem, $biayaJasaPasang, $luasWaste, $totalBiaya, $tanggalEstimasi, $geometry['json'], $idCustomer
    ]);

    $lastId = $pdo->lastInsertId();
    $pdo->commit();

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
    <link rel="stylesheet" href="assets/css/dash.css?v=20261123-sidebar2">
    <link rel="stylesheet" href="assets/css/estimasi.css">
    <style>
        .calculation-status { display:flex; align-items:center; gap:12px; padding:16px 18px; margin:16px 0; border:1px solid #dbeafe; border-radius:12px; background:#eff6ff; color:#1e40af; font-size:15px; font-weight:600; }
        .calculation-spinner { display:none; width:22px; height:22px; flex:0 0 22px; border:3px solid #bfdbfe; border-top-color:#2563eb; border-radius:50%; animation:calculation-spin .8s linear infinite; }
        .calculation-status[data-loading="true"] .calculation-spinner { display:block; }
        .calculation-status[data-state="error"] { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
        #measurementSummary[aria-busy="true"] { opacity:.45; }
        #measurementStatus:empty { display:none; }
        #nextCarpetButton:disabled { opacity:.5; cursor:wait; }
        .layout-tools { margin:14px 0; }
        .layout-tools summary { cursor:pointer; font-weight:600; color:#475569; margin-bottom:12px; }
        @keyframes calculation-spin { to { transform:rotate(360deg); } }
        @media (prefers-reduced-motion:reduce) { .calculation-spinner { animation:none; } }

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
<link rel="stylesheet" href="assets/css/app-theme.css?v=20261123-1">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <?php $activePage = 'estimasi'; require __DIR__ . '/includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-body">
<?php require __DIR__ . '/includes/app_toolbar.php'; ?>
            <h1 class="main-title">Estimasi Karpet</h1>
            <p class="breadcrumb">Buat estimasi kebutuhan material dan biaya pemasangan, langkah demi langkah.</p>

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
                <input type="hidden" name="layout_geometry" id="post_layout_geometry">
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
                        <h3 class="box-title">Tahap 1: Formulir Data Umum Estimasi</h3>

                        <div class="form-row-2">
                            <div>
                                <label class="form-lbl">Nama Pelanggan / Perusahaan <span style="color:#ef4444;">*</span></label>
                                <select name="id_customer" id="txtPelanggan" class="form-ctrl" required>
                                    <option value="">Pilih customer terdaftar</option>
                                    <?php foreach ($customerList as $customer): ?>
                                    <option value="<?= (int)$customer['id_customer'] ?>" <?= (int)($_GET['id_customer'] ?? 0)===(int)$customer['id_customer']?'selected':'' ?>><?= htmlspecialchars($customer['nama_customer']) ?> (#<?= (int)$customer['id_customer'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="data_customer.php">Tambah / kelola customer</a>
                                <?php if (!$customerList): ?><p>Daftarkan customer terlebih dahulu sebelum membuat proyek.</p><?php endif; ?>
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
                            <span class="upload-icon"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg></span>
                            <div style="font-weight:700; margin-top:6px; color:#1e293b;" id="txtUploadNama">Klik untuk memilih foto denah layout</div>
                            <div style="font-size:12px; color:#94a3b8;">Format sketsa tampak atas (JPG/PNG) </div>
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
                        <h3 class="box-title">Tahap 2: Batas Ruangan & Kalibrasi Ukuran</h3>

                        <div class="canvas-container-box">
                            <canvas id="layoutSimCanvas" width="1000" height="470" style="width:100%;height:auto;cursor:crosshair"></canvas>
                        </div>

                        <div id="calculationStatus" class="calculation-status" role="status" aria-live="polite" data-loading="false">
                            <span class="calculation-spinner" aria-hidden="true"></span>
                            <span id="dimensionReadStatus">Unggah gambar, lalu pilih garis untuk mengisi ukuran.</span>
                        </div>
                        <section style="padding:16px;border:1px solid #bfdbfe;border-radius:12px;margin:16px 0;background:#f8fbff">
                            <h4 style="margin:0 0 12px">Ukuran per sisi — klik garis pada gambar</h4>
                            <div class="form-row-3">
                                <div><label class="form-lbl" for="edgeSelect">Garis yang dipilih</label><select id="edgeSelect" class="form-ctrl" onchange="selectRoomEdge(Number(this.value))"><option value="-1">Pilih garis</option></select></div>
                                <div><label class="form-lbl" for="edgeLength">Panjang sisi</label><input id="edgeLength" type="text" inputmode="decimal" class="form-ctrl" placeholder="Masukkan panjang"></div>
                                <div><label class="form-lbl" for="edgeUnit">Satuan</label><select id="edgeUnit" class="form-ctrl"><option value="m">m</option><option value="cm">cm</option></select></div>
                            </div>
                            <div style="display:flex;gap:10px;flex-wrap:wrap"><button type="button" class="btn-wizard btn-next" onclick="applyRoomEdgeMeasurement()">Terapkan ukuran sisi</button><button type="button" class="btn-wizard" onclick="clearRoomEdgeMeasurements()">Hapus ukuran sisi manual</button></div>
                            <p id="edgeMeasurementStatus" role="status" style="margin:12px 0 0">Pilih garis hijau atau pilih pasangan titik di atas.</p>
                        </section>
                        <details class="layout-tools"><summary>Edit batas ruangan</summary>
                            <p id="layoutStatus" role="status"></p>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                                <button type="button" class="btn-wizard" onclick="prosesCitraDigitalNyata(true)">Deteksi ulang</button>
                                <button type="button" class="btn-wizard" onclick="startManualOutline()">Gambar batas manual</button>
                                <button type="button" class="btn-wizard" onclick="resetLastPoint()">Batalkan titik terakhir</button>
                                <button type="button" class="btn-wizard" onclick="finishManualOutline()">Selesai menggambar</button>
                            </div>
                        </details>
                        <div class="form-row-2">
                            <div><label class="form-lbl">Panjang total (m)</label>
                                <input type="number" min="0.001" step="0.001" id="dimPanjang" readonly class="form-ctrl" placeholder="Masukkan hasil pengukuran" oninput="runSimulasiDanHitung()"></div>
                            <div><label class="form-lbl">Lebar total (m)</label>
                                <input type="number" min="0.001" step="0.001" id="dimLebar" readonly class="form-ctrl" placeholder="Masukkan hasil pengukuran" oninput="runSimulasiDanHitung()"></div>
                        </div>
                        <select id="dimensionUnit" hidden><option value="m">Meter</option></select>
                        <div id="vertexInputs" hidden></div>
                        <input type="checkbox" id="confirmScale" hidden>
                        <section style="padding:16px;border:1px solid #ddd6fe;border-radius:12px;margin:16px 0">
                            <h4>Objek di dalam denah</h4>
                            <p>Klik garis merah/ungu untuk mengukur sisi objek, atau isi lebar dan tinggi (meter) di bawah. Kandidat belum tentu objek: periksa sebelum menghitung. Lebar dan tinggi adalah ukuran total horizontal dan vertikal objek. Luas mengikuti bentuk sudut yang digambar; pastikan proporsi bentuk sesuai denah.</p>
                            <div id="detectedObjects" aria-live="polite"></div>
                            <button type="button" class="btn-wizard" onclick="startObjectPolygon()">Gambar objek (3+ sudut)</button>
                            <button type="button" class="btn-wizard" onclick="undoObjectPoint()">Batalkan sudut terakhir</button>
                            <button type="button" class="btn-wizard btn-next" onclick="finishObjectPolygon()">Selesai gambar objek</button>
                            <p id="objectDrawStatus" role="status" aria-live="polite"></p>
                            <button type="button" class="btn-wizard" onclick="cancelObjectPolygon()">Batalkan gambar objek</button>
                        </section>
                        <!-- KOTAK EDIT KETERANGAN & LUAS OBJEK -->
                        <div class="objek-edit-box">
                            <div style="font-size:12px; font-weight:700; color:#334155; margin-bottom:8px;">Luas objek yang tidak dipasangi karpet (opsional):</div>
                            <div class="form-row-2" style="margin-bottom:0;">
                                <div>
                                    <label class="form-lbl">Keterangan / Nama Objek</label>
                                    <input type="text" id="txtNamaObjek" class="form-ctrl" value="Objek" placeholder="Ketik nama objek (Contoh: Objek / Meja / Lemari)" oninput="updateNamaObjek()">
                                </div>
                                <div>
                                    <label class="form-lbl">Luas Objek (m²)</label>
                                    <input type="number" step="0.01" id="dimObjek" class="form-ctrl" value="0" min="0" oninput="runSimulasiDanHitung()">
                                </div>
                            </div>
                        </div>

                        <details style="margin:12px 0"><summary>Hitung luas objek dari panjang × lebar × jumlah</summary>

                            <div class="form-row-3">
                                <div><label class="form-lbl">Panjang objek (m)</label><input type="number" id="objectLength" min="0" step="0.01" class="form-ctrl"></div>
                                <div><label class="form-lbl">Lebar objek (m)</label><input type="number" id="objectWidth" min="0" step="0.01" class="form-ctrl"></div>
                                <div><label class="form-lbl">Jumlah objek</label><input type="number" id="objectCount" min="1" step="1" value="1" class="form-ctrl"></div>
                            </div>
                            <button type="button" class="btn-wizard" onclick="calculateObjectArea()">Gunakan luas objek</button>
                        </details>
                        <p id="measurementStatus" role="status" style="color:#334155;line-height:1.6"></p>
                        <!-- Geometri Ringkasan -->
                        <div id="measurementSummary" aria-busy="false" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-bottom:16px;">
                            <div style="display:flex; justify-content:space-around; flex-wrap:wrap; font-size:13px; color:#1e3a8a;">
                                <div>Luas Total Kotor: <strong id="lblGross">—</strong></div>
                                <div>Luas Objek: <strong id="lblObjectArea">0,00 m²</strong></div>
                                <div>Luas Bersih Efektif: <strong id="lblNet" style="color:#2563eb;">—</strong></div>
                                <div>Keliling Total Tepi: <strong id="lblPerimeter" style="color:#10b981;">—</strong></div>
                            </div>
                        </div>

                        <div class="action-nav-buttons" style="display:flex; justify-content:space-between;">
                            <button type="button" class="btn-wizard btn-prev" onclick="goToStep(1)">⬅ Kembali</button>
                            <button type="button" id="nextCarpetButton" class="btn-wizard btn-next" onclick="goToStep(3)">Lanjut Pilih Karpet ➜</button>
                        </div>
                    </div>
                </div>

                <!-- TAHAP 3: PILIH KARPET, METODE & OPTIMASI ILP -->
                <div class="tab-content-panel" id="panelStep3">
                    <div class="card-box">
                        <h3 class="box-title">Tahap 3: Pilih Karpet, Metode & Arah Pemasangan</h3>

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
                                <label class="form-lbl">Tarif Jasa Obras Tepi Ruangan (per m)</label>
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

                        <p style="font-size:13px;color:#475569">Potongan mengikuti rentang kontur tiap jalur roll. Perhitungan belum mencakup cadangan potong, penyamaan motif, atau penggunaan ulang sisa. Pengurangan objek hanya mengubah luas bersih.</p>
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
                        <h3 class="box-title">Tahap 4: Ringkasan Estimasi & Penawaran Biaya</h3>

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
                                <th>Keliling Obras Tepi Ruangan</th>
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
    <script src="assets/js/room-geometry.js?v=3"></script>
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
                resetLayoutEstimate(false);
                const uploadVersion = layoutUploadVersion;
                setCalculationLoading(true, 'Mendeteksi garis ruangan…');
                const lbl = document.getElementById('txtUploadNama');
                if (lbl) lbl.textContent = `Memuat berkas sketsa: ${file.name}...`;

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (uploadVersion !== layoutUploadVersion) return;
                    const img = new Image();
                    img.onload = function() {
                        if (uploadVersion !== layoutUploadVersion) return;
                        uploadedImageObj = img;
                        isImageReady = true;
                        if (lbl) lbl.textContent = `Gambar Denah Terpasang: ${file.name}`;
                        prosesCitraDigitalNyata();
                        setCalculationLoading(false, 'Pilih garis dan masukkan panjang sisi.');
                        if (currentStep === 2) {
                            prosesCitraDigitalNyata();
                        }
                    };
                    img.onerror = () => { if (uploadVersion !== layoutUploadVersion) return; isImageReady = false; uploadedImageObj = null; acceptRoomPoints([]); setCalculationLoading(false, 'Gambar tidak dapat dibaca. Pilih JPG atau PNG.', true); };
                    img.src = e.target.result;
                };
                reader.onerror = () => { if (uploadVersion === layoutUploadVersion) setCalculationLoading(false, 'Gambar gagal dimuat. Coba unggah ulang.', true); };
                reader.readAsDataURL(file);
            }
        }

        // 2. NAVIGASI STEP WIZARD
        function goToStep(step) {
            if (step === 1 && currentStep !== 1) resetLayoutEstimate();
            if (step >= 3 && !validateLayout()) return;
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

        // 11. PERHITUNGAN POTONGAN PER JALUR POLIGON
        function runSimulasiDanHitung() {
            const points = realRoomPoints();
            const valid = RoomGeometry.valid(points);
            const box = valid ? RoomGeometry.bounds(points) : {minX:0,maxX:0,minY:0,maxY:0};
            const P = box.maxX-box.minX, L = box.maxY-box.minY;
            const luasObjek = Math.max(0, Number(document.getElementById('dimObjek').value) || 0);
            const grossArea = valid ? RoomGeometry.area(points) : 0;
            const netArea = Math.max(0, grossArea-luasObjek);
            const totalKelilingObras = valid ? RoomGeometry.perimeter(points) : 0;
            document.getElementById('post_layout_geometry').value = valid ? JSON.stringify({version:1,points}) : '';
            document.getElementById('lblGross').innerText = valid ? grossArea.toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' m²' : 'Belum dihitung';
            document.getElementById('lblNet').innerText = valid && luasObjek<grossArea ? netArea.toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' m²' : 'Belum dihitung';
            document.getElementById('lblPerimeter').innerText = valid ? totalKelilingObras.toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' m' : 'Belum dihitung';

            document.getElementById('lblObjectArea').innerText = luasObjek.toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' m²';
            updateMeasurementStatus();

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
            const planV = RoomGeometry.strips(points, rollW, 0);
            const stripsV = planV.count;
            const meterLariV = planV.length;
            const wasteV = Math.max(0, meterLariV * rollW - netArea);
            const seamsV = planV.seams;

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
            const planH = RoomGeometry.strips(points, rollW, 1);
            const stripsH = planH.count;
            const meterLariH = planH.length;
            const wasteH = Math.max(0, meterLariH * rollW - netArea);
            const seamsH = planH.seams;

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
            renderVisualArahVertikal();
            renderVisualArahHorizontal();
            renderMainCanvas();
        }

        // 12. SINKRONISASI TABEL RINCIAN TAHAP 4
        function syncFinalTable() {
            runSimulasiDanHitung();
            const kSelect = document.getElementById('selectBahanKarpet');
            const pel = document.getElementById('txtPelanggan')?.selectedOptions[0]?.textContent || '';
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
        function validateLayout() {
            const error = measurementError();
            if (calculationLoading) { return false; }
            if (error) {
                goToStep(2);
                const message = error;
                document.getElementById('measurementStatus').textContent=message;
                alert(message); return false;
            }
            return true;
        }
        function submitForm(type) {
            if (!validateLayout()) return;
            const select=document.getElementById('selectBahanKarpet');
            if (!select.value) { alert('Tambahkan dan pilih data karpet terlebih dahulu.'); return; }
            if (!document.getElementById('txtPelanggan').value.trim() || !document.getElementById('txtRuangan').value.trim()) {goToStep(1); alert('Isi nama pelanggan dan ruangan.'); return;}
            runSimulasiDanHitung();
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
    <script src="assets/js/edge-dimensions.js?v=3"></script>
    <script src="assets/js/layout-editor.js?v=11"></script>
</body>
</html>