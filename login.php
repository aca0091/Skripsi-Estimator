<?php
// login.php
require_once __DIR__ . '/require.php';

// Jika sudah login, langsung arahkan ke dashboard
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM tb_user WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Membandingkan password secara langsung (plain text)
        if ($user && $password === $user['password']) {
            session_regenerate_id(true);
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['nama_lengkap'];
            $_SESSION['user_email'] = $user['email'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Username atau Password yang Anda masukkan salah!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Carpet Estimator</title>
    <link rel="icon" type="image/png" href="assets/images/logo_estimasi.png">
    <link rel="stylesheet" href="assets/css/login.css">

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
    <link rel="stylesheet" href="assets/css/form-polish.css?v=20261124-1">
</head>

<body>
    <div class="auth-card">

        <div class="auth-header">
            <div class="logo"><img src="assets/images/logo_estimasi.png"
                    alt="Logo CV. Plastik Indah"></div>
            <h2>Carpet Estimator</h2>
            <p>Sistem Pengukuran & Estimasi CV. Plastik Indah</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert-box">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username Anda"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>

                <div class="pwd-wrapper">
                    <input type="password" id="loginPassword" name="password"
                        placeholder="Masukkan password Anda" required>

                    <button type="button" class="toggle-btn"
                        onclick="togglePass('loginPassword', this)">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-auth">
                Masuk ke Sistem
            </button>

        </form>

        <div class="auth-footer">
            Belum punya akun?
            <a href="register.php">Daftar di sini</a>
        </div>

    </div>

    <script>
        // Menampilkan atau menyembunyikan kata sandi.
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
