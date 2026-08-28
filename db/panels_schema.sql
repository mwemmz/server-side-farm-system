-- Phase 6 — Live Control Panels (simulated sensors + history).
-- All statements are idempotent so migrate.php can run them on every boot.

CREATE TABLE IF NOT EXISTS simulated_sensors (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT NOT NULL,
    sensor_key VARCHAR(50) NOT NULL,
    current_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_value DECIMAL(12,2),
    max_value DECIMAL(12,2),
    drift_rate DECIMAL(10,4) NOT NULL DEFAULT 0,
    unit VARCHAR(20),
    extra TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_sensor_entity UNIQUE (entity_type, entity_id, sensor_key)
);

CREATE TABLE IF NOT EXISTS sensor_history (
    id SERIAL PRIMARY KEY,
    sensor_id INT NOT NULL REFERENCES simulated_sensors(id) ON DELETE CASCADE,
    value DECIMAL(12,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sensor_history_sensor ON sensor_history (sensor_id, recorded_at);
