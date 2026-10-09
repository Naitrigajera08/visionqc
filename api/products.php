<?php
require_once __DIR__ . '/../config.php'; require_login();
$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'){ $rows=db()->query('SELECT id,product_code,name,unit_price,created_at FROM products ORDER BY id DESC')->fetchAll(); json_response(['ok'=>true,'products'=>$rows]); }
if(!in_array($method,['POST','PUT','DELETE'],true)) json_response(['ok'=>false,'error'=>'Method not allowed.'],405);
require_csrf();
if($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<1) json_response(['ok'=>false,'error'=>'Invalid product ID.'],422); $s=db()->prepare('DELETE FROM products WHERE id=?');$s->execute([$id]);json_response(['ok'=>true,'deleted'=>$s->rowCount()]); }
$d=request_json();$code=trim((string)($d['product_code']??''));$name=trim((string)($d['name']??''));$price=filter_var($d['unit_price']??null,FILTER_VALIDATE_FLOAT);
if($code===''||$name===''||$price===false||$price<0) json_response(['ok'=>false,'error'=>'Product code, name and a non-negative price are required.'],422);
try {
 if($method==='POST'){$s=db()->prepare('INSERT INTO products(product_code,name,unit_price) VALUES(?,?,?)');$s->execute([$code,$name,$price]);json_response(['ok'=>true,'id'=>(int)db()->lastInsertId()],201);}
 $id=(int)($_GET['id']??0);if($id<1)json_response(['ok'=>false,'error'=>'Invalid product ID.'],422);
 $s=db()->prepare('UPDATE products SET product_code=?,name=?,unit_price=? WHERE id=?');$s->execute([$code,$name,$price,$id]);json_response(['ok'=>true,'updated'=>$s->rowCount()]);
} catch(PDOException $e){ if($e->getCode()==='23000')json_response(['ok'=>false,'error'=>'That product code already exists.'],409); throw $e; }
