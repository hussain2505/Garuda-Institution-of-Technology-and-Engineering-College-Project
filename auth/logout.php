<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    logAudit($pdo, 'logout', 'auth', $_SESSION['user_id'], "User '{$_SESSION['username']}' logged out");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

redirect(BASE_URL . '/auth/login.php');
