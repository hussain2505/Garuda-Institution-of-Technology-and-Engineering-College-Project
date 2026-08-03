<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$error = null;

if (isset($_GET['timeout'])) {
    $error = "Your session expired due to inactivity. Please log in again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = "Please enter both your username/email and password.";
    } else {

        $stmt = $pdo->prepare(
            "SELECT u.user_id, u.username, u.password_hash, u.status,
                    u.failed_login_attempts, u.locked_until,
                    r.role_id, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.username = ? OR u.email = ?
             LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        // Generic error message: never reveal whether the username exists
        $genericError = "Invalid username/email or password.";

        if (!$user) {
            $error = $genericError;
        } elseif ($user['status'] === 'locked' ||
                  ($user['locked_until'] && strtotime($user['locked_until']) > time())) {
            $error = "This account is temporarily locked due to repeated failed logins. Try again later.";
        } elseif ($user['status'] !== 'active') {
            $error = "This account is inactive. Please contact the administrator.";
        } elseif (!password_verify($password, $user['password_hash'])) {

            // increment failed attempts, lock if threshold exceeded
            $attempts = (int)$user['failed_login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?")
                    ->execute([$attempts, $lockedUntil, $user['user_id']]);
                logAudit($pdo, 'account_locked', 'auth', $user['user_id'], "Locked after $attempts failed attempts");
            } else {
                $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE user_id = ?")
                    ->execute([$attempts, $user['user_id']]);
            }
            logAudit($pdo, 'login_failed', 'auth', $user['user_id'] ?? null, "Failed login for '$identifier'");
            $error = $genericError;

        } else {
            // SUCCESS
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $pdo->prepare(
                "UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?"
            )->execute([$user['user_id']]);

            logAudit($pdo, 'login_success', 'auth', $user['user_id'], "User '{$user['username']}' logged in");

            $target = match ($user['role_name']) {
                'Super Admin', 'Admin' => 'admin/dashboard.php',
                'Faculty'              => 'faculty/dashboard.php',
                'Parent'               => 'parent/dashboard.php',
                default                => 'student/dashboard.php',
            };
            redirect(BASE_URL . '/' . $target);
        }
    }
}

$pageTitle = APP_NAME . ' | Login';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-wrap">
    <div class="auth-card">
        <h2>GITEC ERP Login</h2>
        <p class="muted">Sign in with your college username/email and password.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/auth/login.php" autocomplete="off">
            <?php csrfField(); ?>

            <label for="identifier">Username or Email</label>
            <input type="text" id="identifier" name="identifier" required autofocus
                   value="<?= e($_POST['identifier'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="muted small">
            Demo accounts (password <code>Gitec@123</code> for all):<br>
            superadmin / admin1 / faculty1 / student1
        </p>
        <p class="muted small">New student? <a href="<?= BASE_URL ?>/auth/register.php">Register here</a></p>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
