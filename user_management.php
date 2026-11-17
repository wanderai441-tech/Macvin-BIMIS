
<?php
require_once "auth.php";
require_role(["General Manager"]);
require_once "db_connect.php";

$message = "";
$error = "";

$allowed_roles = [
    "General Manager",
    "Sales",
    "Inventory",
    "Procurement",
    "Finance",
    "HR"
];

// Create a user account for an existing employee
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_account"])) {

    $employee_id = (int)($_POST["employee_id"] ?? 0);
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";

    if ($employee_id <= 0 || $username === "" || strlen($password) < 6 ||
        !in_array($role, $allowed_roles, true)) {

        $error = "Enter valid account details. Password must have at least 6 characters.";

    } else {

        // Do not create a second General Manager account
        if ($role === "General Manager") {
            $checkGM = $conn->query(
                "SELECT user_id FROM user_accounts
                 WHERE role = 'General Manager' LIMIT 1"
            );

            if ($checkGM && $checkGM->num_rows > 0) {
                $error = "A General Manager account already exists.";
            }
        }

        if ($error === "") {
            $check = $conn->prepare(
                "SELECT user_id FROM user_accounts
                 WHERE username = ? OR employee_id = ? LIMIT 1"
            );

            if (!$check) {
                $error = "Database error: " . $conn->error;
            } else {
                $check->bind_param("si", $username, $employee_id);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();

                if ($exists) {
                    $error = "This username or employee already has an account.";
                } else {
                    $employeeCheck = $conn->prepare(
                        "SELECT employee_id FROM employees
                         WHERE employee_id = ?
                           AND employment_status = 'Active'
                         LIMIT 1"
                    );

                    if (!$employeeCheck) {
                        $error = "Database error: " . $conn->error;
                    } else {
                        $employeeCheck->bind_param("i", $employee_id);
                        $employeeCheck->execute();
                        $validEmployee = $employeeCheck->get_result()->num_rows > 0;
                        $employeeCheck->close();

                        if (!$validEmployee) {
                            $error = "Select an existing active employee.";
                        } else {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);

                            $insert = $conn->prepare(
                                "INSERT INTO user_accounts
                                 (employee_id, username, password_hash, role, account_status)
                                 VALUES (?, ?, ?, ?, 'Active')"
                            );

                            if (!$insert) {
                                $error = "Database error: " . $conn->error;
                            } else {
                                $insert->bind_param(
                                    "isss",
                                    $employee_id,
                                    $username,
                                    $password_hash,
                                    $role
                                );

                                if ($insert->execute()) {
                                    $message = "User account created successfully.";
                                } else {
                                    $error = "Account creation failed: " . $insert->error;
                                }

                                $insert->close();
                            }
                        }
                    }
                }
            }
        }
    }
}

// Update account role or status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_account"])) {

    $user_id = (int)($_POST["user_id"] ?? 0);
    $new_role = $_POST["role"] ?? "";
    $new_status = $_POST["account_status"] ?? "";

    if ($user_id <= 0 ||
        !in_array($new_role, $allowed_roles, true) ||
        !in_array($new_status, ["Active", "Inactive"], true)) {

        $error = "Invalid account information.";

    } else {

        $check = $conn->prepare(
            "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Database error: " . $conn->error;
        } else {
            $check->bind_param("i", $user_id);
            $check->execute();
            $account = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$account) {
                $error = "Account not found.";
            } elseif ($account["role"] === "General Manager") {
                $error = "The General Manager account is protected.";
            } elseif ($new_role === "General Manager") {
                $error = "You cannot assign another General Manager account.";
            } else {
                $update = $conn->prepare(
                    "UPDATE user_accounts
                     SET role = ?, account_status = ?
                     WHERE user_id = ?"
                );

                if (!$update) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("ssi", $new_role, $new_status, $user_id);

                    if ($update->execute()) {
                        $message = "User account updated successfully.";
                    } else {
                        $error = "Update failed: " . $update->error;
                    }

                    $update->close();
                }
            }
        }
    }
}

// Reset account password
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reset_password"])) {

    $user_id = (int)($_POST["user_id"] ?? 0);
    $new_password = $_POST["new_password"] ?? "";

    if ($user_id <= 0 || strlen($new_password) < 6) {
        $error = "Enter a password with at least 6 characters.";
    } else {
        $check = $conn->prepare(
            "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Database error: " . $conn->error;
        } else {
            $check->bind_param("i", $user_id);
            $check->execute();
            $account = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$account) {
                $error = "Account not found.";
            } elseif ($account["role"] === "General Manager") {
                $error = "The General Manager password cannot be reset from this page.";
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare(
                    "UPDATE user_accounts
                     SET password_hash = ?
                     WHERE user_id = ?"
                );

                if (!$update) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("si", $hash, $user_id);

                    if ($update->execute()) {
                        $message = "Password reset successfully.";
                    } else {
                        $error = "Password reset failed: " . $update->error;
                    }

                    $update->close();
                }
            }
        }
    }
}

// Employees who do not yet have user accounts
$availableEmployees = $conn->query(
    "SELECT e.employee_id, e.full_name, e.job_title, d.department_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.department_id
     LEFT JOIN user_accounts u ON e.employee_id = u.employee_id
     WHERE u.user_id IS NULL
       AND e.employment_status = 'Active'
     ORDER BY e.full_name"
);

