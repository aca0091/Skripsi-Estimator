<?php
// cetak_laporan.php
require_once __DIR__ . '/require.php';

$tglMulai     = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tglSelesai   = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$filterKarpet = isset($_GET['id_karpet']) ? (int)$_GET['id_karpet'] : 0;
$filterMetode = isset($_GET['metode']) ? $_GET['metode'] : '';

$sql = "
    SELECT e.*, r.nama_ruangan, r.nama_pelanggan, k.nama_karpet, k.lebar_roll, k.harga_per_m_lari
    FROM tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    JOIN tb_karpet k ON e.id_karpet = k.id_karpet
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

$sql .= " ORDER BY e.tanggal_estimasi ASC, e.id_estimasi ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dataLaporan = $stmt->fetchAll();

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
    <title>Laporan Rekapitulasi Estimasi Karpet - CV. Plastik Indah</title>
    <style>
        :root {
            --primary-font: 'Times New Roman', Times, serif;
            --brand-color: #a31d24;
            --text-dark: #000000;
            --border-table: #555555;
            --bg-table-header: #f2f2f2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--primary-font) !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            background: #525659;
            padding: 20px;
            display: flex;
            justify-content: center;
            color: var(--text-dark);
            font-size: 10pt;
            line-height: 1.3;
        }

        .page-landscape {
            background: #ffffff;
            width: 297mm;
            min-height: 210mm;
            padding: 12mm 16mm;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            position: relative;
        }

        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--brand-color);
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .kop-logo img {
            max-width: 210px;
            height: auto;
            display: block;
        }

        .kop-address {
            text-align: right;
            font-size: 9pt;
            line-height: 1.25;
        }

        .kop-address strong {
            color: var(--brand-color);
            font-size: 10.5pt;
            font-weight: bold;
        }

        .doc-title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .doc-period {
            text-align: center;
            font-size: 10pt;
            margin-bottom: 12px;
            color: #333;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5pt;
        }

        .report-table th, .report-table td {
            border: 1px solid var(--border-table);
            padding: 5px 6px;
        }

        .report-table th {
            background: var(--bg-table-header);
            font-weight: bold;
            text-align: center;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .report-summary-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .summary-notes {
            width: 55%;
            font-size: 9.5pt;
            line-height: 1.35;
        }

        .signature-container {
            width: 240px;
            text-align: center;
            font-size: 10pt;
        }

        .signature-space {
            height: 48px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .no-print {
            position: fixed;
            top: 18px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 999;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            padding: 7px 12px;
            font-size: 10pt;
            font-weight: bold;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .btn-print { background: var(--brand-color); color: #fff; }
        .btn-back { background: #475569; color: #fff; }

        @media print {
            body { background: #fff; padding: 0; }
            .page-landscape { box-shadow: none; padding: 8mm 12mm; width: 100%; }
            .no-print { display: none; }
            @page { size: landscape; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="laporan.php" class="btn-action btn-back">⬅ Kembali</a>
        <button onclick="window.print()" class="btn-action btn-print">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="page-landscape">
        <!-- KOP SURAT -->
        <div class="kop-surat">
            <div class="kop-logo">
                <img src="assets/images/logo_plastik_indah.png" alt="CV. Plastik Indah">
            </div>
            <div class="kop-address">
                <strong>CV. PLASTIK INDAH</strong><br>
                Jl. Kartini No. 114, Denpasar Utara, Kota Denpasar, Bali<br>
                Telp: (0361) 223456 | Email: cv.plastikindah@gmail.com
            </div>
        </div>

        <div class="doc-title">LAPORAN REKAPITULASI ESTIMASI & PENGGUNAAN BAHAN FINISHING (KARPET)</div>
        <div class="doc-period">
            Periode: <?= date('d/m/Y', strtotime($tglMulai)) ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)) ?>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="10%">No. Estimasi</th>
                    <th width="9%">Tanggal</th>
                    <th width="16%">Pelanggan</th>
                    <th width="14%">Nama Ruangan</th>
                    <th width="14%">Bahan Karpet</th>
                    <th width="7%">Arah</th>
                    <th width="9%">Metode</th>
                    <th width="8%">Meter Lari</th>
                    <th width="7%">Waste</th>
                    <th width="11%">Total Biaya</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dataLaporan)): ?>
                    <tr>
                        <td colspan="11" class="text-center" style="padding: 20px;">Tidak ada transaksi estimasi pada periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($dataLaporan as $row): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['kode_estimasi']) ?></strong></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal_estimasi'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_pelanggan']) ?></td>
                            <td><?= htmlspecialchars($row['nama_ruangan']) ?></td>
                            <td><?= htmlspecialchars($row['nama_karpet']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['arah_pemasangan']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['metode_pemasangan']) ?></td>
                            <td class="text-center"><?= number_format($row['total_meter_lari'], 1) ?> m</td>
                            <td class="text-center"><?= number_format($row['luas_waste'], 1) ?> m²</td>
                            <td class="text-right">Rp <?= number_format($row['total_biaya'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background: var(--bg-table-header);">
                    <td colspan="8" class="text-right">TOTAL REKAPITULASI:</td>
                    <td class="text-center"><?= number_format($totMeterLari, 1) ?> m</td>
                    <td class="text-center"><?= number_format($totWaste, 1) ?> m²</td>
                    <td class="text-right" style="color: var(--brand-color);">Rp <?= number_format($totBiaya, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- RINGKASAN & TANDA TANGAN -->
        <div class="report-summary-box">
            <div class="summary-notes">
                <strong>Catatan Laporan Operasional:</strong>
                <ul style="margin-left: 18px; margin-top: 4px;">
                    <li>Laporan ini merekapitulasi seluruh hasil kalkulasi matematis optimasi pemotongan karpet roll menggunakan <em>Integer Linear Programming</em>.</li>
                    <li>Akumulasi kebutuhan meter lari roll utuh yang dipotong: <strong><?= number_format($totMeterLari, 1) ?> meter lari</strong>.</li>
                    <li>Tingkat sisa potongan material (waste) terakumulasi sebesar <strong><?= number_format($totWaste, 1) ?> m²</strong> pada rentang periode yang dipilih.</li>
                </ul>
            </div>

            <div class="signature-container">
                Denpasar, <?= date('d F Y') ?><br>
                <strong>CV. PLASTIK INDAH</strong><br>
                <div class="signature-space"></div>
                <div class="signature-name">Diana I Dana</div><br>
                <div>Owner / Pimpinan Usaha</div>
            </div>
        </div>
    </div>

</body>
</html>