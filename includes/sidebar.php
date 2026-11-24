<aside class="sidebar">
    <div class="brand">
        <img class="brand-icon" src="assets/images/logo_estimasi.png" width="42"
            height="42" alt="Logo CV. Plastik Indah">
        <div class="brand-title">CARPET ESTIMATOR<span>CV. Plastik Indah</span></div>
    </div>
    <nav class="nav-menu" aria-label="Menu utama">
        <div class="nav-caption">MENU UTAMA</div>
        <?php if (can_access_page('dashboard.php')): ?>
        <a href="dashboard.php" class="nav-link<?= $activePage === 'dashboard' ? ' active' : '' ?>" <?= $activePage === 'dashboard' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                <rect x="14" y="3" width="7" height="7" rx="1.5" />
                <rect x="3" y="14" width="7" height="7" rx="1.5" />
                <rect x="14" y="14" width="7" height="7" rx="1.5" />
            </svg><span>Dashboard</span></a>
        <?php endif; ?>
        <?php if (can_access_page('data_customer.php')): ?>
        <a href="data_customer.php" class="nav-link<?= $activePage === 'data_customer' ? ' active' : '' ?>" <?= $activePage === 'data_customer' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="7" r="4" />
                <path
                    d="M2 21v-3a7 7 0 0 1 14 0v3M16 3a4 4 0 0 1 0 8m3 3a6 6 0 0 1 3 5v2" />
            </svg><span>Data Customer</span></a>
        <?php endif; ?>
        <?php if (can_access_page('data_karpet.php')): ?>
        <a href="data_karpet.php" class="nav-link<?= $activePage === 'data_karpet' ? ' active' : '' ?>" <?= $activePage === 'data_karpet' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path
                    d="M5 4h14v16H5zM8 4v16M16 4v16M3 7h2m-2 5h2m-2 5h2m14-10h2m-2 5h2m-2 5h2" />
            </svg><span>Data Karpet</span></a>
        <?php endif; ?>
        <?php if (can_access_page('estimasi.php')): ?>
        <a href="estimasi.php" class="nav-link<?= $activePage === 'estimasi' ? ' active' : '' ?>" <?= $activePage === 'estimasi' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="5" y="3" width="14" height="18" rx="2" />
                <path d="M8 7h8M8 11h2m4 0h2m-8 4h2m4 0h2m-8 3h2m4 0h2" />
            </svg><span>Estimasi</span></a>
        <?php endif; ?>
        <?php if (can_access_page('riwayat_estimasi.php')): ?>
        <a href="riwayat_estimasi.php" class="nav-link<?= $activePage === 'riwayat_estimasi' ? ' active' : '' ?>"
            <?= $activePage === 'riwayat_estimasi' ? ' aria-current="page"' : '' ?>><svg class="sidebar-icon" width="20" height="20"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7M12 7v5l3 2" />
            </svg><span>Riwayat Estimasi</span></a>
        <?php endif; ?>
        <?php if (can_access_page('laporan.php')): ?>
        <a href="laporan.php" class="nav-link<?= $activePage === 'laporan' ? ' active' : '' ?>" <?= $activePage === 'laporan' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path
                    d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 17v-3m4 3v-6m4 6v-4" />
            </svg><span>Laporan</span></a>
        <?php endif; ?>
        <?php if (can_access_page('pengaturan.php')): ?>
        <a href="pengaturan.php" class="nav-link<?= $activePage === 'pengaturan' ? ' active' : '' ?>" <?= $activePage === 'pengaturan' ? ' aria-current="page"' : '' ?>><svg
                class="sidebar-icon" width="20" height="20" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path
                    d="m9 3-.7 2.3-2 .9L4 5.7 2 9l1.6 1.8v2.4L2 15l2 3.3 2.3-.5 2 .9L9 21h6l.7-2.3 2-.9 2.3.5 2-3.3-1.6-1.8v-2.4L22 9l-2-3.3-2.3.5-2-.9L15 3z" />
                <circle cx="12" cy="12" r="3" />
            </svg><span>Pengaturan</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="avatar"><svg class="sidebar-icon" width="20" height="20"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                </svg></div>
            <div class="profile-info">
                <div class="p-name"><?= htmlspecialchars( $_SESSION['user_name'] ?? 'Admin' ) ?></div>
                <div class="p-email"><?= ($_SESSION['user_role'] ?? '') === 'owner' ? 'Owner' : 'Karyawan' ?></div>
                <div class="p-email"><?= htmlspecialchars( $_SESSION['user_email'] ?? '' ) ?></div>
            </div>
        </div>
        <a class="sidebar-logout" href="logout.php"
            onclick="return confirm('Yakin ingin keluar?');"><svg class="sidebar-icon"
                width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5M21 12H9" />
            </svg><span>Keluar</span></a>
    </div>
</aside>
