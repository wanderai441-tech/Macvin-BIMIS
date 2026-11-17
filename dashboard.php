
<?php
require_once "auth.php";
require_login();

$role = $_SESSION["role"] ?? "";
$name = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";

$menus = [
    "General Manager" => [
        ["Management Overview", "View business performance and key activities.", "management_overview.php", "📊"],
        ["Sales Management", "Record sales and generate customer receipts.", "sales.php", "🛒"],
        ["Inventory Management", "Monitor products, quantities and stock levels.", "inventory.php", "📦"],
        ["Procurement Management", "Manage suppliers and purchase requests.", "procurement.php", "🚚"],
        ["Finance Management", "Record and monitor business expenses.", "finance.php", "💰"],
        ["HR Management", "Manage employee information and records.", "hr.php", "👥"],
        ["Reports", "View business and departmental reports.", "reports.php", "📈"],
        ["User Management", "Manage system accounts and access rights.", "user_management.php", "🔐"]
    ],

    "Sales" => [
        ["Sales Management", "Record customer purchases and process payments.", "sales.php", "🛒"],
        ["Generate Customer Receipts", "Prepare receipts for completed sales.", "sales.php", "🧾"]
    ],

    "Inventory" => [
        ["Inventory Management", "View products and available stock.", "inventory.php", "📦"],
        ["Receive Stock", "Record stock received from suppliers.", "inventory.php", "🚚"],
        ["Stock Alerts", "Identify products requiring replenishment.", "inventory.php", "⚠️"]
    ],

    "Procurement" => [
        ["Supplier Management", "View and manage supplier information.", "procurement.php", "🏢"],
        ["Purchase Requests", "Prepare and monitor purchase requests.", "procurement.php", "📋"]
    ],

    "Finance" => [
        ["Finance Management", "Manage business financial records.", "finance.php", "💰"],
        ["Record Expenses", "Enter and monitor business expenses.", "finance.php", "💳"],
        ["Financial Reports", "View financial summaries and reports.", "reports.php", "📈"]
    ],

    "HR" => [
        ["HR Management", "Manage employee information.", "hr.php", "👥"],
        ["Employee Records", "View and update employee records.", "hr.php", "📁"],
        ["Staff Reports", "View employee information and reports.", "hr.php", "📊"]
    ]
];

$my_modules = $menus[$role] ?? [];

if (empty($my_modules)) {
    http_response_code(403);
    exit("Your account does not have an assigned dashboard.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BIMIS | Macvin Hardware</title>

<style>
:root {
    --navy: #14243b;
    --blue: #2864dc;
    --light: #f3f6fb;
    --white: #ffffff;
    --muted: #718096;
    --border: #e5eaf1;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: var(--light);
    color: #26364d;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 245px;
    background: var(--navy);
    color: white;
    padding: 25px 16px;
    overflow-y: auto;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
}

.logo {
    width: 48px;
    height: 48px;
    background: white;
    color: var(--navy);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 27px;
}

.brand h2 {
    margin: 0;
    font-size: 23px;
}

.brand small {
    color: #d9a441;
    font-size: 9px;
    letter-spacing: 1px;
}

.company {
    color: #aebed1;
    font-size: 10px;
    line-height: 1.6;
    padding: 8px;
    margin-bottom: 25px;
}

.nav-label {
    color: #91a4bc;
    font-size: 10px;
    letter-spacing: 1.5px;
    padding: 12px 10px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 12px 11px;
    margin: 4px 0;
    border-radius: 7px;
    color: #d4dfeb;
    text-decoration: none;
    font-size: 13px;
    transition: .2s;
}

.nav-link:hover,
.nav-link.active {
    background: #294463;
    color: white;
}

.sidebar-bottom {
    border-top: 1px solid #34465e;
    margin-top: 28px;
    padding: 18px 8px;
    font-size: 12px;
}

.logout {
    display: block;
    text-decoration: none;
    text-align: center;
    background: #294463;
    color: white;
    padding: 12px;
    border-radius: 7px;
    margin-top: 15px;
}

.logout:hover {
    background: #b83232;
}

/* MAIN AREA */
.main {
    margin-left: 245px;
    min-height: 100vh;
}

.topbar {
    height: 70px;
    background: white;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.topbar strong {
    font-size: 16px;
    color: var(--navy);
}

.user {
    font-size: 13px;
    color: #526176;
}

.content {
    padding: 30px;
    max-width: 1600px;
    margin: auto;
}

.page-title {
    font-size: 25px;
    margin: 0 0 8px;
    color: #182b45;
}

.page-subtitle {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 25px;
}

/* WELCOME PANEL */
.welcome {
    background: linear-gradient(120deg, #14243b, #28588c);
    color: white;
    padding: 27px;
    border-radius: 11px;
    margin-bottom: 28px;
}

.welcome h2 {
    margin: 0 0 10px;
    font-size: 22px;
}

.welcome p {
    color: #d6e5f7;
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 0;
}

/* SUMMARY */
.summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 28px;
}

.summary-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 19px;
}

.summary-card span {
    color: var(--muted);
    font-size: 12px;
}

.summary-card h3 {
    font-size: 21px;
    color: var(--navy);
    margin: 12px 0 5px;
}

.summary-card p {
    font-size: 11px;
    color: var(--muted);
    margin: 0;
}

/* MODULE CARDS */
.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-heading h2 {
    font-size: 17px;
    color: var(--navy);
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(190px, 1fr));
    gap: 17px;
}

.module-card {
    display: block;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 21px;
    text-decoration: none;
    color: #26364d;
    transition: .2s;
    box-shadow: 0 3px 12px rgba(23,43,70,.03);
}

.module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 7px 18px rgba(23,43,70,.10);
    border-color: #b7c9e8;
}

