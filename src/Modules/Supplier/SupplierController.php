<?php
require_once __DIR__ . '/SupplierModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../../Helpers/UndoHelper.php';

class SupplierController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new SupplierModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['name'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['name', 'category', 'phone', 'contact_info']));
                $row['name'] = trim($row['name']);
                foreach (['category', 'phone', 'contact_info'] as $k) { if (isset($row[$k]) && trim($row[$k]) === '') $row[$k] = null; }
                if ($this->model->create($row)) {
                    SessionHelper::setFlash('success', 'Supplier added successfully!');
                    SessionHelper::flashCreated('Supplier', $this->model->lastId(), trim($data['name']));
                    header('Location: /index.php?module=Supplier&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add supplier.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
        $id = ValidationHelper::id($_POST['id'] ?? '');
        if (!$id) { SessionHelper::setFlash('error', 'Invalid supplier.'); header('Location: index.php?module=Supplier&action=manage'); exit; }
        foreach (['procurement_records' => 'supplier_id', 'supplier_quotations' => 'supplier_id', 'supplier_reviews' => 'supplier_id', 'contracts' => 'supplier_id'] as $table => $col) {
            try {
                $st = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = ?");
                $st->execute([$id]);
                if ((int)$st->fetchColumn() > 0) {
                    SessionHelper::setFlash('error', "Cannot delete supplier: it has records in {$table}. Remove those first.");
                    header('Location: index.php?module=Supplier&action=manage'); exit;
                }
            } catch (PDOException $e) { /* table may not exist */ }
        }
        try {
            $st = $this->pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $st->execute([$id]);
            $rowData = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
            AuditHelper::log($this->pdo, 'human', 'supplier_deleted', 'suppliers', $id, [], (int)($_SESSION['user_id'] ?? 0));
            $this->pdo->commit();
            UndoHelper::stash($this->pdo, 'suppliers', $rowData, 'Supplier', 'index.php?module=Supplier&action=manage');
            SessionHelper::setFlash('success', 'Supplier deleted.');
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            SessionHelper::setFlash('error', 'Could not delete supplier (it may be referenced elsewhere).');
        }
        header('Location: index.php?module=Supplier&action=manage'); exit;
    }}
?>
