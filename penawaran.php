<?php
require_once __DIR__ . '/auth.php';
check_page_access('estimasi.php');
require_once __DIR__ . '/require.php';
require_once __DIR__ . '/quotation_helpers.php';

// Ambil draft dari sesi agar pelanggan dan estimasi tetap dalam satu penawaran.
$key = $_POST['draft'] ?? ($_GET['draft'] ?? '');
if (!is_string($key) || !isset($_SESSION['quotations'][$key])) {
    http_response_code(404);
    exit(
        'Draft tidak ditemukan atau sudah disimpan. <a href="estimasi.php">Buat penawaran baru</a>'
    );
}
$draft = &$_SESSION['quotations'][$key];
$error = '';
// Proses penghapusan estimasi dari draft atau penyimpanan penawaran final.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    quotation_check_token();
    if (isset($_POST['remove'])) {
        unset($draft['rows'][(int) $_POST['remove']]);
        $draft['rows'] = array_values($draft['rows']);
        header('Location: penawaran.php?draft=' . $key);
        exit();
    }
    try {
        $discount = [
            'type' => $_POST['discount_type'] ?? 'percent',
            'value' => $_POST['discount_value'] ?? 0,
        ];
        $draft['discount'] = $discount;
        $id = save_quotation($pdo, $draft, $discount);
        unset($_SESSION['quotations'][$key]);
        header(
            'Location: ' .
                (isset($_POST['print'])
                    ? 'cetak_estimasi.php?penawaran=' . $id
                    : 'riwayat_estimasi.php'),
        );
        exit();
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

// Siapkan subtotal setelah diskon item untuk ringkasan dan diskon keseluruhan.
$subtotal = array_sum(
    array_map(fn($r) => $r['pricing']['subtotal'], $draft['rows']),
);
$discount = $draft['discount'] ?? ['type' => 'percent', 'value' => ''];
?>
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Ringkasan Penawaran</title>
        <link rel="stylesheet" href="assets/css/dash.css" />
        <link rel="stylesheet" href="assets/css/estimasi.css" />
        <link rel="stylesheet" href="assets/css/app-theme.css" />
        <link rel="stylesheet" href="assets/css/form-polish.css" />
        <style>
            .quote-rows {
                display: grid;
                gap: 16px;
            }
            .quote-row {
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 18px;
                display: flex;
                gap: 20px;
                align-items: start;
            }
            .quote-row img {
                width: 130px;
                height: 110px;
                object-fit: contain;
            }
            .quote-row h3 {
                margin: 0 0 8px;
            }
            .quote-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 20px;
            }
            @media (max-width: 650px) {
                .quote-row {
                    flex-wrap: wrap;
                }
            }
        </style>
    </head>
    <body>
        <div class="app-layout">
            <?php $activePage = 'estimasi'; require __DIR__ . '/includes/sidebar.php'; ?>
            <main class="main-body">
                <?php require __DIR__ . '/includes/app_toolbar.php'; ?>
                <h1 class="main-title">Ringkasan Penawaran</h1>
                <p>Satu pelanggan, satu nomor penawaran, beberapa estimasi.</p>
                <div class="card-box">
                    <strong><?= quotation_escape( $draft['customer_name'] ?? 'Belum ada pelanggan') ?></strong>
                    <p>Tanggal: <?= quotation_escape( $draft['date'] ?? '—') ?> · <?= count($draft['rows']) ?> estimasi</p>
                    <!-- DAFTAR ESTIMASI DALAM SATU PENAWARAN -->
                    <div class="quote-rows">
                        <?php foreach ($draft['rows'] as $i => $row): ?>
                        <article class="quote-row">
                            <?php if ($row['image']): ?>
                            <img src="<?= quotation_escape( $row['image']) ?>" alt="Denah <?= quotation_escape($row['name']) ?>" />
                            <?php endif; ?>
                            <div style="flex: 1">
                                <h3>Estimasi <?= $i + 1 ?> — <?= quotation_escape( $row['name']) ?></h3>
                                <p><?= quotation_escape( $row['carpet_name']) ?> · <?= quotation_escape($row['method']) ?> · <?= quotation_escape( $row['direction']) ?></p>
                                <p>Luas: <?= number_format( $row['net_area'], 2, ',', '.') ?> m² · Karpet: <?= number_format($row['meters'], 2, ',', '.') ?> m lari</p>
                                <strong>Rp <?= number_format( $row['pricing']['subtotal'], 2, ',', '.') ?></strong>
                                <small>(setelah diskon item)</small>
                                <details>
                                    <summary>Rincian biaya</summary>
                                    <ul>
                                        <?php foreach ( $row['pricing']['items'] as $itemKey => $item ): ?>
                                        <li>
                                            <?= quotation_escape(ucfirst($itemKey)) ?>: Rp <?= number_format( $item['gross'], 2, ',', '.') ?> − diskon Rp <?= number_format( $item['discount']['amount'], 2, ',', '.') ?> =
                                            Rp <?= number_format( $item['net'], 2, ',', '.') ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            </div>
                            <form
                                method="post"
                                onsubmit="
                                    return confirm('Hapus estimasi ini dari draft penawaran?');
                                "
                            >
                                <input type="hidden" name="draft" value="<?= $key ?>" />
                                <input type="hidden" name="csrf" value="<?= quotation_token() ?>" />
                                <button
                                    type="submit"
                                    class="btn-wizard btn-prev"
                                    name="remove"
                                    value="<?= $i ?>"
                                >
                                    Hapus
                                </button>
                            </form>
                        </article>
                        <?php endforeach; ?>
                        <?php if ( !$draft['rows'] ): ?>
                        <p>Belum ada estimasi. Tambahkan ruangan dan bahan untuk memulai.</p>
                        <?php endif; ?>
                    </div>
                    <div class="quote-actions">
                        <a class="btn-wizard btn-next" href="estimasi.php?draft=<?= $key ?>">
                            ＋ Tambah Estimasi
                        </a>
                    </div>
                </div>
                <!-- DISKON KESELURUHAN DAN AKSI SIMPAN -->
                <form method="post" class="card-box" id="quoteForm">
                    <input type="hidden" name="draft" value="<?= $key ?>" />
                    <input type="hidden" name="csrf" value="<?= quotation_token() ?>" />
                    <h3>Total Penawaran</h3>
                    <p>
                        Subtotal seluruh estimasi:
                        <strong>Rp <?= number_format( $subtotal, 2, ',', '.') ?></strong>
                    </p>
                    <p>
                        Diskon keseluruhan bersifat opsional dan dihitung sekali dari gabungan
                        estimasi setelah diskon item.
                    </p>
                    <div class="form-row-2">
                        <div>
                            <label for="discountType" class="form-lbl">Jenis diskon</label>
                            <select name="discount_type" id="discountType" class="form-ctrl">
                                <option value="percent" <?= $discount[ 'type' ] === 'percent' ? 'selected' : '' ?>>Persen (%)</option>
                                <option value="amount" <?= $discount['type'] === 'amount' ? 'selected' : '' ?>>Nominal (Rp)</option>
                            </select>
                        </div>
                        <div>
                            <label for="discountValue" class="form-lbl">Nilai diskon</label>
                            <input
                                type="number"
                                name="discount_value"
                                id="discountValue"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                class="form-ctrl"
                                value="<?= quotation_escape( $discount['value']) ?>"
                            />
                        </div>
                    </div>
                    <p id="discountError" role="alert" style="color: #b91c1c"><?= quotation_escape( $error) ?></p>
                    <p>
                        Potongan:
                        <strong id="quoteDiscount"></strong>
                    </p>
                    <h2>
                        Total akhir:
                        <span id="quoteTotal"></span>
                    </h2>
                    <div class="quote-actions">
                        <button class="btn-wizard btn-next" name="save" <?= !$draft[ 'rows' ] ? 'disabled' : '' ?>>
                            Simpan Penawaran
                        </button>
                        <button class="btn-wizard btn-save-final" name="print" <?= !$draft[ 'rows' ] ? 'disabled' : '' ?>>
                            Simpan &amp; Cetak Penawaran
                        </button>
                    </div>
                </form>
            </main>
        </div>
        <script>
            const subtotal = <?= json_encode($subtotal) ?>;
            const type = document.getElementById('discountType'),
                value = document.getElementById('discountValue');
            // Memperbarui potongan dan total akhir saat pengguna mengubah diskon.
            function updateQuote() {
                const amount = Number(value.value),
                    maximum = type.value === 'percent' ? 100 : subtotal;
                const valid =
                    !value.validity.badInput &&
                    Number.isFinite(amount) &&
                    amount >= 0 &&
                    amount <= maximum;
                value.max = maximum;
                value.setCustomValidity(valid ? '' : 'Diskon melebihi batas yang diperbolehkan.');
                const discount =
                    Math.round(
                        (type.value === 'percent' ? (subtotal * amount) / 100 : amount) * 100,
                    ) / 100;
                const money = (n) =>
                    'Rp ' +
                    n.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                document.getElementById('quoteDiscount').textContent = valid
                    ? money(discount)
                    : '—';
                document.getElementById('quoteTotal').textContent = valid
                    ? money(Math.round((subtotal - discount) * 100) / 100)
                    : '—';
                document.getElementById('discountError').textContent = valid
                    ? ''
                    : 'Diskon maksimal 100% atau sebesar subtotal penawaran.';
            }
            type.addEventListener('change', updateQuote);
            value.addEventListener('input', updateQuote);
            updateQuote();
        </script>
    </body>
</html>
