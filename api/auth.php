<?php
require_once __DIR__ . '/../config.php';
$action = $_GET['action'] ?? '';
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $d = request_json();
    $email = filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($d['password'] ?? '');
    if (!$email || $password === '') json_response(['ok'=>false,'error'=>'Email and password are required.'], 422);
    $s = db()->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email=? AND active=1 LIMIT 1');
    $s->execute([$email]); $u = $s->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) json_response(['ok'=>false,'error'=>'Incorrect email or password.'], 401);
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$u['id']; $_SESSION['user_name']=$u['name']; $_SESSION['user_role']=$u['role'];
    json_response(['ok'=>true,'user'=>['name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]]);
}
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_csrf(); $_SESSION=[]; session_destroy(); json_response(['ok'=>true]);
}
json_response(['ok'=>false,'error'=>'Unsupported auth action.'],405);
