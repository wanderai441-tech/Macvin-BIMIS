
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the employee is logged in
function require_login() {
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

// Ensure the employee has the required role
function require_role($allowed_roles) {
    require_login();

    if (!in_array($_SESSION["role"], $allowed_roles, true)) {
        http_response_code(403);
        exit("Access denied. You are not authorised to view this page.");
    }
}
?>
