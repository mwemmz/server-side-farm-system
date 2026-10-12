<?php
require_once __DIR__ . '/../BaseModuleModel.php';
require_once __DIR__ . '/../../Intelligence/AttentionService.php';

class NotificationsModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'notifications'); }

    /** Unread first, then newest. */
    public function getAll() {
        return $this->pdo->query(
            "SELECT * FROM notifications ORDER BY is_read ASC, created_at DESC, id DESC"
        )->fetchAll();
    }

    public function unreadCount() {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = FALSE")->fetchColumn();
    }

    public function markRead($id) {
        return $this->pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?")->execute([(int) $id]);
    }

    public function markAllRead() {
        return $this->pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE is_read = FALSE")->execute([]);
    }

    // ---- Alert rules -------------------------------------------------
    public function rules() {
        return $this->pdo->query("SELECT * FROM notification_rules ORDER BY id DESC")->fetchAll();
    }

    public function addRule($metric, $operator, $threshold, $message) {
        $st = $this->pdo->prepare(
            "INSERT INTO notification_rules (metric, operator, threshold, message) VALUES (?, ?, ?, ?)");
        return $st->execute([$metric, $operator, (float) $threshold, $message]);
    }

    public function toggleRule($id) {
        return $this->pdo->prepare("UPDATE notification_rules SET is_active = NOT is_active WHERE id = ?")->execute([(int) $id]);
    }

    public function deleteRule($id) {
        return $this->pdo->prepare("DELETE FROM notification_rules WHERE id = ?")->execute([(int) $id]);
    }

    /** Current value of a supported metric, or null if unknown. */
    public function metricValue($metric) {
        switch ($metric) {
            case 'low_stock_items':
                return (int) $this->pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 10")->fetchColumn();
            case 'pending_leave':
                return (int) $this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
            case 'open_grievances':
                return (int) $this->pdo->query("SELECT COUNT(*) FROM grievances WHERE status IN ('open','in_progress')")->fetchColumn();
            case 'overdue_harvests':
                return (int) $this->pdo->query("SELECT COUNT(*) FROM crops WHERE expected_harvest_date IS NOT NULL AND expected_harvest_date < CURRENT_DATE")->fetchColumn();
            case 'purchase_orders_draft':
                return (int) $this->pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'draft'")->fetchColumn();
            case 'unread_notifications':
                return $this->unreadCount();
            default:
                return null;
        }
    }

    private function compare($value, $operator, $threshold) {
        $t = (float) $threshold;
        switch ($operator) {
            case '>':  return $value >  $t;
            case '<':  return $value <  $t;
            case '<=': return $value <= $t;
            case '==': return $value == $t;
            case '>=':
            default:   return $value >= $t;
        }
    }

    /** Evaluate every active rule; raise a notification when it trips (max once/day per rule). */
    public function runRules() {
        $fired = 0;
        foreach ($this->rules() as $rule) {
            if (empty($rule['is_active'])) continue;
            $value = $this->metricValue($rule['metric']);
            if ($value === null) continue;
            if (!$this->compare($value, $rule['operator'], $rule['threshold'])) continue;
            $dupe = $this->pdo->prepare(
                "SELECT 1 FROM notifications WHERE rule_id = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL '1 day' LIMIT 1");
            $dupe->execute([(int) $rule['id']]);
            if ($dupe->fetchColumn()) continue;
            $st = $this->pdo->prepare("INSERT INTO notifications (message, rule_id) VALUES (?, ?)");
            $st->execute([$rule['message'] . ' (current: ' . $value . ')', (int) $rule['id']]);
            $fired++;
        }
        $fired += $this->raiseAttention();
        return $fired;
    }

    /**
     * B5: raise notifications for the shared "needs attention" items
     * (overdue vaccinations, withdrawals, low stock, service due, unpaid
     * finance, ...). De-duplicated by message so a scan never spams.
     */
    public function raiseAttention() {
        $fired = 0;
        try {
            $svc = new AttentionService($this->pdo, 'admin');
            $items = $svc->collect();
        } catch (\Throwable $e) {
            error_log('Notifications attention failed: ' . $e->getMessage());
            return 0;
        }
        $dupe = $this->pdo->prepare(
            "SELECT 1 FROM notifications WHERE message = ? AND created_at > CURRENT_TIMESTAMP - INTERVAL '1 day' LIMIT 1");
        $st = $this->pdo->prepare("INSERT INTO notifications (message) VALUES (?)");
        foreach ($items as $it) {
            $msg = '⚠ ' . $it['title'] . ' — ' . $it['detail'];
            $dupe->execute([$msg]);
            if ($dupe->fetchColumn()) continue;
            $st->execute([$msg]);
            $fired++;
        }
        return $fired;
    }
}
?>
