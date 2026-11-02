-- Farm-to-market traceability (Wave 3A)
CREATE TABLE IF NOT EXISTS harvest_batches (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id) ON DELETE CASCADE,
    field_id INT REFERENCES fields(id),
    harvest_record_id INT REFERENCES harvest_records(id),
    batch_code VARCHAR(64) UNIQUE NOT NULL,
    quantity DECIMAL(12,2),
    unit VARCHAR(40),
    quality_grade VARCHAR(40),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS batch_events (
    id SERIAL PRIMARY KEY,
    batch_id INT NOT NULL REFERENCES harvest_batches(id) ON DELETE CASCADE,
    event_type VARCHAR(20) NOT NULL
        CHECK (event_type IN ('harvest','storage','dispatch','sale')),
    ref_type VARCHAR(40),
    ref_id INT,
    event_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location VARCHAR(255),
    notes TEXT,
    actor_id INT REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_harvest_batches_farm ON harvest_batches(farm_id);
CREATE INDEX IF NOT EXISTS idx_harvest_batches_code ON harvest_batches(batch_code);
CREATE INDEX IF NOT EXISTS idx_batch_events_batch ON batch_events(batch_id);
