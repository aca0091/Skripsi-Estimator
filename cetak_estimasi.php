<?php
// cetak_estimasi.php
require_once __DIR__ . '/auth.php';
check_page_access('cetak_estimasi.php');
require_once __DIR__ . '/require.php';

$quotationId = (int) ($_GET['penawaran'] ?? 0);
if (!$quotationId && (isset($_GET['id']) || isset($_GET['id_estimasi']))) {
    $parent = $pdo->prepare('SELECT id_penawaran FROM tb_estimasi WHERE id_estimasi=?');
    $parent->execute([(int) ($_GET['id'] ?? $_GET['id_estimasi'])]);
    $quotationId = (int) $parent->fetchColumn();
}
if ($quotationId > 0) { require __DIR__ . '/includes/print_quotation.php'; exit; }

// Ambil ID Estimasi secara fleksibel
$idEstimasi = isset($_GET['id'])
    ? (int) $_GET['id']
    : (isset($_GET['id_estimasi'])
        ? (int) $_GET['id_estimasi']
        : 0);
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'id';

if ($idEstimasi <= 0) {
    die(
        "<div style='padding:30px; font-family:sans-serif; text-align:center;'><h3>ID Estimasi tidak valid atau belum dipilih.</h3><a href='estimasi.php'>Kembali ke Estimasi</a></div>"
    );
}

