<?php
require_once __DIR__ . '/ReportsModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class ReportsController {
    private $model;
    private $pdo;

    /** Whitelisted export datasets: key => [label, SQL]. */
    private $datasets = [
        'crops'         => ['Crops', 'SELECT id, farm_id, name, variety, planting_date, expected_harvest_date FROM crops ORDER BY id'],
        'fields'        => ['Fields', 'SELECT id, farm_id, name, size, soil_type FROM fields ORDER BY id'],
        'livestock'     => ['Livestock', 'SELECT id, farm_id, type, breed, dob FROM livestock ORDER BY id'],
        'harvests'      => ['Harvests', 'SELECT id, crop_id, harvest_date, quantity, quality FROM harvest_records ORDER BY id'],
        'inventory'     => ['Inventory', 'SELECT id, name, quantity, unit FROM inventory ORDER BY id'],
        'finance'       => ['Finance Records', 'SELECT id, type, amount, description, date FROM finance_records ORDER BY id'],
        'sales'         => ['Sales', 'SELECT id, customer_name, amount, sale_date FROM sales_records ORDER BY id'],
        'procurement'   => ['Procurement', 'SELECT id, supplier_id, item_name, quantity, cost, purchase_date FROM procurement_records ORDER BY id'],
        'pest'          => ['Pest Records', 'SELECT id, farm_id, pest_name, detected_date, action_taken FROM pest_records ORDER BY id'],
        'suppliers'     => ['Suppliers', 'SELECT id, name, contact_info FROM suppliers ORDER BY id'],
        'employees'     => ['Employees', 'SELECT id, emp_no, name, job_title, department_id, employment_status, hire_date FROM employees ORDER BY id'],
        'leave'         => ['Leave Requests', 'SELECT id, employee_id, leave_type, start_date, end_date, days, status FROM leave_requests ORDER BY id'],
        'loans'         => ['Loans', 'SELECT id, lender, principal, interest_rate, balance, due_date, status FROM loans ORDER BY id'],
        'budgets'       => ['Budgets', 'SELECT id, category, period, planned_amount FROM budgets ORDER BY id'],
    ];

    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new ReportsModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['report_type', 'data'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Report generated successfully!');
                    header('Location: /index.php?module=Reports&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to generate report.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    /** Fetch rows for a whitelisted dataset. */
    private function datasetRows($key) {
        if (!isset($this->datasets[$key])) return null;
        try {
            return $this->pdo->query($this->datasets[$key][1])->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /** Stream a dataset as a CSV download. */
    public function export() {
        $key = $_GET['dataset'] ?? '';
        if (!isset($this->datasets[$key])) {
            http_response_code(400);
            echo "Unknown dataset.";
            exit;
        }
        $rows = $this->datasetRows($key) ?: [];
        $filename = 'ffms_' . $key . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens it correctly.
        fwrite($out, "\xEF\xBB\xBF");
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) { fputcsv($out, $r); }
        } else {
            fputcsv($out, ['No records']);
        }
        fclose($out);
        exit;
    }

    /** Render a dataset as a standalone printable page (Save as PDF via browser). */
    public function printable() {
        $key = $_GET['dataset'] ?? '';
        if (!isset($this->datasets[$key])) {
            http_response_code(400);
            echo "Unknown dataset.";
            exit;
        }
        $rows = $this->datasetRows($key) ?: [];
        $label = $this->datasets[$key][0];
        $title = 'FFMS ' . $label . ' Report';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($title); ?></title>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: #1e293b; margin: 32px; }
  h1 { color: #166534; font-size: 22px; margin: 0 0 4px; }
  .meta { color: #64748b; font-size: 12px; margin-bottom: 18px; }
  table { border-collapse: collapse; width: 100%; font-size: 12px; }
  th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
  th { background: #f0fdf4; color: #166534; text-transform: uppercase; font-size: 10px; letter-spacing: .04em; }
  tr:nth-child(even) td { background: #f8fafc; }
  .toolbar { margin-bottom: 16px; }
  button { background: #16a34a; color: #fff; border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 600; cursor: pointer; font-size: 13px; }
  @media print { .toolbar { display: none; } body { margin: 0; } }
</style>
</head>
<body>
  <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
  <h1><?php echo htmlspecialchars($title); ?></h1>
  <div class="meta">Generated <?php echo htmlspecialchars(date('Y-m-d H:i')); ?> · <?php echo count($rows); ?> record(s)</div>
  <?php if (!empty($rows)): ?>
  <table>
    <thead><tr><?php foreach (array_keys($rows[0]) as $col): ?><th><?php echo htmlspecialchars(str_replace('_', ' ', $col)); ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><?php foreach ($r as $v): ?><td><?php echo htmlspecialchars((string) $v); ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p>No records found for this dataset.</p>
  <?php endif; ?>
</body>
</html>
        <?php
        exit;
    }
}
?>
