<?php
require_once __DIR__ . '/auth.php';
check_page_access('read_dimensions.php');
require_once __DIR__ . '/require.php';
header('Content-Type: application/json; charset=utf-8');
function dimension_error($status, $message) {
    http_response_code($status);
    echo json_encode(['error'=>$message]);
    exit;
}
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') dimension_error(405, 'Gunakan unggahan gambar.');
$file=$_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) dimension_error(422, 'Gambar tidak berhasil diunggah untuk pembacaan ukuran.');
if ($file['size'] > 10*1024*1024) dimension_error(422, 'Ukuran gambar maksimal 10 MB.');
$info=@getimagesize($file['tmp_name']);
if (!$info || !in_array($info['mime'], ['image/jpeg','image/png'],true) || $info[0]*$info[1]>24000000) {
    dimension_error(422, 'Gunakan gambar JPG atau PNG dengan maksimal 24 megapiksel.');
}
$binary=__DIR__.'/scripts/bin/read_dimensions';
if (!is_executable($binary)) dimension_error(503, 'Pembaca angka belum tersedia. Ukuran tetap bisa diisi manual.');
$output=[]; $code=0;
exec(escapeshellarg($binary).' '.escapeshellarg($file['tmp_name']).' 2>/dev/null', $output, $code);
$readings=json_decode(implode("\n",$output),true);
if ($code !== 0 || !is_array($readings)) dimension_error(422, 'Angka belum terbaca. Masukkan ukuran manual atau unggah gambar lebih jelas.');
echo json_encode(['readings'=>$readings]);
