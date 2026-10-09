<?php
require_once __DIR__ . '/../config.php'; require_login();$m=$_SERVER['REQUEST_METHOD'];
if($m==='GET')json_response(['ok'=>true,'alerts'=>db()->query('SELECT id,severity,message,source,status,created_at FROM alerts ORDER BY created_at DESC LIMIT 100')->fetchAll()]);
if(!in_array($m,['POST','PATCH'],true))json_response(['ok'=>false,'error'=>'Method not allowed.'],405);
require_csrf();$d=request_json();
if($m==='POST'){$sev=$d['severity']??'Info';$msg=trim((string)($d['message']??''));if(!in_array($sev,['Info','Warning','Critical'],true)||$msg==='')json_response(['ok'=>false,'error'=>'Valid severity and message are required.'],422);$s=db()->prepare('INSERT INTO alerts(severity,message,source) VALUES(?,?,?)');$s->execute([$sev,$msg,'Dashboard']);json_response(['ok'=>true,'id'=>(int)db()->lastInsertId()],201);}
$id=(int)($_GET['id']??0);$status=$d['status']??'Acknowledged';if($id<1||!in_array($status,['Open','Acknowledged','Resolved'],true))json_response(['ok'=>false,'error'=>'Invalid alert update.'],422);$s=db()->prepare('UPDATE alerts SET status=? WHERE id=?');$s->execute([$status,$id]);json_response(['ok'=>true]);
