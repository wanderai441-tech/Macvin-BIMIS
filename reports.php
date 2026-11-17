
<?php
require_once "auth.php";
require_role(["General Manager"]);
require_once "db_connect.php";

$start = $_GET["start_date"] ?? date("Y-m-01");
$end = $_GET["end_date"] ?? date("Y-m-d");

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end) {
    die("Invalid date range.");
}

function getTotal($conn, $sql, $start, $end) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Report error: " . $conn->error);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

$sales = getTotal($conn,
    "SELECT COALESCE(SUM(total_amount),0) revenue, COUNT(*) transactions
     FROM sales
     WHERE sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)",
    $start, $end
);

$expenses = getTotal($conn,
    "SELECT COALESCE(SUM(amount),0) expenses
     FROM expenses
     WHERE expense_date >= ? AND expense_date < DATE_ADD(?, INTERVAL 1 DAY)",
    $start, $end
);

$revenue = (float)$sales["revenue"];
$totalExpenses = (float)$expenses["expenses"];
$balance = $revenue - $totalExpenses;

$inventory = $conn->query(
    "SELECT COUNT(*) products,
     COALESCE(SUM(stock_quantity),0) units,
     SUM(stock_quantity <= reorder_level) low_stock,
     SUM(stock_quantity = 0) out_stock,
     COALESCE(SUM(stock_quantity * unit_price),0) stock_value
     FROM products"
);

if (!$inventory) die("Inventory report error: " . $conn->error);
$stock = $inventory->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT p.product_name,
     SUM(si.quantity) units_sold,
     SUM(si.quantity * si.unit_price) sales_value
     FROM sale_items si
     JOIN sales s ON si.sale_id = s.sale_id
     JOIN products p ON si.product_id = p.product_id
     WHERE s.sale_date >= ?
       AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY p.product_id, p.product_name
     ORDER BY units_sold DESC LIMIT 10"
);

if (!$stmt) die("Product report error: " . $conn->error);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$topProducts = $stmt->get_result();

$lowStock = $conn->query(
    "SELECT product_name, stock_quantity, reorder_level
     FROM products
     WHERE stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC"
);

if (!$lowStock) die("Low stock report error: " . $conn->error);

$stmt2 = $conn->prepare(
    "SELECT sale_id, sale_date, total_amount
     FROM sales
     WHERE sale_date >= ?
       AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY sale_date DESC LIMIT 20"
);

if (!$stmt2) die("Recent sales report error: " . $conn->error);
$stmt2->bind_param("ss", $start, $end);
$stmt2->execute();
$recentSales = $stmt2->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Macvin BIMIS Reports</title>
<style>
body{font-family:Arial;background:#f2f4f7;padding:20px}
h2,h3{color:#183153}
.card{background:white;padding:18px;border-radius:8px;margin-bottom:15px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:15px}
table{width:100%;border-collapse:collapse;background:white}
th,td{padding:11px;border:1px solid #ddd;text-align:left}
th{background:#183153;color:white}
input,button{padding:10px;margin:5px}
button{background:#183153;color:white;border:0;cursor:pointer}
.warning{color:#b83232;font-weight:bold}
@media print{.no-print{display:none}}
</style>
</head>
<body>

<a class="no-print" href="dashboard.php">← Dashboard</a>
<button class="no-print" onclick="window.print()">Print / Save PDF</button>

<h2>MACVIN HARDWARE</h2>
<h3>Management Reports</h3>

<div class="card no-print">
<form method="GET">
From: <input type="date" name="start_date" value="<?= htmlspecialchars($start) ?>">
To: <input type="date" name="end_date" value="<?= htmlspecialchars($end) ?>">
<button type="submit">Generate Report</button>
</form>
</div>

<div class="grid">
<div class="card"><h3>Sales Revenue</h3><h2>UGX <?= number_format($revenue) ?></h2></div>
<div class="card"><h3>Expenses</h3><h2>UGX <?= number_format($totalExpenses) ?></h2></div>
<div class="card"><h3>Sales Minus Expenses</h3><h2>UGX <?= number_format($balance) ?></h2></div>
<div class="card"><h3>Transactions</h3><h2><?= (int)$sales["transactions"] ?></h2></div>
<div class="card"><h3>Products</h3><h2><?= (int)$stock["products"] ?></h2></div>
<div class="card"><h3>Stock Value</h3><h2>UGX <?= number_format($stock["stock_value"]) ?></h2></div>
<div class="card"><h3>Low Stock</h3><h2><?= (int)$stock["low_stock"] ?></h2></div>
<div class="card"><h3>Out of Stock</h3><h2><?= (int)$stock["out_stock"] ?></h2></div>
</div>

<div class="card">
<h3>Best-Selling Products</h3>
<table>
<tr><th>Product</th><th>Units Sold</th><th>Sales Value (UGX)</th></tr>
<?php if ($topProducts->num_rows): while($p=$topProducts->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($p["product_name"]) ?></td>
<td><?= (int)$p["units_sold"] ?></td>
<td><?= number_format($p["sales_value"]) ?></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="3">No sales recorded for this period.</td></tr>
<?php endif; ?>
</table>
</div>

<div class="card">
<h3>Low Stock Products</h3>
<table>
<tr><th>Product</th><th>Available</th><th>Reorder Level</th><th>Status</th></tr>
<?php if ($lowStock->num_rows): while($p=$lowStock->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($p["product_name"]) ?></td>
<td><?= (int)$p["stock_quantity"] ?></td>
<td><?= (int)$p["reorder_level"] ?></td>
<td class="warning"><?= $p["stock_quantity"] == 0 ? "Out of Stock" : "Low Stock" ?></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="4">No low-stock products.</td></tr>
<?php endif; ?>
</table>
</div>

<div class="card">
<h3>Recent Sales</h3>
<table>
<tr><th>Sale ID</th><th>Date</th><th>Amount (UGX)</th></tr>
<?php if ($recentSales->num_rows): while($s=$recentSales->fetch_assoc()): ?>
<tr>
<td><?= (int)$s["sale_id"] ?></td>
<td><?= htmlspecialchars($s["sale_date"]) ?></td>
<td><?= number_format($s["total_amount"]) ?></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="3">No sales found.</td></tr>
<?php endif; ?>
</table>
</div>

<p>Prepared by: <?= htmlspecialchars($_SESSION["full_name"]) ?></p>
</body>
</html>
<?php
$stmt->close();
$stmt2->close();
?>
