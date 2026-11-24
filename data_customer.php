<?php
require_once __DIR__ . '/auth.php';
check_page_access('data_customer.php');
require_once __DIR__ . '/require.php';
require_once __DIR__ . '/customer_helpers.php';
$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$error = '';
$openCustomerForm = false;
$customer = [
    'nama_customer' => '',
    'kontak_person' => '',
    'telepon' => '',
    'email' => '',
    'alamat' => ''
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tb_customer WHERE id_customer=?');
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        http_response_code(404);
        exit('Customer tidak ditemukan.');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    customer_check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $deleteId = filter_var($_POST['id_customer'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if (!$deleteId) {
            http_response_code(422);
            $error = 'Customer yang akan dihapus tidak valid.';
        } else {
            try {
                // The foreign key also protects against a project being added concurrently.
                $stmt = $pdo->prepare('DELETE FROM tb_customer WHERE id_customer=?');
                $stmt->execute([$deleteId]);
                if (!$stmt->rowCount()) {
                    http_response_code(404);
                    $error = 'Customer tidak ditemukan atau sudah dihapus.';
                } else {
                    header('Location: data_customer.php?deleted=1');
                    exit();
                }
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                http_response_code(409);
                $error =
                    'Customer tidak dapat dihapus karena memiliki riwayat proyek. Riwayat pesanan tetap disimpan.';
            }
        }
    } elseif ($action === 'status' && $id) {
        $status = $_POST['status_proyek'] ?? '';
        if (!in_array($status, customer_statuses(), true)) {
            $error = 'Status proyek tidak valid.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE tb_estimasi SET status_proyek=? WHERE id_estimasi=? AND id_customer=?'
            );
            $stmt->execute([$status, (int) ($_POST['id_estimasi'] ?? 0), $id]);
            header('Location: data_customer.php?id=' . $id . '&saved=1');
            exit();
        }
    } elseif ($action === 'save') {
        $openCustomerForm = true;
        foreach (array_keys($customer) as $key) {
            if (
                in_array(
                    $key,
                    ['nama_customer', 'kontak_person', 'telepon', 'email', 'alamat'],
                    true
                )
            ) {
                $customer[$key] = is_string($_POST[$key] ?? null)
                    ? trim($_POST[$key])
                    : '';
            }
        }
        if (
            $customer['nama_customer'] === '' ||
            strlen($customer['nama_customer']) > 255 ||
            strlen($customer['kontak_person']) > 255 ||
            strlen($customer['telepon']) > 50 ||
            strlen($customer['email']) > 255 ||
            strlen($customer['alamat']) > 10000
        ) {
            $error = 'Nama wajib diisi dan panjang data harus sesuai batas kolom.';
        } elseif (
            $customer['email'] !== '' &&
            !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $error = 'Format email tidak valid.';
        } else {
            $values = [
                $customer['nama_customer'],
                $customer['kontak_person'],
                $customer['telepon'],
                $customer['email'],
                $customer['alamat']
            ];
            if ($id) {
                $values[] = $id;
                $stmt = $pdo->prepare(
                    'UPDATE tb_customer SET nama_customer=?,kontak_person=?,telepon=?,email=?,alamat=? WHERE id_customer=?'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO tb_customer(nama_customer,kontak_person,telepon,email,alamat,tanggal_daftar) VALUES(?,?,?,?,?,CURDATE())'
                );
            }
            $stmt->execute($values);
            if (!$id) {
                $id = $pdo->lastInsertId();
            }
            header('Location: data_customer.php?id=' . $id . '&saved=1');
            exit();
        }
    } else {
        http_response_code(422);
        $error = 'Aksi tidak valid.';
    }
}
$projects = [];
if ($id) {
    $stmt = $pdo->prepare(
        'SELECT e.*,r.nama_ruangan FROM tb_estimasi e LEFT JOIN tb_ruangan r ON r.id_ruangan=e.id_ruangan WHERE e.id_customer=? ORDER BY e.tanggal_estimasi DESC,e.id_estimasi DESC'
    );
    $stmt->execute([$id]);
    $projects = $stmt->fetchAll();
}
$projectCount = count($projects);
$completedCount = count(
    array_filter($projects, fn($p) => $p['status_proyek'] === 'Selesai')
);
$totalProjectValue = array_sum(array_column($projects, 'total_biaya'));
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$stmt = $pdo->prepare(
    'SELECT c.*,COALESCE(p.jumlah,0) jumlah_proyek FROM tb_customer c LEFT JOIN (SELECT id_customer,COUNT(*) jumlah FROM tb_estimasi GROUP BY id_customer) p ON p.id_customer=c.id_customer WHERE c.nama_customer LIKE ? OR c.telepon LIKE ? OR c.email LIKE ? ORDER BY c.nama_customer,c.id_customer'
);
$stmt->execute(array_fill(0, 3, '%' . $search . '%'));
$customers = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $id ? 'Detail Customer' : 'Data Customer' ?> - Carpet Estimator</title>
    <link rel="icon" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/dash.css?v=20261123-sidebar2">
    <link rel="stylesheet" href="assets/css/customer.css?v=20261123-modal1">
    <link rel="stylesheet" href="assets/css/app-theme.css?v=20261123-1">
    <script src="assets/js/customer.js?v=20261123-1" defer></script>
    <link rel="stylesheet" href="assets/css/form-polish.css?v=20261124-1">
