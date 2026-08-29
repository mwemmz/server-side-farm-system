-- Phase 5 Intelligence-layer tables.
-- All CREATEs are idempotent (IF NOT EXISTS). Migrations that add columns to
-- existing tables use ADD COLUMN IF NOT EXISTS so they can run on every boot.
-- This file duplicates nothing from the Phase 1-4 schema.sql; it builds on it.

-- ============================================================
-- Feature 1 — Inventory Reorder Engine
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_movements (
    id SERIAL PRIMARY KEY,
    item_id INT REFERENCES inventory(id) ON DELETE CASCADE,
    direction VARCHAR(10) NOT NULL CHECK (direction IN ('in', 'out')),
    quantity INT NOT NULL,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reorder_rules (
    id SERIAL PRIMARY KEY,
    item_id INT NOT NULL REFERENCES inventory(id) ON DELETE CASCADE,
    threshold_qty INT NOT NULL DEFAULT 10,
    reorder_qty INT NOT NULL DEFAULT 20,
    preferred_supplier_id INT REFERENCES suppliers(id),
    auto_create_po BOOLEAN DEFAULT false
);

CREATE TABLE IF NOT EXISTS purchase_orders (
    id SERIAL PRIMARY KEY,
    item_id INT REFERENCES inventory(id),
    supplier_id INT REFERENCES suppliers(id),
    quantity INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT REFERENCES users(id),
    approved_at TIMESTAMP
);

-- ============================================================
-- Feature 2 — Weather-driven irrigation recommendations
-- ============================================================
ALTER TABLE crops ADD COLUMN IF NOT EXISTS water_need_mm_per_week DECIMAL(8,2) DEFAULT 35.00;
ALTER TABLE weather_records ADD COLUMN IF NOT EXISTS rainfall_mm DECIMAL(6,2) DEFAULT 0.00;

CREATE TABLE IF NOT EXISTS irrigation_recommendations (
    id SERIAL PRIMARY KEY,
    field_id INT NOT NULL REFERENCES fields(id) ON DELETE CASCADE,
    recommended_date DATE,
    recommended_liters DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','accepted','dismissed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS irrigation_schedules (
    id SERIAL PRIMARY KEY,
    field_id INT REFERENCES fields(id),
    schedule_date DATE,
    liters DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Feature 3 — Real yield prediction
-- ============================================================
CREATE TABLE IF NOT EXISTS scouting_reports (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id),
    report_date DATE,
    pest_name VARCHAR(100),
    -- severity as a fraction 0..1 (1 = catastrophic pest pressure)
    severity DECIMAL(4,2) DEFAULT 0 CHECK (severity BETWEEN 0 AND 1),
    notes TEXT
);

CREATE TABLE IF NOT EXISTS yield_predictions (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id),
    predicted_yield DECIMAL(10,2),
    inputs_json TEXT,
    predicted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Feature 4 — Sell-now-vs-wait recommendation
-- ============================================================
CREATE TABLE IF NOT EXISTS stored_produce (
    id SERIAL PRIMARY KEY,
    harvest_id INT REFERENCES harvest_records(id),
    crop_id INT REFERENCES crops(id),
    quantity DECIMAL(10,2) DEFAULT 0,
    grade VARCHAR(50),
    storage_facility_id INT REFERENCES storage_records(id),
    storage_start_date DATE DEFAULT CURRENT_DATE,
    is_in_storage BOOLEAN DEFAULT true
);

CREATE TABLE IF NOT EXISTS sell_recommendations (
    id SERIAL PRIMARY KEY,
    stored_produce_id INT REFERENCES stored_produce(id) ON DELETE CASCADE,
    recommendation VARCHAR(20) NOT NULL CHECK (recommendation IN ('sell_now','wait')),
    expected_price_now DECIMAL(10,2),
    expected_price_future DECIMAL(10,2),
    spoilage_risk_pct DECIMAL(5,2),
    reasoning TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Feature 5 — Harvest approval workflow
-- ============================================================
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'submitted'
    CHECK (status IN ('submitted','approved','rejected'));
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS approved_by INT REFERENCES users(id);
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP;
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS rejection_reason TEXT;

-- ============================================================
-- Shared — Audit logging (system-vs-human)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    source VARCHAR(20) NOT NULL DEFAULT 'human' CHECK (source IN ('human','system')),
    action VARCHAR(255),
    entity_type VARCHAR(100),
    entity_id INT,
    details TEXT,
    user_id INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Phase 7 — Assistant chat memory (per-user, full history)
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) DEFAULT 'New chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
    role VARCHAR(10) NOT NULL CHECK (role IN ('user','assistant')),
    message TEXT NOT NULL,
    cards_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_chat_messages_session ON chat_messages(session_id);
CREATE INDEX IF NOT EXISTS idx_chat_sessions_user ON chat_sessions(user_id);

-- ============================================================
-- Phase 8 — HR / Labour Management (sub-sectioned)
-- Zambia Employment Code Act 2019 aligned (leave, statutory deductions).
-- ============================================================
CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    emp_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    job_title VARCHAR(150),
    department_id INT REFERENCES departments(id),
    employment_status VARCHAR(20) DEFAULT 'active' CHECK (employment_status IN ('active','inactive')),
    contract_type VARCHAR(20) DEFAULT 'permanent' CHECK (contract_type IN ('permanent','seasonal','casual')),
    hire_date DATE,
    documents TEXT,
    pay_type VARCHAR(20) DEFAULT 'monthly' CHECK (pay_type IN ('monthly','daily','piece')),
    monthly_salary DECIMAL(12,2) DEFAULT 0,
    daily_rate DECIMAL(12,2) DEFAULT 0,
    piece_rate DECIMAL(12,2) DEFAULT 0,
    farm_id INT REFERENCES farms(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_records (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    course_name VARCHAR(255) NOT NULL,
    provider VARCHAR(255),
    completion_date DATE,
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('completed','pending','in_progress')),
    certified BOOLEAN DEFAULT false
);

CREATE TABLE IF NOT EXISTS leave_balances (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type VARCHAR(20) NOT NULL CHECK (leave_type IN ('annual','sick','maternity','paternity','unpaid')),
    total_days INT DEFAULT 0,
    used_days INT DEFAULT 0,
    UNIQUE (employee_id, leave_type)
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type VARCHAR(20) NOT NULL CHECK (leave_type IN ('annual','sick','maternity','paternity','unpaid')),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days INT DEFAULT 0,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    approved_by INT REFERENCES users(id),
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll_records (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    period_start DATE,
    period_end DATE,
    gross_pay DECIMAL(12,2) DEFAULT 0,
    overtime DECIMAL(12,2) DEFAULT 0,
    bonus DECIMAL(12,2) DEFAULT 0,
    advances DECIMAL(12,2) DEFAULT 0,
    loans DECIMAL(12,2) DEFAULT 0,
    napsa DECIMAL(12,2) DEFAULT 0,
    paye DECIMAL(12,2) DEFAULT 0,
    nhima DECIMAL(12,2) DEFAULT 0,
    net_pay DECIMAL(12,2) DEFAULT 0,
    payment_method VARCHAR(30) DEFAULT 'bank' CHECK (payment_method IN ('mobile_money','bank','cash')),
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft','paid')),
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS shift_schedules (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    shift_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    shift_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled','swapped','conflict'))
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    work_date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    hours DECIMAL(6,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS grievances (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open','in_progress','resolved','closed')),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_employees_dept ON employees(department_id);
CREATE INDEX IF NOT EXISTS idx_training_emp ON training_records(employee_id);
CREATE INDEX IF NOT EXISTS idx_leave_emp ON leave_requests(employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_emp ON payroll_records(employee_id);
CREATE INDEX IF NOT EXISTS idx_attendance_emp ON attendance_records(employee_id);
CREATE INDEX IF NOT EXISTS idx_shifts_emp ON shift_schedules(employee_id);
CREATE INDEX IF NOT EXISTS idx_grievances_emp ON grievances(employee_id);


