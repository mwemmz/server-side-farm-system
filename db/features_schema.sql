-- Phase 1 and standout feature additions. Every statement is idempotent.
CREATE TABLE IF NOT EXISTS role_permissions (
    role VARCHAR(30) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    PRIMARY KEY (role, permission)
);
CREATE TABLE IF NOT EXISTS sms_simulator_logs (
    id SERIAL PRIMARY KEY,
    recipient VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    driver VARCHAR(30) NOT NULL DEFAULT 'simulator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE livestock ADD COLUMN IF NOT EXISTS tag_code VARCHAR(80);
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS tag_code VARCHAR(80);
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS tag_code VARCHAR(80);
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS inventory_id INT REFERENCES inventory(id);
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS livestock_id INT REFERENCES livestock(id);
ALTER TABLE sales_records ADD COLUMN IF NOT EXISTS field_id INT REFERENCES fields(id);
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS field_id INT REFERENCES fields(id);
ALTER TABLE finance_records ADD COLUMN IF NOT EXISTS livestock_id INT REFERENCES livestock(id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_livestock_tag_code ON livestock(tag_code) WHERE tag_code IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_inventory_tag_code ON inventory(tag_code) WHERE tag_code IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_equipment_tag_code ON equipment(tag_code) WHERE tag_code IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_sales_profit_links ON sales_records(field_id, livestock_id);
INSERT INTO role_permissions(role, permission) VALUES
('farm-owner','farm.read'),('farm-owner','finance.read'),('farm-owner','profit.read'),('farm-owner','reports.read'),
('agronomist','crop.read'),('agronomist','field.read'),('agronomist','irrigation.read'),('agronomist','pest.read'),('agronomist','insights.read')
ON CONFLICT DO NOTHING;