if (!$availableEmployees) {
    die("Employee list error: " . $conn->error);
}

// All user accounts
$accounts = $conn->query(
    "SELECT
        u.user_id,
        u.employee_id,
        u.username,
        u.role,
        u.account_status,
        e.full_name,
        e.job_title
     FROM user_accounts u
     LEFT JOIN employees e ON u.employee_id = e.employee_id
     ORDER BY u.user_id"
);

if (!$accounts) {
    die("User account list error: " . $conn->error);
}

$totalAccounts = $accounts->num_rows;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Macvin BIMIS - User Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f7;
            padding: 20px;
            margin: 0;
        }

        h2, h3 {
            color: #183153;
        }

        .section, .card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin: 7px 0;
            box-sizing: border-box;
        }

        button {
            background: #183153;
            color: white;
            padding: 10px 14px;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px 0;
        }

        button:hover {
            background: #28558a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 11px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #183153;
            color: white;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        .error, .protected {
            color: #b83232;
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-bottom: 15px;
            color: #183153;
            text-decoration: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .small-form {
            min-width: 190px;
        }

        .status {
            font-weight: bold;
        }
    </style>
</head>

<body>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

<h2>MACVIN HARDWARE - USER MANAGEMENT</h2>

<p>Logged in as: <?php echo htmlspecialchars($_SESSION["full_name"]); ?></p>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<div class="card">
    <h3>Total User Accounts</h3>
    <h2><?php echo (int)$totalAccounts; ?></h2>
</div>

<div class="section">

    <h3>Create User Account</h3>

    <form method="POST">

        <label>Select Employee</label>

        <select name="employee_id" required>
            <option value="">Choose an employee</option>

            <?php while ($employee = $availableEmployees->fetch_assoc()): ?>
                <option value="<?php echo (int)$employee["employee_id"]; ?>">
                    <?php
                    echo htmlspecialchars(
                        $employee["full_name"] . " - " .
                        $employee["job_title"] . " (" .
                        $employee["department_name"] . ")"
                    );
                    ?>
                </option>
            <?php endwhile; ?>

        </select>

        <label>Username</label>
        <input type="text" name="username" maxlength="50" required>

        <label>Password</label>
        <input type="password" name="password" minlength="6" required>

        <label>System Role</label>

        <select name="role" required>
            <option value="">Choose role</option>
            <option value="Sales">Sales</option>
            <option value="Inventory">Inventory</option>
            <option value="Procurement">Procurement</option>
            <option value="Finance">Finance</option>
            <option value="HR">HR</option>
        </select>

        <button type="submit" name="create_account">
            Create Account
        </button>

    </form>

</div>

<div class="section">

    <h3>Existing User Accounts</h3>

    <div class="table-wrap">

        <table>

            <tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Update Account</th>
                <th>Reset Password</th>
            </tr>

            <?php while ($account = $accounts->fetch_assoc()): ?>

                <?php $isGM = ($account["role"] === "General Manager"); ?>

                <tr>

                    <td><?php echo (int)$account["user_id"]; ?></td>

                    <td>
                        <?php echo htmlspecialchars($account["full_name"] ?? "Unknown"); ?>
                        <br>
                        <small><?php echo htmlspecialchars($account["job_title"] ?? ""); ?></small>
                    </td>

                    <td><?php echo htmlspecialchars($account["username"]); ?></td>

                    <td><?php echo htmlspecialchars($account["role"]); ?></td>

                    <td class="status">
                        <?php echo htmlspecialchars($account["account_status"]); ?>
                    </td>

                    <td>

                        <?php if ($isGM): ?>

                            <span class="protected">Protected Account</span>

                        <?php else: ?>

                            <form method="POST" class="small-form">

                                <input type="hidden" name="user_id"
                                       value="<?php echo (int)$account["user_id"]; ?>">

                                <label>Role</label>

                                <select name="role" required>
                                    <?php foreach ($allowed_roles as $r): ?>
                                        <?php if ($r !== "General Manager"): ?>
                                            <option value="<?php echo htmlspecialchars($r); ?>"
                                                <?php echo $account["role"] === $r ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($r); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>

                                <label>Status</label>

                                <select name="account_status">
                                    <option value="Active"
                                        <?php echo $account["account_status"] === "Active" ? "selected" : ""; ?>>
                                        Active
                                    </option>

                                    <option value="Inactive"
                                        <?php echo $account["account_status"] === "Inactive" ? "selected" : ""; ?>>
                                        Inactive
                                    </option>
                                </select>

                                <button type="submit" name="update_account">
                                    Save Changes
                                </button>

                            </form>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php if (!$isGM): ?>

                            <form method="POST" class="small-form">

                                <input type="hidden" name="user_id"
                                       value="<?php echo (int)$account["user_id"]; ?>">

                                <label>New Password</label>
                                <input type="password" name="new_password"
                                       minlength="6" required>

                                <button type="submit" name="reset_password">
                                    Reset Password
                                </button>

                            </form>

                        <?php else: ?>

                            <span class="protected">Protected</span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

        </table>

    </div>

</div>

</body>
</html>
