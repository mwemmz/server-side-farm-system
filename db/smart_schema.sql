-- ============================================================
-- FFMS Smart Upgrade — reference data (Phase 1)
-- Idempotent: safe to run on every boot via db/migrate.php.
-- ============================================================

-- Universal lookup list backing every "pick or type" combobox.
--   category     = which list this value belongs to (e.g. 'irrigation_type')
--   value        = the stored value (stable key)
--   label        = what the user sees
--   sort_order   = display order (lower first)
--   meta         = optional JSON (e.g. {"species":"Cattle"} to filter breeds)
--   needs_review = TRUE when a user typed a brand-new custom value
CREATE TABLE IF NOT EXISTS lookup_options (
    id SERIAL PRIMARY KEY,
    category VARCHAR(60) NOT NULL,
    value VARCHAR(120) NOT NULL,
    label VARCHAR(160) NOT NULL,
    sort_order INT DEFAULT 100,
    meta JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    needs_review BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (category, value)
);

CREATE INDEX IF NOT EXISTS idx_lookup_options_category ON lookup_options (category);

-- ------------------------------------------------------------------
-- Smart Upgrade columns on existing tables (all idempotent).
-- ------------------------------------------------------------------
ALTER TABLE crops             ADD COLUMN IF NOT EXISTS field_id INT;
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS tag_no VARCHAR(50);
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS sex VARCHAR(20);
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS purpose VARCHAR(40);
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS poultry_production VARCHAR(40);
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS flock_size INT;
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS batch_date DATE;
ALTER TABLE livestock         ADD COLUMN IF NOT EXISTS housing VARCHAR(40);

ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS category VARCHAR(60);
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS used_for VARCHAR(40);
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS farm_id INT;
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS min_level INT;
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS expiry_date DATE;
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS supplier_id INT;
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2);
ALTER TABLE inventory         ADD COLUMN IF NOT EXISTS storage_location VARCHAR(120);

ALTER TABLE finance_records   ADD COLUMN IF NOT EXISTS category VARCHAR(80);
ALTER TABLE finance_records   ADD COLUMN IF NOT EXISTS farm_id INT;
ALTER TABLE finance_records   ADD COLUMN IF NOT EXISTS related_module VARCHAR(40);
ALTER TABLE finance_records   ADD COLUMN IF NOT EXISTS related_id INT;
ALTER TABLE finance_records   ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40);

ALTER TABLE suppliers         ADD COLUMN IF NOT EXISTS category VARCHAR(60);
ALTER TABLE suppliers         ADD COLUMN IF NOT EXISTS phone VARCHAR(40);
ALTER TABLE irrigation_systems ADD COLUMN IF NOT EXISTS water_source VARCHAR(60);
ALTER TABLE storage_records   ADD COLUMN IF NOT EXISTS facility_type VARCHAR(60);
ALTER TABLE storage_records   ADD COLUMN IF NOT EXISTS unit VARCHAR(20);
ALTER TABLE equipment         ADD COLUMN IF NOT EXISTS equipment_type VARCHAR(60);
ALTER TABLE equipment         ADD COLUMN IF NOT EXISTS farm_id INT;
ALTER TABLE equipment         ADD COLUMN IF NOT EXISTS purchase_date DATE;
ALTER TABLE equipment         ADD COLUMN IF NOT EXISTS service_interval_days INT;
ALTER TABLE equipment         ADD COLUMN IF NOT EXISTS last_service_date DATE;
ALTER TABLE notifications     ADD COLUMN IF NOT EXISTS notif_type VARCHAR(20);
ALTER TABLE notifications     ADD COLUMN IF NOT EXISTS related_module VARCHAR(40);
ALTER TABLE notifications     ADD COLUMN IF NOT EXISTS related_id INT;

ALTER TABLE sales_records     ADD COLUMN IF NOT EXISTS crop_name VARCHAR(120);
ALTER TABLE sales_records     ADD COLUMN IF NOT EXISTS quantity DECIMAL(12,2);
ALTER TABLE sales_records     ADD COLUMN IF NOT EXISTS unit VARCHAR(20);
ALTER TABLE sales_records     ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2);
ALTER TABLE sales_records     ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40);

ALTER TABLE vaccinations      ADD COLUMN IF NOT EXISTS dose_number INT;
ALTER TABLE vaccinations      ADD COLUMN IF NOT EXISTS batch_number VARCHAR(60);
ALTER TABLE vaccinations      ADD COLUMN IF NOT EXISTS administered_by INT;
ALTER TABLE vaccinations      ADD COLUMN IF NOT EXISTS cost DECIMAL(12,2);
ALTER TABLE vaccinations      ADD COLUMN IF NOT EXISTS vaccine_catalog_id INT;
ALTER TABLE pest_records      ADD COLUMN IF NOT EXISTS severity VARCHAR(20);
