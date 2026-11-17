
<?php
require_once "auth.php";
require_role(["General Manager", "Procurement"]);
require_once "db_connect.php";

$message = "";
$error = "";

// Register a supplier
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_supplier"])) {
    $name = trim($_POST["supplier_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($name === "") {
        $error = "Enter the supplier name.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO suppliers (supplier_name, phone, email)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $name, $phone, $email);

        if ($stmt->execute()) {
            $message = "Supplier registered successfully.";
        } else {
            $error = "Unable to register supplier: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Create a purchase request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_request"])) {
    $supplier_id = (int)($_POST["supplier_id"] ?? 0);
    $product_id = (int)($_POST["product_id"] ?? 0);
    $quantity = (int)($_POST["quantity"] ?? 0);
    $cost_price = (float)($_POST["cost_price"] ?? 0);
    $user_id = (int)$_SESSION["user_id"];

    if ($supplier_id <= 0 || $product_id <= 0 || $quantity <= 0 || $cost_price <= 0) {
        $error = "Please complete all purchase request fields correctly.";
    } else {
        $conn->begin_transaction();

        try {
            $check = $conn->prepare(
                "SELECT supplier_id FROM suppliers WHERE supplier_id = ?"
            );
            $check->bind_param("i", $supplier_id);
            $check->execute();
            $supplier_exists = $check->get_result()->num_rows > 0;
            $check->close();

            $check = $conn->prepare(
                "SELECT product_id FROM products WHERE product_id = ?"
            );
            $check->bind_param("i", $product_id);
            $check->execute();
            $product_exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$supplier_exists || !$product_exists) {
                throw new Exception("The selected supplier or product does not exist.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO purchase_orders
                 (supplier_id, user_id, order_date, status)
                 VALUES (?, ?, NOW(), 'Pending')"
            );
            $stmt->bind_param("ii", $supplier_id, $user_id);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $purchase_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO purchase_items
                 (purchase_id, product_id, quantity, cost_price)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "iiid",
                $purchase_id,
                $product_id,
                $quantity,
                $cost_price
            );

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $stmt->close();
            $conn->commit();

            $message = "Purchase request created successfully. Request number: " . $purchase_id;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Purchase request failed: " . $e->getMessage();
        }
    }
}

// Update purchase request status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    $purchase_id = (int)($_POST["purchase_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    $allowed_statuses = ["Pending", "Approved", "Rejected"];

    if ($purchase_id <= 0 || !in_array($status, $allowed_statuses, true)) {
        $error = "Invalid purchase request or status.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE purchase_orders SET status = ? WHERE purchase_id = ?"
        );
        $stmt->bind_param("si", $status, $purchase_id);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $message = "Purchase request status updated.";
        } else {
            $error = "Unable to update request status.";
        }
        $stmt->close();
    }
}

// Load suppliers
$suppliers = $conn->query(
    "SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name"
);

// Load products
$products = $conn->query(
    "SELECT product_id, product_name, stock_quantity
     FROM products ORDER BY product_name"
);

// Load supplier records
$supplier_list = $conn->query(
    "SELECT supplier_id, supplier_name, phone, email
     FROM suppliers ORDER BY supplier_name"
);

// Load purchase requests
$requests = $conn->query(
    "SELECT po.purchase_id, po.order_date, po.status,
            s.supplier_name, e.full_name,
            p.product_name, pi.quantity, pi.cost_price
     FROM purchase_orders po
     JOIN suppliers s ON po.supplier_id = s.supplier_id
     JOIN user_accounts u ON po.user_id = u.user_id
     JOIN employees e ON u.emp_id = e.emp_id
     JOIN purchase_items pi ON po.purchase_id = pi.purchase_id
     JOIN products p ON pi.product_id = p.product_id
     ORDER BY po.purchase_id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BIMIS Procurement Management</title>

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
    background: #c0392b;
    padding: 9px 14px;
    border-radius: 5px;
    text-decoration: none;
}

