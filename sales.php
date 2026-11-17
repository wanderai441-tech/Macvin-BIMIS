
<?php
require_once "auth.php";
require_login();
require_once "db_connect.php";

$role = strtolower(trim($_SESSION["role"] ?? ""));

if (!in_array($role, ["sales", "general manager"], true)) {
    http_response_code(403);
    exit("Access denied. You are not authorised to process sales.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$message = "";
$error = "";
$receipt = null;
$receipt_items = [];

// ADD PRODUCT TO CART
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "add") {

    $product_id = (int)($_POST["product_id"] ?? 0);
    $quantity = (int)($_POST["quantity"] ?? 0);

    if ($product_id <= 0 || $quantity <= 0) {
        $error = "Select a product and enter a valid quantity.";
    } else {
        $stmt = $conn->prepare(
            "SELECT product_name, stock_quantity
             FROM products WHERE product_id = ?"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            $error = "Product not found.";
        } else {
            $current = $_SESSION["cart"][$product_id] ?? 0;

            if (($current + $quantity) > $product["stock_quantity"]) {
                $error = "Not enough stock available for " .
                    htmlspecialchars($product["product_name"]) . ".";
            } else {
                $_SESSION["cart"][$product_id] = $current + $quantity;
                $message = "Product added to cart.";
            }
        }
    }
}

// UPDATE CART QUANTITY
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update") {

    $product_id = (int)($_POST["product_id"] ?? 0);
    $quantity = (int)($_POST["quantity"] ?? 0);

    if ($quantity <= 0) {
        unset($_SESSION["cart"][$product_id]);
    } else {
        $stmt = $conn->prepare(
            "SELECT stock_quantity FROM products WHERE product_id = ?"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($product && $quantity <= $product["stock_quantity"]) {
            $_SESSION["cart"][$product_id] = $quantity;
            $message = "Cart updated.";
        } else {
            $error = "Requested quantity exceeds available stock.";
        }
    }
}

// REMOVE PRODUCT FROM CART
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "remove") {

    $product_id = (int)($_POST["product_id"] ?? 0);
    unset($_SESSION["cart"][$product_id]);
    $message = "Product removed from cart.";
}

// COMPLETE SALE
if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "checkout") {

    if (empty($_SESSION["cart"])) {
        $error = "Your cart is empty.";
    } else {
        try {
            $conn->begin_transaction();

            $items = [];
            $total = 0;

            // Check current stock and prices
            foreach ($_SESSION["cart"] as $product_id => $quantity) {

                $stmt = $conn->prepare(
                    "SELECT product_id, product_name, unit_price,
                     stock_quantity
                     FROM products
                     WHERE product_id = ?
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$product ||
                    $quantity <= 0 ||
                    $quantity > $product["stock_quantity"]) {
                    throw new Exception("Insufficient stock or product unavailable.");
                }

                $subtotal = $product["unit_price"] * $quantity;
                $total += $subtotal;

                $items[] = [
                    "id" => $product_id,
                    "name" => $product["product_name"],
                    "quantity" => $quantity,
                    "price" => $product["unit_price"]
                ];
            }

            // Generate receipt number
            $receipt_no = "MAC-" . date("YmdHis") .
                "-" . random_int(100, 999);

            $user_id = (int)$_SESSION["user_id"];

            // Save sale
            $stmt = $conn->prepare(
                "INSERT INTO sales
                 (user_id, sale_date, receipt_no, total_amount)
                 VALUES (?, NOW(), ?, ?)"
            );

            $stmt->bind_param("isd", $user_id, $receipt_no, $total);
            $stmt->execute();

            $sale_id = $conn->insert_id;
            $stmt->close();

            // Save sale items and reduce stock
            foreach ($items as $item) {

                $stmt = $conn->prepare(
                    "INSERT INTO sale_items
                     (sale_id, product_id, quantity, unit_price)
                     VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiid",
                    $sale_id,
                    $item["id"],
                    $item["quantity"],
                    $item["price"]
                );

                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "UPDATE products
                     SET stock_quantity = stock_quantity - ?
                     WHERE product_id = ?"
                );

                $stmt->bind_param(
                    "ii",
                    $item["quantity"],
                    $item["id"]
                );

                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            $receipt = [
                "number" => $receipt_no,
                "total" => $total,
                "date" => date("d M Y, H:i")
            ];

            $receipt_items = $items;

            $_SESSION["cart"] = [];

            $message = "Sale completed successfully.";

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Sale could not be completed: " . $e->getMessage();
        }
    }
}

// Load available products
$products = $conn->query(
    "SELECT product_id, product_name, unit_price, stock_quantity
     FROM products
     WHERE stock_quantity > 0
     ORDER BY product_name"
);

// Load cart details
$cart_items = [];
$cart_total = 0;

