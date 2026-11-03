<?php
require_once __DIR__ . '/../../Services/CommsService.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';

class CommunicationsController {
    private CommsService $svc;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->svc = new CommsService($pdo);
    }

    // -----------------------------------------------------------------------
    // Intranet (within own farm — staff messaging)
    // -----------------------------------------------------------------------

    public function index(): array {
        $uid  = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'worker';
        return [
            'conversations' => $this->svc->listConversations($uid, 'intranet'),
            'role'   => $role,
            'userId' => $uid,
            'scope'  => 'intranet',
        ];
    }

    public function view(): array {
        $uid  = (int)($_SESSION['user_id'] ?? 0);
        $cid  = ValidationHelper::id($_GET['id'] ?? '');
        return [
            'conversations' => $this->svc->listConversations($uid, 'intranet'),
            'messages'  => $cid ? $this->svc->listMessages($uid, $cid) : [],
            'activeId'  => $cid,
            'userId'    => $uid,
            'role'      => $_SESSION['role'] ?? 'worker',
            'scope'     => 'intranet',
        ];
    }

    // -----------------------------------------------------------------------
    // Extranet (between connected farms)
    // -----------------------------------------------------------------------

    public function extranet(): array {
        $uid  = (int)($_SESSION['user_id'] ?? 0);
        $cid  = ValidationHelper::id($_GET['id'] ?? '');
        return [
            'conversations' => $this->svc->listConversations($uid, 'extranet'),
            'messages'  => $cid ? $this->svc->listMessages($uid, $cid) : [],
            'activeId'  => $cid,
            'userId'    => $uid,
            'role'      => $_SESSION['role'] ?? 'worker',
            'scope'     => 'extranet',
        ];
    }

    public function createExtranet(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?module=Communications&action=extranet'); exit;
        }
        $uid   = (int)($_SESSION['user_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $members = [];
        $rawMembers = isset($_POST['members']) && is_array($_POST['members']) ? $_POST['members'] : [];
        foreach ($rawMembers as $m) {
            $i = ValidationHelper::id($m);
            if ($i) $members[] = $i;
        }
        try {
            $cid = $this->svc->createConversation(
                $uid, 'extranet',
                $title !== '' ? $title : 'Farm-to-Farm Chat',
                $members, 'group'
            );
            SessionHelper::setFlash('success', 'Extranet conversation started.');
            header('Location: index.php?module=Communications&action=extranet&id=' . $cid); exit;
        } catch (\Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
            header('Location: index.php?module=Communications&action=extranet'); exit;
        }
    }

    // -----------------------------------------------------------------------
    // Shared actions
    // -----------------------------------------------------------------------

    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?module=Communications'); exit;
        }
        $uid   = (int)($_SESSION['user_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $rawMembers = isset($_POST['members']) && is_array($_POST['members']) ? $_POST['members'] : [];
        $members = [];
        foreach ($rawMembers as $m) { $i = ValidationHelper::id($m); if ($i) $members[] = $i; }
        try {
            $cid = $this->svc->createConversation($uid, 'intranet', $title !== '' ? $title : 'New conversation', $members, 'group');
            SessionHelper::setFlash('success', 'Conversation created.');
            header('Location: index.php?module=Communications&action=view&id=' . $cid); exit;
        } catch (\Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
            header('Location: index.php?module=Communications'); exit;
        }
    }

    public function post(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $uid  = (int)($_SESSION['user_id'] ?? 0);
        $cid  = ValidationHelper::id($_POST['conversation_id'] ?? $_GET['id'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $back = $_POST['back'] ?? 'index.php?module=Communications';
        if (!$cid) {
            SessionHelper::setFlash('error', 'Invalid conversation.');
            header('Location: ' . $back); exit;
        }
        try {
            $this->svc->postMessage($uid, $cid, $body);
        } catch (\Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
        }
        header('Location: ' . $back . '&id=' . $cid); exit;
    }

    public function broadcast(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        $uid   = (int)($_SESSION['user_id'] ?? 0);
        $role  = $_SESSION['role'] ?? 'worker';
        $title = trim($_POST['title'] ?? 'Broadcast');
        $body  = trim($_POST['body'] ?? '');
        try {
            $cid = $this->svc->broadcast($uid, $role, $title, $body);
            SessionHelper::setFlash('success', 'Broadcast sent to all farm members.');
            header('Location: index.php?module=Communications&action=view&id=' . $cid); exit;
        } catch (\Throwable $e) {
            SessionHelper::setFlash('error', $e->getMessage());
            header('Location: index.php?module=Communications'); exit;
        }
    }

    /** JSON: unread count badge (polled by layout). */
    public function unreadJson(): void {
        header('Content-Type: application/json; charset=UTF-8');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM messages m
                 JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = ?
                 WHERE m.deleted_at IS NULL
                   AND (cm.last_read_message_id IS NULL OR m.id > cm.last_read_message_id)
                   AND m.sender_id != ?"
            );
            $st->execute([$uid, $uid]);
            $count = (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            $count = 0;
        }
        echo json_encode(['success' => true, 'unread' => $count]);
        exit;
    }
}
