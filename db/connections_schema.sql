-- Farm connections (mutual-accept graph) + farm_members (Wave 1B)
CREATE TABLE IF NOT EXISTS farm_members (
    farm_id INT REFERENCES farms(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    farm_role VARCHAR(40) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (farm_id, user_id)
);

CREATE TABLE IF NOT EXISTS farm_connections (
    id SERIAL PRIMARY KEY,
    requester_farm_id INT NOT NULL REFERENCES farms(id) ON DELETE CASCADE,
    target_farm_id INT NOT NULL REFERENCES farms(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','accepted','declined','blocked')),
    requested_by INT REFERENCES users(id),
    decided_by INT REFERENCES users(id),
    decided_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (requester_farm_id, target_farm_id)
);

CREATE INDEX IF NOT EXISTS idx_farm_connections_requester ON farm_connections(requester_farm_id);
CREATE INDEX IF NOT EXISTS idx_farm_connections_target ON farm_connections(target_farm_id);
CREATE INDEX IF NOT EXISTS idx_farm_connections_status ON farm_connections(status);
CREATE INDEX IF NOT EXISTS idx_farm_members_user ON farm_members(user_id);
