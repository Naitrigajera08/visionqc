<?php
// VisionQC dashboard template
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VisionQC | Industrial Defect Detection</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head>
<body>
<!-- SIDEBAR: items generated in app.js -->
<aside id="sb"><div class="brand"><i class="fa-solid fa-eye"></i><b>VisionQC</b></div><nav id="nav"></nav></aside>
<div class="main">
<!-- TOP NAVBAR -->
<header class="top">
  <button class="ib d-lg-none" id="menu"><i class="fa fa-bars"></i></button>
  <div class="sys d-none d-md-block"><b>AI Defect Detection &amp; Quality Control</b><small>Apex Precision Components Pvt. Ltd.</small></div>
  <div class="search"><i class="fa fa-search"></i><input id="gs" placeholder="Search product, ID, camera…"></div>
  <span class="chip d-none d-xl-inline"><i class="fa fa-industry"></i> Plant 02 · Rajkot</span>
  <span class="chip d-none d-md-inline"><i class="fa fa-clock"></i> <span id="clock"></span></span>
  <button class="ib" id="theme" title="Toggle theme"><i class="fa fa-moon"></i></button>
  <button class="ib bell"><i class="fa fa-bell"></i><em id="bc">3</em></button>
  <div class="admin"><span class="av">RS</span><div class="d-none d-md-block"><b>Rohan Shah</b><small>Plant Admin</small></div></div>
</header>

<main class="p-3 p-xl-4">
<section id="dashboard"><h2 class="st">Dashboard</h2><div class="row g-3" id="kpis"></div></section>

<section id="live"><h2 class="st">Live monitoring</h2>
<div class="glass p-3 mb-3">
  <div class="flow" id="flow"></div>
  <div class="belt"><span id="item"><i class="fa fa-gear"></i></span></div>
  <div class="row g-3 mt-1" id="bins"></div>
</div>
<div class="row g-3">
 <div class="col-xl-8"><div class="glass cam">
  <div class="scan"></div>
  <div class="live"><i></i>LIVE</div>
  <div class="meta tl"><b>CAM-03 · Line A Inspection</b><small><span id="fps">60</span> FPS · 1920×1080</small></div>
  <i class="fa-solid fa-gear obj" id="obj"></i>
  <div class="bbox" id="bbox"><span id="bl">Cracked 96.4%</span></div>
  <div class="meta bl"><span>Product <b id="cp">Steel Gear</b></span><span>Category <b id="cc">Cracked</b></span><span>Confidence <b id="cf">96.4%</b></span></div>
 </div></div>
 <div class="col-xl-4"><div class="glass p-3 h-100"><h5>Factory overview</h5><div class="row g-2" id="fo"></div></div></div>
</div></section>

<section id="cats"><h2 class="st">Category dashboard</h2><div class="row g-3" id="catc"></div></section>

<section id="charts"><h2 class="st">Analytics</h2><div class="row g-3">
 <div class="col-lg-8"><div class="glass p-3"><h5>Production trend</h5><div class="ch"><canvas id="c1"></canvas></div></div></div>
 <div class="col-lg-4"><div class="glass p-3"><h5>Defect category</h5><div class="ch"><canvas id="c2"></canvas></div></div></div>
 <div class="col-lg-6"><div class="glass p-3"><h5>Hourly detection</h5><div class="ch"><canvas id="c3"></canvas></div></div></div>
 <div class="col-lg-6"><div class="glass p-3"><h5>Machine comparison</h5><div class="ch"><canvas id="c4"></canvas></div></div></div>
 <div class="col-12"><div class="glass p-3"><h5>Weekly production</h5><div class="ch"><canvas id="c5"></canvas></div></div></div>
</div></section>

<section id="hist"><h2 class="st">Detection history</h2>
<div class="glass p-3"><div class="d-flex flex-wrap gap-2 justify-content-between mb-2"><h5 class="m-0">Recent detections</h5><div class="search sm"><i class="fa fa-search"></i><input id="ts" placeholder="Filter table…"></div></div>
<div class="tw"><table class="tb" id="tbl"><thead><tr id="th"></tr></thead><tbody></tbody></table></div>
<div class="pg" id="pg"></div></div></section>

<section><div class="row g-3 mt-0">
 <div class="col-xl-5"><h2 class="st">Top defective products</h2><div class="glass p-3" id="topd"></div></div>
 <div class="col-xl-7" id="loss"><h2 class="st">Product-wise loss &amp; pricing</h2><div class="glass p-3 tw"><table class="tb" id="lt"></table></div></div>
</div></section>

<section><div class="row g-3">
 <div class="col-xl-5" id="alerts"><h2 class="st">Real-time alerts</h2><div class="glass p-3 alist" id="al"></div></div>
 <div class="col-xl-7" id="mach"><h2 class="st">Machine health</h2><div class="row g-3" id="mc"></div></div>
</div></section>

<section id="ops"><h2 class="st">Operator performance</h2><div class="row g-3" id="oc"></div></section>

<section id="cost"><h2 class="st">Cost &amp; material waste analytics</h2><div class="row g-3">
 <div class="col-lg-5"><div class="glass p-3"><h5>Loss by category</h5><div class="ch sm"><canvas id="c6"></canvas></div></div></div>
 <div class="col-lg-7"><div class="row g-3" id="cw"></div></div>
</div></section>
</main>

<footer id="foot" class="foot">
  <span>VisionQC v3.2.1</span><span><i class="dot"></i>Server online</span><span><i class="dot"></i>Database connected</span><span><i class="dot"></i>API healthy · 42 ms</span>
</footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="app.js"></script>
</body>
</html>
<?php
// End of PHP template
?>
