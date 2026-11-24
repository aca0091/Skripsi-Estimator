<?php
require_once __DIR__ . '/auth.php';
check_page_access('read_dimensions.php');
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Pembacaan ukuran kini dilakukan langsung di browser. Muat ulang halaman Estimasi.']);