.module-icon {
    width: 43px;
    height: 43px;
    border-radius: 9px;
    background: #eaf0fc;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 22px;
    margin-bottom: 17px;
}

.module-card h3 {
    font-size: 15px;
    color: var(--navy);
    margin: 0 0 9px;
}

.module-card p {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.6;
    margin: 0;
}

.open-module {
    display: inline-block;
    color: var(--blue);
    font-size: 12px;
    font-weight: bold;
    margin-top: 17px;
}

footer {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    padding: 25px;
}

/* RESPONSIVE */
@media(max-width: 1050px) {
    .module-grid {
        grid-template-columns: repeat(2, minmax(180px, 1fr));
    }
}

@media(max-width: 750px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .module-grid {
        grid-template-columns: 1fr;
    }

    .content {
        padding: 18px;
    }

    .topbar {
        padding: 0 18px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">
        <div class="logo">⌂</div>
        <div>
            <h2>BIMIS</h2>
            <small>MACVIN HARDWARE</small>
        </div>
    </div>

    <div class="company">
        MACVIN HARDWARE & BUILDING SUPPLIES LTD
    </div>

    <div class="nav-label">MAIN MENU</div>

    <a href="dashboard.php" class="nav-link active">
        <span>▦</span> Dashboard
    </a>

    <?php foreach ($my_modules as $module): ?>
        <a href="<?= htmlspecialchars($module[2]) ?>" class="nav-link">
            <span><?= htmlspecialchars($module[3]) ?></span>
            <?= htmlspecialchars($module[0]) ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-bottom">
        <div>Signed in as:</div>
        <strong><?= htmlspecialchars($name) ?></strong>
        <div style="color:#aebed1;margin-top:5px">
            <?= htmlspecialchars($role) ?>
        </div>

        <a href="logout.php" class="logout">Sign Out</a>
    </div>

</aside>

<main class="main">

    <header class="topbar">
        <strong>Business Management Dashboard</strong>
        <div class="user">
            <?= htmlspecialchars($name) ?> |
            <?= htmlspecialchars($role) ?>
        </div>
    </header>

    <div class="content">

        <div class="welcome">
            <h2>Welcome back, <?= htmlspecialchars($name) ?>!</h2>
            <p>
                Welcome to the Macvin Business Integrated Management
                Information System. Use your authorised modules to
                manage departmental activities and access business information.
            </p>
        </div>

        <h1 class="page-title">Dashboard Overview</h1>

        <p class="page-subtitle">
            Your workspace for managing Macvin Hardware operations.
        </p>

        <div class="summary">

            <div class="summary-card">
                <span>Account Role</span>
                <h3><?= htmlspecialchars($role) ?></h3>
                <p>Your assigned system responsibility</p>
            </div>

            <div class="summary-card">
                <span>Available Modules</span>
                <h3><?= count($my_modules) ?></h3>
                <p>Modules available to your account</p>
            </div>

            <div class="summary-card">
                <span>System Status</span>
                <h3 style="color:#159765">Active</h3>
                <p>Logged in successfully</p>
            </div>

        </div>

        <div class="section-heading">
            <h2>Available Modules</h2>
        </div>

        <div class="module-grid">

            <?php foreach ($my_modules as $module): ?>

                <a class="module-card"
                   href="<?= htmlspecialchars($module[2]) ?>">

                    <div class="module-icon">
                        <?= htmlspecialchars($module[3]) ?>
                    </div>

                    <h3><?= htmlspecialchars($module[0]) ?></h3>

                    <p><?= htmlspecialchars($module[1]) ?></p>

                    <span class="open-module">Open Module →</span>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <footer>
        Macvin BIMIS &copy; <?= date("Y") ?> |
        Business Integrated Management Information System
    </footer>

</main>

</body>
</html>
