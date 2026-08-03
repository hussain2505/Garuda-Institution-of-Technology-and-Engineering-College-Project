<?php
/**
 * Include this at the very top of every protected page.
 * Starts a secure session, enforces login + idle timeout.
 */

require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Idle session timeout
if (isset($_SESSION['last_activity']) &&
    (time() - $_SESSION['last_activity']) > (SESSION_TIMEOUT_MINUTES * 60)) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . '/auth/login.php?timeout=1');
}
$_SESSION['last_activity'] = time();

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/login.php');
}