</head>
<body>
    <div class="app-layout">
        <?php $activePage = 'data_customer'; require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="main-body">
            <?php require __DIR__ . '/includes/app_toolbar.php'; ?>
            <div class="customer-page-heading">
                <div>
                    <?php if ( $id ): ?><a class="customer-back" href="data_customer.php">←
                        Kembali ke Data Customer</a><?php endif; ?>
                    <h1 class="main-title"><?= $id ? 'Detail Customer' : 'Data Customer' ?></h1>
                    <p class="breadcrumb"><?= $id
                    ? 'Identitas pelanggan dan seluruh riwayat pesanan dalam satu halaman.'
                    : 'Kelola pelanggan dan lihat riwayat pesanan perusahaan.' ?></p>
                </div>
                <button type="button" class="customer-button" data-open-customer>
                    <svg class="ui-icon" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    <?= $id ? 'Edit Customer' : 'Tambah Customer' ?>
                </button>
            </div>
            <?php if ( isset($_GET['saved']) ): ?><p class="customer-badge" role="status">Data berhasil
                disimpan.</p><?php endif; ?>
            <?php if ( isset($_GET['deleted']) ): ?><p class="customer-badge" role="status">Customer berhasil
                dihapus.</p><?php endif; ?>
            <?php if ( $error && !$openCustomerForm ): ?><p class="customer-error" role="alert"><?= customer_escape( $error ) ?></p>
            <?php endif; ?>

            <?php if ($id): ?>
            <section class="customer-card customer-identity">
                <div class="customer-section-heading">
                    <div><span class="customer-eyebrow">CUSTOMER #<?= (int) $id ?></span>
                        <h2><?= customer_escape( $customer['nama_customer'] ) ?></h2>
                    </div>
                    <span class="customer-badge"><?= $projectCount > 1
                    ? 'Repeat Customer'
                    : ($projectCount === 1
                        ? 'Pesanan Pertama'
                        : 'Belum Ada Pesanan') ?></span>
                </div>
                <dl class="customer-identity-grid">
                    <?php foreach (
                        [
                            'kontak_person' => 'Kontak Person',
                            'telepon' => 'Nomor Telepon',
                            'email' => 'Email',
                            'alamat' => 'Alamat'
                        ]
                        as $key => $label
                    ): ?>
                    <div>
                        <dt><?= $label ?></dt>
                        <dd><?= nl2br( customer_escape($customer[$key] ?: 'Belum diisi') ) ?></dd>
                    </div>
                    <?php endforeach; ?>
                    <div>
                        <dt>Terdaftar Sejak</dt>
                        <dd><?= date( 'd/m/Y', strtotime($customer['tanggal_daftar']) ) ?></dd>
                    </div>
                </dl>
            </section>
            <div class="customer-detail-metrics">
                <div><span>Jumlah Pesanan</span><strong><?= $projectCount ?>
                        <small>kali</small></strong>
                    <p>Seluruh proyek yang tercatat</p>
                </div>
                <div><span>Proyek Selesai</span><strong><?= $completedCount ?>
                        <small>proyek</small></strong>
                    <p>Berstatus selesai</p>
                </div>
                <div><span>Total Nilai Proyek</span><strong class="customer-value">Rp
                        <?= number_format( $totalProjectValue, 0, ',', '.' ) ?></strong>
                    <p>Akumulasi nilai estimasi</p>
                </div>
                <div><span>Pesanan Terakhir</span><strong
                        class="customer-value"><?= $projects ? date('d/m/Y', strtotime($projects[0]['tanggal_estimasi'])) : '—' ?></strong>
                    <p>Berdasarkan tanggal proyek</p>
                </div>
            </div>
            <section class="customer-card">
                <div class="customer-section-heading">
                    <div>
                        <h2>Riwayat Proyek Customer</h2>
                        <p class="customer-note">Sudah tercatat <?= $projectCount ?> kali
                            pesanan. Daftar mencakup semua status, termasuk Estimasi dan
                            Dibatalkan.</p>
                    </div>
                    <a class="customer-button"
                        href="estimasi.php?id_customer=<?= (int) $id ?>">Buat Proyek</a>
                </div>
                <div class="customer-table-wrap">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Proyek / Ruangan</th>
                                <th>Tanggal Pesanan</th>
                                <th>Nilai Proyek</th>
                                <th>Status Proyek</th>
                                <th>Dokumen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $i => $project): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= customer_escape( $project['nama_ruangan'] ?: 'Nama proyek belum tersedia' ) ?></strong><br><span
                                        class="customer-note"><?= customer_escape( $project['kode_estimasi'] ) ?></span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($project['tanggal_estimasi'])) ?></td>
                                <td class="customer-nowrap">Rp <?= number_format( $project['total_biaya'], 0, ',', '.' ) ?></td>
                                <td>
                                    <form method="post" class="customer-form">
                                        <input type="hidden" name="csrf"
                                            value="<?= customer_csrf() ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id_estimasi"
                                            value="<?= (int) $project[ 'id_estimasi' ] ?>">
                                        <select aria-label="Status <?= customer_escape( $project['kode_estimasi'] ) ?>"
                                            name="status_proyek">
                                            <?php foreach ( customer_statuses() as $status ): ?><option <?= $status === $project['status_proyek'] ? 'selected' : '' ?>>
                                                <?= $status ?></option>
                                            <?php endforeach; ?>
                                        </select><button
                                            class="customer-button">Simpan</button>
                                    </form>
                                </td>
                                <td><a href="cetak_estimasi.php?id=<?= (int) $project[ 'id_estimasi' ] ?>">Cetak
                                        estimasi</a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ( !$projects ): ?><tr>
                                <td colspan="6" class="customer-empty">Belum ada pesanan.
                                    Klik Buat Proyek untuk mencatat pesanan pertama.</td>
                            </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="customer-card customer-delete-section">
                <div>
                    <h2>Hapus Customer</h2>
                    <p class="customer-note"><?= $projectCount
                    ? 'Customer ini memiliki riwayat proyek sehingga tidak dapat dihapus.'
                    : 'Customer tanpa riwayat proyek dapat dihapus dari daftar.' ?></p>
                </div>
                <button class="customer-danger" type="button"
                    data-delete-customer="<?= (int) $id ?>"
                    data-customer-name="<?= customer_escape( $customer['nama_customer'] ) ?>" <?= $projectCount ? 'disabled' : '' ?>>Hapus
                    Customer</button>
            </section>
            <?php else: ?>
            <section class="customer-card">
                <div class="customer-section-heading">
                    <h2>Daftar Customer</h2><span class="customer-note"><?= count( $customers ) ?>
                        customer<?= $search !== '' ? ' ditemukan' : '' ?></span>
                </div>
                <form method="get" class="customer-form customer-search">
                    <input name="q" aria-label="Cari customer"
                        placeholder="Cari nama, telepon, atau email…"
                        value="<?= customer_escape( $search ) ?>">
                    <button class="customer-button">Cari</button><?php if ( $search !== '' ): ?><a
                        href="data_customer.php">Reset</a><?php endif; ?>
                </form>
                <div class="customer-table-wrap">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Kontak</th>
                                <th>Terdaftar</th>
                                <th>Jumlah Pesanan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><strong><?= customer_escape( $c['nama_customer'] ) ?></strong>
                                    <small>#<?= (int) $c['id_customer'] ?></small><?php if ( $c['jumlah_proyek'] > 1 ): ?><br><span
                                        class="customer-badge">Repeat
                                        Customer</span><?php endif; ?></td>
                                <td><?= customer_escape( $c['kontak_person'] ?: '—' ) ?><br><?= customer_escape( $c['telepon'] ?: '—' ) ?><br><?= customer_escape($c['email'] ?: '—') ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($c['tanggal_daftar'])) ?></td>
                                <td><strong><?= (int) $c[ 'jumlah_proyek' ] ?> kali</strong><br><span
                                        class="customer-note">pesanan / proyek</span></td>
                                <td>
                                    <div class="customer-row-actions"><a
                                            class="customer-secondary"
                                            href="data_customer.php?id=<?= (int) $c[ 'id_customer' ] ?>">Lihat
                                            Detail</a>
                                        <button type="button" class="customer-danger"
                                            data-delete-customer="<?= (int) $c[ 'id_customer' ] ?>"
                                            data-customer-name="<?= customer_escape( $c['nama_customer'] ) ?>"
                                            <?= $c['jumlah_proyek'] ? 'disabled title="Customer memiliki riwayat proyek"' : '' ?>>Hapus</button>
                                    </div>
                                    <?php if ( $c['jumlah_proyek'] ): ?><span class="customer-note">Riwayat
                                        proyek dilindungi</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ( !$customers ): ?><tr>
                                <td colspan="5" class="customer-empty"><?= $search !== ''
                                ? 'Tidak ada customer yang cocok dengan pencarian.'
                                : 'Belum ada customer. Klik Tambah Customer untuk mulai.' ?>
                                </td>
                            </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <dialog id="customerDialog" class="customer-dialog"
                aria-labelledby="customerDialogTitle"
                data-open-on-load="<?= $openCustomerForm ? 'true' : 'false' ?>">
                <div class="customer-dialog-heading">
                    <div>
                        <h2 id="customerDialogTitle"><?= $id ? 'Edit Customer' : 'Tambah Customer' ?></h2>
                        <p class="customer-note">Isi identitas pelanggan. Nama customer
                            wajib diisi.</p>
                    </div><button type="button" class="customer-dialog-close"
                        data-close-dialog aria-label="Tutup popup">×</button>
                </div>
                <?php if ( $error && $openCustomerForm ): ?><p class="customer-error" role="alert"><?= customer_escape( $error ) ?>
                </p><?php endif; ?>
                <form method="post" class="customer-form">
                    <input type="hidden" name="csrf" value="<?= customer_csrf() ?>"><input
                        type="hidden" name="action" value="save">
                    <div class="customer-grid">
                        <?php foreach (
                            [
                                'nama_customer' => 'Nama Customer / Perusahaan',
                                'kontak_person' => 'Kontak Person',
                                'telepon' => 'Nomor Telepon',
                                'email' => 'Email'
                            ]
                            as $key => $label
                        ): ?>
                        <div><label
                                for="<?= $key ?>"><?= $label, $key === 'nama_customer' ? ' *' : '' ?></label><input
                                id="<?= $key ?>" name="<?= $key ?>"
                                type="<?= $key === 'email' ? 'email' : ($key === 'telepon' ? 'tel' : 'text') ?>" maxlength="<?= $key === 'telepon' ? 50 : 255 ?>"
                                value="<?= customer_escape($customer[$key]) ?>" <?= $key === 'nama_customer' ? 'required autofocus' : '' ?>></div>
                        <?php endforeach; ?>
                        <div class="customer-full-field"><label
                                for="alamat">Alamat</label><textarea id="alamat"
                                name="alamat" maxlength="10000"
                                rows="3"><?= customer_escape( $customer['alamat'] ) ?></textarea></div>
                    </div>
                    <div class="customer-dialog-footer"><button type="button"
                            class="customer-secondary"
                            data-close-dialog>Batal</button><button
                            class="customer-button">Simpan Customer</button></div>
                </form>
            </dialog>
            <dialog id="deleteCustomerDialog"
                class="customer-dialog customer-delete-dialog"
                aria-labelledby="deleteCustomerTitle">
                <div class="customer-dialog-heading">
                    <h2 id="deleteCustomerTitle">Hapus Customer?</h2><button type="button"
                        class="customer-dialog-close" data-close-dialog
                        aria-label="Tutup konfirmasi">×</button>
                </div>
                <p class="customer-note">Customer <strong
                        id="deleteCustomerName"></strong> akan dihapus. Tindakan ini tidak
                    dapat dibatalkan. Customer yang memiliki proyek tidak dapat dihapus.
                </p>
                <form method="post"><input type="hidden" name="csrf"
                        value="<?= customer_csrf() ?>"><input type="hidden" name="action"
                        value="delete"><input type="hidden" name="id_customer"
                        id="deleteCustomerId">
                    <div class="customer-dialog-footer"><button type="button"
                            class="customer-secondary" data-close-dialog
                            autofocus>Batal</button><button
                            class="customer-danger customer-danger-solid">Ya, Hapus
                            Customer</button></div>
                </form>
            </dialog>
        </main>
    </div>
</body>
</html>
