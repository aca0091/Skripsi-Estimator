<?php
// pengaturan.php
require_once __DIR__ . '/auth.php';
check_page_access('pengaturan.php');
require_once __DIR__ . '/require.php';

$msg = '';
$msgType = '';
$userId = (int) $_SESSION['user_id'];

// 1. Ambil Data User Aktif
$stmtU = $pdo->prepare("SELECT * FROM tb_user WHERE id_user = ?");
$stmtU->execute([$userId]);
$user = $stmtU->fetch();

// 2. Handle Update Profil (Gambar 3.30)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama     = trim($_POST['nama_lengkap']);
    $email    = trim($_POST['email']);
    $username = trim($_POST['username']);

    $stmtUpd = $pdo->prepare("UPDATE tb_user SET nama_lengkap = ?, email = ?, username = ? WHERE id_user = ?");
    if ($stmtUpd->execute([$nama, $email, $username, $userId])) {
        $_SESSION['user_name']  = $nama;
        $_SESSION['user_email'] = $email;
        $user['nama_lengkap']   = $nama;
        $user['email']          = $email;
        $user['username']       = $username;
        $msg = 'Profil akun berhasil diperbarui.';
        $msgType = 'success';
    } else {
        $msg = 'Gagal memperbarui profil.';
        $msgType = 'danger';
    }
}

// 3. Handle Update Keamanan / Password (Gambar 3.33)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $passLama = trim($_POST['pass_lama']);
    $passBaru = trim($_POST['pass_baru']);
    $passKonf = trim($_POST['pass_konfirm']);

    if (md5($passLama) !== $user['password']) {
        $msg = 'Kata sandi saat ini tidak cocok.';
        $msgType = 'danger';
    } elseif ($passBaru !== $passKonf) {
        $msg = 'Konfirmasi kata sandi baru tidak cocok.';
        $msgType = 'danger';
    } elseif (strlen($passBaru) < 6) {
        $msg = 'Kata sandi baru minimal terdiri dari 6 karakter.';
        $msgType = 'danger';
    } else {
        $stmtPass = $pdo->prepare("UPDATE tb_user SET password = ? WHERE id_user = ?");
        $stmtPass->execute([md5($passBaru), $userId]);
        $msg = 'Kata sandi berhasil diperbarui.';
        $msgType = 'success';
    }
}

