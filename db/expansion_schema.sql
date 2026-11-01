-- ============================================================
-- FFMS Operations Expansion — closes the remaining gaps in
-- Full_Farm_Management_System.md (fields, crops, livestock,
-- equipment, labour, pest, harvest, market, suppliers).
--
-- Every statement is idempotent so db/migrate.php can run it on
-- every boot. Tables are additive; no existing column is dropped
-- or rewritten, and no destructive backfill is performed.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Farm & Field depth
-- ------------------------------------------------------------
ALTER TABLE fields ADD COLUMN IF NOT EXISTS boundary_geojson JSONB;
ALTER TABLE fields ADD COLUMN IF NOT EXISTS computed_hectares DECIMAL(12,4);
ALTER TABLE fields ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION;
ALTER TABLE fields ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION;
ALTER TABLE fields ADD COLUMN IF NOT EXISTS irrigation_system_id INT;

CREATE TABLE IF NOT EXISTS soil_conditions (
    id SERIAL PRIMARY KEY,
    field_id INT NOT NULL REFERENCES fields(id) ON DELETE CASCADE,
    sample_date DATE NOT NULL,
    ph DECIMAL(4,2),
    organic_matter_pct DECIMAL(6,2),
    nitrogen_ppm DECIMAL(10,2),
    phosphorus_ppm DECIMAL(10,2),
    potassium_ppm DECIMAL(10,2),
    moisture_pct DECIMAL(6,2),
    texture VARCHAR(80),
    lab_reference VARCHAR(120),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_soil_conditions_field ON soil_conditions (field_id, sample_date DESC);

CREATE TABLE IF NOT EXISTS field_history (
    id SERIAL PRIMARY KEY,
    field_id INT NOT NULL REFERENCES fields(id) ON DELETE CASCADE,
    crop_id INT REFERENCES crops(id),
    crop_name VARCHAR(160),
    season VARCHAR(60),
    start_date DATE,
    end_date DATE,
    yield_quantity DECIMAL(12,2),
    yield_unit VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_field_history_field ON field_history (field_id, start_date DESC);

CREATE TABLE IF NOT EXISTS land_use_plans (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    field_id INT REFERENCES fields(id) ON DELETE CASCADE,
    planned_crop VARCHAR(160),
    season VARCHAR(60),
    planned_area_ha DECIMAL(12,2),
    start_date DATE,
    end_date DATE,
    status VARCHAR(20) DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_land_use_plans_field ON land_use_plans (field_id, start_date DESC);

-- ------------------------------------------------------------
-- 2. Crop production depth
-- ------------------------------------------------------------
ALTER TABLE crops ADD COLUMN IF NOT EXISTS area_ha DECIMAL(12,2);
ALTER TABLE crops ADD COLUMN IF NOT EXISTS planting_density DECIMAL(12,2);
ALTER TABLE crops ADD COLUMN IF NOT EXISTS spacing_cm VARCHAR(40);
ALTER TABLE crops ADD COLUMN IF NOT EXISTS expected_yield DECIMAL(12,2);
ALTER TABLE crops ADD COLUMN IF NOT EXISTS yield_unit VARCHAR(20) DEFAULT 'kg';
ALTER TABLE crops ADD COLUMN IF NOT EXISTS growth_stage VARCHAR(80);
ALTER TABLE crops ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
ALTER TABLE crops ADD COLUMN IF NOT EXISTS seed_cost DECIMAL(12,2);

CREATE TABLE IF NOT EXISTS seed_records (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id) ON DELETE SET NULL,
    seed_name VARCHAR(160) NOT NULL,
    variety VARCHAR(160),
    source VARCHAR(160),
    lot_number VARCHAR(80),
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    unit_cost DECIMAL(12,2),
    total_cost DECIMAL(12,2),
    received_date DATE,
    expiry_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_seed_records_crop ON seed_records (crop_id, received_date DESC);

CREATE TABLE IF NOT EXISTS planting_records (
    id SERIAL PRIMARY KEY,
    crop_id INT NOT NULL REFERENCES crops(id) ON DELETE CASCADE,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    seed_record_id INT REFERENCES seed_records(id) ON DELETE SET NULL,
    planting_date DATE NOT NULL,
    area_ha DECIMAL(12,2),
    planting_density DECIMAL(12,2),
    spacing_cm VARCHAR(40),
    method VARCHAR(80),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_planting_records_crop ON planting_records (crop_id, planting_date DESC);

CREATE TABLE IF NOT EXISTS fertilizer_applications (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id) ON DELETE SET NULL,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    inventory_item_id INT REFERENCES inventory(id) ON DELETE SET NULL,
    application_date DATE NOT NULL,
    product VARCHAR(160) NOT NULL,
    application_type VARCHAR(40) DEFAULT 'fertilizer',
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    method VARCHAR(80),
    cost DECIMAL(12,2),
    applied_by INT REFERENCES employees(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fertilizer_apps_crop ON fertilizer_applications (crop_id, application_date DESC);

CREATE TABLE IF NOT EXISTS crop_growth_stages (
    id SERIAL PRIMARY KEY,
    crop_id INT NOT NULL REFERENCES crops(id) ON DELETE CASCADE,
    stage VARCHAR(80) NOT NULL,
    stage_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_crop_growth_crop ON crop_growth_stages (crop_id, stage_date DESC);

CREATE TABLE IF NOT EXISTS spray_schedules (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id) ON DELETE CASCADE,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    planned_date DATE NOT NULL,
    product VARCHAR(160),
    target VARCHAR(160),
    dose VARCHAR(80),
    unit VARCHAR(20) DEFAULT 'ml',
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_spray_schedules_date ON spray_schedules (planned_date, status);

CREATE TABLE IF NOT EXISTS chemical_applications (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id) ON DELETE SET NULL,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    spray_schedule_id INT REFERENCES spray_schedules(id) ON DELETE SET NULL,
    health_incident_id INT REFERENCES health_incidents(id) ON DELETE SET NULL,
    inventory_item_id INT REFERENCES inventory(id) ON DELETE SET NULL,
    application_date DATE NOT NULL,
    product VARCHAR(160) NOT NULL,
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'ml',
    cost DECIMAL(12,2),
    applied_by INT REFERENCES employees(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_chemical_apps_crop ON chemical_applications (crop_id, application_date DESC);

ALTER TABLE health_incidents ADD COLUMN IF NOT EXISTS recommended_control TEXT;
ALTER TABLE health_incidents ADD COLUMN IF NOT EXISTS followup_due DATE;
ALTER TABLE health_incidents ADD COLUMN IF NOT EXISTS effectiveness INT;
ALTER TABLE health_incidents ADD COLUMN IF NOT EXISTS outbreak_flag BOOLEAN DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS treatment_followups (
    id SERIAL PRIMARY KEY,
    health_incident_id INT NOT NULL REFERENCES health_incidents(id) ON DELETE CASCADE,
    followup_date DATE NOT NULL,
    effectiveness INT,
    status VARCHAR(20) DEFAULT 'monitoring',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_treatment_followups_incident ON treatment_followups (health_incident_id, followup_date DESC);

-- ------------------------------------------------------------
-- 3. Livestock production depth
-- ------------------------------------------------------------
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS purchase_date DATE;
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(12,2);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS current_weight_kg DECIMAL(10,2);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS sire_id INT;
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS dam_id INT;

CREATE TABLE IF NOT EXISTS animal_weights (
    id SERIAL PRIMARY KEY,
    livestock_id INT NOT NULL REFERENCES livestock(id) ON DELETE CASCADE,
    weight_kg DECIMAL(10,2) NOT NULL,
    measured_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_animal_weights_animal ON animal_weights (livestock_id, measured_date DESC);

CREATE TABLE IF NOT EXISTS breeding_records (
    id SERIAL PRIMARY KEY,
    livestock_id INT NOT NULL REFERENCES livestock(id) ON DELETE CASCADE,
    sire_id INT REFERENCES livestock(id) ON DELETE SET NULL,
    service_date DATE NOT NULL,
    method VARCHAR(40) DEFAULT 'natural',
    pregnancy_check_date DATE,
    result VARCHAR(40) DEFAULT 'pending',
    expected_birth_date DATE,
    actual_birth_date DATE,
    offspring_count INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_breeding_animal ON breeding_records (livestock_id, service_date DESC);

CREATE TABLE IF NOT EXISTS production_records (
    id SERIAL PRIMARY KEY,
    livestock_id INT NOT NULL REFERENCES livestock(id) ON DELETE CASCADE,
    production_type VARCHAR(40) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20),
    record_date DATE NOT NULL,
    quality VARCHAR(40),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_production_records_animal ON production_records (livestock_id, record_date DESC);

CREATE TABLE IF NOT EXISTS mortality_records (
    id SERIAL PRIMARY KEY,
    livestock_id INT REFERENCES livestock(id) ON DELETE SET NULL,
    death_date DATE NOT NULL,
    cause VARCHAR(160),
    diagnosis VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mortality_records_date ON mortality_records (death_date DESC);

CREATE TABLE IF NOT EXISTS feed_records (
    id SERIAL PRIMARY KEY,
    livestock_id INT REFERENCES livestock(id) ON DELETE SET NULL,
    feed_date DATE NOT NULL,
    feed_name VARCHAR(160) NOT NULL,
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    cost DECIMAL(12,2),
    inventory_item_id INT REFERENCES inventory(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_feed_records_date ON feed_records (feed_date DESC);

-- ------------------------------------------------------------
-- 4. Equipment costing depth
-- ------------------------------------------------------------
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(12,2);
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS salvage_value DECIMAL(12,2);
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS useful_life_years INT;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS meter_reading DECIMAL(12,2);

CREATE TABLE IF NOT EXISTS equipment_allocations (
    id SERIAL PRIMARY KEY,
    equipment_id INT NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    employee_id INT REFERENCES employees(id) ON DELETE SET NULL,
    assigned_date DATE NOT NULL,
    released_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_equipment_alloc_equipment ON equipment_allocations (equipment_id, assigned_date DESC);

CREATE TABLE IF NOT EXISTS fuel_records (
    id SERIAL PRIMARY KEY,
    equipment_id INT NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
    fuel_date DATE NOT NULL,
    litres DECIMAL(12,2) NOT NULL,
    cost DECIMAL(12,2),
    odometer DECIMAL(12,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fuel_records_equipment ON fuel_records (equipment_id, fuel_date DESC);

CREATE TABLE IF NOT EXISTS equipment_hours (
    id SERIAL PRIMARY KEY,
    equipment_id INT NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
    work_date DATE NOT NULL,
    hours DECIMAL(8,2) NOT NULL,
    operator_id INT REFERENCES employees(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_equipment_hours_equipment ON equipment_hours (equipment_id, work_date DESC);

CREATE TABLE IF NOT EXISTS equipment_repairs (
    id SERIAL PRIMARY KEY,
    equipment_id INT NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
    repair_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    cost DECIMAL(12,2),
    downtime_days INT,
    vendor VARCHAR(160),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_equipment_repairs_equipment ON equipment_repairs (equipment_id, repair_date DESC);

CREATE TABLE IF NOT EXISTS equipment_depreciation (
    id SERIAL PRIMARY KEY,
    equipment_id INT NOT NULL REFERENCES equipment(id) ON DELETE CASCADE,
    method VARCHAR(40) DEFAULT 'straight_line',
    purchase_value DECIMAL(12,2),
    salvage_value DECIMAL(12,2),
    useful_life_years INT,
    annual_depreciation DECIMAL(12,2),
    as_of_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_equipment_depr_equipment ON equipment_depreciation (equipment_id, as_of_date DESC);

-- ------------------------------------------------------------
-- 5. Labour / daily task management
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    crop_id INT REFERENCES crops(id) ON DELETE SET NULL,
    livestock_id INT REFERENCES livestock(id) ON DELETE SET NULL,
    equipment_id INT REFERENCES equipment(id) ON DELETE SET NULL,
    employee_id INT REFERENCES employees(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    priority VARCHAR(20) DEFAULT 'normal',
    status VARCHAR(20) DEFAULT 'open',
    estimated_hours DECIMAL(8,2),
    actual_hours DECIMAL(8,2),
    labour_cost DECIMAL(12,2),
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks (due_date, status);
CREATE INDEX IF NOT EXISTS idx_tasks_employee ON tasks (employee_id, due_date);

-- ------------------------------------------------------------
-- 6. Harvest, post-harvest & quality
-- ------------------------------------------------------------
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS field_id INT REFERENCES fields(id);
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'kg';
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS expected_quantity DECIMAL(12,2);
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS grade VARCHAR(40);
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS price_per_unit DECIMAL(12,2);
ALTER TABLE harvest_records ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE stored_produce ADD COLUMN IF NOT EXISTS quality_grade VARCHAR(40);
ALTER TABLE stored_produce ADD COLUMN IF NOT EXISTS spoilage_quantity DECIMAL(12,2) DEFAULT 0;
ALTER TABLE stored_produce ADD COLUMN IF NOT EXISTS expected_price DECIMAL(12,2);
ALTER TABLE stored_produce ADD COLUMN IF NOT EXISTS storage_end_date DATE;
ALTER TABLE stored_produce ADD COLUMN IF NOT EXISTS storage_cost DECIMAL(12,2);

CREATE TABLE IF NOT EXISTS harvest_schedules (
    id SERIAL PRIMARY KEY,
    crop_id INT NOT NULL REFERENCES crops(id) ON DELETE CASCADE,
    field_id INT REFERENCES fields(id) ON DELETE SET NULL,
    expected_date DATE NOT NULL,
    expected_quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    status VARCHAR(20) DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_harvest_schedules_date ON harvest_schedules (expected_date, status);

CREATE TABLE IF NOT EXISTS harvest_losses (
    id SERIAL PRIMARY KEY,
    harvest_id INT REFERENCES harvest_records(id) ON DELETE CASCADE,
    loss_date DATE NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    cause VARCHAR(160),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_harvest_losses_harvest ON harvest_losses (harvest_id);

CREATE TABLE IF NOT EXISTS packaging_records (
    id SERIAL PRIMARY KEY,
    harvest_id INT REFERENCES harvest_records(id) ON DELETE CASCADE,
    packaging_date DATE NOT NULL,
    package_type VARCHAR(80),
    units INT,
    unit_weight_kg DECIMAL(10,2),
    cost DECIMAL(12,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_packaging_records_harvest ON packaging_records (harvest_id);

CREATE TABLE IF NOT EXISTS quality_grades (
    id SERIAL PRIMARY KEY,
    harvest_id INT REFERENCES harvest_records(id) ON DELETE CASCADE,
    grade_date DATE NOT NULL,
    grade VARCHAR(40) NOT NULL,
    moisture_pct DECIMAL(6,2),
    defects_pct DECIMAL(6,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_quality_grades_harvest ON quality_grades (harvest_id, grade_date DESC);

CREATE TABLE IF NOT EXISTS produce_dispatches (
    id SERIAL PRIMARY KEY,
    harvest_id INT REFERENCES harvest_records(id) ON DELETE SET NULL,
    stored_produce_id INT REFERENCES stored_produce(id) ON DELETE SET NULL,
    customer_id INT,
    dispatch_date DATE NOT NULL,
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    destination VARCHAR(160),
    vehicle VARCHAR(120),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_produce_dispatches_date ON produce_dispatches (dispatch_date DESC);

-- ------------------------------------------------------------
-- 7. Market, customers, orders, invoices & contracts
-- ------------------------------------------------------------
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS customer_id INT;
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS crop_id INT;
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS order_id INT;
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS invoice_id INT;
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS market_destination_id INT;
ALTER TABLE payment_records ADD COLUMN IF NOT EXISTS invoice_id INT;
ALTER TABLE payment_records ADD COLUMN IF NOT EXISTS notes TEXT;

CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    customer_type VARCHAR(40) DEFAULT 'buyer',
    phone VARCHAR(40),
    email VARCHAR(160),
    address VARCHAR(255),
    market_destination_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_customers_name ON customers (name);

CREATE TABLE IF NOT EXISTS market_destinations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    location VARCHAR(160),
    market_type VARCHAR(60),
    distance_km DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_orders (
    id SERIAL PRIMARY KEY,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    order_date DATE NOT NULL,
    delivery_date DATE,
    status VARCHAR(20) DEFAULT 'open',
    total_amount DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_sales_orders_customer ON sales_orders (customer_id, order_date DESC);

CREATE TABLE IF NOT EXISTS sales_order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES sales_orders(id) ON DELETE CASCADE,
    crop_id INT REFERENCES crops(id) ON DELETE SET NULL,
    description VARCHAR(255),
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'kg',
    unit_price DECIMAL(12,2),
    amount DECIMAL(12,2)
);
CREATE INDEX IF NOT EXISTS idx_sales_order_items_order ON sales_order_items (order_id);

CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    invoice_no VARCHAR(40) UNIQUE,
    sales_order_id INT REFERENCES sales_orders(id) ON DELETE SET NULL,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'unpaid',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices (customer_id, invoice_date DESC);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (status, due_date);

CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(255),
    quantity DECIMAL(12,2),
    unit_price DECIMAL(12,2),
    amount DECIMAL(12,2)
);
CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items (invoice_id);

CREATE TABLE IF NOT EXISTS contracts (
    id SERIAL PRIMARY KEY,
    contract_type VARCHAR(20) NOT NULL DEFAULT 'buyer',
    party_name VARCHAR(255) NOT NULL,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    supplier_id INT REFERENCES suppliers(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    start_date DATE,
    end_date DATE,
    value DECIMAL(12,2),
    status VARCHAR(20) DEFAULT 'active',
    terms TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_contracts_end ON contracts (end_date, status);

-- ------------------------------------------------------------
-- 8. Supplier & procurement depth
-- ------------------------------------------------------------
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS email VARCHAR(160);
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS address VARCHAR(255);
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS rating DECIMAL(4,2);

CREATE TABLE IF NOT EXISTS supplier_quotations (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES suppliers(id) ON DELETE CASCADE,
    item_name VARCHAR(160) NOT NULL,
    quantity DECIMAL(12,2),
    unit VARCHAR(20) DEFAULT 'unit',
    unit_price DECIMAL(12,2),
    quote_date DATE NOT NULL,
    valid_until DATE,
    status VARCHAR(20) DEFAULT 'received',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_supplier_quotations_supplier ON supplier_quotations (supplier_id, quote_date DESC);

CREATE TABLE IF NOT EXISTS supplier_reviews (
    id SERIAL PRIMARY KEY,
    supplier_id INT NOT NULL REFERENCES suppliers(id) ON DELETE CASCADE,
    review_date DATE NOT NULL,
    quality_score INT,
    delivery_score INT,
    price_score INT,
    reliability_score INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_supplier_reviews_supplier ON supplier_reviews (supplier_id, review_date DESC);

-- ------------------------------------------------------------
-- 9. Finance dimension columns (single costing ledger)
-- ------------------------------------------------------------
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS crop_id INT REFERENCES crops(id);
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS equipment_id INT REFERENCES equipment(id);
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS customer_id INT REFERENCES customers(id);
CREATE INDEX IF NOT EXISTS idx_finance_crop ON finance_records (crop_id);
CREATE INDEX IF NOT EXISTS idx_finance_equipment ON finance_records (equipment_id);
CREATE INDEX IF NOT EXISTS idx_finance_date_type ON finance_records (date, type);
