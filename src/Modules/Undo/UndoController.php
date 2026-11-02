<?php
require_once __DIR__ . '/../../Helpers/UndoHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class UndoController {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function restore() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
        require_once __DIR__ . '/../../Helpers/UndoHelper.php';
        [$ok, $msg] = UndoHelper::restore($this->pdo);
        if ($ok) SessionHelper::setFlash('success', $msg); else SessionHelper::setFlash('error', $msg);
        $u = $_SESSION['last_delete'] ?? ['module' => 'Dashboard'];
        $mod = $u['module'] ?? 'Dashboard';
        $redir = $u['redirect'] ?? ('index.php?module=' . $mod . '&action=manage');
        header('Location: ' . $redir); exit;
    }
}
?>