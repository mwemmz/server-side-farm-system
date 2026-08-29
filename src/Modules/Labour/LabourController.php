<?php
require_once __DIR__ . '/LabourModel.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Intelligence/HrService.php';

class LabourController {
    private $model;
    private $hr;

    public function __construct($pdo) {
        $this->model = new LabourModel($pdo);
        $this->hr = new HrService($pdo);
    }

    // Legacy: the original labour_records table (kept for backward compatibility).
    public function index() { return $this->model->getAll(); }

    // Public accessor to the HR service (used by the front controller's JSON endpoints).
    public function hrNew() { return $this->hr; }

    // ---- Human-verifiable employee count for the legacy redirect list ----
    public function legacyList() { return $this->model->getAll(); }

    // ---- Sub-section view data ---------------------------------------
    public function employeesView() {
        $deptId = isset($_GET['dept']) ? (int) $_GET['dept'] : null;
        return [
            'employees' => $this->hr->employees($deptId),
            'departments' => $this->hr->departments(),
        ];
    }
    public function departmentsView() {
        return ['departments' => $this->hr->departments()];
    }
    public function trainingView() {
        return ['training' => $this->hr->training(), 'employees' => $this->hr->employees()];
    }
    public function payrollView() {
        $records = $this->hr->payroll();
        // Attach a computed preview for the "new pay run" form default period.
        return [
            'payroll' => $records,
            'totals' => $this->hr->payrollTotals(),
            'employees' => $this->hr->employees(),
        ];
    }
    public function leaveView() {
        return [
            'balances' => $this->hr->leaveBalances(),
            'requests' => $this->hr->leaveRequests(),
            'pending' => $this->hr->leaveRequests(true),
            'upcoming' => $this->hr->upcomingLeave(30),
            'employees' => $this->hr->employees(),
        ];
    }
    public function shiftsView() {
        return [
            'shifts' => $this->hr->shifts(),
            'attendance' => $this->hr->attendance(),
            'employees' => $this->hr->employees(),
        ];
    }
    public function grievancesView() {
        return ['grievances' => $this->hr->grievances(), 'employees' => $this->hr->employees()];
    }
    public function dashboardView() {
        return $this->hr->stats();
    }

    // ---- POST handlers per sub-section (return true/error string) ----
    public function handleAdd($sub) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
        $d = $_POST;
        $name = trim($d['name'] ?? '');
        switch ($sub) {
            case 'employees':
                if ($name === '') return 'Employee name is required.';
                return $this->hr->addEmployee($d) ? true : 'Failed to add employee.';
            case 'departments':
                if ($name === '') return 'Department name is required.';
                return $this->hr->addDepartment($name, trim($d['description'] ?? '')) ? true : 'Failed to add department.';
            case 'training':
                if (empty($d['employee_id']) || empty(trim($d['course_name'] ?? ''))) return 'Employee and course name are required.';
                return $this->hr->addTraining($d) ? true : 'Failed to add training record.';
            case 'payroll':
                if (empty($d['employee_id'])) return 'Select an employee.';
                $emp = $this->hr->employee((int) $d['employee_id']);
                if (!$emp) return 'Employee not found.';
                $bonus = (float) ($d['bonus'] ?? 0);
                $advances = (float) ($d['advances'] ?? 0);
                $loans = (float) ($d['loans'] ?? 0);
                $gross = $this->hr->computeGross($emp, $d['work_days'] ?? 0, $d['work_units'] ?? 0, $d['overtime'] ?? 0);
                $finalGross = round($gross + $bonus, 2);
                $ded = $this->hr->statutoryDeductions($finalGross);
                $net = round($finalGross - $ded['napsa'] - $ded['paye'] - $ded['nhima'] - $advances - $loans, 2);
                return $this->hr->savePayroll($d, $finalGross, $ded, $net) ? true : 'Failed to save pay run.';
            case 'leave':
                if (empty($d['employee_id']) || empty($d['start_date']) || empty($d['end_date'])) return 'Employee and dates are required.';
                return $this->hr->requestLeave($d) ? true : 'Failed to submit leave request.';
            case 'shifts':
                if (empty($d['employee_id']) || empty($d['shift_date'])) return 'Employee and shift date are required.';
                return $this->hr->addShift($d) ? true : 'Failed to add shift.';
            case 'attendance':
                if (empty($d['employee_id']) || empty($d['work_date'])) return 'Employee and work date are required.';
                return $this->hr->addAttendance($d) ? true : 'Failed to record attendance.';
            case 'grievances':
                if (empty($d['employee_id']) || empty(trim($d['description'] ?? ''))) return 'Employee and description are required.';
                return $this->hr->addGrievance($d) ? true : 'Failed to log grievance.';
        }
        return null;
    }

    // ---- JSON-endpoints (used by live adds) --------------------------
    public function employeesJson() {
        $deptId = isset($_GET['dept']) ? (int) $_GET['dept'] : null;
        header('Content-Type: application/json');
        echo json_encode($this->hr->employees($deptId));
        exit;
    }

    // ---- Sub-action handlers (approve/reject resolve, mark paid) -----
    public function handleAction($sub, $action) {
        $officerId = (int) ($_SESSION['user_id'] ?? 0);
        switch ($sub . ':' . $action) {
            case 'leave:approve':
                return $this->hr->approveLeave((int) $_GET['id'], $officerId);
            case 'leave:reject':
                return $this->hr->rejectLeave((int) $_GET['id'], $officerId);
            case 'grievances:resolve':
                return $this->hr->resolveGrievance((int) $_GET['id'], trim($_POST['resolution_notes'] ?? 'Resolved'));
            case 'payroll:paid':
                return $this->hr->markPaid((int) $_GET['id']);
        }
        return null;
    }
}