// Query data estimasi
$stmt = $pdo->prepare("
    SELECT e.*, 
           r.nama_ruangan, r.nama_pelanggan, r.panjang, r.lebar, 
           k.nama_karpet, k.lebar_roll, k.harga_per_m_lari, k.motif
    FROM tb_estimasi e
    JOIN tb_ruangan r ON e.id_ruangan = r.id_ruangan
    JOIN tb_karpet k ON e.id_karpet = k.id_karpet
    WHERE e.id_estimasi = ?
");
$stmt->execute([$idEstimasi]);
$est = $stmt->fetch();

if (!$est) {
    die(
        "<div style='padding:30px; font-family:sans-serif; text-align:center;'><h3>Data estimasi #$idEstimasi tidak ditemukan di database.</h3><a href='estimasi.php'>Kembali ke Estimasi</a></div>"
    );
}

$pricing = !empty($est['pricing_snapshot']) ? json_decode($est['pricing_snapshot'], true) : null;

$biayaKarpet = $est['total_meter_lari'] * $est['harga_per_m_lari'];

// Kamus Bahasa
$dict = [
    'id' => [
        'doc_title' => 'SURAT PENAWARAN & ESTIMASI BIAYA',
        'no_ref' => 'No. Penawaran',
        'date' => 'Tanggal',
        'customer' => 'Kepada Yth.',
        'room_dim' => 'Dimensi Ruang',
        'project_name' => 'Nama Proyek',
        'net_area' => 'Luas Bersih',
        'net_area_sub' => '(Terpotong Objek)',
        'no' => 'No',
        'work_desc' => 'Uraian Pekerjaan & Spesifikasi',
        'volume' => 'Volume',
        'unit_price' => 'Harga Satuan',
        'subtotal' => 'Subtotal',
        'roll_w' => 'Lebar roll',
        'motif' => 'Motif',
        'm_linear' => 'm lari',
        'seam_desc' => 'Jasa Sambung / Heatbond Tape',
        'obras_desc' => 'Jasa Obras Keliling Tepi Karpet (Termasuk Lekukan Dinding)',
        'glue_desc' => 'Bahan Lem Khusus Karpet (Aplikasi Perekat Lantai Penuh)',
        'install_desc' => 'Jasa Pemasangan Permanen (Instalasi Lem Rapi ke Lantai)',
        'grand_total' => 'TOTAL BIAYA KESELURUHAN (NET)',
        'heading_pattern' => 'POLA PENATAAN & ARAH PEMASANGAN KARPET ROLL:',
        'method_label' => 'Metode',
        'method_perm' => 'Pasang Permanen (Full Lem)',
        'method_seam' => 'Sambung (Non-Permanen)',
        'pattern_rule' => 'Pola pemasangan',
        'pattern_dir' => 'Arah',
        'pattern_desc' =>
            '(berdasarkan perhitungan optimasi keteraturan motif & minimasi limbah bahan).',
        'seam_info' =>
            'Garis putus-putus kuning menunjukkan letak sambungan karpet (Heatbond Tape) total panjang:',
        'obras_info' =>
            'Keliling tepi luar dan lekukan ruangan diobras rapi (total keliling obras:',
        'perm_info' =>
            'Pemasangan sistem lem penuh ke permukaan lantai tanpa perlu obras dan tanpa biaya sambungan.',
        'waste_info' => 'Perkiraan sisa bahan potongan (waste):',
        'terms_title' => 'Syarat dan Ketentuan:',
        'term_1' =>
            'Harga berlaku 7 hari setelah penawaran ini dibuat, setelahnya tidak mengikat dan harga net tidak termasuk pajak.',
        'term_2' =>
            'Stok tersedia (stok berjalan) yang dapat berubah sewaktu-waktu tanpa pemberitahuan terlebih dahulu.',
        'term_3' =>
            'Pesanan wajib dibayar lunas / sebagian (minimal 50%) sebelum dikerjakan.',
        'term_4' =>
            'Pembayaran melalui transfer ke rekening perusahaan (nomor rekening akan dikirim setelah penawaran diterima).',
        'term_5' => 'Barang yang sudah dibeli, tidak bisa dikembalikan.',
        'closing' =>
            'Demikian penawaran ini kami ajukan dan kami berharap penawaran ini dapat memenuhi kriteria yang Bapak/Ibu inginkan. Kami tentunya berharap dapat segera memenuhi kebutuhan pesanan tersebut. Terimakasih.',
        'regards' => 'Hormat kami,',
        'owner_title' => 'Owner / Pimpinan Usaha',
        'btn_back' => 'Kembali',
        'btn_print' => 'Cetak / Simpan PDF',
        'lang_toggle' => 'English Version'
    ],
    'en' => [
        'doc_title' => 'QUOTATION & COST ESTIMATION',
        'no_ref' => 'Quotation No.',
        'date' => 'Date',
        'customer' => 'Customer',
        'room_dim' => 'Room Dimensions',
        'project_name' => 'Project Name',
        'net_area' => 'Net Area',
        'net_area_sub' => '(Deducted Obstacles)',
        'no' => 'No',
        'work_desc' => 'Work Description & Specifications',
        'volume' => 'Volume',
        'unit_price' => 'Unit Price',
        'subtotal' => 'Subtotal',
        'roll_w' => 'Roll width',
        'motif' => 'Pattern',
        'm_linear' => 'linear m',
        'seam_desc' => 'Seaming Service / Heatbond Tape',
        'obras_desc' =>
            'Carpet Edge Binding/Overlocking Service (Including Wall Notches)',
        'glue_desc' => 'Special Carpet Adhesive (Full Floor Surface Gluing)',
        'install_desc' => 'Permanent Installation Service (Full Glue Floor Laying)',
        'grand_total' => 'TOTAL ESTIMATED COST (NET)',
        'heading_pattern' => 'CARPET ROLL LAYOUT PATTERN & INSTALLATION DIRECTION:',
        'method_label' => 'Method',
        'method_perm' => 'Permanent Installation (Full Glue)',
        'method_seam' => 'Seamed Only',
        'pattern_rule' => 'Installation pattern',
        'pattern_dir' => 'Direction',
        'pattern_desc' =>
            '(based on mathematical optimization for pattern alignment & material waste minimization).',
        'seam_info' =>
            'Yellow dashed lines indicate carpet heatbond tape seam locations (total seam length:',
        'obras_info' =>
            'All outer edges and perimeter notches are bound cleanly (total bound perimeter:',
        'perm_info' =>
            'Full-glue application to the subfloor with no seaming charges and no perimeter binding required.',
        'waste_info' => 'Estimated cutting waste:',
        'terms_title' => 'Terms and Conditions:',
        'term_1' =>
            'Prices are valid for 7 days from the issuance date, non-binding thereafter, and net excluding applicable taxes.',
        'term_2' =>
            'Stock availability is rolling and subject to change without prior notice.',
        'term_3' =>
            'Orders require payment in full or an initial deposit (minimum 50%) prior to project commencement.',
        'term_4' =>
            'Payment via company bank transfer (account details provided upon quotation acceptance).',
        'term_5' => 'Purchased materials are strictly non-returnable and non-refundable.',
        'closing' =>
            'We submit this proposal in the hope of fulfilling your requirements. We look forward to executing this project promptly. Thank you for your consideration.',
        'regards' => 'Sincerely,',
        'owner_title' => 'Owner / Managing Director',
        'btn_back' => 'Back',
        'btn_print' => 'Print / Save PDF',
        'lang_toggle' => 'Versi Bahasa Indonesia'
    ]
];

$t = $dict[$lang];
$nextLang = $lang === 'id' ? 'en' : 'id';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['doc_title'] ?> - <?= htmlspecialchars( $est['kode_estimasi'] ?? 'EST' ) ?></title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <style>
        :root {
            --primary-font: 'Times New Roman', Times, serif;
            --brand-color: #a31d24;
            --text-dark: #000000;
            --text-regular: #111111;
            --border-light: #cccccc;
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

        .page {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 12mm 16mm;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        /* Kop Surat */
        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6mm;
            min-height: 17mm;
            border-bottom: 2px solid #922c32;
            padding-bottom: 2mm;
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 10px;
        }

        .kop-logo {
            flex: 0 0 50mm;
        }

        .kop-logo img {
            width: 50mm;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .kop-address {
            flex: 1;
            text-align: right;
            font-size: 9pt;
            color: var(--text-regular);
            line-height: 1.25;
        }

        .kop-address strong {
            color: #922c32;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .doc-title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            color: var(--text-dark);
            text-decoration: underline;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 8px;
            font-size: 10pt;
            color: var(--text-dark);
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 1.5px 2px;
            vertical-align: top;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
            color: var(--text-dark);
        }

        .item-table th,
        .item-table td {
            border: 1px solid var(--border-table);
            padding: 4px 6px;
        }

        .item-table th {
            background: var(--bg-table-header);
            font-weight: bold;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .visual-layout-box {
            border: 1px solid var(--border-light);
            border-radius: 4px;
            background: #fafafa;
            padding: 6px 8px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .visual-header {
            font-size: 10pt;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }

        .visual-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .visual-canvas {
            background: #0f172a;
            border-radius: 4px;
            border: 1px solid #334155;
            flex-shrink: 0;
        }

        .visual-desc {
            font-size: 9.5pt;
            color: var(--text-regular);
            line-height: 1.3;
        }

        .terms-box {
            font-size: 9.5pt;
            color: var(--text-regular);
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .terms-box h4 {
            font-size: 10pt;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .terms-box ul {
            margin-left: 18px;
        }

        .closing-text {
            font-size: 10pt;
            line-height: 1.3;
            color: var(--text-regular);
            margin-bottom: 10px;
            text-align: justify;
        }

        .signature-container {
            display: flex;
            justify-content: flex-end;
            font-size: 10pt;
            page-break-inside: avoid;
        }

        .signature-block {
            width: 200px;
            text-align: center;
            color: var(--text-dark);
        }

        .signature-space {
            height: 42px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        /* Bilah Tombol Atas */
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
            border-radius: 4px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: 0.2s;
        }

        .btn-print {
            background: var(--brand-color);
            color: #fff;
        }

        .btn-print:hover {
            background: #82151b;
        }

        .btn-lang {
            background: #0f172a;
            color: #38bdf8;
        }

        .btn-lang:hover {
            background: #1e293b;
        }

        .btn-back {
            background: #475569;
            color: #fff;
        }

        .btn-back:hover {
            background: #334155;
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }

            .page {
                box-shadow: none !important;
                padding: 6mm 12mm !important;
                width: 100% !important;
            }

            .no-print {
                display: none !important;
            }
        }

    </style>
</head>
<body>

    <div class="no-print">
        <a href="estimasi.php" class="btn-action btn-back"><?= $t['btn_back'] ?></a>
        <a href="cetak_estimasi.php?id=<?= $idEstimasi ?>&lang=<?= $nextLang ?>"
            class="btn-action btn-lang">
            <?= $t['lang_toggle'] ?>
        </a>
        <button type="button" onclick="triggerPrint();" class="btn-action btn-print">🖨️
            <?= $t[ 'btn_print' ] ?></button>
    </div>

    <div class="page">
        <!-- KOP SURAT -->
        <div class="kop-surat">
            <div class="kop-logo">
                <img src="assets/images/logo_plastik_indah.png" alt="Plastik Indah — Interior Decoration &amp; Furnishing Material">
            </div>
            <div class="kop-address">
                <strong>CV. PLASTIK INDAH</strong><br>
                Jl. Kartini No. 114, Denpasar Utara, Kota Denpasar, Bali<br>
                Telp: (0361) 223456 | Email: cv.plastikindah@gmail.com
            </div>
        </div>

        <div class="doc-title"><?= $t['doc_title'] ?></div>

        <!-- INFORMASI UMUM -->
        <table class="meta-table">
            <tr>
                <td width="16%"><strong><?= $t['no_ref'] ?></strong></td>
                <td width="38%">: <?= htmlspecialchars( $est['kode_estimasi'] ?? 'EST-' . $est['id_estimasi'] ) ?></td>
                <td width="16%"><strong><?= $t['date'] ?></strong></td>
                <td width="30%">: <?= $lang === 'en'
                ? date('F d, Y', strtotime($est['tanggal_estimasi'] ?? 'now'))
                : date('d F Y', strtotime($est['tanggal_estimasi'] ?? 'now')) ?></td>
            </tr>
            <tr>
                <td><strong><?= $t['customer'] ?></strong></td>
                <td>: <?= htmlspecialchars($est['nama_pelanggan']) ?></td>
                <td><strong><?= $t['room_dim'] ?></strong></td>
                <td>: <?= number_format($est['panjang'], 2) ?> × <?= number_format( $est['lebar'], 2 ) ?> m</td>
            </tr>
            <tr>
                <td><strong><?= $t['project_name'] ?></strong></td>
                <td>: <?= htmlspecialchars($est['nama_ruangan']) ?></td>
                <td><strong><?= $t['net_area'] ?></strong></td>
                <td>: <?= number_format($est['total_luas'], 2) ?> m² <?= $t[ 'net_area_sub' ] ?></td>
            </tr>
        </table>

        <!-- TABEL RINCIAN MATERIAL & PEKERJAAN -->
        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%"><?= $t['no'] ?></th>
                    <th width="45%"><?= $t['work_desc'] ?></th>
                    <th width="15%"><?= $t['volume'] ?></th>
                    <th width="15%"><?= $t['unit_price'] ?></th>
                    <th width="20%"><?= $t['subtotal'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pricing): ?>
                <?php
                $labels = ['karpet' => $est['nama_karpet'], 'sambung' => $t['seam_desc'], 'obras' => $t['obras_desc'], 'lem' => $t['glue_desc'], 'pasang' => $t['install_desc']];
                $discountLabel = $lang === 'en' ? 'Discount' : 'Diskon';
                $rowNumber = 0;
                foreach ($pricing['items'] as $key => $item): ?>
                <tr>
                    <td class="text-center"><?= ++$rowNumber ?></td>
                    <td><?= htmlspecialchars($labels[$key], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($item['discount']['amount'] > 0): ?>
                        <br><small><?= $discountLabel ?> <?= $item['discount']['type'] === 'percent' ? number_format($item['discount']['value'], 2, ',', '.') . '%' : '' ?>: −Rp <?= number_format($item['discount']['amount'], 2, ',', '.') ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format($item['quantity'], 2, ',', '.') ?> <?= $key === 'karpet' ? $t['m_linear'] : (in_array($key, ['lem', 'pasang']) ? 'm²' : 'm') ?></td>
                    <td class="text-right">Rp <?= number_format($item['rate'], 2, ',', '.') ?></td>
                    <td class="text-right">
                        <?php if ($item['discount']['amount'] > 0): ?><small><s>Rp <?= number_format($item['gross'], 2, ',', '.') ?></s></small><br><?php endif; ?>
                        Rp <?= number_format($item['net'], 2, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr><td colspan="4" class="text-right"><?= $lang === 'en' ? 'Subtotal after item discounts' : 'Subtotal setelah diskon item' ?></td><td class="text-right">Rp <?= number_format($pricing['subtotal'], 2, ',', '.') ?></td></tr>
                <?php if ($pricing['discount']['amount'] > 0): ?>
                <tr><td colspan="4" class="text-right"><?= $lang === 'en' ? 'Overall discount' : 'Diskon keseluruhan' ?> <?= $pricing['discount']['type'] === 'percent' ? number_format($pricing['discount']['value'], 2, ',', '.') . '%' : '' ?></td><td class="text-right">−Rp <?= number_format($pricing['discount']['amount'], 2, ',', '.') ?></td></tr>
                <?php endif; ?>
                <?php else: ?>
                <!-- 1. Bahan Karpet -->
                <tr>
                    <td class="text-center">1</td>
                    <td>
                        <strong><?= htmlspecialchars($est['nama_karpet']) ?></strong><br>
                        <small style="color: #444;"><?= $t['roll_w'] ?>: <?= $est[ 'lebar_roll' ] ?> m
                            | <?= $t['motif'] ?>: <?= htmlspecialchars($est['motif']) ?></small>
                    </td>
                    <td class="text-center"><?= number_format( $est['total_meter_lari'], 1 ) ?> <?= $t['m_linear'] ?></td>
                    <td class="text-right">Rp <?= number_format( $est['harga_per_m_lari'], 0, ',', '.' ) ?></td>
                    <td class="text-right">Rp <?= number_format( $biayaKarpet, 0, ',', '.' ) ?></td>
                </tr>

                <?php if (($est['metode_pemasangan'] ?? 'Disambung') === 'Disambung'): ?>
                <tr>
                    <td class="text-center">2</td>
                    <td><?= $t['seam_desc'] ?></td>
                    <td class="text-center"><?= number_format( $est['total_sambungan'], 1 ) ?> m</td>
                    <td class="text-right">Rp 25.000</td>
                    <td class="text-right">Rp <?= number_format( $est['total_sambungan'] * 25000, 0, ',', '.' ) ?></td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td><?= $t['obras_desc'] ?></td>
                    <td class="text-center"><?= number_format( $est['total_obras'], 1 ) ?> m</td>
                    <td class="text-right">Rp 15.000</td>
                    <td class="text-right">Rp <?= number_format( $est['total_obras'] * 15000, 0, ',', '.' ) ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td class="text-center">2</td>
                    <td><?= $t['glue_desc'] ?></td>
                    <td class="text-center"><?= number_format( $est['total_luas'], 2 ) ?> m²</td>
                    <td class="text-right">Rp <?= number_format( ($est['biaya_lem'] ?? 0) / max(1, $est['total_luas']), 0, ',', '.' ) ?></td>
                    <td class="text-right">Rp <?= number_format( $est['biaya_lem'] ?? 0, 0, ',', '.' ) ?></td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td><?= $t['install_desc'] ?></td>
                    <td class="text-center"><?= number_format( $est['total_luas'], 2 ) ?> m²</td>
                    <td class="text-right">Rp <?= number_format(
                        ($est['biaya_jasa_pasang'] ?? 0) / max(1, $est['total_luas']),
                        0,
                        ',',
                        '.'
                    ) ?></td>
                    <td class="text-right">Rp <?= number_format( $est['biaya_jasa_pasang'] ?? 0, 0, ',', '.' ) ?></td>
                </tr>
                <?php endif; ?>

                <?php endif; ?>
                <tr style="background: var(--bg-table-header); font-weight: bold;">
                    <td colspan="4" class="text-right"><?= $t['grand_total'] ?>:</td>
                    <td class="text-right"
                        style="font-size: 10pt; color: var(--brand-color);">Rp
                        <?= number_format( $est['total_biaya'], $pricing ? 2 : 0, ',', '.' ) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- VISUALISASI JALUR PEMASANGAN -->
        <div class="visual-layout-box">
            <div class="visual-header">
                <span><?= $t['heading_pattern'] ?> <u><?= strtoupper( $est['arah_pemasangan'] ?? 'Vertikal' ) ?></u></span>
                <span><?= $t['method_label'] ?>: <?= ($est['metode_pemasangan'] ??
                    'Disambung') ===
                'Permanen'
                    ? $t['method_perm']
                    : $t['method_seam'] ?></span>
            </div>
            <div class="visual-container">
                <canvas id="pdfVisualCanvas" width="190" height="90"
                    class="visual-canvas"></canvas>
                <div class="visual-desc">
                    <p>• <?= $t['pattern_rule'] ?>: <strong><?= $t[ 'pattern_dir' ] ?>
                            <?= htmlspecialchars($est['arah_pemasangan'] ?? 'Vertikal') ?></strong> <?= $t[ 'pattern_desc' ] ?></p>
                    <?php if ( ($est['metode_pemasangan'] ?? 'Disambung') === 'Disambung' ): ?>
                    <p>• <?= $t['seam_info'] ?> <strong><?= number_format( $est['total_sambungan'], 1 ) ?> m</strong>).</p>
                    <p>• <?= $t['obras_info'] ?> <strong><?= number_format( $est['total_obras'], 1 ) ?> m</strong>).</p>
                    <?php else: ?>
                    <p>• <?= $t['perm_info'] ?></p>
                    <?php endif; ?>
                    <p>• <?= $t['waste_info'] ?> <strong><?= number_format( $est['luas_waste'] ?? 0, 2 ) ?> m²</strong>.</p>
                </div>
            </div>
        </div>

        <!-- SYARAT & KETENTUAN -->
        <div class="terms-box">
            <h4><?= $t['terms_title'] ?></h4>
            <ul>
                <li><?= $t['term_1'] ?></li>
                <li><?= $t['term_2'] ?></li>
                <li><?= $t['term_3'] ?></li>
                <li><?= $t['term_4'] ?></li>
                <li><?= $t['term_5'] ?></li>
            </ul>
        </div>

        <div class="closing-text">
            <?= $t['closing'] ?>
        </div>

        <!-- TANDA TANGAN -->
        <div class="signature-container">
            <div class="signature-block">
                <?= $t['regards'] ?><br>
                <strong>CV. PLASTIK INDAH</strong><br>
                <div class="signature-space"></div>
                <div class="signature-name">Diana I Dana</div><br>
                <div><?= $t['owner_title'] ?></div>
            </div>
        </div>
    </div>

    <script src="assets/js/room-geometry.js"></script>
    <script>
        // Membuka dialog cetak laporan.
        function triggerPrint() {
            window.print();
        }

        const canvas = document.getElementById('pdfVisualCanvas');
        const ctx = canvas.getContext('2d');
        const arah = "<?= $est['arah_pemasangan'] ?? 'Vertikal' ?>";
        const metode = "<?= $est['metode_pemasangan'] ?? 'Disambung' ?>";
        const isEnglish = <?= json_encode($lang === 'en') ?> ;

        // Menggambar denah pada laporan cetak.
        function renderLayout() {
            if (!ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const geometry = <?= json_encode(
                json_decode($est['layout_geometry'] ?? 'null', true),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?> ;
            if (!geometry || !RoomGeometry.valid(geometry.points)) {
                ctx.fillStyle = '#475569';
                ctx.font = '11px Arial';
                ctx.fillText(isEnglish ? 'No saved room outline' :
                    'Kontur belum tersimpan', 12, 40);
                return;
            }
            const points = geometry.points,
                b = RoomGeometry.bounds(points);
            const scale = Math.min((canvas.width - 24) / (b.maxX - b.minX), (canvas
                .height - 24) / (b.maxY - b.minY));
            const x = (canvas.width - (b.maxX - b.minX) * scale) / 2,
                y = (canvas.height - (b.maxY - b.minY) * scale) / 2;
            ctx.save();
            ctx.beginPath();
            points.forEach((p, i) => ctx[i ? 'lineTo' : 'moveTo'](x + (p[0] - b.minX) *
                scale, y + (p[1] - b.minY) * scale));
            ctx.closePath();
            ctx.fillStyle = 'rgba(16,185,129,.12)';
            ctx.fill();
            ctx.strokeStyle = '#059669';
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.clip();
            const width = <?= json_encode((float) $est['lebar_roll']) ?> ;
            const axis = arah === 'Vertikal' ? 0 : 1,
                start = axis === 0 ? b.minX : b.minY,
                end = axis === 0 ? b.maxX : b.maxY;
            ctx.strokeStyle = '#d97706';
            ctx.setLineDash([4, 3]);
            if (width > 0)
                for (let v = start + width, n = 0; v < end && n < 1000; v += width, n++) {
                    ctx.beginPath();
                    if (axis === 0) {
                        ctx.moveTo(x + (v - b.minX) * scale, y);
                        ctx.lineTo(x + (v - b.minX) * scale, canvas.height);
                    } else {
                        ctx.moveTo(x, y + (v - b.minY) * scale);
                        ctx.lineTo(canvas.width, y + (v - b.minY) * scale);
                    }
                    ctx.stroke();
                }
            ctx.restore();
        }

        // Menggambar panah arah pemasangan.
        function drawArrow(ctx, fromX, fromY, toX, toY) {
            const headlen = 5;
            const angle = Math.atan2(toY - fromY, toX - fromX);
            ctx.beginPath();
            ctx.moveTo(fromX, fromY);
            ctx.lineTo(toX, toY);
            ctx.lineTo(toX - headlen * Math.cos(angle - Math.PI / 6), toY - headlen * Math
                .sin(angle - Math.PI / 6));
            ctx.moveTo(toX, toY);
            ctx.lineTo(toX - headlen * Math.cos(angle + Math.PI / 6), toY - headlen * Math
                .sin(angle + Math.PI / 6));
            ctx.stroke();
        }

        window.onload = function() {
            renderLayout();
        };

    </script>
</body>
</html>
