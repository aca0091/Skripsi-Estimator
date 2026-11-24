<?php
require_once __DIR__ . '/auth.php';
check_page_access('data_ruangan.php');
require_once __DIR__ . '/require.php';

// Data ruangan kini dikelola melalui proses estimasi.
header('Location: estimasi.php');
exit;