foreach ($_SESSION["cart"] as $product_id => $quantity) {

    $stmt = $conn->prepare(
        "SELECT product_id, product_name, unit_price, stock_quantity
         FROM products WHERE product_id = ?"
    );

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($product) {
        $product["quantity"] = $quantity;
        $product["subtotal"] = $product["unit_price"] * $quantity;

        $cart_total += $product["subtotal"];
        $cart_items[] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sales Management | MACVIN BIMIS</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f4f8;
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
    margin-bottom: 22px;
    border-radius: 8px;
}

input, select {
    padding: 11px;
    border: 1px solid #ccd3dd;
    border-radius: 5px;
    margin: 7px 0 15px;
    width: 100%;
}

button {
    background: #17365d;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 5px;
    cursor: pointer;
}

.remove {
    background: #c0392b;
}

.success { color: green; }
.error { color: #b42318; }

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

th { background: #e9eef5; }

.total {
    font-size: 21px;
    font-weight: bold;
    text-align: right;
}

.receipt {
    border: 1px dashed #777;
    padding: 20px;
    max-width: 600px;
}

@media print {
    header, .no-print, .message-area {
        display: none;
    }

    body { background: white; }

    .receipt {
        border: none;
    }
}
</style>
</head>

<body>

<header>
    <a href="dashboard.php">Back to Dashboard</a>
    <h2>MACVIN BIMIS - Sales Management</h2>
</header>

<main>

<h2>Customer Sales and Receipts</h2>

<div class="message-area">
    <?php if ($message): ?>
        <p class="success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>

<?php if ($receipt): ?>

<div class="card receipt">

    <h2>MACVIN HARDWARE</h2>
    <p>Customer Sales Receipt</p>

    <p><strong>Receipt Number:</strong>
        <?= htmlspecialchars($receipt["number"]) ?></p>

    <p><strong>Date:</strong>
        <?= htmlspecialchars($receipt["date"]) ?></p>

    <hr>

    <table>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Subtotal</th>
        </tr>

        <?php foreach ($receipt_items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item["name"]) ?></td>
            <td><?= (int)$item["quantity"] ?></td>
            <td><?= number_format($item["price"], 2) ?></td>
            <td><?= number_format($item["price"] * $item["quantity"], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h3 class="total">
        Total: UGX <?= number_format($receipt["total"], 2) ?>
    </h3>

    <p>Thank you for shopping with Macvin Hardware!</p>

    <button class="no-print" onclick="window.print()">Print Receipt</button>

    <a class="no-print" href="sales.php">New Sale</a>

</div>

<?php endif; ?>

<div class="card no-print">

    <h3>Add Products to Customer Cart</h3>

    <form method="POST">

        <input type="hidden" name="action" value="add">

        <label>Select Product</label>

        <select name="product_id" required>
            <option value="">Choose a product</option>

            <?php if ($products): ?>
                <?php while ($product = $products->fetch_assoc()): ?>

                    <option value="<?= (int)$product["product_id"] ?>">
                        <?= htmlspecialchars($product["product_name"]) ?>
                        - UGX <?= number_format($product["unit_price"], 2) ?>
                        (Stock: <?= (int)$product["stock_quantity"] ?>)
                    </option>

                <?php endwhile; ?>
            <?php endif; ?>

        </select>

        <label>Quantity</label>
        <input type="number" name="quantity" min="1" value="1" required>

        <button type="submit">Add to Cart</button>

    </form>

</div>

<div class="card no-print">

    <h3>Customer Shopping Cart</h3>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>

        <?php if (count($cart_items) > 0): ?>

            <?php foreach ($cart_items as $item): ?>

            <tr>
                <td><?= htmlspecialchars($item["product_name"]) ?></td>

                <td><?= number_format($item["unit_price"], 2) ?></td>

                <td>
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id"
                               value="<?= (int)$item["product_id"] ?>">

                        <input type="number" name="quantity"
                               value="<?= (int)$item["quantity"] ?>"
                               min="1" max="<?= (int)$item["stock_quantity"] ?>"
                               style="width:80px">

                        <button type="submit">Update</button>
                    </form>
                </td>

                <td><?= number_format($item["subtotal"], 2) ?></td>

                <td>
                    <form method="POST">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id"
                               value="<?= (int)$item["product_id"] ?>">
                        <button class="remove" type="submit">Remove</button>
                    </form>
                </td>
            </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="5">Your cart is currently empty.</td>
            </tr>

        <?php endif; ?>

        </tbody>
    </table>

    <p class="total">
        Total: UGX <?= number_format($cart_total, 2) ?>
    </p>

    <?php if (count($cart_items) > 0): ?>

        <form method="POST"
              onsubmit="return confirm('Complete this sale and update stock?');">

            <input type="hidden" name="action" value="checkout">

            <button type="submit">Complete Sale and Generate Receipt</button>

        </form>

    <?php endif; ?>

</div>

</main>

</body>
</html>
