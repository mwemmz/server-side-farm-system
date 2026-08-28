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
