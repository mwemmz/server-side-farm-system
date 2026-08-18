<?php
require_once __DIR__ . '/SecurityModel.php';
class SecurityController {
    private $model;
    public function __construct($pdo) { $this->model = new SecurityModel($pdo); }
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = $this->model->getUserByUsername($_POST['username']);
            if ($user && password_verify($_POST['password'], $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: index.php?module=Farm');
                exit;
            }
            return ['error' => 'Invalid credentials'];
        }
        return [];
    }
    public function logout() {
        session_destroy();
        header('Location: index.php?module=Security&action=login');
        exit;
    }
}
?>
