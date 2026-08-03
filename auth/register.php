<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) redirect(BASE_URL . '/index.php');

$errors = [];
$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $first      = trim($_POST['first_name'] ?? '');
    $last       = trim($_POST['last_name'] ?? '');
    $roll       = trim($_POST['roll_number'] ?? '');
    $deptId     = (int)($_POST['department_id'] ?? 0);

    if ($username === '' || $email === '' || $first === '' || $roll === '' || $deptId <= 0) {
        $errors[] = "Please fill in all required fields.";
    }
    if (!isValidEmail($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (!$errors) {
        // uniqueness checks
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "That username or email is already registered.";
        }
    }

    if (!$errors) {
        $studentRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Student'")->fetchColumn();

        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'active')"
            )->execute([$username, $email, $hash, $studentRoleId]);
            $userId = $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO students (user_id, roll_number, first_name, last_name, email, department_id, current_year, current_semester, status)
                 VALUES (?, ?, ?, ?, ?, ?, 1, 1, 'active')"
            )->execute([$userId, $roll, $first, $last, $email, $deptId]);

            $pdo->commit();
            logAudit($pdo, 'self_register', 'auth', $userId, "New student self-registered: $username");

            setFlash('success', 'Registration successful! You can now log in.');
            if (session_status() === PHP_SESSION_ACTIVE) session_start();
            redirect(BASE_URL . '/auth/login.php');

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed. That roll number may already be in use.";
        }
    }
}

$pageTitle = APP_NAME . ' | Student Registration';
include __DIR__ . '/../includes/header.php';
?>

<section class="auth-wrap">
    <div class="auth-card auth-card-wide">
        <h2>Student Self-Registration</h2>
        <p class="muted">Create your GITEC ERP student account.</p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= BASE_URL ?>/auth/register.php" autocomplete="off">
            <?php csrfField(); ?>

            <div class="form-row">
                <div>
                    <label>First Name</label>
                    <input type="text" name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>">
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Roll Number</label>
                    <input type="text" name="roll_number" required value="<?= e($_POST['roll_number'] ?? '') ?>">
                </div>
                <div>
                    <label>Department</label>
                    <select name="department_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['department_id'] ?>"><?= e($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Email</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">

            <label>Username</label>
            <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">

            <div class="form-row">
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div>
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>
        <p class="muted small">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Login here</a></p>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
