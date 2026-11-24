<div class="app-toolbar">
    <div class="toolbar-brand"><span class="toolbar-mark" aria-hidden="true"></span>
        Workspace <span class="toolbar-divider">/</span> <span>CV. Plastik Indah</span>
    </div>
    <div class="toolbar-account"><span
            class="role-chip"><?= ($_SESSION['user_role'] ?? '') === 'owner' ? 'Owner' : 'Karyawan' ?></span><span><?= htmlspecialchars( $_SESSION['user_name'] ?? 'Pengguna', ENT_QUOTES, 'UTF-8' ) ?></span></div>
</div>
