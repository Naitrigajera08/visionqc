<?php
require_once __DIR__ . '/../config.php'; require_login();if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['ok'=>false,'error'=>'POST required.'],405);require_csrf();
if(empty($_FILES['image'])||$_FILES['image']['error']!==UPLOAD_ERR_OK)json_response(['ok'=>false,'error'=>'Choose a valid image file.'],422);
$f=$_FILES['image'];if($f['size']>10*1024*1024)json_response(['ok'=>false,'error'=>'Image must be 10 MB or smaller.'],413);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))json_response(['ok'=>false,'error'=>'Only JPEG, PNG or WebP images are allowed.'],415);
$dir=__DIR__.'/../uploads';if(!is_dir($dir)||!is_writable($dir))json_response(['ok'=>false,'error'=>'The uploads folder must exist and be writable.'],500);
$filename=bin2hex(random_bytes(16)).'.'.$allowed[$mime];$path=$dir.'/'.$filename;if(!move_uploaded_file($f['tmp_name'],$path))json_response(['ok'=>false,'error'=>'Could not save uploaded image.'],500);
$camera=trim((string)($_POST['camera']??'CAM-01'));
$payload=json_encode(['image_path'=>$path,'camera'=>$camera]);
$ch=curl_init(FLASK_API_URL.'/detect');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>90]);
$response=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
if($response===false||$http<200||$http>=300){@unlink($path);json_response(['ok'=>false,'error'=>'AI service unavailable: '.($err?:'HTTP '.$http).'. Start the Flask service and check its model.'],502);}
$d=json_decode($response,true);if(!is_array($d)||empty($d['ok'])||empty($d['detections'])){@unlink($path);json_response(['ok'=>false,'error'=>$d['error']??'No defect object was returned by AI.'],422);}
$first=$d['detections'][0];$category=trim((string)($first['category']??'Unknown'));$confidence=max(0,min(100,(float)($first['confidence']??0)));$productId=trim((string)($first['product_id']??'UNASSIGNED'));$productName=trim((string)($first['product_name']??'Unassigned product'));
$s=db()->prepare('SELECT name,unit_price FROM products WHERE product_code=? LIMIT 1');$s->execute([$productId]);$prod=$s->fetch();if($prod){$productName=$prod['name'];$loss=$category==='Good'?0:(float)$prod['unit_price'];}else{$loss=$category==='Good'?0:0;}
$status=$category==='Good'?'Passed':($confidence<60?'Review':'Rejected');$code='DET-'.strtoupper(bin2hex(random_bytes(6)));
$s=db()->prepare('INSERT INTO detections(detection_code,product_id,product_name,category,line_name,camera,confidence,status,estimated_loss) VALUES(?,?,?,?,?,?,?,?,?)');
$s->execute([$code,$productId,$productName,$category,'Line A',$camera,$confidence,$status,$loss]);
if($category!=='Good'){$a=db()->prepare('INSERT INTO alerts(severity,message,source) VALUES(?,?,?)');$a->execute([$category==='Burned'?'Critical':'Warning',"$category item detected: $productName ($confidence%)",$camera]);}
json_response(['ok'=>true,'detection'=>['id'=>(int)db()->lastInsertId(),'detection_code'=>$code,'product_name'=>$productName,'category'=>$category,'confidence'=>$confidence,'status'=>$status,'estimated_loss'=>$loss]]);
