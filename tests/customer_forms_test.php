<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
 foreach (['create','edit','status','invalid_status','invalid_email','delete_empty','delete_linked','delete_csrf'] as $case) {
  $p=proc_open([PHP_BINARY,__FILE__,$case],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
  if ($exit || $err || strpos($out,'FORM_PASS')===false) throw new RuntimeException("Form $case failed: $err ".substr($out,-300));
 }
 echo "Customer create/edit, status, validation and deletion protections passed. All fixture changes rolled back.\n";exit;
}
session_start();
require __DIR__.'/../config_database.php';
$case=$argv[1];$name='Form test '.bin2hex(random_bytes(5));
$pdo->beginTransaction();
$pdo->prepare('INSERT INTO tb_customer(nama_customer,alamat,tanggal_daftar) VALUES(?,?,CURDATE())')->execute([$name,'']);$customerId=(int)$pdo->lastInsertId();
$base=$pdo->query('SELECT * FROM tb_estimasi LIMIT 1')->fetch();unset($base['id_estimasi']);$base['id_customer']=$customerId;$base['status_proyek']='Estimasi';$base['kode_estimasi']='TEST-'.bin2hex(random_bytes(6));
$pdo->prepare('INSERT INTO tb_estimasi (`'.implode('`,`',array_keys($base)).'`) VALUES ('.implode(',',array_fill(0,count($base),'?')).')')->execute(array_values($base));$projectId=(int)$pdo->lastInsertId();
$_SESSION=['user_logged_in'=>true,'user_role'=>'karyawan','user_id'=>(int)$base['id_user'],'customer_csrf'=>'test-token'];
$_GET=$case==='create'?[]:['id'=>$customerId];$_SERVER['REQUEST_METHOD']='POST';
$_POST=['action'=>'save','csrf'=>'test-token','nama_customer'=>$name.' updated','kontak_person'=>'Kontak','telepon'=>'08123456789','email'=>'test@example.com','alamat'=>'Alamat uji'];
if (in_array($case,['status','invalid_status'],true)) $_POST=['action'=>'status','csrf'=>'test-token','id_estimasi'=>$projectId,'status_proyek'=>$case==='status'?'Selesai':'Invalid'];
if ($case==='invalid_email') $_POST['email']='invalid';
if (in_array($case,['delete_empty','delete_linked','delete_csrf'],true)) {
 if ($case==='delete_empty') $pdo->prepare('DELETE FROM tb_estimasi WHERE id_estimasi=?')->execute([$projectId]);
 $_POST=['action'=>'delete','csrf'=>$case==='delete_csrf'?'invalid':'test-token','id_customer'=>$customerId];
}
register_shutdown_function(function()use($pdo,$case,$name,$customerId,$projectId){
 try {
  if (in_array($case,['delete_empty','delete_linked','delete_csrf'],true)) {
   $q=$pdo->prepare('SELECT COUNT(*) FROM tb_customer WHERE id_customer=?');$q->execute([$customerId]);
   $ok=(int)$q->fetchColumn()===($case==='delete_empty'?0:1);
   if ($case==='delete_linked') $ok=$ok && http_response_code()===409;
   if ($case==='delete_csrf') $ok=$ok && http_response_code()===403;
  }
  elseif ($case==='create') {$q=$pdo->prepare('SELECT COUNT(*) FROM tb_customer WHERE nama_customer=?');$q->execute([$name.' updated']);$ok=(int)$q->fetchColumn()===1;}
  elseif (in_array($case,['status','invalid_status'],true)) {$q=$pdo->prepare('SELECT status_proyek FROM tb_estimasi WHERE id_estimasi=?');$q->execute([$projectId]);$ok=$q->fetchColumn()===($case==='status'?'Selesai':'Estimasi');}
  else {$q=$pdo->prepare('SELECT nama_customer FROM tb_customer WHERE id_customer=?');$q->execute([$customerId]);$ok=$q->fetchColumn()===($case==='edit'?$name.' updated':$name);}
  echo $ok?'FORM_PASS':'FORM_FAIL';
 } finally {if($pdo->inTransaction())$pdo->rollBack();}
});
require __DIR__.'/../data_customer.php';
