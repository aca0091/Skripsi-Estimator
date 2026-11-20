```php
<?php
require_once __DIR__ . '/require.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirm  = $_POST['konfirmasi_password'] ?? '';

    // Validasi input
    if (empty($nama) || empty($email) || empty($username) || empty($password)) {
        $error = 'Semua kolom wajib diisi!';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok! Silakan cek kembali.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal terdiri dari 6 karakter!';
    } else {
        // Cek duplikasi username atau email
        $stmtCheck = $pdo->prepare(
            "SELECT id_user FROM tb_user WHERE username = ? OR email = ?"
        );
        $stmtCheck->execute([$username, $email]);

        if ($stmtCheck->rowCount() > 0) {
            $error = 'Username atau Email sudah terdaftar. Silakan gunakan yang lain!';
        } else {
            // Menyimpan password dalam bentuk teks biasa
            $stmtInsert = $pdo->prepare(
                "INSERT INTO tb_user (nama_lengkap, email, username, password)
                 VALUES (?, ?, ?, ?)"
            );

            if ($stmtInsert->execute([$nama, $email, $username, $password])) {
                $success = 'Pendaftaran berhasil! Silakan login.';
            } else {
                $error = 'Terjadi kesalahan sistem saat mendaftar.';
            }
        }
    }
}
?>
```
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up / Daftar Akun - Carpet Estimator</title>
    <link rel="stylesheet" href="assets/css/register.css">
    <style>
        .pwd-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .pwd-wrapper input {
            width: 100%;
            padding-right: 40px;
        }
        .toggle-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="logo">📐</div>
            <h2>Daftar Akun Baru</h2>
            <p>Buat akun dan tentukan kata sandi Anda sendiri</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert-box success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email Perusahaan / Staf</label>
                <input type="email" name="email" placeholder="contoh@plastikindah.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Buat username unik" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Password Pilihan Anda</label>
                <div class="pwd-wrapper">
                    <input type="password" id="regPassword" name="password" placeholder="Ketik password sendiri (min. 6 digit)" required>
                    <button type="button" class="toggle-btn" onclick="togglePass('regPassword', this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label>Ulangi Password</label>
                <div class="pwd-wrapper">
                    <input type="password" id="regConfirm" name="konfirmasi_password" placeholder="Ketik ulang password yang sama" required>
                    <button type="button" class="toggle-btn" onclick="togglePass('regConfirm', this)">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-auth">Simpan & Daftarkan Akun</button>
        </form>

        <div class="auth-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                btn.innerText = "🙈";
            } else {
                input.type = "password";
                btn.innerText = "👁️";
            }
        }
    </script>
</body>
</html>