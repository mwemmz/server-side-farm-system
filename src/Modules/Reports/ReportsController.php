<?php
require_once __DIR__ . '/ReportsModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class ReportsController {
    private $model;
    private $pdo;

    /**
     * Whitelisted export datasets: key => [label, SQL].
     * Table/column names are trusted constants; dataset values never reach SQL.
     */
    private const DATASETS = [
        // Core records
        'crops'         => ['Crops', 'SELECT id, farm_id, name, variety, planting_date, expected_harvest_date FROM crops ORDER BY id'],
        'fields'        => ['Fields', 'SELECT id, farm_id, name, size, soil_type FROM fields ORDER BY id'],
        'livestock'     => ['Livestock', 'SELECT id, farm_id, type, breed, dob FROM livestock ORDER BY id'],
        'harvests'      => ['Harvests', 'SELECT id, crop_id, harvest_date, quantity, quality FROM harvest_records ORDER BY id'],
        'inventory'     => ['Inventory', 'SELECT id, name, quantity, unit FROM inventory ORDER BY id'],
        'finance'       => ['Finance Records', 'SELECT id, type, amount, description, date FROM finance_records ORDER BY id'],
        'sales'         => ['Sales', 'SELECT id, customer_name, amount, sale_date FROM sales_records ORDER BY id'],
        'procurement'   => ['Procurement', 'SELECT id, supplier_id, item_name, quantity, cost, purchase_date FROM procurement_records ORDER BY id'],
        'pest'          => ['Health Incidents', 'SELECT id, farm_id, subject_type, issue_name, issue_kind, severity, detected_date, status FROM health_incidents ORDER BY id'],
        'suppliers'     => ['Suppliers', 'SELECT id, name, contact_info FROM suppliers ORDER BY id'],
        'employees'     => ['Employees', 'SELECT id, emp_no, name, job_title, department_id, employment_status, hire_date FROM employees ORDER BY id'],
        'leave'         => ['Leave Requests', 'SELECT id, employee_id, leave_type, start_date, end_date, days, status FROM leave_requests ORDER BY id'],
        'loans'         => ['Loans', 'SELECT id, lender, principal, interest_rate, balance, due_date, status FROM loans ORDER BY id'],
        'budgets'       => ['Budgets', 'SELECT id, category, period, planned_amount FROM budgets ORDER BY id'],

        // Field & land depth
        'soil'          => ['Soil Conditions', 'SELECT id, field_id, sample_date, ph, organic_matter_pct, nitrogen_ppm, phosphorus_ppm, potassium_ppm, moisture_pct, texture FROM soil_conditions ORDER BY sample_date DESC, id'],
        'field_history' => ['Field History / Rotation', 'SELECT id, field_id, crop_id, crop_name, season, start_date, end_date, yield_quantity, yield_unit FROM field_history ORDER BY start_date DESC NULLS LAST, id'],
        'land_use'      => ['Land-Use Plans', 'SELECT id, farm_id, field_id, planned_crop, season, planned_area_ha, start_date, end_date, status FROM land_use_plans ORDER BY start_date DESC NULLS LAST, id'],

        // Crop production & protection
        'seed'          => ['Seed Records', 'SELECT id, crop_id, seed_name, variety, source, lot_number, quantity, unit, unit_cost, total_cost, received_date, expiry_date FROM seed_records ORDER BY received_date DESC NULLS LAST, id'],
        'planting'      => ['Planting Records', 'SELECT id, crop_id, field_id, planting_date, area_ha, planting_density, spacing_cm, method FROM planting_records ORDER BY planting_date DESC, id'],
        'fertilizer'    => ['Fertilizer Applications', 'SELECT id, crop_id, field_id, application_date, product, application_type, quantity, unit, method, cost FROM fertilizer_applications ORDER BY application_date DESC, id'],
        'growth'        => ['Crop Growth Stages', 'SELECT id, crop_id, stage, stage_date, notes FROM crop_growth_stages ORDER BY stage_date DESC, id'],
        'sprays'        => ['Spray Schedules', 'SELECT id, crop_id, field_id, planned_date, product, target, dose, unit, status FROM spray_schedules ORDER BY planned_date DESC, id'],
        'chemicals'     => ['Chemical Applications', 'SELECT id, crop_id, field_id, application_date, product, quantity, unit, cost FROM chemical_applications ORDER BY application_date DESC, id'],
        'followups'     => ['Treatment Follow-ups', 'SELECT id, health_incident_id, followup_date, effectiveness, status, notes FROM treatment_followups ORDER BY followup_date DESC, id'],

        // Livestock production
        'weights'       => ['Animal Weights', 'SELECT id, livestock_id, weight_kg, measured_date, notes FROM animal_weights ORDER BY measured_date DESC, id'],
        'breeding'      => ['Breeding Records', 'SELECT id, livestock_id, sire_id, service_date, method, pregnancy_check_date, result, expected_birth_date, actual_birth_date, offspring_count FROM breeding_records ORDER BY service_date DESC, id'],
        'production'    => ['Production Records', 'SELECT id, livestock_id, production_type, quantity, unit, record_date, quality FROM production_records ORDER BY record_date DESC, id'],
        'mortality'     => ['Mortality Records', 'SELECT id, livestock_id, death_date, cause, diagnosis, notes FROM mortality_records ORDER BY death_date DESC, id'],
        'feed'          => ['Feed Records', 'SELECT id, livestock_id, feed_date, feed_name, quantity, unit, cost FROM feed_records ORDER BY feed_date DESC, id'],

        // Equipment costing
        'equipment_allocations' => ['Equipment Allocations', 'SELECT id, equipment_id, field_id, employee_id, assigned_date, released_date FROM equipment_allocations ORDER BY assigned_date DESC, id'],
        'fuel'          => ['Fuel Records', 'SELECT id, equipment_id, fuel_date, litres, cost, odometer FROM fuel_records ORDER BY fuel_date DESC, id'],
        'equipment_hours' => ['Equipment Operating Hours', 'SELECT id, equipment_id, work_date, hours, operator_id FROM equipment_hours ORDER BY work_date DESC, id'],
        'repairs'       => ['Equipment Repairs', 'SELECT id, equipment_id, repair_date, description, cost, downtime_days, vendor FROM equipment_repairs ORDER BY repair_date DESC, id'],
        'depreciation'  => ['Equipment Depreciation', 'SELECT id, equipment_id, method, purchase_value, salvage_value, useful_life_years, annual_depreciation, as_of_date FROM equipment_depreciation ORDER BY as_of_date DESC, id'],

        // Labour / tasks
        'tasks'         => ['Daily Tasks', 'SELECT id, title, employee_id, field_id, crop_id, livestock_id, equipment_id, due_date, priority, status, estimated_hours, actual_hours, labour_cost FROM tasks ORDER BY due_date DESC NULLS LAST, id'],

        // Harvest, quality & storage
        'harvest_schedules' => ['Harvest Schedules', 'SELECT id, crop_id, field_id, expected_date, expected_quantity, unit, status FROM harvest_schedules ORDER BY expected_date DESC, id'],
        'harvest_losses' => ['Harvest Losses', 'SELECT id, harvest_id, loss_date, quantity, unit, cause FROM harvest_losses ORDER BY loss_date DESC, id'],
        'packaging'     => ['Packaging Records', 'SELECT id, harvest_id, packaging_date, package_type, units, unit_weight_kg, cost FROM packaging_records ORDER BY packaging_date DESC, id'],
        'quality'       => ['Quality Grades', 'SELECT id, harvest_id, grade_date, grade, moisture_pct, defects_pct FROM quality_grades ORDER BY grade_date DESC, id'],
        'dispatches'    => ['Produce Dispatches', 'SELECT id, harvest_id, stored_produce_id, customer_id, dispatch_date, quantity, unit, destination, vehicle FROM produce_dispatches ORDER BY dispatch_date DESC, id'],

        // Market, sales & invoicing
        'customers'     => ['Customers', 'SELECT id, name, customer_type, phone, email, address FROM customers ORDER BY name'],
        'markets'       => ['Market Destinations', 'SELECT id, name, location, market_type, distance_km FROM market_destinations ORDER BY name'],
        'sales_orders'  => ['Sales Orders', 'SELECT id, customer_id, order_date, delivery_date, status, total_amount FROM sales_orders ORDER BY order_date DESC, id'],
        'sales_order_items' => ['Sales Order Items', 'SELECT id, order_id, crop_id, description, quantity, unit, unit_price, amount FROM sales_order_items ORDER BY id'],
        'invoices'      => ['Invoices', 'SELECT id, invoice_no, customer_id, invoice_date, due_date, total_amount, amount_paid, status FROM invoices ORDER BY invoice_date DESC, id'],
        'invoice_items' => ['Invoice Items', 'SELECT id, invoice_id, description, quantity, unit_price, amount FROM invoice_items ORDER BY id'],
        'contracts'     => ['Contracts', 'SELECT id, contract_type, party_name, customer_id, supplier_id, title, start_date, end_date, value, status FROM contracts ORDER BY end_date DESC NULLS LAST, id'],

        // Supplier depth
        'quotations'    => ['Supplier Quotations', 'SELECT id, supplier_id, item_name, quantity, unit, unit_price, quote_date, valid_until, status FROM supplier_quotations ORDER BY quote_date DESC, id'],
        'reviews'       => ['Supplier Reviews', 'SELECT id, supplier_id, review_date, quality_score, delivery_score, price_score, reliability_score FROM supplier_reviews ORDER BY review_date DESC, id'],
    ];

    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new ReportsModel($pdo); }
    public function index() { return $this->model->getAll(); }

    /** key => human label for the export picker (no SQL exposed). */
    public static function exportSets() {
        $out = [];
        foreach (self::DATASETS as $key => $meta) {
            $out[$key] = $meta[0];
        }
        return $out;
    }

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
        if (!isset(self::DATASETS[$key])) return null;
        try {
            return $this->pdo->query(self::DATASETS[$key][1])->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /** Stream a dataset as a CSV download. */
    public function export() {
        $key = $_GET['dataset'] ?? '';
        if (!isset(self::DATASETS[$key])) {
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
        if (!isset(self::DATASETS[$key])) {
            http_response_code(400);
            echo "Unknown dataset.";
            exit;
        }
        $rows = $this->datasetRows($key) ?: [];
        $label = self::DATASETS[$key][0];
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
