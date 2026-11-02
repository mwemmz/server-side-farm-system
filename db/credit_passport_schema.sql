-- Farm Credit Passport (Wave 2B)
CREATE TABLE IF NOT EXISTS practice_scores (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    score INT NOT NULL,
    band VARCHAR(20) NOT NULL CHECK (band IN ('bronze','silver','gold','platinum')),
    factors JSONB,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    computed_by VARCHAR(40) DEFAULT 'system'
);
CREATE INDEX IF NOT EXISTS idx_practice_scores_user ON practice_scores(user_id);
CREATE INDEX IF NOT EXISTS idx_practice_scores_computed ON practice_scores(computed_at);

CREATE TABLE IF NOT EXISTS credit_passport_shares (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) UNIQUE NOT NULL,
    scope JSONB,
    expires_at TIMESTAMP,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_credit_shares_token ON credit_passport_shares(token);
CREATE INDEX IF NOT EXISTS idx_credit_shares_user ON credit_passport_shares(user_id);
