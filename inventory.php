
<?php
require_once "auth.php";
require_role(["General Manager", "Inventory"]);
require_once "db_connect.php";

$message = "";
$error = "";

// Receive new stock
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["receive_stock"])) {

    $product_id = (int)($_POST["product_id"] ?? 0);
    $quantity = (int)($_POST["quantity"] ?? 0);

    if ($product_id <= 0 || $quantity <= 0) {
        $error = "Please select a product and enter a valid quantity.";
    } else {

        $stmt = $conn->prepare(
            "UPDATE products
             SET stock_quantity = stock_quantity + ?
             WHERE product_id = ?"
        );

        $stmt->bind_param("ii", $quantity, $product_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Stock received successfully. Inventory has been updated.";
        } else {
            $error = "Unable to update stock. Please check the selected product.";
        }

        $stmt->close();
    }
}

// Get all products
$sql = "
    SELECT
        p.product_id,
        p.product_name,
        c.category_name,
        p.unit_price,
        p.stock_quantity,
        p.reorder_level
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    ORDER BY p.product_name ASC
";

$result = $conn->query($sql);

// Inventory summary
$summary = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_stock,
        COALESCE(SUM(
            CASE WHEN stock_quantity <= reorder_level
            THEN 1 ELSE 0 END
        ), 0) AS low_stock
    FROM products
")->fetch_assoc();

// Products available for receiving stock
$product_options = $conn->query("
    SELECT product_id, product_name, stock_quantity
    FROM products
    ORDER BY product_name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BIMIS Inventory Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f4f8;
    color: #263238;
}

header {
    background: #17365d;
    color: white;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header a {
    color: white;
    text-decoration: none;
    background: #c0392b;
    padding: 9px 14px;
    border-radius: 5px;
}

main {
    max-width: 1150px;
    margin: auto;
    padding: 25px;
}

.card {
    background: white;
    padding: 22px;
    margin-bottom: 22px;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.04);
}

.summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.summary .card {
    border-left: 5px solid #17365d;
}

.summary h2 {
    color: #17365d;
    margin-bottom: 5px;
}

input, select {
    width: 100%;
    padding: 11px;
    margin: 8px 0 15px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background: #17365d;
    color: white;
    padding: 11px 18px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

button:hover {
    background: #24558b;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background: #e8edf4;
}

.low {
    color: #c0392b;
    font-weight: bold;
}

.normal {
    color: #237a38;
    font-weight: bold;
}

.message {
    background: #dff0df;
    color: #237a38;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.error {
    background: #f8dddd;
    color: #a93226;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.table-container {
    overflow-x: auto;
}

.back {
    display: inline-block;
    margin-bottom: 15px;
    color: #17365d;
    text-decoration: none;
}

</style>
</head>

<body>

<header>
    <h2>MACVIN BIMIS - INVENTORY</h2>
    <a href="logout.php">Logout</a>
</header>

<main>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

<h2>Inventory Management</h2>

<?php if ($message): ?>
    <div class="message">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<section class="summary">

    <div class="card">
        <h2><?= $summary["total_products"] ?></h2>
        <p>Total Products</p>
    </div>

    <div class="card">
        <h2><?= $summary["total_stock"] ?></h2>
        <p>Total Units in Stock</p>
    </div>

    <div class="card">
        <h2><?= $summary["low_stock"] ?></h2>
        <p>Low or Out-of-Stock Products</p>
    </div>

</section>

<section class="card">

    <h3>Receive New Stock</h3>

    <form method="POST">

        <label>Select Product</label>

        <select name="product_id" required>
            <option value="">Choose a product</option>

            <?php while ($product = $product_options->fetch_assoc()): ?>
                <option value="<?= $product["product_id"] ?>">
                    <?= htmlspecialchars($product["product_name"]) ?>
                    (Current stock: <?= $product["stock_quantity"] ?>)
                </option>
            <?php endwhile; ?>

        </select>

        <label>Quantity Received</label>

        <input
            type="number"
            name="quantity"
            min="1"
            placeholder="Enter quantity received"
            required
        >

        <button type="submit" name="receive_stock">
            Update Inventory
        </button>

    </form>

</section>

<section class="card">

    <h3>Current Stock Records</h3>

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Unit Price</th>
                    <th>Available Stock</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>
                        <td><?= htmlspecialchars($row["product_name"]) ?></td>

                        <td><?= htmlspecialchars($row["category_name"] ?? "Uncategorised") ?></td>

                        <td>UGX <?= number_format($row["unit_price"]) ?></td>

                        <td><?= $row["stock_quantity"] ?></td>

                        <td><?= $row["reorder_level"] ?></td>

                        <td>
                            <?php if ($row["stock_quantity"] <= $row["reorder_level"]): ?>
                                <span class="low">Low Stock</span>
                            <?php else: ?>
                                <span class="normal">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No products found.</td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

</main>

</body>
</html>
