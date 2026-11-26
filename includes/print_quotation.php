<?php
require_once __DIR__ . '/../quotation_helpers.php';

// Ambil data induk penawaran dan rincian estimasi untuk satu dokumen cetak.
$query = $pdo->prepare('SELECT * FROM tb_penawaran WHERE id_penawaran=?');
$query->execute([$quotationId]);
$quote = $query->fetch();
if (!$quote) {
    http_response_code(404);
    exit('Penawaran tidak ditemukan.');
}
$query = $pdo->prepare(
    'SELECT e.*, r.nama_ruangan, k.nama_karpet, k.lebar_roll
     FROM tb_estimasi e
     JOIN tb_ruangan r ON r.id_ruangan=e.id_ruangan
     JOIN tb_karpet k ON k.id_karpet=e.id_karpet
     WHERE e.id_penawaran=?
     ORDER BY e.id_estimasi',
);
$query->execute([$quotationId]);
$rows = $query->fetchAll();
// Gunakan harga tersimpan agar cetakan tidak berubah saat tarif katalog diperbarui.
$summary = json_decode($quote['pricing_snapshot'], true);
// Sediakan label dokumen dalam bahasa Indonesia atau Inggris.
$en = ($_GET['lang'] ?? '') === 'en';
$word = fn($id, $english) => $en ? $english : $id;
$money = fn($n) => 'Rp ' . number_format($n, 2, ',', '.');
$labels = [
    'karpet' => $word('Karpet', 'Carpet'),
    'sambung' => $word('Jasa Sambung', 'Seaming'),
    'obras' => $word('Obras', 'Edge Binding'),
    'lem' => $word('Lem', 'Adhesive'),
    'pasang' => $word('Pemasangan', 'Installation'),
];
?>
<!doctype html>
<html lang="<?= $en ? 'en' : 'id' ?>">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?= quotation_escape( $quote['kode_penawaran']) ?> — <?= $word('Penawaran', 'Quotation') ?></title>
        <style>
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0;
                background: #e9edf2;
                color: #18212f;
                font:
                    14px Arial,
                    sans-serif;
            }
            .toolbar {
                max-width: 960px;
                margin: 20px auto;
                display: flex;
                gap: 12px;
            }
            .toolbar a,
            .toolbar button {
                padding: 10px 15px;
                background: #334155;
                color: white;
                text-decoration: none;
                border: 0;
                border-radius: 6px;
                cursor: pointer;
            }
            .page {
                max-width: 960px;
                margin: 20px auto;
                padding: 42px;
                background: white;
            }
            .letterhead {
                display: flex;
                justify-content: space-between;
                gap: 24px;
                border-bottom: 3px solid #a31d24;
                padding-bottom: 20px;
            }
            .letterhead img {
                width: 180px;
                object-fit: contain;
            }
            .letterhead address {
                text-align: right;
                font-style: normal;
                line-height: 1.6;
                font-size: 12px;
            }
            h1 {
                text-align: center;
                font-size: 22px;
                margin: 28px 0;
            }
            .meta {
                display: flex;
                justify-content: space-between;
                gap: 24px;
                margin-bottom: 25px;
            }
            .estimate {
                margin-top: 30px;
            }
            h2 {
                font-size: 17px;
                color: #a31d24;
            }
            .muted {
                color: #536074;
                font-size: 12px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 12px;
            }
            th,
            td {
                border: 1px solid #d7dce2;
                padding: 9px;
                text-align: right;
            }
            th:first-child,
            td:first-child {
                text-align: left;
            }
            th {
                background: #f1f4f8;
            }
            thead {
                display: table-header-group;
            }
            tr {
                break-inside: avoid;
            }
            .images {
                display: flex;
                gap: 20px;
                align-items: center;
                justify-content: center;
                break-inside: avoid;
            }
            .images figure {
                margin: 10px;
                text-align: center;
                flex: 1;
            }
            .images img,
            .images svg {
                max-width: 100%;
                height: 180px;
                object-fit: contain;
            }
            .totals {
                margin: 30px 0 20px auto;
                max-width: 500px;
                break-inside: avoid;
            }
            .totals p {
                display: flex;
                justify-content: space-between;
                gap: 18px;
            }
            .grand {
                font-size: 19px;
                border-top: 2px solid #a31d24;
                padding-top: 15px;
            }
            .terms {
                font-size: 12px;
                line-height: 1.7;
                break-inside: avoid;
            }
            .signature {
                text-align: right;
                margin-top: 40px;
            }
            @page {
                size: A4;
                margin: 14mm;
            }
            @media print {
                body {
                    background: white;
                }
                .toolbar {
                    display: none;
                }
                .page {
                    padding: 0;
                    margin: 0;
                    max-width: none;
                }
                .estimate h2 {
                    break-after: avoid;
                }
                .images img,
                .images svg {
                    height: 150px;
                }
            }
            @media (max-width: 650px) {
                .page {
                    padding: 18px;
                }
                .letterhead,
                .meta {
                    flex-wrap: wrap;
                }
                .table-wrap {
                    overflow-x: auto;
                }
            }
        </style>
    </head>
    <body>
        <nav class="toolbar">
            <a href="riwayat_estimasi.php"><?= $word( 'Kembali', 'Back') ?></a>
            <a href="cetak_estimasi.php?penawaran=<?= $quotationId ?>&amp;lang=<?= $en ? 'id' : 'en' ?>"><?= $en ? 'Bahasa Indonesia' : 'English' ?></a>
            <button onclick="window.print()"><?= $word( 'Cetak / Simpan PDF', 'Print / Save PDF') ?></button>
        </nav>
        <main class="page">
            <!-- KOP SURAT DAN IDENTITAS PENAWARAN -->
            <header class="letterhead">
                <img src="assets/images/logo_plastik_indah.png" alt="CV. Plastik Indah" />
                <address>
                    <strong>CV. PLASTIK INDAH</strong>
                    <br />
                    Jl. Kartini No. 114, Denpasar Utara, Kota Denpasar, Bali
                    <br />
                    Telp: (0361) 223456 | cv.plastikindah@gmail.com
                </address>
            </header>
            <h1><?= $word( 'SURAT PENAWARAN & ESTIMASI BIAYA', 'QUOTATION & COST ESTIMATION') ?></h1>
            <div class="meta">
                <div>
                    <strong><?= $word( 'Kepada Yth.', 'Customer') ?></strong>
                    <p><?= quotation_escape( $quote['nama_pelanggan']) ?></p>
                </div>
                <div>
                    <?= $word( 'No. Penawaran', 'Quotation No.') ?>:
                    <strong><?= quotation_escape( $quote['kode_penawaran']) ?></strong>
                    <p><?= $word('Tanggal', 'Date') ?>: <?= date( 'd/m/Y', strtotime($quote['tanggal_penawaran'])) ?> · <?= count($rows) ?> <?= $word('estimasi', 'estimates') ?></p>
                </div>
            </div>
            <?php foreach ($rows as $i => $row): $pricing = json_decode($row['pricing_snapshot'], true); $geometry = json_decode($row['layout_geometry'], true); ?>
            <!-- RINCIAN BIAYA DAN DENAH TIAP ESTIMASI -->
            <section class="estimate">
                <h2><?= $word('Estimasi', 'Estimate') ?> <?= $i + 1 ?> — <?= quotation_escape($row['nama_ruangan']) ?></h2>
                <p><?= quotation_escape($row['nama_karpet']) ?> · <?= quotation_escape( $row['metode_pemasangan']) ?> · <?= quotation_escape($row['arah_pemasangan']) ?></p>
                <p class="muted">
                    <?= $word('Luas bersih', 'Net area') ?>: <?= number_format( $row['total_luas'], 2, ',', '.') ?> m² · <?= $word('Lebar roll', 'Roll width') ?>: <?= number_format( $row['lebar_roll'], 2, ',', '.') ?> m · Waste:
                    <?= number_format($row['luas_waste'], 2, ',', '.') ?> m²
                </p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><?= $word( 'Uraian Pekerjaan', 'Description') ?></th>
                                <th><?= $word('Volume', 'Quantity') ?></th>
                                <th><?= $word( 'Harga Satuan', 'Unit Price') ?></th>
                                <th><?= $word('Harga Awal', 'Gross') ?></th>
                                <th><?= $word( 'Diskon', 'Discount') ?></th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pricing['items'] as $itemKey => $item ): ?>
                            <tr>
                                <td><?= quotation_escape( $itemKey === 'karpet' ? $row['nama_karpet'] : $labels[$itemKey]) ?></td>
                                <td><?= number_format($item['quantity'], 2, ',', '.') ?> <?= in_array( $itemKey, ['lem', 'pasang']) ? 'm²' : ($itemKey === 'karpet' ? $word('m lari', 'linear m') : 'm') ?></td>
                                <td><?= $money($item['rate']) ?></td>
                                <td><?= $money( $item['gross']) ?></td>
                                <td>
                                    <?= $money($item['discount']['amount']) ?>
                                    <?php if ( $item['discount']['type'] === 'percent' && $item['discount']['value'] > 0 ): ?>
                                    <br />
                                    <small>(<?= number_format( $item['discount']['value'], 2, ',', '.') ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $money($item['net']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="5"><strong><?= $word( 'Subtotal estimasi setelah diskon item', 'Estimate subtotal after item discounts') ?></strong></td>
                                <td><strong><?= $money( $pricing['subtotal']) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="images">
                    <?php if ($row['layout_image']): ?>
                    <figure>
                        <img src="<?= quotation_escape( $row['layout_image']) ?>" alt="<?= quotation_escape( $row['nama_ruangan']) ?>" />
                        <figcaption class="muted"><?= $word( 'Gambar denah', 'Floor plan') ?></figcaption>
                    </figure>
                    <?php endif; ?>
                    <figure>
                        <svg
                            class="room-plan"
                            data-geometry="<?= quotation_escape( $row['layout_geometry']) ?>"
                            data-roll="<?= (float) $row[ 'lebar_roll' ] ?>"
                            data-direction="<?= quotation_escape( $row['arah_pemasangan']) ?>"
                            role="img"
                            aria-label="<?= $word( 'Pola pemasangan', 'Installation plan') ?>"
                        ></svg>
                        <figcaption class="muted"><?= $word( 'Pola pemasangan', 'Installation plan') ?> — <?= quotation_escape($row['arah_pemasangan']) ?></figcaption>
                    </figure>
                </div>
            </section>
            <?php endforeach; ?>
            <!-- TOTAL GABUNGAN SETELAH DISKON PENAWARAN -->
            <section class="totals">
                <p>
                    <span><?= $word( 'Subtotal seluruh estimasi', 'All estimates subtotal') ?></span>
                    <strong><?= $money($summary['subtotal']) ?></strong>
                </p>
                <p>
                    <span><?= $word('Diskon keseluruhan', 'Overall discount') ?> <?= $summary['discount']['type'] === 'percent' ? ' (' . number_format($summary['discount']['value'], 2, ',', '.') . '%)' : '' ?></span>
                    <strong>−<?= $money( $summary['discount']['amount']) ?></strong>
                </p>
                <p class="grand">
                    <strong><?= $word( 'TOTAL PENAWARAN', 'QUOTATION TOTAL') ?></strong>
                    <strong><?= $money($quote['total_biaya']) ?></strong>
                </p>
            </section>
            <section class="terms">
                <strong><?= $word( 'Syarat dan Ketentuan', 'Terms and Conditions') ?></strong>
                <ol>
                    <li><?= $word( 'Harga berlaku 7 hari sejak penawaran dibuat dan belum termasuk pajak.', 'Prices are valid for 7 days and exclude taxes.') ?></li>
                    <li><?= $word( 'Ketersediaan stok dapat berubah sewaktu-waktu.', 'Stock availability is subject to change.') ?></li>
                    <li><?= $word( 'Pembayaran lunas atau uang muka minimal 50% sebelum pekerjaan dimulai.', 'Full payment or a minimum 50% deposit is required before work begins.') ?></li>
                    <li><?= $word( 'Pembayaran melalui transfer ke rekening perusahaan yang diberikan setelah penawaran diterima.', 'Payment is by bank transfer to the company account provided upon acceptance.') ?></li>
                    <li><?= $word( 'Barang yang sudah dibeli tidak dapat dikembalikan.', 'Purchased materials are non-returnable.') ?></li>
                </ol>
            </section>
            <div class="signature">
                <?= $word( 'Hormat kami,', 'Sincerely,') ?>
                <br />
                <br />
                <br />
                <strong>CV. PLASTIK INDAH</strong>
            </div>
        </main>
        <script src="assets/js/room-geometry.js"></script>
        <script>
            // Menggambar batas ruangan dan jalur karpet sesuai arah serta lebar roll.
            document.querySelectorAll('.room-plan').forEach((svg, index) => {
                const points = JSON.parse(svg.dataset.geometry).points;
                const xs = points.map((p) => p[0]),
                    ys = points.map((p) => p[1]);
                const x = Math.min(...xs),
                    y = Math.min(...ys),
                    w = Math.max(...xs) - x,
                    h = Math.max(...ys) - y;
                const pad = Math.max(w, h) * 0.05,
                    stroke = Math.max(w, h) / 200;
                svg.setAttribute('viewBox', `${x - pad} ${y - pad} ${w + 2 * pad} ${h + 2 * pad}`);
                const ns = 'http://www.w3.org/2000/svg';
                // Membuat elemen SVG dengan atribut yang diperlukan oleh denah.
                function shape(tag, attributes, parent = svg) {
                    const el = document.createElementNS(ns, tag);
                    for (const [k, v] of Object.entries(attributes)) el.setAttribute(k, v);
                    parent.appendChild(el);
                    return el;
                }
                const id = 'plan-' + index,
                    defs = shape('defs', {}),
                    clip = shape('clipPath', { id }, defs);
                shape('polygon', { points: points.map((p) => p.join(',')).join(' ') }, clip);
                shape('polygon', {
                    points: points.map((p) => p.join(',')).join(' '),
                    fill: '#fff6e2',
                    stroke: '#334155',
                    'stroke-width': stroke,
                });
                const vertical = svg.dataset.direction === 'Vertikal',
                    roll = Number(svg.dataset.roll);
                const start = vertical ? x : y,
                    limit = vertical ? x + w : y + h;
                if (roll > 0)
                    for (let v = start + roll, n = 0; v < limit && n < 1000; v += roll, n++)
                        shape('line', {
                            x1: vertical ? v : x,
                            y1: vertical ? y : v,
                            x2: vertical ? v : x + w,
                            y2: vertical ? y + h : v,
                            stroke: '#b45309',
                            'stroke-width': stroke,
                            'stroke-dasharray': `${stroke * 4} ${stroke * 3}`,
                            'clip-path': `url(#${id})`,
                        });
            });
        </script>
    </body>
</html>
