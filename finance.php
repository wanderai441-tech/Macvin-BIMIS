
<?php
require_once "auth.php";
require_role(["General Manager", "Finance"]);
require_once "db_connect.php";

$message = "";
$error = "";

// Record a new expense
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_expense"])) {

    $description = trim($_POST["description"] ?? "");
    $amount = (float)($_POST["amount"] ?? 0);
    $expense_date = $_POST["expense_date"] ?? date("Y-m-d");

    if ($description === "" || $amount <= 0) {
        $error = "Enter a valid description and amount.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO expenses (user_id, expense_date, description, amount)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issd",
            $_SESSION["user_id"],
            $expense_date,
            $description,
            $amount
        );

        if ($stmt->execute()) {
            $message = "Expense recorded successfully.";
        } else {
            $error = "Failed to record expense: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Calculate total sales
$sales_result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM sales"
);
$total_sales = (float)$sales_result->fetch_assoc()["total_sales"];

// Calculate total expenses
$expense_result = $conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses"
);
$total_expenses = (float)$expense_result->fetch_assoc()["total_expenses"];

$balance = $total_sales - $total_expenses;

// Get expense records
$expenses = $conn->query(
    "SELECT e.expense_date, e.description, e.amount,
            u.username
     FROM expenses e
     LEFT JOIN user_accounts u ON e.user_id = u.user_id
     ORDER BY e.expense_date DESC"
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Finance Management - Macvin BIMIS</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f7;
            margin: 0;
            padding: 20px;
        }

        h2 {
            color: #183153;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #555;
            margin-top: 0;
        }

        .card p {
            font-size: 22px;
            font-weight: bold;
            color: #183153;
        }

        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        input, button {
            padding: 10px;
            margin: 7px 0;
            width: 100%;
            box-sizing: border-box;
        }

        button {
            background: #183153;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background: #28558a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 11px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #183153;
            color: white;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            color: #183153;
            text-decoration: none;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

<a class="back no-print" href="dashboard.php">← Back to Dashboard</a>

<h2>Macvin Hardware - Finance Management</h2>

<p>
    Logged in as:
    <?php echo htmlspecialchars($_SESSION["full_name"]); ?>
</p>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="summary">

    <div class="card">
        <h3>Total Sales Revenue</h3>
        <p>UGX <?php echo number_format($total_sales); ?></p>
    </div>

    <div class="card">
        <h3>Total Expenses</h3>
        <p>UGX <?php echo number_format($total_expenses); ?></p>
    </div>

    <div class="card">
        <h3>Remaining Balance</h3>
        <p>UGX <?php echo number_format($balance); ?></p>
    </div>

</div>

<div class="section no-print">

    <h3>Record Business Expense</h3>

    <form method="POST">

        <label>Expense Description</label>
        <input
            type="text"
            name="description"
            placeholder="e.g. Electricity bill"
            required
        >

        <label>Amount (UGX)</label>
        <input
            type="number"
            name="amount"
            min="1"
            step="1"
            placeholder="Enter amount"
            required
        >

        <label>Expense Date</label>
        <input
            type="date"
            name="expense_date"
            value="<?php echo date('Y-m-d'); ?>"
            required
        >

        <button type="submit" name="add_expense">
            Record Expense
        </button>

    </form>

</div>

<div class="section">

    <h3>Business Expense Records</h3>

    <table>
        <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Amount (UGX)</th>
            <th>Recorded By</th>
        </tr>

        <?php if ($expenses && $expenses->num_rows > 0): ?>

            <?php while ($row = $expenses->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row["expense_date"]); ?></td>
                    <td><?php echo htmlspecialchars($row["description"]); ?></td>
                    <td><?php echo number_format((float)$row["amount"]); ?></td>
                    <td><?php echo htmlspecialchars($row["username"] ?? "Unknown"); ?></td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="4">No expenses have been recorded.</td>
            </tr>

        <?php endif; ?>

    </table>

    <br>

    <button class="no-print" onclick="window.print()">
        Print Financial Report
    </button>

</div>

</body>
</html>