// 4. Handle Backup Database (Gambar 3.32)
if (isset($_GET['action']) && $_GET['action'] === 'export_backup') {
    $tables = ['tb_user', 'tb_karpet', 'tb_ruangan', 'tb_estimasi'];
    $backupContent = "-- BACKUP SISTEM CARPET ESTIMATOR (CV. PLASTIK INDAH)\n";
    $backupContent .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $t) {
        $backupContent .= "DROP TABLE IF EXISTS `$t`;\n";
        $rowTable = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $backupContent .= $rowTable[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $keys = array_map(function($k) { return "`$k`"; }, array_keys($r));
            $values = array_map(function($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote($v);
            }, array_values($r));
            $backupContent .= "INSERT INTO `$t` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $backupContent .= "\n";
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup_estimator_' . date('Ymd_His') . '.sql"');
    echo $backupContent;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - Carpet Estimator</title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/dash.css?v=20261123-sidebar2">
    <link rel="stylesheet" href="assets/css/pengaturan.css">
    <link rel="stylesheet" href="assets/css/logout.css">
<link rel="stylesheet" href="assets/css/app-theme.css?v=20261123-1">
</head>
<body>
    <div class="app-layout">
        <!-- SIDEBAR -->
        <?php $activePage = 'pengaturan'; require __DIR__ . '/includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-body">
<?php require __DIR__ . '/includes/app_toolbar.php'; ?>
            <h1 class="main-title">Pengaturan Sistem</h1>
            <p class="breadcrumb">Kelola profil, informasi perusahaan, keamanan, dan pencadangan data.</p>

            <?php if ($msg !== ''): ?>
                <div class="alert-message alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- MENU TAB (Sesuai Bab 3.8.10) -->
                <div class="settings-menu">
                    <button class="settings-tab-btn active" onclick="switchTab('tabProfil', this)">
                        <span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span> Pengaturan Profil
                    </button>
                    <button class="settings-tab-btn" onclick="switchTab('tabPerusahaan', this)">
                        <span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-4h6v4M8 7h1m6 0h1M8 11h1m6 0h1"/></svg></span> Pengaturan Perusahaan
                    </button>
                    <button class="settings-tab-btn" onclick="switchTab('tabBackup', this)">
                        <span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/></svg></span> Backup Data
                    </button>
                    <button class="settings-tab-btn" onclick="switchTab('tabKeamanan', this)">
                        <span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span> Pengaturan Keamanan
                    </button>
                   <a href="logout.php" 
                    onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');" 
                     class="settings-tab-btn tab-btn-logout">
                    <span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5M21 12H9"/></svg></span> Log Out </a> 
                </div>

                <!-- AREA KONTEN TAB -->
                <div class="settings-content">
                    
                    <!-- 1. PENGATURAN PROFIL (Gambar 3.30) -->
                    <div class="card-box settings-panel active" id="tabProfil">
                        <h3 class="box-title">Pengaturan Profil Pengguna</h3>
                        <form method="POST">
                            <input type="hidden" name="update_profil" value="1">
                            <div class="form-group-set">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>" required>
                            </div>
                            <div class="form-row-set">
                                <div class="form-group-set">
                                    <label>Username</label>
                                    <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                                </div>
                                <div class="form-group-set">
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-save-settings">Simpan Perubahan Profil</button>
                        </form>
                    </div>

                    <!-- 2. PENGATURAN PERUSAHAAN (Gambar 3.31) -->
                    <div class="card-box settings-panel" id="tabPerusahaan">
                        <h3 class="box-title">Pengaturan Identitas Perusahaan</h3>
                        <form method="POST">
                            <div class="form-group-set">
                                <label>Nama Usaha / Perusahaan</label>
                                <input type="text" value="CV. PLASTIK INDAH" readonly style="background:#f8fafc;">
                            </div>
                            <div class="form-row-set">
                                <div class="form-group-set">
                                    <label>Pemilik / Pimpinan Usaha</label>
                                    <input type="text" value="Diana Imelda I Dana" readonly style="background:#f8fafc;">
                                </div>
                                <div class="form-group-set">
                                    <label>Telepon Kantor</label>
                                    <input type="text" value="(0361) 223456" readonly style="background:#f8fafc;">
                                </div>
                            </div>
                            <div class="form-group-set">
                                <label>Alamat Operasional</label>
                                <textarea rows="3" readonly style="background:#f8fafc;">Jl. Kartini No. 114, Denpasar Utara, Kota Denpasar, Bali</textarea>
                            </div>
                            <p style="font-size:11.5px; color:#64748b; margin-top:6px;">
                                *Identitas perusahaan digunakan sebagai kop resmi pada seluruh lembar cetak estimasi dan rekapitulasi laporan.
                            </p>
                        </form>
                    </div>

                    <!-- 3. BACKUP DATA (Gambar 3.32) -->
                    <div class="card-box settings-panel" id="tabBackup">
                        <h3 class="box-title">Pencadangan Basis Data (Backup)</h3>
                        <p style="font-size:12.5px; color:#475569; line-height:1.5;">
                            Fitur ini memungkinkan pengelola sistem untuk mencadangkan seluruh master data karpet, data ruangan pelanggan, serta arsip kalkulasi estimasi ke dalam format file SQL terstruktur.
                        </p>
                        <div class="backup-card">
                            <div style="font-size:36px;"><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/></svg></div>
                            <div style="font-size:13px; font-weight:700; color:#0f172a; margin-top:6px;">Unduh Berkas Salinan Basis Data</div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Format .sql kompatibel dengan phpMyAdmin MySQL</div>
                            <a href="pengaturan.php?action=export_backup" class="btn-backup-download">
                                ⬇ Unduh Backup SQL Sekarang
                            </a>
                        </div>
                    </div>

                    <!-- 4. PENGATURAN KEAMANAN (Gambar 3.33) -->
                    <div class="card-box settings-panel" id="tabKeamanan">
                        <h3 class="box-title">Pengaturan Keamanan & Sandi Akun</h3>
                        <form method="POST">
                            <input type="hidden" name="update_password" value="1">
                            <div class="form-group-set">
                                <label>Kata Sandi Saat Ini</label>
                                <input type="password" name="pass_lama" placeholder="Masukkan kata sandi lama" required>
                            </div>
                            <div class="form-row-set">
                                <div class="form-group-set">
                                    <label>Kata Sandi Baru</label>
                                    <input type="password" name="pass_baru" placeholder="Minimal 6 karakter" required>
                                </div>
                                <div class="form-group-set">
                                    <label>Konfirmasi Kata Sandi Baru</label>
                                    <input type="password" name="pass_konfirm" placeholder="Ulangi kata sandi baru" required>
                                </div>
                            </div>
                            <button type="submit" class="btn-save-settings">Perbarui Kata Sandi</button>
                        </form>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabId, elem) {
            document.querySelectorAll('.settings-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.settings-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabId).classList.add('active');
            elem.classList.add('active');
        }
    </script>
</body>
</html>