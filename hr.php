
<?php
require_once "auth.php";
require_role(["General Manager", "HR"]);
require_once "db_connect.php";

$role = $_SESSION["role"];
$message = "";
$error = "";

// Identify and protect the General Manager
function isGeneralManager($conn, $employee_id) {
    $stmt = $conn->prepare(
        "SELECT job_title FROM employees WHERE employee_id = ? LIMIT 1"
    );

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();

    if (!$employee) {
        return false;
    }

    $title = strtolower($employee["job_title"]);

    return (
        strpos($title, "general manager") !== false ||
        strpos($title, "ceo") !== false ||
        strpos($title, "chief executive officer") !== false
    );
}

// Register employee
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_employee"])) {

    $name = trim($_POST["full_name"] ?? "");
    $job_title = trim($_POST["job_title"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department_id = (int)($_POST["department_id"] ?? 0);

    if ($name === "" || $job_title === "" || $department_id <= 0) {
        $error = "Please complete the employee name, job title and department.";
    } elseif ($role !== "General Manager" &&
              (stripos($job_title, "general manager") !== false ||
               stripos($job_title, "ceo") !== false)) {
        $error = "Only the General Manager can register another General Manager.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO employees
            (department_id, full_name, job_title, phone, email, employment_status)
            VALUES (?, ?, ?, ?, ?, 'Active')"
        );

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param(
                "issss",
                $department_id,
                $name,
                $job_title,
                $phone,
                $email
            );

            if ($stmt->execute()) {
                $message = "Employee registered successfully.";
            } else {
                $error = "Registration failed: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Update employee
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_employee"])) {

    $employee_id = (int)($_POST["employee_id"] ?? 0);
    $name = trim($_POST["full_name"] ?? "");
    $job_title = trim($_POST["job_title"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department_id = (int)($_POST["department_id"] ?? 0);

    if ($employee_id <= 0 || $name === "") {
        $error = "Invalid employee details.";
    } else {
        $protected = isGeneralManager($conn, $employee_id);

        if ($protected && $role !== "General Manager") {

            // HR can only change the General Manager's contact details
            $stmt = $conn->prepare(
                "UPDATE employees
                 SET full_name = ?, phone = ?, email = ?
                 WHERE employee_id = ?"
            );

            if (!$stmt) {
                $error = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("sssi", $name, $phone, $email, $employee_id);

                if ($stmt->execute()) {
                    $message = "Employee contact details updated.";
                } else {
                    $error = "Update failed: " . $stmt->error;
                }

                $stmt->close();
            }

        } else {

            if ($job_title === "" || $department_id <= 0) {
                $error = "Please enter a valid job title and department.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE employees
                     SET full_name = ?, job_title = ?, phone = ?,
                         email = ?, department_id = ?
                     WHERE employee_id = ?"
                );

                if (!$stmt) {
                    $error = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param(
                        "ssssii",
                        $name,
                        $job_title,
                        $phone,
                        $email,
                        $department_id,
                        $employee_id
                    );

                    if ($stmt->execute()) {
                        $message = "Employee details updated successfully.";
                    } else {
                        $error = "Update failed: " . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}

// Activate or deactivate employee
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_status"])) {

    $employee_id = (int)($_POST["employee_id"] ?? 0);
    $new_status = $_POST["new_status"] ?? "";

    if (!in_array($new_status, ["Active", "Inactive"], true)) {
        $error = "Invalid employee status.";
    } elseif (isGeneralManager($conn, $employee_id)) {
        $error = "The General Manager's record cannot be deactivated.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE employees
             SET employment_status = ?
             WHERE employee_id = ?"
        );

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("si", $new_status, $employee_id);

            if ($stmt->execute()) {
                $message = "Employee status updated successfully.";
            } else {
                $error = "Status update failed: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Get departments
$departments = $conn->query(
    "SELECT department_id, department_name
     FROM departments
     ORDER BY department_name"
);

if (!$departments) {
    die("Department database error: " . $conn->error);
}

// Search employees
$search = trim($_GET["search"] ?? "");

$stmt = $conn->prepare(
    "SELECT
        e.employee_id,
        e.department_id,
        e.full_name,
        e.job_title,
        e.phone,
        e.email,
        e.employment_status,
        d.department_name
     FROM employees e
     LEFT JOIN departments d
       ON e.department_id = d.department_id
     WHERE e.full_name LIKE ?
        OR e.job_title LIKE ?
     ORDER BY e.employee_id DESC"
);

if (!$stmt) {
    die("Employee search database error: " . $conn->error);
}

$search_term = "%" . $search . "%";
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();
$employees = $stmt->get_result();

// Employee statistics
$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(employment_status = 'Active') AS active,
        SUM(employment_status = 'Inactive') AS inactive
     FROM employees"
);

if (!$stats_result) {
    die("Statistics database error: " . $conn->error);
}

$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Macvin BIMIS - HR Management</title>

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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .stats p {
            font-size: 25px;
            font-weight: bold;
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
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px 0;
        }

        button:hover {
            background: #28558a;
        }

        .danger {
            background: #b83232;
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
            font-weight: bold;
        }

        .error, .protected {
            color: red;
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

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

<a class="back no-print" href="dashboard.php">← Back to Dashboard</a>

<h2>MACVIN HARDWARE - HR MANAGEMENT</h2>

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

<div class="stats">

    <div class="card">
        <h3>Total Employees</h3>
        <p><?php echo (int)$stats["total"]; ?></p>
    </div>

    <div class="card">
        <h3>Active Employees</h3>
        <p><?php echo (int)$stats["active"]; ?></p>
    </div>

    <div class="card">
        <h3>Inactive Employees</h3>
        <p><?php echo (int)$stats["inactive"]; ?></p>
    </div>

</div>

<div class="section no-print">

    <h3>Register New Employee</h3>

    <form method="POST">

        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Job Title</label>
        <input type="text" name="job_title" required>

        <label>Phone Number</label>
        <input type="text" name="phone">

        <label>Email Address</label>
        <input type="email" name="email">

        <label>Department</label>

        <select name="department_id" required>
            <option value="">Select Department</option>

            <?php
            $departments->data_seek(0);
            while ($dept = $departments->fetch_assoc()):
            ?>
                <option value="<?php echo (int)$dept["department_id"]; ?>">
                    <?php echo htmlspecialchars($dept["department_name"]); ?>
                </option>
            <?php endwhile; ?>

        </select>

        <button type="submit" name="add_employee">
            Register Employee
        </button>

    </form>

</div>

<div class="section no-print">

    <h3>Search Employees</h3>

    <form method="GET">
        <input
            type="text"
            name="search"
            placeholder="Search by employee name or job title"
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit">Search</button>
        <a href="hr.php">Show All</a>
    </form>

</div>

<div class="section">

    <h3>Employee Records</h3>

    <div class="table-wrap">

        <table>

            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Job Title</th>
                <th>Department</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Status</th>
                <th class="no-print">Actions</th>
            </tr>

            <?php if ($employees->num_rows > 0): ?>

                <?php while ($employee = $employees->fetch_assoc()): ?>

                    <?php
                    $protected = (
                        stripos($employee["job_title"], "general manager") !== false ||
                        stripos($employee["job_title"], "ceo") !== false ||
                        stripos($employee["job_title"], "chief executive officer") !== false
                    );
                    ?>

                    <tr>

                        <td><?php echo (int)$employee["employee_id"]; ?></td>

                        <td><?php echo htmlspecialchars($employee["full_name"]); ?></td>

                        <td><?php echo htmlspecialchars($employee["job_title"]); ?></td>

                        <td><?php echo htmlspecialchars($employee["department_name"] ?? ""); ?></td>

                        <td><?php echo htmlspecialchars($employee["phone"] ?? ""); ?></td>

                        <td><?php echo htmlspecialchars($employee["email"] ?? ""); ?></td>

                        <td><?php echo htmlspecialchars($employee["employment_status"]); ?></td>

                        <td class="no-print">

                            <details>
                                <summary>Edit</summary>

                                <form method="POST">

                                    <input type="hidden" name="employee_id"
                                           value="<?php echo (int)$employee["employee_id"]; ?>">

                                    <label>Full Name</label>
                                    <input type="text" name="full_name"
                                           value="<?php echo htmlspecialchars($employee["full_name"]); ?>"
                                           required>

                                    <label>Job Title</label>
                                    <input type="text" name="job_title"
                                           value="<?php echo htmlspecialchars($employee["job_title"]); ?>"
                                           <?php echo ($protected && $role !== "General Manager") ? "readonly" : ""; ?>
                                           required>

                                    <label>Phone</label>
                                    <input type="text" name="phone"
                                           value="<?php echo htmlspecialchars($employee["phone"] ?? ""); ?>">

                                    <label>Email</label>
                                    <input type="email" name="email"
                                           value="<?php echo htmlspecialchars($employee["email"] ?? ""); ?>">

                                    <label>Department</label>

                                    <select name="department_id"
                                        <?php echo ($protected && $role !== "General Manager") ? "disabled" : ""; ?>>

                                        <?php
                                        $departments->data_seek(0);
                                        while ($dept = $departments->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo (int)$dept["department_id"]; ?>"
                                                <?php echo ((int)$dept["department_id"] === (int)$employee["department_id"]) ? "selected" : ""; ?>>
                                                <?php echo htmlspecialchars($dept["department_name"]); ?>
                                            </option>
                                        <?php endwhile; ?>

                                    </select>

                                    <button type="submit" name="update_employee">
                                        Save Changes
                                    </button>

                                </form>
                            </details>

                            <?php if ($protected): ?>

                                <p class="protected">Protected</p>

                            <?php else: ?>

                                <form method="POST">

                                    <input type="hidden" name="employee_id"
                                           value="<?php echo (int)$employee["employee_id"]; ?>">

                                    <input type="hidden" name="new_status"
                                           value="<?php echo $employee["employment_status"] === "Active" ? "Inactive" : "Active"; ?>">

                                    <button type="submit"
                                            name="change_status"
                                            class="<?php echo $employee["employment_status"] === "Active" ? "danger" : ""; ?>">

                                        <?php echo $employee["employment_status"] === "Active" ? "Deactivate" : "Activate"; ?>

                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8">No employees found.</td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

    <br>

    <button class="no-print" onclick="window.print()">
        Print Employee Report
    </button>

</div>

</body>
</html>

<?php
$stmt->close();
?>
