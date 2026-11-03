-- Farm Trust Score (per-farm, visible on connections/extranet)
CREATE TABLE IF NOT EXISTS farm_trust_scores (
    id SERIAL PRIMARY KEY,
    farm_id INT NOT NULL REFERENCES farms(id) ON DELETE CASCADE,
    score INT NOT NULL CHECK (score >= 0 AND score <= 100),
    band VARCHAR(20) NOT NULL CHECK (band IN ('bronze','silver','gold','platinum')),
    factors JSONB,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_farm_trust_farm ON farm_trust_scores(farm_id);
CREATE INDEX IF NOT EXISTS idx_farm_trust_computed ON farm_trust_scores(computed_at);
CREATE INDEX IF NOT EXISTS idx_farm_trust_score ON farm_trust_scores(score DESC);

-- App settings table (used by NotificationService + WeatherForecastService cache)
CREATE TABLE IF NOT EXISTS app_settings (
    key VARCHAR(80) PRIMARY KEY,
    value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
