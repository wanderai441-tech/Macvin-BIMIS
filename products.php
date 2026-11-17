
<?php
require_once "auth.php";
require_login();
require_once "db_connect.php";

$role = strtolower(trim($_SESSION["role"] ?? ""));

$can_manage = in_array(
    $role,
    ["general manager", "inventory"],
    true
);

$message = "";
$error = "";

// Process product submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$can_manage) {
        $error = "You are not authorised to add products.";
    } else {

        $name = trim($_POST["product_name"] ?? "");
        $category = (int)($_POST["category_id"] ?? 0);
        $price = (float)($_POST["unit_price"] ?? 0);
        $quantity = (int)($_POST["stock_quantity"] ?? 0);
        $reorder = (int)($_POST["reorder_level"] ?? 5);

        if ($name === "" || $category <= 0 ||
            $price < 0 || $quantity < 0 || $reorder < 0) {

            $error = "Please enter valid product information.";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO products
                (category_id, product_name, unit_price,
                stock_quantity, reorder_level)
                VALUES (?, ?, ?, ?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "isdii",
                    $category,
                    $name,
                    $price,
                    $quantity,
                    $reorder
                );

                if ($stmt->execute()) {
                    $message = "Product added successfully.";
                } else {
                    $error = "Unable to save product: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Load categories
$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories ORDER BY category_name"
);

// Load products
$products = $conn->query(
    "SELECT p.*, c.category_name
     FROM products p
     LEFT JOIN categories c
     ON p.category_id = c.category_id
     ORDER BY p.product_name"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Management | BIMIS</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f1f4f8;
    margin: 0;
    color: #263238;
}

header {
    background: #17365d;
    color: white;
    padding: 20px;
}

header a {
    float: right;
    color: white;
    text-decoration: none;
}

main {
    max-width: 1100px;
    margin: auto;
    padding: 25px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 8px;
    margin-bottom: 22px;
}

input, select {
    width: 100%;
    padding: 11px;
    margin: 7px 0 15px;
    border: 1px solid #ccd3dd;
    border-radius: 5px;
}

button {
    background: #17365d;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 5px;
    cursor: pointer;
}

button:disabled {
    background: #999;
    cursor: not-allowed;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th { background: #e9eef5; }

.success { color: green; }
.error { color: #b42318; }

@media(max-width:650px) {
    main { padding: 12px; }
    table { font-size: 12px; }
    th, td { padding: 7px; }
}
</style>
</head>

<body>

<header>
    <a href="dashboard.php">Back to Dashboard</a>
    <h2>MACVIN BIMIS - Product Management</h2>
</header>

<main>

<h2>Product Catalogue</h2>

<p>
    Logged in as:
    <strong><?= htmlspecialchars($_SESSION["role"] ?? "Unknown") ?></strong>
</p>

<?php if ($message): ?>
    <p class="success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="card">

    <h3>Add New Product</h3>

    <?php if (!$categories): ?>

        <p class="error">Could not load product categories. Check the categories table.</p>

    <?php elseif ($categories->num_rows === 0): ?>

        <p class="error">No product categories exist. Add categories in phpMyAdmin first.</p>

    <?php else: ?>

    <form method="POST" action="products.php">

        <label>Product Name</label>
        <input type="text" name="product_name"
               placeholder="Enter product name" required>

        <label>Product Category</label>
        <select name="category_id" required>
            <option value="">Select category</option>

            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$cat["category_id"] ?>">
                    <?= htmlspecialchars($cat["category_name"]) ?>
                </option>
            <?php endwhile; ?>

        </select>

        <label>Selling Price (UGX)</label>
        <input type="number" name="unit_price"
               min="0" step="0.01" required>

        <label>Opening Stock Quantity</label>
        <input type="number" name="stock_quantity"
               min="0" required>

        <label>Reorder Level</label>
        <input type="number" name="reorder_level"
               min="0" value="5" required>

        <button type="submit" <?= !$can_manage ? "disabled" : "" ?>>
            Save Product
        </button>

        <?php if (!$can_manage): ?>
            <p class="error">Your account can view products but cannot add them.</p>
        <?php endif; ?>

    </form>

    <?php endif; ?>

</div>

<div class="card">

    <h3>Available Products</h3>

    <?php if (!$products): ?>

        <p class="error">Unable to load products: <?= htmlspecialchars($conn->error) ?></p>

    <?php else: ?>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price (UGX)</th>
                <th>Stock</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($products->num_rows > 0): ?>

            <?php while ($product = $products->fetch_assoc()): ?>

            <tr>
                <td><?= htmlspecialchars($product["product_name"]) ?></td>
                <td><?= htmlspecialchars($product["category_name"] ?? "Uncategorised") ?></td>
                <td><?= number_format((float)$product["unit_price"], 2) ?></td>
                <td><?= (int)$product["stock_quantity"] ?></td>
                <td>
                    <?php
                    if ($product["stock_quantity"] <= 0) {
                        echo "Out of Stock";
                    } elseif ($product["stock_quantity"] <= $product["reorder_level"]) {
                        echo "Low Stock";
                    } else {
                        echo "Available";
                    }
                    ?>
                </td>
            </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="5">No products have been added yet.</td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

    <?php endif; ?>

</div>

</main>
</body>
</html>
