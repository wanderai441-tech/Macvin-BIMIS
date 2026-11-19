<?php
require_once "auth.php";
require_login();
require_once "db_connect.php";

$role = $_SESSION["role"] ?? "";
$name = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";

$menus = [
    "General Manager" => [
        ["Management Overview", "View business performance and key activities.", "management_overview.php", "📊"],
        ["Sales Management", "Record sales and generate customer receipts.", "sales.php", "🛒"],
        ["Inventory Management", "Monitor products, quantities and stock levels.", "inventory.php", "📦"],
        ["Procurement Management", "Manage suppliers and purchase requests.", "procurement.php", "🚚"],
        ["Finance Management", "Record and monitor business expenses.", "finance.php", "💰"],
        ["HR Management", "Manage employee information and records.", "hr.php", "👥"],
        ["Reports", "View business and departmental reports.", "reports.php", "📈"],
        ["User Management", "Manage system accounts and access rights.", "user_management.php", "🔐"]
    ],
    "Sales" => [
        ["Sales Management", "Record customer purchases and process sales.", "sales.php", "🛒"],
        ["Generate Customer Receipts", "Prepare receipts for completed sales.", "sales.php", "🧾"]
    ],
    "Inventory" => [
        ["Inventory Management", "View products and available stock.", "inventory.php", "📦"],
        ["Receive Stock", "Record stock received from suppliers.", "inventory.php", "🚚"],
        ["Stock Alerts", "Identify products requiring replenishment.", "inventory.php", "⚠️"]
    ],
    "Procurement" => [
        ["Supplier Management", "View and manage supplier information.", "procurement.php", "🏢"],
        ["Purchase Requests", "Prepare and monitor purchase requests.", "procurement.php", "📋"]
    ],
    "Finance" => [
        ["Finance Management", "Manage business financial records.", "finance.php", "💰"],
        ["Record Expenses", "Enter and monitor business expenses.", "finance.php", "💳"],
        ["Financial Reports", "View financial summaries and reports.", "reports.php", "📈"]
    ],
    "HR" => [
        ["HR Management", "Manage employee information.", "hr.php", "👥"],
        ["Employee Records", "View and update employee records.", "hr.php", "📁"],
        ["Staff Reports", "View employee information and reports.", "hr.php", "📊"]
    ]
];

$my_modules = $menus[$role] ?? [];
if (!$my_modules) {
    http_response_code(403);
    exit("Your account does not have an assigned dashboard.");
}

