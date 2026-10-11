<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class FinanceModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'finance_records'); }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM finance_records ORDER BY date DESC, id DESC")->fetchAll();
    }
}
?>
