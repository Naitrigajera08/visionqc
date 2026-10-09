<?php
require_once __DIR__ . '/../config.php'; require_login();
$type=$_GET['type']??'stats';
if($type==='stats'){
 $p=(int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
 $total=(int)db()->query('SELECT COUNT(*) FROM detections')->fetchColumn();
 $def=(int)db()->query("SELECT COUNT(*) FROM detections WHERE category <> 'Good'")->fetchColumn();
 $loss=(float)db()->query("SELECT COALESCE(SUM(estimated_loss),0) FROM detections WHERE DATE(detected_at)=CURDATE()")->fetchColumn();
 json_response(['ok'=>true,'products'=>$p,'total'=>$total,'defects'=>$def,'loss'=>$loss]);
}
if($type==='history'){
 $q=trim((string)($_GET['q']??''));$sql='SELECT id,detection_code,product_id,product_name,category,camera,confidence,status,estimated_loss,detected_at FROM detections';$params=[];
 if($q!==''){$sql.=' WHERE detection_code LIKE ? OR product_id LIKE ? OR product_name LIKE ? OR category LIKE ? OR camera LIKE ?';$like='%'.$q.'%';$params=[$like,$like,$like,$like,$like];}
 $sql.=' ORDER BY detected_at DESC LIMIT 500';$s=db()->prepare($sql);$s->execute($params);json_response(['ok'=>true,'rows'=>$s->fetchAll()]);
}
if($type==='machines'){json_response(['ok'=>true,'machines'=>db()->query('SELECT machine_code,name,status,health_score,availability FROM machines ORDER BY name')->fetchAll()]);}
json_response(['ok'=>false,'error'=>'Unknown data type.'],400);
