
<?php
require_once "auth.php";
require_login();

$role = $_SESSION["role"];
$name = $_SESSION["full_name"] ?? $_SESSION["username"];

// Modules available to each role
$modules = [
    "General Manager" => [
        "Management Overview",
        "Sales Management",
        "Inventory Management",
        "Procurement Management",
        "Finance Management",
        "HR Management",
        "Reports",
        "User Management"
    ],

    "Sales" => [
        "Sales Management",
        "Generate Customer Receipts"
    ],

    "Inventory" => [
        "Inventory Management",
        "Receive Stock",
        "Stock Alerts"
    ],

    "Procurement" => [
        "Supplier Management",
        "Purchase Requests"
    ],

    "Finance" => [
        "Finance Management",
        "Record Expenses",
        "Financial Reports"
    ],

    "HR" => [
        "HR Management",
        "Employee Records",
        "Staff Reports"
    ]
];

// Module links
$module_links = [
    "Management Overview" => "management_overview.php",

    "Sales Management" => "sales.php",
    "Generate Customer Receipts" => "sales.php",

    "Inventory Management" => "inventory.php",
    "Receive Stock" => "inventory.php",
    "Stock Alerts" => "inventory.php",

    "Procurement Management" => "procurement.php",
    "Supplier Management" => "procurement.php",
    "Purchase Requests" => "procurement.php",

    "Finance Management" => "finance.php",
    "Record Expenses" => "finance.php",
    "Financial Reports" => "reports.php",

    "HR Management" => "hr.php",
    "Employee Records" => "hr.php",
    "Staff Reports" => "hr.php",

    "Reports" => "reports.php",
    "User Management" => "user_management.php"
];

$my_modules = $modules[$role] ?? [];

if (!$my_modules) {
    http_response_code(403);
    exit("Your account does not have an assigned dashboard.");
}
?>

<!DOCTYPE html>
<html>
<head>

    <title>Macvin BIMIS Dashboard</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f4f8;
        }

        header {
            background: #183153;
            color: white;
            padding: 22px;
        }

        header h2 {
            margin: 0 0 8px;
        }

        .container {
            padding: 25px;
        }

        .welcome {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .module {
            background: white;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            text-decoration: none;
            color: #183153;
            border-left: 5px solid #183153;
            transition: 0.2s;
        }

        .module:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.14);
        }

        .module h3 {
            margin-top: 0;
        }

        .module p {
            color: #555;
            font-size: 14px;
        }

        .logout {
            display: inline-block;
            background: #b83232;
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 5px;
            margin-top: 20px;
        }

        footer {
            padding: 20px;
            text-align: center;
            color: #666;
        }
    </style>

</head>

<body>

<header>
    <h2>MACVIN HARDWARE & BUILDING SUPPLIES</h2>
    <p>Business Integrated Management Information System (BIMIS)</p>
</header>

<div class="container">

    <div class="welcome">
        <h2>Welcome, <?php echo htmlspecialchars($name); ?></h2>

        <p>
            Department / Role:
            <strong><?php echo htmlspecialchars($role); ?></strong>
        </p>

        <p>Select a module below to continue.</p>
    </div>

    <h2>Available Modules</h2>

    <div class="modules">

        <?php foreach ($my_modules as $module): ?>

            <?php if (isset($module_links[$module])): ?>

                <a class="module"
                   href="<?php echo htmlspecialchars($module_links[$module]); ?>">

                    <h3><?php echo htmlspecialchars($module); ?></h3>

                    <p>
                        Open <?php echo htmlspecialchars($module); ?>
                        module.
                    </p>

                </a>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>

    <a class="logout" href="logout.php">Logout</a>

</div>

<footer>
    Macvin BIMIS &copy; <?php echo date("Y"); ?>
</footer>

</body>
</html>
