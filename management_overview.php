<?php
require_once "auth.php";
require_role(["General Manager"]);
require_once "db_connect.php";

function overviewRow($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("BIMIS management overview query error: " . $conn->error);
        return [];
    }
    return $result->fetch_assoc() ?: [];
}
function overviewRows($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("BIMIS management overview query error: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}
function ugx($value) { return "UGX " . number_format((float)$value); }

$allSales = overviewRow($conn, "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales");
$todaySales = overviewRow($conn, "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE DATE(sale_date)=CURDATE()");
$weekSales = overviewRow($conn, "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND sale_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$monthSales = overviewRow($conn, "SELECT COALESCE(SUM(total_amount),0) amount, COUNT(*) transactions FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE())");
$stock = overviewRow($conn, "SELECT COUNT(*) total, COALESCE(SUM(stock_quantity),0) units, COALESCE(SUM(stock_quantity*unit_price),0) stock_value, COALESCE(SUM(stock_quantity<=reorder_level AND stock_quantity>0),0) low_stock, COALESCE(SUM(stock_quantity<=0),0) out_stock FROM products");
$todayExpenses = overviewRow($conn, "SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE DATE(expense_date)=CURDATE()");
$recent = overviewRows($conn, "SELECT sale_id, receipt_number, sale_date, total_amount FROM sales ORDER BY sale_date DESC, sale_id DESC LIMIT 10");
$low = overviewRows($conn, "SELECT product_name, stock_quantity, reorder_level FROM products WHERE stock_quantity<=reorder_level ORDER BY stock_quantity ASC, product_name ASC LIMIT 10");
$staff = overviewRows($conn, "SELECT d.department_name, COUNT(e.employee_id) total FROM departments d LEFT JOIN employees e ON d.department_id=e.department_id GROUP BY d.department_id,d.department_name ORDER BY d.department_name");
$top = overviewRow($conn, "SELECT p.product_name, COALESCE(SUM(si.quantity),0) units_sold, COALESCE(SUM(si.quantity*si.unit_price),0) revenue FROM sale_items si INNER JOIN products p ON p.product_id=si.product_id GROUP BY p.product_id,p.product_name ORDER BY units_sold DESC,revenue DESC LIMIT 1");
$least = overviewRow($conn, "SELECT p.product_name, COALESCE(SUM(si.quantity),0) units_sold, COALESCE(SUM(si.quantity*si.unit_price),0) revenue FROM products p LEFT JOIN sale_items si ON si.product_id=p.product_id GROUP BY p.product_id,p.product_name ORDER BY units_sold ASC,p.product_name ASC LIMIT 1");
$name = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "Manager";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Management Overview | Macvin BIMIS</title>
<style>
:root{--navy:#14243b;--blue:#2864dc;--light:#f2f5fb;--muted:#718096;--border:#e1e8f2;--green:#078b62;--red:#c0392b}
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--light);color:#26364d}
.topbar{height:68px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 30px}
.topbar strong{color:var(--navy)}.topbar a{color:var(--blue);text-decoration:none;font-size:13px}
.content{max-width:1600px;margin:auto;padding:28px 30px}
.heading{display:flex;justify-content:space-between;align-items:flex-end;gap:15px;margin-bottom:22px}
h1{font-size:25px;color:#182b45;margin:0 0 7px}.sub{font-size:13px;color:var(--muted);margin:0}
.date{font-size:12px;color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:20px}
.card{background:#fff;border:1px solid var(--border);border-radius:11px;padding:19px;box-shadow:0 3px 12px rgba(23,43,70,.03);min-width:0}
.label{font-size:12px;color:var(--muted)}.value{font-size:clamp(19px,2vw,25px);font-weight:800;color:var(--navy);margin:12px 0 6px;overflow-wrap:anywhere}.note{font-size:11px;color:var(--green);margin:0}
.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;margin-bottom:18px}
h2{font-size:16px;color:var(--navy);margin:0 0 15px}
.product{font-size:20px;font-weight:700;color:var(--navy);margin:0 0 8px}.meta{font-size:12px;color:var(--muted);margin:0}
.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:11px 10px;border-bottom:1px solid #edf0f5;white-space:nowrap}th{background:#f7f9fc;color:#617187;font-size:11px}td{color:#34445a}
.empty{color:var(--muted);font-size:12px}.low{color:var(--red);font-weight:700}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.btn{display:inline-block;background:var(--navy);color:#fff;text-decoration:none;padding:11px 15px;border-radius:7px;font-size:12px}.btn.secondary{background:#e7eefb;color:var(--navy)}
@media(max-width:1050px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.content{padding:18px}.topbar{padding:0 18px}.two{grid-template-columns:1fr}.heading{align-items:flex-start;flex-direction:column}}@media(max-width:430px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="topbar"><strong>Macvin Hardware | Management Overview</strong><a href="dashboard.php">← Back to Dashboard</a></header>
<main class="content">
<div class="heading"><div><h1>Owner Dashboard</h1><p class="sub">Business performance summary · Welcome, <?= htmlspecialchars($name) ?></p></div><div class="date"><?= date("l, d F Y") ?></div></div>

<div class="grid">
<div class="card"><span class="label">All-time Sales</span><div class="value"><?= ugx($allSales["amount"] ?? 0) ?></div><p class="note"><?= (int)($allSales["transactions"] ?? 0) ?> recorded transactions</p></div>
<div class="card"><span class="label">Today's Sales</span><div class="value"><?= ugx($todaySales["amount"] ?? 0) ?></div><p class="note"><?= (int)($todaySales["transactions"] ?? 0) ?> transactions today</p></div>
<div class="card"><span class="label">This Week</span><div class="value"><?= ugx($weekSales["amount"] ?? 0) ?></div><p class="note"><?= (int)($weekSales["transactions"] ?? 0) ?> transactions this week</p></div>
<div class="card"><span class="label">This Month</span><div class="value"><?= ugx($monthSales["amount"] ?? 0) ?></div><p class="note"><?= (int)($monthSales["transactions"] ?? 0) ?> transactions this month</p></div>
<div class="card"><span class="label">Total Products</span><div class="value"><?= (int)($stock["total"] ?? 0) ?></div><p class="note">Registered product types</p></div>
<div class="card"><span class="label">Stock Value</span><div class="value"><?= ugx($stock["stock_value"] ?? 0) ?></div><p class="note">Current stock at selling prices</p></div>
<div class="card"><span class="label">Low Stock Items</span><div class="value"><?= (int)($stock["low_stock"] ?? 0) ?></div><p class="note">Products needing replenishment</p></div>
<div class="card"><span class="label">Out of Stock</span><div class="value"><?= (int)($stock["out_stock"] ?? 0) ?></div><p class="note">Products unavailable</p></div>
</div>

<div class="two">
<section class="card"><h2>Top-Selling Product</h2><?php if($top && (int)$top["units_sold"]>0): ?><p class="product"><?= htmlspecialchars($top["product_name"]) ?></p><p class="meta"><?= (int)$top["units_sold"] ?> units sold · <?= ugx($top["revenue"]) ?> revenue</p><?php else: ?><p class="empty">No completed sales are available yet.</p><?php endif; ?></section>
<section class="card"><h2>Least-Selling Product</h2><?php if($least): ?><p class="product"><?= htmlspecialchars($least["product_name"]) ?></p><p class="meta"><?= (int)$least["units_sold"] ?> units sold · <?= ugx($least["revenue"]) ?> revenue</p><?php else: ?><p class="empty">No product records are available.</p><?php endif; ?></section>
</div>

<div class="two">
<section class="card"><h2>Recent Sales</h2><div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th></tr></thead><tbody>
<?php if($recent): foreach($recent as $r): ?><tr><td><?= htmlspecialchars($r["receipt_number"] ?? ("Sale #".$r["sale_id"])) ?></td><td><?= htmlspecialchars(date("d M Y, H:i",strtotime($r["sale_date"]))) ?></td><td><?= ugx($r["total_amount"]) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="empty">No sales recorded.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="card"><h2>Products Requiring Attention</h2><div class="table-wrap"><table><thead><tr><th>Product</th><th>Available</th><th>Reorder level</th></tr></thead><tbody>
<?php if($low): foreach($low as $r): ?><tr><td><?= htmlspecialchars($r["product_name"]) ?></td><td class="<?= (int)$r["stock_quantity"]<=0?'low':'' ?>"><?= (int)$r["stock_quantity"] ?></td><td><?= (int)$r["reorder_level"] ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="empty">No products require replenishment.</td></tr><?php endif; ?>
</tbody></table></div></section>
</div>

<section class="card"><h2>Employee Distribution</h2><div class="table-wrap"><table><thead><tr><th>Department</th><th>Employees</th></tr></thead><tbody>
<?php if($staff): foreach($staff as $r): ?><tr><td><?= htmlspecialchars($r["department_name"]) ?></td><td><?= (int)$r["total"] ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="empty">No department records found.</td></tr><?php endif; ?>
</tbody></table></div></section>

<div class="actions"><a class="btn" href="reports.php">Open Detailed Reports</a><a class="btn secondary" href="dashboard.php">Return to Dashboard</a></div>
</main>
</body>
</html>
