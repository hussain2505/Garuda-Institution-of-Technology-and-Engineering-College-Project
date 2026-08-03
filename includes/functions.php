<?php
/**
 * Common helper functions used across the ERP.
 */

/** Redirect and stop execution */
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

/** Safe HTML output escaping (XSS protection) */
function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Is the current visitor authenticated? */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/** Get the logged-in user's role name, or null */
function currentRole()
{
    return $_SESSION['role_name'] ?? null;
}

/** Restrict a page to one or more roles. Call after auth-check.php */
function requireRole(...$roles)
{
    if (!isLoggedIn() || !in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        die('<h2>403 - Access Denied</h2><p>You do not have permission to view this page.</p>');
    }
}

/** Generate (once per session) and return a CSRF token */
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Output a hidden CSRF field for a form */
function csrfField()
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Validate a submitted CSRF token, die()-ing on mismatch */
function verifyCsrf()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

/** Write an audit-log entry */
function logAudit(PDO $pdo, $action, $module = null, $recordId = null, $description = null)
{
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $module,
        $recordId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Simple flash-message helper (stored in session, shown once) */
function setFlash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function renderFlashes()
{
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $flash) {
        $cls = $flash['type'] === 'error' ? 'alert-error' : ($flash['type'] === 'success' ? 'alert-success' : 'alert-info');
        echo '<div class="alert ' . $cls . '">' . e($flash['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Basic email format validation */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Attendance percentage helper, used by dashboards */
function calculateAttendancePercentage($present, $total)
{
    if ($total <= 0) return 0.0;
    return round(($present / $total) * 100, 2);
}
