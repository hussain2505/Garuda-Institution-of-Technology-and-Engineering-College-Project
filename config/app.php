<?php
/**
 * Application configuration
 */

define('APP_NAME', 'Garuda Institute of Technology & Engineering College');
define('APP_SHORT_NAME', 'GITEC');
define('APP_VERSION', '1.0.0');

// Change this to match your actual XAMPP project folder name if different
define('BASE_URL', 'http://localhost/GITEC-ERP');

date_default_timezone_set('Asia/Kolkata');

// Session security tuning (must run before session_start())
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
// Uncomment when serving over HTTPS in production:
// ini_set('session.cookie_secure', 1);

// Login security policy
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('SESSION_TIMEOUT_MINUTES', 30);
