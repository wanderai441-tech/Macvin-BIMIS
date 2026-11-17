
<?php
require_once "auth.php";
require_role(["General Manager"]);
require_once "db_connect.php";

$sales = $conn->query(
    "SELECT COALESCE(SUM(total_amount),0) revenue, COUNT(*) transactions
     FROM sales WHERE DATE(sale_date) = CURDATE()"
);

$expenses = $conn->query(
    "SELECT COALESCE(SUM(amount),0) total
     FROM expenses WHERE DATE(expense_date) = CURDATE()"
);

$products = $conn->query(
    "SELECT COUNT(*) total,
     COALESCE(SUM(stock_quantity),0) units,
     SUM(stock_quantity <= reorder_level) low_stock
     FROM products"
);

if (!$sales || !$expenses || !$products) {
    die("Management overview error: " . $conn->error);
}

$s = $sales->fetch_assoc();
$e = $expenses->fetch_assoc();
$p = $products->fetch_assoc();

$recent = $conn->query(
    "SELECT sale_id, sale_date, total_amount
     FROM sales ORDER BY sale_date DESC LIMIT 10"
);

$low = $conn->query(
    "SELECT product_name, stock_quantity, reorder_level
     FROM products
     WHERE stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC LIMIT 10"
);

$staff = $conn->query(
    "SELECT d.department_name, COUNT(e.employee_id) total
     FROM departments d
     LEFT JOIN employees e ON d.department_id=e.department_id
     GROUP BY d.department_id,d.department_name"
);
?>

<!DOCTYPE html>
<html>
<head>
<title>Management Overview - Macvin BIMIS</title>
<style>
body{font-family:Arial;background:#f2f4f7;padding:20px}
h2,h3{color:#183153}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:15px}
.card{background:white;padding:20px;border-radius:8px;margin-bottom:18px}
.card h2{font-size:25px}
table{width:100%;border-collapse:collapse;background:white}
th,td{padding:11px;border:1px solid #ddd;text-align:left}
th{background:#183153;color:white}
a{color:#183153}
</style>
</head>
<body>

<a href="dashboard.php">← Back to Dashboard</a>

<h2>MACVIN HARDWARE</h2>
<h3>Management Overview</h3>
<p>Welcome, <?= htmlspecialchars($_SESSION["full_name"]) ?></p>
<p>Today's date: <?= date("d M Y") ?></p>

<div class="grid">
<div class="card"><h3>Today's Revenue</h3><h2>UGX <?= number_format($s["revenue"]) ?></h2></div>
<div class="card"><h3>Today's Expenses</h3><h2>UGX <?= number_format($e["total"]) ?></h2></div>
<div class="card"><h3>Today's Transactions</h3><h2><?= (int)$s["transactions"] ?></h2></div>
<div class="card"><h3>Total Products</h3><h2><?= (int)$p["total"] ?></h2></div>
<div class="card"><h3>Stock Units</h3><h2><?= (int)$p["units"] ?></h2></div>
<div class="card"><h3>Low Stock Products</h3><h2><?= (int)$p["low_stock"] ?></h2></div>
</div>

<div class="card">
<h3>Recent Sales</h3>
<table>
<tr><th>Sale ID</th><th>Date</th><th>Amount</th></tr>
<?php while($r=$recent->fetch_assoc()): ?>
<tr>
<td><?= (int)$r["sale_id"] ?></td>
<td><?= htmlspecialchars($r["sale_date"]) ?></td>
<td>UGX <?= number_format($r["total_amount"]) ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h3>Low Stock Alerts</h3>
<table>
<tr><th>Product</th><th>Available</th><th>Reorder Level</th></tr>
<?php while($r=$low->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r["product_name"]) ?></td>
<td><?= (int)$r["stock_quantity"] ?></td>
<td><?= (int)$r["reorder_level"] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<div class="card">
<h3>Employee Distribution</h3>
<table>
<tr><th>Department</th><th>Employees</th></tr>
<?php while($r=$staff->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r["department_name"]) ?></td>
<td><?= (int)$r["total"] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<p><a href="reports.php">Open Detailed Management Reports</a></p>
</body>
</html>
