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
                    SessionHelper::setFlash('success', 'Notification added and dispatched successfully!');
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
        SessionHelper::setFlash('success', $fired > 0 ? "$fired alert(s) raised and sent via SMTP." : 'No rules tripped — all clear.');
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

    // ---- SMTP Settings & Testing --------------------------------------
    public function smtp() {
        require_once __DIR__ . '/../../Services/NotificationService.php';
        $notifService = new NotificationService($this->model->getPdo());
        $errors = [];
        $testResult = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actionType = $_POST['action_type'] ?? 'save';

            if ($actionType === 'test') {
                $testTo = trim((string) ($_POST['test_recipient'] ?? ''));
                if ($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                    $errors['test_recipient'] = 'Please enter a valid recipient email address.';
                } else {
                    $testResult = $notifService->testConnection($testTo);
                    if ($testResult['success']) {
                        SessionHelper::setFlash('success', 'Test email sent successfully to ' . htmlspecialchars($testTo) . ' via SMTP!');
                    } else {
                        $errors['general'] = 'SMTP Test Failed: ' . ($testResult['error'] ?? 'Connection error');
                    }
                }
            } elseif ($actionType === 'save') {
                $saveData = [
                    'SMTP_HOST'           => trim((string) ($_POST['smtp_host'] ?? '')),
                    'SMTP_PORT'           => trim((string) ($_POST['smtp_port'] ?? '587')),
                    'SMTP_SECURE'         => trim((string) ($_POST['smtp_secure'] ?? 'tls')),
                    'SMTP_USER'           => trim((string) ($_POST['smtp_user'] ?? '')),
                    'SMTP_FROM'           => trim((string) ($_POST['smtp_from'] ?? '')),
                    'SMTP_FROM_NAME'      => trim((string) ($_POST['smtp_from_name'] ?? 'FFMS Alerts')),
                    'SMTP_RECIPIENT'      => trim((string) ($_POST['smtp_recipient'] ?? '')),
                    'SMTP_ENABLED'        => !empty($_POST['smtp_enabled']) ? '1' : '0',
                    'NOTIFICATION_DRIVER' => !empty($_POST['smtp_enabled']) ? 'smtp' : 'log',
                ];

                $newPass = (string) ($_POST['smtp_pass'] ?? '');
                if ($newPass !== '') {
                    $saveData['SMTP_PASS'] = $newPass;
                }

                $notifService->saveSettings($saveData);
                SessionHelper::setFlash('success', 'SMTP notification settings saved successfully.');
                header('Location: index.php?module=Notifications&action=smtp');
                exit;
            }
        }

        $config = $notifService->getSmtpConfig();
        return [
            'config'     => $config,
            'errors'     => $errors,
            'testResult' => $testResult,
        ];
    }
}
?>
