
<?php
session_start();
require_once "db_connect.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.password_hash, u.role,
               e.full_name, e.job_title, d.department_name
        FROM user_accounts u
        JOIN employees e ON u.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE u.username = ? AND u.account_status = 'Active'
        AND e.employment_status = 'Active'
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password_hash"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["department"] = $user["department_name"];

        header("Location: dashboard.php");
        exit;

    } else {
        $error = "Invalid username or password, or account inactive.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BIMIS Login | Macvin Hardware</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #edf1f7;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-box {
    background: white;
    width: 90%;
    max-width: 400px;
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

h1 {
    color: #17365d;
    text-align: center;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin-bottom: 28px;
    font-size: 14px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccd3dd;
    border-radius: 6px;
    margin-bottom: 18px;
    font-size: 15px;
}

button {
    width: 100%;
    padding: 13px;
    background: #17365d;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
}

button:hover {
    background: #244c7c;
}

.error {
    background: #fdecec;
    color: #b42318;
    padding: 11px;
    border-radius: 6px;
    margin-bottom: 18px;
    font-size: 14px;
}

.footer {
    text-align: center;
    margin-top: 22px;
    color: #777;
    font-size: 12px;
}
</style>
</head>

<body>

<div class="login-box">

    <h1>MACVIN HARDWARE</h1>

    <p class="subtitle">
        Business Integrated Management Information System
    </p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               placeholder="Enter your username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" required>

        <button type="submit">Login to BIMIS</button>

    </form>

    <div class="footer">
        Macvin Hardware & Building Supplies Ltd
    </div>

</div>

</body>
</html>
