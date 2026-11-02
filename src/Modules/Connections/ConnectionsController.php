<?php
require_once __DIR__ . '/../../Services/FarmConnectionService.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';

class ConnectionsController {
    private $svc; private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->svc = new FarmConnectionService($pdo); }
    public function index() {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        $conns = $this->svc->listConnections($uid);
        $search = [];
        if (!empty($_GET['q']) || !empty($_GET['district'])) {
            $search = $this->svc->searchFarms($uid, $_GET['district'] ?? '', $_GET['q'] ?? '');
        }
        return ['connections' => $conns, 'search' => $search, 'role' => $role, 'userId' => $uid];
    }
    public function request() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?module=Connections'); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        $target = ValidationHelper::id($_POST['target_farm_id'] ?? '');
        try {
            if (!$target) throw new Exception('Invalid farm.');
            $this->svc->requestConnection($uid, $target, $role);
            SessionHelper::setFlash('success', 'Connection request sent.');
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
        }
        header('Location: index.php?module=Connections'); exit;
    }
    public function decide() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?module=Connections'); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        $id = ValidationHelper::id($_POST['id'] ?? '');
        $decision = $_POST['decision'] ?? '';
        try {
            $this->svc->decide($uid, $id, $decision, $role);
            SessionHelper::setFlash('success', 'Decision recorded.');
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
        }
        header('Location: index.php?module=Connections'); exit;
    }
}
?>