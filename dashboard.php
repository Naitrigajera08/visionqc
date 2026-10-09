<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$name = htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>VisionQC Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" rel="preload" as="script">
    <style>
        :root {
            --bg: #0e1826;
            --card: #1b2a41;
            --teal: #138496;
            --text: #f3f6f8
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: Segoe UI, sans-serif
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 235px;
            background: #111d2e;
            padding: 20px 12px;
            overflow: auto
        }

        .sidebar a {
            display: block;
            color: #c6d2df;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 9px;
            margin: 3px 0
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #138496;
            color: white
        }

        main {
            margin-left: 235px;
            padding: 22px
        }

        .card {
            background: #1b2a41;
            color: white;
            border: 1px solid #304258;
            border-radius: 14px
        }

        .navhead {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px
        }

        .stat {
            font-size: 1.8rem;
            font-weight: 700
        }

        .section {
            scroll-margin-top: 20px;
            margin-bottom: 28px
        }

        .table {
            color: #f3f6f8
        }

        .table td,
        .table th {
            vertical-align: middle
        }

        .form-control,
        .form-select {
            background: #101d2e;
            color: #fff;
            border-color: #40536a
        }

        .form-control:focus,
        .form-select:focus {
            background: #101d2e;
            color: #fff
        }

        @media(max-width:800px) {
            .sidebar {
                position: static;
                width: auto
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap
            }

            .sidebar a {
                padding: 7px
            }

            main {
                margin: 0;
                padding: 14px
            }
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <h3>👁 VisionQC</h3>
        <p class="small text-secondary">Quality control</p>
        <nav id="nav">
            <a href="#overview" class="active">Dashboard</a><a href="#products">Product Master</a><a href="#detect">Run Detection</a><a href="#history">Detection History</a><a href="#analytics">Analytics & Reports</a><a href="#alerts">Alerts</a><a href="#machines">Machine Health</a><a href="#account">Account</a>
        </nav>
    </aside>
    <main>
        <div class="navhead">
            <div>
                <h2>Industrial Defect Detection</h2>
                <div class="text-secondary">Welcome, <?= $name ?> · Rajkot Plant</div>
            </div><button id="logout" class="btn btn-outline-light">Log out</button>
        </div>
        <input type="hidden" id="csrf" value="<?= $csrf ?>">
        <div id="notice" class="alert d-none"></div>
        <section id="overview" class="section">
            <h4>Dashboard overview</h4>
            <div class="row g-3" id="stats">
                <div class="col-6 col-xl-3">
                    <div class="card p-3">
                        <div>Products</div>
                        <div class="stat" id="sProducts">—</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card p-3">
                        <div>Total detections</div>
                        <div class="stat" id="sTotal">—</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card p-3">
                        <div>Defects</div>
                        <div class="stat" id="sDefects">—</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card p-3">
                        <div>Estimated loss</div>
                        <div class="stat" id="sLoss">—</div>
                    </div>
                </div>
            </div>
        </section>
        <section id="products" class="section">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <h4>Product Master / Price Management</h4><button class="btn btn-info" id="newProduct">Add product</button>
            </div>
            <div class="card p-3 mt-2">
                <form id="productForm" class="row g-2 mb-3"><input type="hidden" id="productId">
                    <div class="col-md-3"><input class="form-control" id="pCode" placeholder="Product code (P-101)" required></div>
                    <div class="col-md-4"><input class="form-control" id="pName" placeholder="Product name" required></div>
                    <div class="col-md-2"><input class="form-control" id="pPrice" type="number" min="0" step=".01" placeholder="Price ₹" required></div>
                    <div class="col-md-3 d-flex gap-2"><button class="btn btn-success" type="submit">Save</button><button class="btn btn-secondary" type="button" id="cancelProduct">Clear</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Product</th>
                                <th>Unit price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productRows"></tbody>
                    </table>
                </div>
            </div>
        </section>
        <section id="detect" class="section">
            <h4>Run AI Detection</h4>
            <div class="card p-3">
                <p class="text-secondary">Upload an image to send it to the Flask/OpenCV/YOLO service. Configure a trained model in flask_ai/app.py before using production results.</p>
                <form id="detectForm" class="row g-2">
                    <div class="col-md-7"><input class="form-control" type="file" id="image" accept="image/*" required></div>
                    <div class="col-md-3"><select class="form-select" id="camera">
                            <option>CAM-01</option>
                            <option>CAM-02</option>
                            <option>CAM-03</option>
                        </select></div>
                    <div class="col-md-2"><button class="btn btn-info w-100">Detect</button></div>
                </form>
                <div id="detectResult" class="mt-3"></div>
            </div>
        </section>
        <section id="history" class="section">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <h4>Detection History</h4>
                <div class="d-flex gap-2"><input class="form-control" id="historySearch" placeholder="Search records"><button class="btn btn-outline-info" id="refreshHistory">Refresh</button></div>
            </div>
            <div class="card p-3 mt-2 table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/time</th>
                            <th>Code</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Confidence</th>
                            <th>Camera</th>
                            <th>Status</th>
                            <th>Loss</th>
                        </tr>
                    </thead>
                    <tbody id="historyRows"></tbody>
                </table>
            </div>
        </section>
        <section id="analytics" class="section">
            <h4>Analytics & Reports</h4>
            <div class="card p-3"><canvas id="categoryChart" height="100"></canvas><button id="exportCsv" class="btn btn-outline-info mt-3 align-self-start">Export history CSV</button></div>
        </section>
        <section id="alerts" class="section">
            <h4>Alerts</h4>
            <div class="card p-3">
                <form id="alertForm" class="row g-2">
                    <div class="col-md-2"><select class="form-select" id="severity">
                            <option>Info</option>
                            <option>Warning</option>
                            <option>Critical</option>
                        </select></div>
                    <div class="col-md-8"><input class="form-control" id="alertMessage" placeholder="Alert message" required></div>
                    <div class="col-md-2"><button class="btn btn-info w-100">Add alert</button></div>
                </form>
                <div id="alertRows" class="mt-3"></div>
            </div>
        </section>
        <section id="machines" class="section">
            <h4>Machine Health</h4>
            <div class="card p-3 table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Machine</th>
                            <th>Status</th>
                            <th>Health %</th>
                            <th>Availability %</th>
                        </tr>
                    </thead>
                    <tbody id="machineRows"></tbody>
                </table>
            </div>
        </section>
        <section id="account" class="section">
            <h4>Account</h4>
            <div class="card p-3">Signed in as <?= $name ?>. Use Log out to end the session.</div>
        </section>
        <footer class="text-secondary text-center py-4">VisionQC · PHP + MySQL + Flask + OpenCV/YOLO</footer>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="dashboard.js"></script>
</body>

</html>