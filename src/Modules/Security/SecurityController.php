<?php
require_once __DIR__ . '/SecurityModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class SecurityController {
    private $model;
    public function __construct($pdo) { $this->model = new SecurityModel($pdo); }

    /** Security log viewer (login/logout/audit trail). */
    public function index() {
        return $this->model->recentLogs();
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $user = $this->model->getUserByUsername($username);
            if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
                session_regenerate_id(true);
                SessionHelper::csrfRegenerate();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $this->model->logEvent((int) $user['id'], 'login_success');
                header('Location: index.php?module=Dashboard');
                exit;
            }
            $this->model->logEvent($user['id'] ?? null, 'login_failed' . ($username !== '' ? ' (' . $username . ')' : ''));
            return ['error' => 'Invalid username or password'];
        }
        return [];
    }

    public function logout() {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid) { $this->model->logEvent($uid, 'logout'); }
        session_destroy();
        header('Location: index.php?module=Security&action=login');
        exit;
    }

    /** Self-service password change for the signed-in user. */
    public function changePassword() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $user = $this->model->getUserById((int) ($_SESSION['user_id'] ?? 0));
            if (!$user || !password_verify($current, $user['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            }
            if (strlen($new) < 6) {
                $errors['new_password'] = 'New password must be at least 6 characters.';
            } elseif ($new !== $confirm) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
            if (empty($errors)) {
                $this->model->updatePassword((int) $user['id'], $new);
                $this->model->logEvent((int) $user['id'], 'password_change');
                SessionHelper::setFlash('success', 'Password updated successfully.');
                header('Location: index.php?module=Security&action=change_password');
                exit;
            }
        }
        return ['errors' => $errors];
    }

    /** Admin user management: list + create. */
    public function users() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $role     = $_POST['role'] ?? 'worker';
            $password = $_POST['password'] ?? '';
            if ($username === '') $errors['username'] = 'Username is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid email is required.';
            if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';
            if (!in_array($role, ['admin', 'manager', 'accountant', 'worker'], true)) $errors['role'] = 'Invalid role.';
            if (empty($errors)) {
                try {
                    $this->model->createUser($username, $email, $password, $role);
                    $this->model->logEvent((int) ($_SESSION['user_id'] ?? 0), 'user_created (' . $username . ')');
                    SessionHelper::setFlash('success', 'User created successfully.');
                    header('Location: index.php?module=Security&action=users');
                    exit;
                } catch (PDOException $e) {
                    $errors['general'] = 'Could not create user: the username or email is already taken.';
                }
            }
        }
        return ['users' => $this->model->users(), 'errors' => $errors, 'data' => $_POST];
    }

    /** Human-vs-system audit log viewer. */
    public function auditLog() {
        return ['logs' => $this->model->auditLogs()];
    }
}
?>
