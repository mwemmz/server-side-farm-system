-- Remaining standout features. Safe to run repeatedly.
CREATE TABLE IF NOT EXISTS harvest_batches (
    id SERIAL PRIMARY KEY,
    batch_code VARCHAR(80) NOT NULL UNIQUE,
    field_id INT REFERENCES fields(id),
    crop_id INT REFERENCES crops(id),
    harvest_id INT REFERENCES harvest_records(id),
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit VARCHAR(30),
    harvest_date DATE,
    public_token VARCHAR(96) NOT NULL UNIQUE,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS chain_events (
    id SERIAL PRIMARY KEY,
    batch_id INT NOT NULL REFERENCES harvest_batches(id) ON DELETE CASCADE,
    event_type VARCHAR(30) NOT NULL CHECK (event_type IN ('field','harvest','storage','sale')),
    location VARCHAR(255),
    notes TEXT,
    event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_chain_events_batch ON chain_events(batch_id, event_time);
CREATE TABLE IF NOT EXISTS sync_operations (
    id SERIAL PRIMARY KEY,
    idempotency_key VARCHAR(120) NOT NULL UNIQUE,
    user_id INT REFERENCES users(id),
    operation_type VARCHAR(80) NOT NULL,
    payload TEXT NOT NULL,
    result TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS payment_records (
    id SERIAL PRIMARY KEY,
    sale_id INT REFERENCES sales_records(id) ON DELETE CASCADE,
    method VARCHAR(30) NOT NULL,
    reference VARCHAR(120),
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    paid_at TIMESTAMP,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS translation_dictionary (
    id SERIAL PRIMARY KEY,
    language VARCHAR(20) NOT NULL,
    phrase VARCHAR(160) NOT NULL,
    translation VARCHAR(255) NOT NULL,
    UNIQUE(language, phrase)
);
INSERT INTO translation_dictionary(language, phrase, translation) VALUES
('bem','Save','Sunga'),('bem','Cancel','Leka'),('bem','Dashboard','Cipampa'),
('nya','Save','Sungani'),('nya','Cancel','Lekani'),('nya','Dashboard','Dashboard')
ON CONFLICT DO NOTHING;
