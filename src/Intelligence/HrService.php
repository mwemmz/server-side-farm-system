<?php
// HR / Labour Management service — data access + Zambia-aligned payroll & leave logic.
// Statutory deductions per Zambia (Employment Code Act 2019 + tax acts):
//   - NAPSA  (pension)  : 5% of gross (employee share)
//   - NHIMA  (health)   : 1% of gross (capped at a ceiling)
//   - PAYE   (income tax): progressive annual bands against annualised gross
// Leave entitlements per the Employment Code Act:
//   - Annual   : 24 working days after 12 months' continuous service
//   - Sick     : 26 working days per 12-month cycle
//   - Maternity: 14 weeks fully paid (2022 amendment)
//   - Paternity: 10 days paid
class HrService {
    private $pdo;

    public function __construct($pdo) { $this->pdo = $pdo; }

    // ---- Departments -------------------------------------------------
    public function departments() {
        return $this->pdo->query("SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) AS headcount FROM departments d ORDER BY d.name")->fetchAll();
    }
    public function addDepartment($name, $desc) {
        $st = $this->pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
        return $st->execute([$name, $desc]);
    }

    // ---- Employees ---------------------------------------------------
    public function employees($deptId = null) {
        $sql = "SELECT e.*, d.name AS department FROM employees e LEFT JOIN departments d ON d.id = e.department_id";
        $params = [];
        if ($deptId) { $sql .= " WHERE e.department_id = ?"; $params[] = (int) $deptId; }
        $sql .= " ORDER BY e.name";
        $st = $this->pdo->prepare($sql); $st->execute($params);
        return $st->fetchAll();
    }
    public function employee($id) {
        $st = $this->pdo->prepare("SELECT e.*, d.name AS department FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ?");
        $st->execute([$id]); return $st->fetch();
    }
    public function addEmployee($d) {
        // Generate emp_no if not provided.
        if (empty($d['emp_no'])) {
            $d['emp_no'] = 'EMP-' . strtoupper(substr(base_convert(mt_rand(), 10, 36), 0, 5));
        }
        $sql = "INSERT INTO employees (emp_no, name, job_title, department_id, employment_status, contract_type, hire_date, documents, pay_type, monthly_salary, daily_rate, piece_rate, farm_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st = $this->pdo->prepare($sql);
        $ok = $st->execute([
            $d['emp_no'], $d['name'], $d['job_title'] ?? null, ($d['department_id'] ?: null),
            $d['employment_status'] ?? 'active', $d['contract_type'] ?? 'permanent', ($d['hire_date'] ?: null),
            $d['documents'] ?? null, $d['pay_type'] ?? 'monthly', (float) ($d['monthly_salary'] ?? 0),
            (float) ($d['daily_rate'] ?? 0), (float) ($d['piece_rate'] ?? 0), ($d['farm_id'] ?: null)
        ]);
        if ($ok) {
            $eid = (int) $this->pdo->lastInsertId('employees_id_seq');
            // Seed default leave balances for the new employee.
            $this->seedLeaveBalances($eid);
            return $eid;
        }
        return false;
    }
    public function updateEmployee($id, $d) {
        $st = $this->pdo->prepare(
            "UPDATE employees SET name=?, job_title=?, department_id=?, employment_status=?, contract_type=?, hire_date=?,
             documents=?, pay_type=?, monthly_salary=?, daily_rate=?, piece_rate=? WHERE id=?");
        return $st->execute([
            $d['name'], $d['job_title'] ?? null, ($d['department_id'] ?: null), $d['employment_status'] ?? 'active',
            $d['contract_type'] ?? 'permanent', ($d['hire_date'] ?: null), $d['documents'] ?? null,
            $d['pay_type'] ?? 'monthly', (float) ($d['monthly_salary'] ?? 0), (float) ($d['daily_rate'] ?? 0),
            (float) ($d['piece_rate'] ?? 0), $id
        ]);
    }
    public function deleteEmployee($id) {
        $st = $this->pdo->prepare("DELETE FROM employees WHERE id = ?"); return $st->execute([$id]);
    }
    public function seedLeaveBalances($empId) {
        // Leave entitlements per the Employment Code Act (annual/sick in working
        // days; maternity 14 weeks; paternity 10 days paid).
        $defaults = ['annual' => 24, 'sick' => 26, 'maternity' => 98, 'paternity' => 10, 'unpaid' => 0];
        foreach ($defaults as $t => $total) {
            $st = $this->pdo->prepare(
                "INSERT INTO leave_balances (employee_id, leave_type, total_days, used_days)
                 VALUES (?, ?, ?, 0) ON CONFLICT (employee_id, leave_type) DO NOTHING");
            $st->execute([$empId, $t, $total]);
        }
    }

    // ---- Training ----------------------------------------------------
    public function training() {
        return $this->pdo->query(
            "SELECT t.*, e.name AS employee_name, e.emp_no FROM training_records t
             LEFT JOIN employees e ON e.id = t.employee_id ORDER BY t.completion_date DESC NULLS LAST"
        )->fetchAll();
    }
    public function addTraining($d) {
        $st = $this->pdo->prepare(
            "INSERT INTO training_records (employee_id, course_name, provider, completion_date, status, certified)
             VALUES (?,?,?,?,?,?)");
        return $st->execute([
            $d['employee_id'], $d['course_name'], $d['provider'] ?? null, ($d['completion_date'] ?: null),
            $d['status'] ?? 'completed', isset($d['certified']) ? true : false
        ]);
    }

    // ---- Leaves ------------------------------------------------------
    public function leaveBalances() {
        return $this->pdo->query(
            "SELECT lb.*, e.name AS employee_name, e.emp_no, e.contract_type FROM leave_balances lb
             LEFT JOIN employees e ON e.id = lb.employee_id ORDER BY e.name"
        )->fetchAll();
    }
    public function leaveRequests($onlyPending = false) {
        $sql = "SELECT lr.*, e.name AS employee_name, e.emp_no FROM leave_requests lr LEFT JOIN employees e ON e.id = lr.employee_id";
        if ($onlyPending) { $sql .= " WHERE lr.status = 'pending'"; }
        $sql .= " ORDER BY lr.created_at DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
    public function requestLeave($d) {
        $st = $this->pdo->prepare(
            "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, reason, status)
             VALUES (?,?,?,?,?,?,'pending')");
        return $st->execute([
            $d['employee_id'], $d['leave_type'], $d['start_date'], $d['end_date'],
            (int) $d['days'], $d['reason'] ?? null
        ]);
    }
    public function approveLeave($id, $officerId) {
        $this->pdo->prepare("UPDATE leave_requests SET status='approved', approved_by=?, approved_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$officerId, $id]);
        // Reduce the employee's leave balance.
        $lr = $this->pdo->prepare("SELECT employee_id, leave_type, days FROM leave_requests WHERE id=?");
        $lr->execute([$id]); $row = $lr->fetch();
        if ($row) {
            $this->pdo->prepare("UPDATE leave_balances SET used_days = used_days + ? WHERE employee_id=? AND leave_type=?")
                ->execute([(int) $row['days'], $row['employee_id'], $row['leave_type']]);
        }
        return true;
    }
    public function rejectLeave($id, $officerId) {
        return $this->pdo->prepare("UPDATE leave_requests SET status='rejected', approved_by=?, approved_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$officerId, $id]);
    }
    public function upcomingLeave($daysAhead = 30) {
        $days = (int) $daysAhead;
        return $this->pdo->query(
            "SELECT lr.*, e.name AS employee_name, e.emp_no FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             WHERE lr.status='approved' AND lr.start_date BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '$days DAY')
             ORDER BY lr.start_date"
        )->fetchAll();
    }

    // ---- Shifts & Attendance -----------------------------------------
    public function shifts() {
        return $this->pdo->query(
            "SELECT s.*, e.name AS employee_name FROM shift_schedules s
             LEFT JOIN employees e ON e.id = s.employee_id ORDER BY s.shift_date DESC, s.start_time"
        )->fetchAll();
    }
    public function addShift($d) {
        $st = $this->pdo->prepare(
            "INSERT INTO shift_schedules (employee_id, shift_date, start_time, end_time, shift_type, status)
             VALUES (?,?,?,?,?,?)");
        return $st->execute([
            $d['employee_id'], $d['shift_date'], ($d['start_time'] ?: null), ($d['end_time'] ?: null),
            $d['shift_type'] ?? null, $d['status'] ?? 'scheduled'
        ]);
    }
    public function attendance() {
        return $this->pdo->query(
            "SELECT a.*, e.name AS employee_name FROM attendance_records a
             LEFT JOIN employees e ON e.id = a.employee_id ORDER BY a.work_date DESC"
        )->fetchAll();
    }
    public function addAttendance($d, $hours = null) {
        $st = $this->pdo->prepare(
            "INSERT INTO attendance_records (employee_id, work_date, clock_in, clock_out, hours) VALUES (?,?,?,?,?)");
        $h = $hours;
        if ($h === null && !empty($d['clock_in']) && !empty($d['clock_out'])) {
            $h = $this->diffHours($d['clock_in'], $d['clock_out']);
        }
        return $st->execute([
            $d['employee_id'], $d['work_date'], ($d['clock_in'] ?: null), ($d['clock_out'] ?: null), $h ?: 0
        ]);
    }
    private function diffHours($in, $out) {
        try {
            $a = new DateTime($in); $b = new DateTime($out);
            $int = $a->diff($b);
            return round(($int->h + $int->i / 60), 2);
        } catch (Exception $e) { return 0; }
    }

    // ---- Grievances --------------------------------------------------
    public function grievances() {
        return $this->pdo->query(
            "SELECT g.*, e.name AS employee_name FROM grievances g
             LEFT JOIN employees e ON e.id = g.employee_id ORDER BY g.created_at DESC"
        )->fetchAll();
    }
    public function addGrievance($d) {
        $st = $this->pdo->prepare(
            "INSERT INTO grievances (employee_id, category, description, status) VALUES (?,?,?,?)");
        return $st->execute([$d['employee_id'], $d['category'] ?? 'general', $d['description'] ?? null, $d['status'] ?? 'open']);
    }
    public function resolveGrievance($id, $notes) {
        return $this->pdo->prepare("UPDATE grievances SET status='resolved', resolution_notes=?, resolved_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$notes, $id]);
    }

    // ---- Payroll -----------------------------------------------------
    public function payroll() {
        return $this->pdo->query(
            "SELECT p.*, e.name AS employee_name, e.emp_no, e.pay_type FROM payroll_records p
             LEFT JOIN employees e ON e.id = p.employee_id ORDER BY p.period_end DESC NULLS LAST"
        )->fetchAll();
    }
    public function payrollTotals() {
        return $this->pdo->query(
            "SELECT COALESCE(SUM(gross_pay),0) AS gross, COALESCE(SUM(net_pay),0) AS net,
                    COALESCE(SUM(napsa),0) AS napsa, COALESCE(SUM(paye),0) AS paye, COALESCE(SUM(nhima),0) AS nhima,
                    COALESCE(SUM(overtime),0) AS overtime FROM payroll_records WHERE status='paid'"
        )->fetch();
    }
    // Compute gross pay for an employee for a period (monthly / daily / piece-rate).
    public function computeGross($emp, $workDays, $workUnits, $overtime) {
        $payType = $emp['pay_type'] ?? 'monthly';
        $gross = 0;
        if ($payType === 'monthly') {
            $gross = (float) ($emp['monthly_salary'] ?? 0);
        } elseif ($payType === 'daily') {
            $gross = (float) ($emp['daily_rate'] ?? 0) * max(0, (int) $workDays);
        } else { // piece-rate
            $gross = (float) ($emp['piece_rate'] ?? 0) * max(0, (float) $workUnits);
        }
        return round($gross + (float) $overtime, 2);
    }
    // Zambia statutory deductions from gross.
    public function statutoryDeductions($gross) {
        $napsa = round($gross * 0.05, 2);                       // 5% pension
        $nhima = round(min($gross, 100000) * 0.01, 2);          // 1% health (subject to ceiling)
        $taxable = $gross - $napsa;                              // PAYE base
        $paye = $this->payeTax($taxable);                        // progressive
        return ['napsa' => $napsa, 'nhima' => $nhima, 'paye' => $paye];
    }
    // Provisional monthly PAYE bands (annualised -> monthly). 2024/25 style bands.
    private function payeTax($monthly) {
        $annual = $monthly * 12;
        $tax = 0;
        // Progressive annual income-tax bands.
        if ($annual > 156000) { $tax += ($annual - 156000) * 0.375; $annual = 156000; }
        if ($annual > 132000) { $tax += ($annual - 132000) * 0.325; $annual = 132000; }
        if ($annual > 102000) { $tax += ($annual - 102000) * 0.30;  $annual = 102000; }
        if ($annual > 72000)  { $tax += ($annual - 72000)  * 0.25;  $annual = 72000; }
        if ($annual > 42000)  { $tax += ($annual - 42000)  * 0.20;  $annual = 42000; }
        if ($annual > 12000)  { $tax += ($annual - 12000)  * 0.10;  $annual = 12000; }
        return round($tax / 12, 2);
    }
    // Persist a computed pay run.
    public function savePayroll($d, $gross, $ded, $net) {
        $st = $this->pdo->prepare(
            "INSERT INTO payroll_records (employee_id, period_start, period_end, gross_pay, overtime, bonus, advances, loans,
                                          napsa, paye, nhima, net_pay, payment_method, status, payment_date)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        return $st->execute([
            $d['employee_id'], ($d['period_start'] ?: null), ($d['period_end'] ?: null),
            $gross, (float) ($d['overtime'] ?? 0), (float) ($d['bonus'] ?? 0),
            (float) ($d['advances'] ?? 0), (float) ($d['loans'] ?? 0),
            $ded['napsa'], $ded['paye'], $ded['nhima'], $net,
            $d['payment_method'] ?? 'bank', $d['status'] ?? 'draft', ($d['payment_date'] ?: null)
        ]);
    }
    public function markPaid($id) {
        return $this->pdo->prepare("UPDATE payroll_records SET status='paid', payment_date=COALESCE(payment_date, CURRENT_DATE) WHERE id=?")
            ->execute([$id]);
    }

    // ---- Dashboard stats ---------------------------------------------
    public function stats() {
        $active = (int) $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
        $total  = (int) $this->pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $departments = (int) $this->pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $pendingLeave = (int) $this->pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
        $pendingGriev = (int) $this->pdo->query("SELECT COUNT(*) FROM grievances WHERE status IN ('open','in_progress')")->fetchColumn();
        $pay = $this->payrollTotals();
        $onLeaveToday = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM leave_requests WHERE status='approved' AND CURRENT_DATE BETWEEN start_date AND end_date"
        )->fetchColumn();
        return [
            'active' => $active, 'total' => $total, 'inactive' => max(0, $total - $active),
            'departments' => $departments, 'pending_leave' => $pendingLeave, 'pending_grievances' => $pendingGriev,
            'on_leave_today' => $onLeaveToday,
            'gross_paid' => (float) $pay['gross'], 'net_paid' => (float) $pay['net'],
            'napsa' => (float) $pay['napsa'], 'paye' => (float) $pay['paye'], 'nhima' => (float) $pay['nhima'],
        ];
    }
}
