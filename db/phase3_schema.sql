-- ============================================================
-- FFMS Phase 3 — Operations depth (B1–B7)
-- Idempotent: safe to run on every boot via db/migrate.php.
-- ============================================================

-- ------------------------------------------------------------
-- B1 — Vaccine catalogue + animal treatment records
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vaccine_catalog (
    id SERIAL PRIMARY KEY,
    species VARCHAR(60) NOT NULL,
    name VARCHAR(160) NOT NULL,
    disease VARCHAR(160),
    dose_interval_days INT,
    first_dose_age_days INT,
    booster_after_days INT,
    notes TEXT,
    needs_review BOOLEAN DEFAULT TRUE,
    UNIQUE (species, name)
);
CREATE INDEX IF NOT EXISTS idx_vaccine_catalog_species ON vaccine_catalog (species);

CREATE TABLE IF NOT EXISTS animal_treatments (
    id SERIAL PRIMARY KEY,
    livestock_id INT REFERENCES livestock(id),
    treatment_name VARCHAR(255) NOT NULL,
    treatment_date DATE NOT NULL,
    dose VARCHAR(120),
    dose_unit VARCHAR(40),
    withdrawal_days INT,
    withdrawal_ends DATE,
    inventory_item_id INT REFERENCES inventory(id),
    quantity_used DECIMAL(12,2),
    cost DECIMAL(12,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_animal_treatments_livestock ON animal_treatments (livestock_id);

-- ------------------------------------------------------------
-- B2 — Finance source linking + payment status
-- ------------------------------------------------------------
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS source_module VARCHAR(40);
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS source_id INT;
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'paid';
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS due_date DATE;
CREATE UNIQUE INDEX IF NOT EXISTS uq_finance_source
    ON finance_records (source_module, source_id)
    WHERE source_module IS NOT NULL AND source_id IS NOT NULL;

-- ------------------------------------------------------------
-- B3 — Stock movement detail
-- ------------------------------------------------------------
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS reason VARCHAR(80);
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS ref_module VARCHAR(40);
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS ref_id INT;
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS note TEXT;
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS created_by INT;
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- B4 — Unified health incidents (replaces standalone pest_records)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS health_issue_catalog (
    id SERIAL PRIMARY KEY,
    applies_to VARCHAR(20) NOT NULL,      -- crop | livestock | both
    species_or_crop VARCHAR(60),          -- NULL = applies to all of applies_to
    name VARCHAR(160) NOT NULL,
    kind VARCHAR(30),                     -- pest | disease | deficiency | injury | other
    typical_treatment TEXT,
    needs_review BOOLEAN DEFAULT TRUE,
    UNIQUE (applies_to, species_or_crop, name)
);

CREATE TABLE IF NOT EXISTS health_incidents (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    subject_type VARCHAR(20) NOT NULL,    -- crop | livestock
    subject_id INT,
    issue_name VARCHAR(160) NOT NULL,
    issue_kind VARCHAR(30) DEFAULT 'other',
    severity VARCHAR(20),
    detected_date DATE,
    status VARCHAR(20) DEFAULT 'open',    -- open | treated | resolved
    treatment_notes TEXT,
    inventory_item_id INT REFERENCES inventory(id),
    quantity_used DECIMAL(12,2),
    cost DECIMAL(12,2),
    crop_id INT,
    livestock_id INT,
    resolved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_health_incidents_subject ON health_incidents (subject_type, subject_id);

-- ------------------------------------------------------------
-- B6 — Equipment servicing schedule
-- ------------------------------------------------------------
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS next_service_date DATE;

-- ------------------------------------------------------------
-- B7 — Harvest to storage link
-- ------------------------------------------------------------
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS storage_record_id INT;