main {
    max-width: 1150px;
    margin: auto;
    padding: 25px;
}

.back {
    display: inline-block;
    margin-bottom: 15px;
    color: #17365d;
    text-decoration: none;
}

.card {
    background: white;
    padding: 22px;
    margin-bottom: 22px;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.04);
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

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 11px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background: #e8edf4;
}

.status {
    font-weight: bold;
}

</style>
</head>

<body>

<header>
    <h2>MACVIN BIMIS - PROCUREMENT</h2>
    <a href="logout.php">Logout</a>
</header>

<main>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

<h2>Procurement Management</h2>

<?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="card">
    <h3>Register New Supplier</h3>

    <form method="POST">
        <label>Supplier Name</label>
        <input type="text" name="supplier_name" required>

        <label>Phone Number</label>
        <input type="text" name="phone">

        <label>Email Address</label>
        <input type="email" name="email">

        <button type="submit" name="add_supplier">
            Register Supplier
        </button>
    </form>
</section>

<section class="card">
    <h3>Create Purchase Request</h3>

    <form method="POST">

        <label>Select Supplier</label>
        <select name="supplier_id" required>
            <option value="">Choose Supplier</option>
            <?php while ($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= $s["supplier_id"] ?>">
                    <?= htmlspecialchars($s["supplier_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Select Product</label>
        <select name="product_id" required>
            <option value="">Choose Product</option>
            <?php while ($p = $products->fetch_assoc()): ?>
                <option value="<?= $p["product_id"] ?>">
                    <?= htmlspecialchars($p["product_name"]) ?>
                    (Stock: <?= $p["stock_quantity"] ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <label>Quantity Requested</label>
        <input type="number" name="quantity" min="1" required>

        <label>Estimated Cost per Unit (UGX)</label>
        <input type="number" name="cost_price" min="1" step="0.01" required>

        <button type="submit" name="create_request">
            Submit Purchase Request
        </button>

    </form>
</section>

<section class="card">
    <h3>Registered Suppliers</h3>

    <div class="table-container">
        <table>
            <tr>
                <th>Supplier</th>
                <th>Phone</th>
                <th>Email</th>
            </tr>

            <?php if ($supplier_list && $supplier_list->num_rows > 0): ?>
                <?php while ($s = $supplier_list->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($s["supplier_name"]) ?></td>
                        <td><?= htmlspecialchars($s["phone"] ?? "") ?></td>
                        <td><?= htmlspecialchars($s["email"] ?? "") ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3">No suppliers registered yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

<section class="card">
    <h3>Purchase Requests</h3>

    <div class="table-container">
        <table>
            <tr>
                <th>Request No.</th>
                <th>Supplier</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Requested By</th>
                <th>Date</th>
                <th>Status</th>
                <th>Update</th>
            </tr>

            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while ($r = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r["purchase_id"] ?></td>
                        <td><?= htmlspecialchars($r["supplier_name"]) ?></td>
                        <td><?= htmlspecialchars($r["product_name"]) ?></td>
                        <td><?= $r["quantity"] ?></td>
                        <td>UGX <?= number_format((float)$r["cost_price"]) ?></td>
                        <td><?= htmlspecialchars($r["full_name"]) ?></td>
                        <td><?= htmlspecialchars($r["order_date"]) ?></td>
                        <td class="status"><?= htmlspecialchars($r["status"]) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="purchase_id"
                                       value="<?= $r["purchase_id"] ?>">

                                <select name="status" required>
                                    <option value="Pending" <?= $r["status"] === "Pending" ? "selected" : "" ?>>Pending</option>
                                    <option value="Approved" <?= $r["status"] === "Approved" ? "selected" : "" ?>>Approved</option>
                                    <option value="Rejected" <?= $r["status"] === "Rejected" ? "selected" : "" ?>>Rejected</option>
                                </select>

                                <button type="submit" name="update_status">
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9">No purchase requests recorded yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</section>

</main>

</body>
</html>