function dashboardRow($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("BIMIS dashboard query error: " . $conn->error);
        return [];
    }
    return $result->fetch_assoc() ?: [];
}
function dashboardRows($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("BIMIS dashboard query error: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

$isManager = ($role === "General Manager");
$kpi = [];
$topProduct = [];
$leastProduct = [];
$recentSales = [];
$stockAlerts = [];

if ($isManager) {
    $kpi["all_sales"] = dashboardRow($conn,
        "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales");
    $kpi["today_sales"] = dashboardRow($conn,
        "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE DATE(sale_date)=CURDATE()");
    $kpi["week_sales"] = dashboardRow($conn,
        "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND sale_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
    $kpi["month_sales"] = dashboardRow($conn,
        "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE())");
    $kpi["products"] = dashboardRow($conn,
        "SELECT COUNT(*) total, COALESCE(SUM(stock_quantity * unit_price),0) stock_value,
         COALESCE(SUM(stock_quantity <= reorder_level AND stock_quantity > 0),0) low_stock,
         COALESCE(SUM(stock_quantity <= 0),0) out_stock FROM products");
    $topProduct = dashboardRow($conn,
        "SELECT p.product_name, COALESCE(SUM(si.quantity),0) units_sold,
         COALESCE(SUM(si.quantity * si.unit_price),0) revenue
         FROM sale_items si INNER JOIN products p ON p.product_id=si.product_id
         GROUP BY p.product_id,p.product_name
         ORDER BY units_sold DESC, revenue DESC LIMIT 1");
    $leastProduct = dashboardRow($conn,
        "SELECT p.product_name, COALESCE(SUM(si.quantity),0) units_sold,
         COALESCE(SUM(si.quantity * si.unit_price),0) revenue
         FROM products p LEFT JOIN sale_items si ON si.product_id=p.product_id
         GROUP BY p.product_id,p.product_name
         ORDER BY units_sold ASC, p.product_name ASC LIMIT 1");
    $recentSales = dashboardRows($conn,
        "SELECT sale_id, receipt_number, sale_date, total_amount
         FROM sales ORDER BY sale_date DESC, sale_id DESC LIMIT 6");
    $stockAlerts = dashboardRows($conn,
        "SELECT product_name, stock_quantity, reorder_level
         FROM products WHERE stock_quantity <= reorder_level
         ORDER BY stock_quantity ASC, product_name ASC LIMIT 6");
}

function moneyUGX($value) {
    return "UGX " . number_format((float)$value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BIMIS | Macvin Hardware</title>
<style>
:root{--navy:#14243b;--navy2:#203c5d;--blue:#2864dc;--light:#f2f5fb;--white:#fff;--muted:#718096;--border:#e1e8f2;--green:#078b62;--red:#c0392b}
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--light);color:#26364d}
.sidebar{position:fixed;inset:0 auto 0 0;width:245px;background:var(--navy);color:#fff;padding:22px 15px;overflow-y:auto;z-index:5}
.brand{display:flex;align-items:center;gap:12px;padding:8px}
.logo{width:52px;height:52px;background:#fff;color:var(--navy);border-radius:12px;display:grid;place-items:center;font-size:30px}
.brand h2{margin:0;font-size:22px;color:#fff}.brand small{color:#e2ad43;font-size:9px;letter-spacing:1px}
.company{color:#b9c7d8;font-size:10px;line-height:1.6;padding:9px 8px 22px}
.nav-label{color:#91a4bc;font-size:10px;letter-spacing:1.5px;padding:12px 10px}
.nav-link{display:flex;align-items:center;gap:11px;padding:12px 11px;margin:4px 0;border-radius:7px;color:#dce5ef;text-decoration:none;font-size:13px}
.nav-link:hover,.nav-link.active{background:#294463;color:#fff}
.sidebar-bottom{border-top:1px solid #34465e;margin-top:25px;padding:17px 8px;font-size:12px}
.logout{display:block;text-align:center;background:#294463;color:#fff;text-decoration:none;padding:12px;border-radius:7px;margin-top:15px}
.logout:hover{background:#b83232}
.main{margin-left:245px;min-height:100vh}
.topbar{height:70px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:3}
.topbar strong{font-size:16px;color:var(--navy)}.user{font-size:13px;color:#526176}
.content{padding:28px 30px;max-width:1700px;margin:auto}
.page-title{font-size:25px;margin:0 0 7px;color:#182b45}.page-subtitle{color:var(--muted);font-size:13px;margin:0 0 23px}
.welcome{background:linear-gradient(120deg,#14243b,#28588c);color:#fff;padding:23px 26px;border-radius:12px;margin-bottom:25px}
.welcome h2{margin:0 0 8px;font-size:22px}.welcome p{margin:0;color:#d6e5f7;font-size:13px;line-height:1.6}
.section-heading{display:flex;justify-content:space-between;align-items:center;margin:24px 0 14px}
.section-heading h2{font-size:17px;color:var(--navy);margin:0}
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:22px}
.kpi-card{background:#fff;border:1px solid var(--border);border-radius:11px;padding:19px;box-shadow:0 3px 12px rgba(23,43,70,.035);min-width:0}
.kpi-label{color:#718096;font-size:12px}.kpi-value{font-size:clamp(19px,2vw,25px);font-weight:800;color:var(--navy);margin:12px 0 6px;overflow-wrap:anywhere}.kpi-note{font-size:11px;color:var(--green);margin:0}
.two-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;margin-bottom:18px}
.panel{background:#fff;border:1px solid var(--border);border-radius:11px;padding:20px;box-shadow:0 3px 12px rgba(23,43,70,.025);min-width:0}
.panel h3{font-size:16px;color:var(--navy);margin:0 0 15px}
.product-name{font-size:20px;font-weight:700;color:var(--navy);margin:0 0 8px}
.product-meta{font-size:12px;color:var(--muted);margin:0}
.module-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:17px}
.module-card{display:block;background:#fff;border:1px solid var(--border);border-radius:11px;padding:21px;text-decoration:none;color:#26364d;box-shadow:0 3px 12px rgba(23,43,70,.03);transition:.2s}
.module-card:hover{transform:translateY(-3px);box-shadow:0 7px 18px rgba(23,43,70,.1);border-color:#b7c9e8}
.module-icon{width:43px;height:43px;border-radius:9px;background:#eaf0fc;display:grid;place-items:center;font-size:22px;margin-bottom:15px}
.module-card h3{font-size:15px;color:var(--navy);margin:0 0 9px}.module-card p{font-size:12px;color:var(--muted);line-height:1.6;margin:0}.open-module{display:inline-block;color:var(--blue);font-size:12px;font-weight:bold;margin-top:16px}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:11px 10px;border-bottom:1px solid #edf0f5;white-space:nowrap}th{color:#617187;background:#f7f9fc;font-size:11px}td{color:#34445a}.empty{color:var(--muted);font-size:12px;padding:12px 0}
.status{color:var(--red);font-weight:700}footer{text-align:center;color:var(--muted);font-size:11px;padding:24px}
@media(max-width:1150px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.module-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.sidebar{position:relative;width:100%;height:auto}.main{margin-left:0}.content{padding:18px}.topbar{padding:0 18px}.two-grid,.module-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:430px){.kpi-grid{grid-template-columns:1fr}.user{display:none}}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="brand"><div class="logo">⌂</div><div><h2>BIMIS</h2><small>MACVIN HARDWARE</small></div></div>
    <div class="company">MACVIN HARDWARE &amp; BUILDING SUPPLIES LTD</div>
    <div class="nav-label">MAIN MENU</div>
    <a href="dashboard.php" class="nav-link active"><span>▦</span> Dashboard</a>
    <?php foreach ($my_modules as $module): ?>
        <a href="<?= htmlspecialchars($module[2]) ?>" class="nav-link"><span><?= htmlspecialchars($module[3]) ?></span><?= htmlspecialchars($module[0]) ?></a>
    <?php endforeach; ?>
    <div class="sidebar-bottom">
        <div>Signed in as:</div><strong><?= htmlspecialchars($name) ?></strong>
        <div style="color:#aebed1;margin-top:5px"><?= htmlspecialchars($role) ?></div>
        <a href="logout.php" class="logout">Sign Out</a>
    </div>
</aside>
<main class="main">
<header class="topbar"><strong>Business Management Dashboard</strong><div class="user"><?= htmlspecialchars($name) ?> | <?= htmlspecialchars($role) ?></div></header>
<div class="content">
    <section class="welcome">
        <h2>Welcome back, <?= htmlspecialchars($name) ?>!</h2>
        <p>Macvin Hardware — Business performance and departmental workspace.</p>
    </section>

    <?php if ($isManager): ?>
        <h1 class="page-title">Owner Dashboard</h1>
        <p class="page-subtitle">Macvin Hardware — Business performance summary</p>
        <div class="kpi-grid">
            <div class="kpi-card"><span class="kpi-label">All-time Sales</span><div class="kpi-value"><?= moneyUGX($kpi["all_sales"]["amount"] ?? 0) ?></div><p class="kpi-note"><?= (int)($kpi["all_sales"]["transactions"] ?? 0) ?> recorded transactions</p></div>
            <div class="kpi-card"><span class="kpi-label">Today's Sales</span><div class="kpi-value"><?= moneyUGX($kpi["today_sales"]["amount"] ?? 0) ?></div><p class="kpi-note"><?= (int)($kpi["today_sales"]["transactions"] ?? 0) ?> transactions today</p></div>
            <div class="kpi-card"><span class="kpi-label">This Week</span><div class="kpi-value"><?= moneyUGX($kpi["week_sales"]["amount"] ?? 0) ?></div><p class="kpi-note"><?= (int)($kpi["week_sales"]["transactions"] ?? 0) ?> transactions this week</p></div>
            <div class="kpi-card"><span class="kpi-label">This Month</span><div class="kpi-value"><?= moneyUGX($kpi["month_sales"]["amount"] ?? 0) ?></div><p class="kpi-note"><?= (int)($kpi["month_sales"]["transactions"] ?? 0) ?> transactions this month</p></div>
            <div class="kpi-card"><span class="kpi-label">Total Products</span><div class="kpi-value"><?= (int)($kpi["products"]["total"] ?? 0) ?></div><p class="kpi-note">Registered product types</p></div>
            <div class="kpi-card"><span class="kpi-label">Stock Value</span><div class="kpi-value"><?= moneyUGX($kpi["products"]["stock_value"] ?? 0) ?></div><p class="kpi-note">Current stock at selling prices</p></div>
            <div class="kpi-card"><span class="kpi-label">Low Stock Items</span><div class="kpi-value"><?= (int)($kpi["products"]["low_stock"] ?? 0) ?></div><p class="kpi-note">Products needing replenishment</p></div>
            <div class="kpi-card"><span class="kpi-label">Out of Stock</span><div class="kpi-value"><?= (int)($kpi["products"]["out_stock"] ?? 0) ?></div><p class="kpi-note">Products unavailable</p></div>
        </div>

        <div class="two-grid">
            <section class="panel"><h3>Top-Selling Product</h3>
                <?php if ($topProduct && (int)$topProduct["units_sold"] > 0): ?>
                    <p class="product-name"><?= htmlspecialchars($topProduct["product_name"]) ?></p>
                    <p class="product-meta"><?= (int)$topProduct["units_sold"] ?> units sold · <?= moneyUGX($topProduct["revenue"]) ?> revenue</p>
                <?php else: ?><p class="empty">No completed sales are available yet.</p><?php endif; ?>
            </section>
            <section class="panel"><h3>Least-Selling Product</h3>
                <?php if ($leastProduct): ?>
                    <p class="product-name"><?= htmlspecialchars($leastProduct["product_name"]) ?></p>
                    <p class="product-meta"><?= (int)$leastProduct["units_sold"] ?> units sold · <?= moneyUGX($leastProduct["revenue"]) ?> revenue</p>
                <?php else: ?><p class="empty">No product records are available.</p><?php endif; ?>
            </section>
        </div>

        <div class="two-grid">
            <section class="panel"><h3>Recent Sales</h3><div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th></tr></thead><tbody>
            <?php if ($recentSales): foreach ($recentSales as $sale): ?>
                <tr><td><?= htmlspecialchars($sale["receipt_number"] ?? ("Sale #" . $sale["sale_id"])) ?></td><td><?= htmlspecialchars(date("d M Y, H:i", strtotime($sale["sale_date"]))) ?></td><td><?= moneyUGX($sale["total_amount"]) ?></td></tr>
            <?php endforeach; else: ?><tr><td colspan="3" class="empty">No sales have been recorded.</td></tr><?php endif; ?>
            </tbody></table></div></section>
            <section class="panel"><h3>Products Requiring Attention</h3><div class="table-wrap"><table><thead><tr><th>Product</th><th>Available</th><th>Reorder level</th></tr></thead><tbody>
            <?php if ($stockAlerts): foreach ($stockAlerts as $item): ?>
                <tr><td><?= htmlspecialchars($item["product_name"]) ?></td><td class="<?= ((int)$item["stock_quantity"] <= 0 ? "status" : "") ?>"><?= (int)$item["stock_quantity"] ?></td><td><?= (int)$item["reorder_level"] ?></td></tr>
            <?php endforeach; else: ?><tr><td colspan="3" class="empty">No products are currently at or below their reorder levels.</td></tr><?php endif; ?>
            </tbody></table></div></section>
        </div>
        <div class="section-heading"><h2>Available Modules</h2></div>
    <?php else: ?>
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">Your workspace for managing Macvin Hardware operations.</p>
        <div class="kpi-grid">
            <div class="kpi-card"><span class="kpi-label">Account Role</span><div class="kpi-value"><?= htmlspecialchars($role) ?></div><p class="kpi-note">Your assigned system responsibility</p></div>
            <div class="kpi-card"><span class="kpi-label">Available Modules</span><div class="kpi-value"><?= count($my_modules) ?></div><p class="kpi-note">Modules available to your account</p></div>
            <div class="kpi-card"><span class="kpi-label">System Status</span><div class="kpi-value" style="color:var(--green)">Active</div><p class="kpi-note">Logged in successfully</p></div>
        </div>
        <div class="section-heading"><h2>Available Modules</h2></div>
    <?php endif; ?>

    <div class="module-grid">
        <?php foreach ($my_modules as $module): ?>
        <a class="module-card" href="<?= htmlspecialchars($module[2]) ?>">
            <div class="module-icon"><?= htmlspecialchars($module[3]) ?></div>
            <h3><?= htmlspecialchars($module[0]) ?></h3><p><?= htmlspecialchars($module[1]) ?></p>
            <span class="open-module">Open Module →</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<footer>Macvin BIMIS &copy; <?= date("Y") ?> | Business Integrated Management Information System</footer>
</main>
</body>
</html>
