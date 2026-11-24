<?php
// index.php
require_once __DIR__ . '/require.php';
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Selamat Datang - Carpet Estimator CV. Plastik Indah</title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/welcomep.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap"
        rel="stylesheet">
</head>
<body>
    <div class="welcome-box">
        <div class="logo-circle"><img src="assets/images/logo_estimasi.png"
                alt="Logo CV. Plastik Indah"></div>
        <h1 class="welcome-title">CARPET ESTIMATOR</h1>
        <p class="welcome-subtitle">Sistem Pengukuran Estimasi Kebutuhan Karpet Roll dan
            Biaya Finishing CV. Plastik Indah Berbasis Pengolahan Citra Digital dan
            Integer Linear Programming.</p>

        <div class="action-buttons">
            <a href="login.php" class="btn btn-login">Masuk ke Sistem</a>
            <a href="register.php" class="btn btn-register">Daftar Akun Baru</a>
        </div>
    </div>
</body>
</html>
