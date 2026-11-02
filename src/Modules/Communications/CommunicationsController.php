<?php
require_once __DIR__ . '/../../Services/CommsService.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';

class CommunicationsController {
    private $svc;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->svc = new CommsService($pdo); }
    public function index() {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        $convs = $this->svc->listConversations($uid, 'intranet');
        return ['conversations' => $convs, 'role' => $role, 'userId' => $uid];
    }
    public function view() {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $cid = ValidationHelper::id($_GET['id'] ?? '');
        $convs = $this->svc->listConversations($uid, 'intranet');
        $msgs = $cid ? $this->svc->listMessages($uid, $cid) : [];
        return ['conversations' => $convs, 'messages' => $msgs, 'activeId' => $cid, 'userId' => $uid, 'role' => $_SESSION['role'] ?? 'worker'];
    }
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?module=Communications'); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $members = isset($_POST['members']) && is_array($_POST['members']) ? $_POST['members'] : [];
        $mids = [];
        foreach ($members as $m) { $i = ValidationHelper::id($m); if ($i) $mids[] = $i; }
        try {
            $cid = $this->svc->createConversation($uid, 'intranet', $title !== '' ? $title : 'New conversation', $mids, 'group');
            SessionHelper::setFlash('success', 'Conversation created.');
            header('Location: index.php?module=Communications&action=view&id=' . $cid); exit;
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
            header('Location: index.php?module=Communications'); exit;
        }
    }
    public function post() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $cid = ValidationHelper::id($_POST['conversation_id'] ?? $_GET['id'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if (!$cid) { SessionHelper::setFlash('error', 'Invalid conversation.'); header('Location: index.php?module=Communications'); exit; }
        try {
            $this->svc->postMessage($uid, $cid, $body);
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
        }
        header('Location: index.php?module=Communications&action=view&id=' . $cid); exit;
    }
    public function broadcast() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        $title = trim($_POST['title'] ?? 'Broadcast');
        $body = trim($_POST['body'] ?? '');
        try {
            $cid = $this->svc->broadcast($uid, $role, $title, $body);
            SessionHelper::setFlash('success', 'Broadcast sent.');
            header('Location: index.php?module=Communications&action=view&id=' . $cid); exit;
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
            header('Location: index.php?module=Communications'); exit;
        }
    }
}
?>