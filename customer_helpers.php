<?php
function customer_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function customer_statuses() { return ['Estimasi', 'Diproses', 'Selesai', 'Dibatalkan']; }
function customer_csrf() {
    if (empty($_SESSION['customer_csrf'])) $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['customer_csrf'];
}
function customer_check_csrf() {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(customer_csrf(), $_POST['csrf'])) {
        http_response_code(403); exit('Form kedaluwarsa. Muat ulang halaman.');
    }
}
function customer_report($pdo, $start, $end, $carpet = 0, $method = '') {
    $where='e.tanggal_estimasi BETWEEN ? AND ?'; $params=[$start,$end];
    if ($carpet>0) { $where.=' AND e.id_karpet=?'; $params[]=$carpet; }
    if ($method!=='') { $where.=' AND e.metode_pemasangan=?'; $params[]=$method; }
    $stmt=$pdo->prepare("SELECT c.*, COALESCE(p.jumlah,0) AS jumlah_periode, COALESCE(p.nilai,0) AS nilai_periode,
       COALESCE(a.jumlah,0) AS jumlah_total
       FROM tb_customer c
       LEFT JOIN (SELECT e.id_customer,COUNT(*) jumlah,SUM(e.total_biaya) nilai FROM tb_estimasi e WHERE $where GROUP BY e.id_customer) p ON p.id_customer=c.id_customer
       LEFT JOIN (SELECT id_customer,COUNT(*) jumlah FROM tb_estimasi WHERE tanggal_estimasi<=? GROUP BY id_customer) a ON a.id_customer=c.id_customer
       WHERE (c.tanggal_daftar<=? OR COALESCE(a.jumlah,0)>0) ORDER BY jumlah_periode DESC,c.nama_customer,c.id_customer");
    $stmt->execute(array_merge($params,[$end,$end])); $rows=$stmt->fetchAll();
    $new=0;$repeat=0;$active=0;
    foreach ($rows as $row) {
        if ($row['tanggal_daftar'] >= $start && $row['tanggal_daftar'] <= $end) $new++;
        if ($row['jumlah_periode']>0) { $active++; if ($row['jumlah_total']>1) $repeat++; }
    }
    return ['rows'=>$rows,'total'=>count($rows),'new'=>$new,'repeat'=>$repeat,'active'=>$active];
}
