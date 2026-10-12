<?php
require_once __DIR__ . '/NotificationsModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class NotificationsController {
    private $model;
    public function __construct($pdo) { $this->model = new NotificationsModel($pdo); }

    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['message'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Notification added successfully!');
                    header('Location: index.php?module=Notifications&action=manage');
                    exit;
                } else {
                    $errors['general'] = "Failed to add notification.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    // ---- Mark-as-read -------------------------------------------------
    public function markRead($id) {
        $this->model->markRead($id);
        header('Location: ' . ($_GET['back'] ?? 'index.php?module=Notifications&action=manage'));
        exit;
    }
    public function markAllRead() {
        $this->model->markAllRead();
        SessionHelper::setFlash('success', 'All notifications marked as read.');
        header('Location: ' . ($_GET['back'] ?? 'index.php?module=Notifications&action=manage'));
        exit;
    }

    // ---- Alert rules --------------------------------------------------
    public function rules() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $metric    = $_POST['metric'] ?? '';
            $operator  = $_POST['operator'] ?? '>=';
            $threshold = $_POST['threshold'] ?? '';
            $message   = trim($_POST['message'] ?? '');
            $validMetrics = ['low_stock_items', 'pending_leave', 'open_grievances', 'overdue_harvests', 'purchase_orders_draft', 'unread_notifications'];
            if (!in_array($metric, $validMetrics, true)) $errors['metric'] = 'Choose a metric.';
            if (!in_array($operator, ['>=', '<=', '>', '<', '=='], true)) $errors['operator'] = 'Choose an operator.';
            $e = ValidationHelper::number($_POST, 'threshold', ['label' => 'Threshold']);
            if ($e) $errors['threshold'] = $e;
            if ($message === '') $errors['message'] = 'Message is required.';
            if (empty($errors)) {
                $this->model->addRule($metric, $operator, (float) $threshold, $message);
                SessionHelper::setFlash('success', 'Alert rule saved.');
                header('Location: index.php?module=Notifications&action=rules');
                exit;
            }
        }
        return ['rules' => $this->model->rules(), 'errors' => $errors, 'data' => $_POST];
    }

    public function runRules() {
        $fired = $this->model->runRules();
        SessionHelper::setFlash('success', $fired > 0 ? "$fired alert(s) raised." : 'No rules tripped — all clear.');
        header('Location: index.php?module=Notifications&action=rules');
        exit;
    }

    public function toggleRule($id) {
        $this->model->toggleRule($id);
        header('Location: index.php?module=Notifications&action=rules');
        exit;
    }

    public function deleteRule($id) {
        $this->model->deleteRule($id);
        SessionHelper::setFlash('success', 'Alert rule deleted.');
        header('Location: index.php?module=Notifications&action=rules');
        exit;
    }
}
?>
