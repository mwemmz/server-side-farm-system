<?php
require_once __DIR__ . '/../../Intelligence/HarvestApproval.php';
require_once __DIR__ . '/../../Intelligence/YieldPredictionService.php';
require_once __DIR__ . '/../../Intelligence/SellRecommendationService.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class InsightsOperationsController {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function index() {
        $harvests = $this->pdo->query("SELECT hr.*, c.name AS crop_name FROM harvest_records hr LEFT JOIN crops c ON c.id=hr.crop_id WHERE hr.status='submitted' ORDER BY hr.harvest_date DESC")->fetchAll();
        $crops = $this->pdo->query("SELECT c.id, c.name, c.expected_yield, yp.predicted_yield, yp.predicted_at FROM crops c LEFT JOIN yield_predictions yp ON yp.crop_id=c.id ORDER BY c.name")->fetchAll();
        $produce = $this->pdo->query("SELECT sp.*, c.name AS crop_name FROM stored_produce sp LEFT JOIN crops c ON c.id=sp.crop_id WHERE sp.is_in_storage=true ORDER BY sp.storage_start_date")->fetchAll();
        $rules = $this->pdo->query("SELECT rr.*, i.name AS item_name, i.quantity AS balance, i.unit, s.name AS supplier_name FROM reorder_rules rr JOIN inventory i ON i.id=rr.item_id LEFT JOIN suppliers s ON s.id=rr.preferred_supplier_id ORDER BY i.name")->fetchAll();
        $items = $this->pdo->query("SELECT id,name,quantity,unit FROM inventory ORDER BY name")->fetchAll();
        $suppliers = $this->pdo->query("SELECT id,name FROM suppliers ORDER BY name")->fetchAll();
        foreach ($produce as &$row) {
            $row['recommendation_data'] = (new SellRecommendationService($this->pdo))->recommendationData((int) $row['id']);
        }
        return compact('harvests', 'crops', 'produce', 'rules', 'items', 'suppliers');
    }

    public function approve($id) {
        $result = (new HarvestApproval($this->pdo))->approveData((int) $id, ['sub' => (int) $_SESSION['user_id']]);
        if ($result === null) throw new InvalidArgumentException('Harvest not found.');
        SessionHelper::setFlash('success', 'Harvest approved and added to stored produce.');
        header('Location: index.php?module=Insights&view=operations'); exit;
    }

    public function reject($id) {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') throw new InvalidArgumentException('A rejection reason is required.');
        $result = (new HarvestApproval($this->pdo))->rejectData((int) $id, ['sub' => (int) $_SESSION['user_id']], ['reason' => $reason]);
        if ($result === null) throw new InvalidArgumentException('Harvest not found.');
        SessionHelper::setFlash('success', 'Harvest rejected and the reason was recorded.');
        header('Location: index.php?module=Insights&view=operations'); exit;
    }

    public function recalculateYield($id) {
        $result = (new YieldPredictionService($this->pdo))->recalculateData((int) $id);
        if ($result === null) throw new InvalidArgumentException('Crop not found.');
        SessionHelper::setFlash('success', 'Yield prediction recalculated.');
        header('Location: index.php?module=Insights&view=operations'); exit;
    }

    public function createRule() {
        $item = (int) ($_POST['item_id'] ?? 0);
        $threshold = (int) ($_POST['threshold_qty'] ?? 0);
        $reorder = (int) ($_POST['reorder_qty'] ?? 0);
        if ($item < 1 || $threshold < 0 || $reorder < 1) throw new InvalidArgumentException('Choose an item and enter valid reorder quantities.');
        $supplier = !empty($_POST['preferred_supplier_id']) ? (int) $_POST['preferred_supplier_id'] : null;
        $this->pdo->prepare("INSERT INTO reorder_rules (item_id,threshold_qty,reorder_qty,preferred_supplier_id,auto_create_po) VALUES (?,?,?,?,?)")
            ->execute([$item, $threshold, $reorder, $supplier, !empty($_POST['auto_create_po']) ? 't' : 'f']);
        SessionHelper::setFlash('success', 'Reorder rule saved.');
        header('Location: index.php?module=Insights&view=operations'); exit;
    }

    public function runReorderCheck() {
        require_once __DIR__ . '/../../Intelligence/ReorderEngine.php';
        $engine = new ReorderEngine($this->pdo);
        $summary = $engine->runCronData();
        SessionHelper::setFlash('success', sprintf(
            'Reorder check completed: %d rule(s), %d low-stock alert(s), %d draft PO(s).',
            $summary['rules_evaluated'], $summary['below_threshold'], $summary['pos_created']
        ));
        header('Location: index.php?module=Insights&view=operations'); exit;
    }
}
