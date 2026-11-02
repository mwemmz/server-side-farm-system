-- Communication Hub (Wave 1C intranet, Wave 2A extranet)
CREATE TABLE IF NOT EXISTS conversations (
    id SERIAL PRIMARY KEY,
    scope VARCHAR(20) NOT NULL DEFAULT 'intranet'
        CHECK (scope IN ('intranet','extranet')),
    kind VARCHAR(20) NOT NULL DEFAULT 'direct'
        CHECK (kind IN ('direct','group','channel','broadcast')),
    farm_id INT REFERENCES farms(id) ON DELETE CASCADE,
    title VARCHAR(255),
    is_public BOOLEAN DEFAULT FALSE,
    district VARCHAR(120),
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conversation_members (
    conversation_id INT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    member_role VARCHAR(40) DEFAULT 'member',
    last_read_message_id INT,
    muted BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    conversation_id INT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    sender_id INT REFERENCES users(id),
    body TEXT NOT NULL,
    message_type VARCHAR(20) NOT NULL DEFAULT 'text'
        CHECK (message_type IN ('text','system')),
    ref_type VARCHAR(40),
    ref_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edited_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS message_reports (
    id SERIAL PRIMARY KEY,
    message_id INT NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    reporter_id INT REFERENCES users(id),
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'open'
        CHECK (status IN ('open','removed','dismissed')),
    resolved_by INT REFERENCES users(id),
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_conversations_farm ON conversations(farm_id);
CREATE INDEX IF NOT EXISTS idx_conversations_scope ON conversations(scope);
CREATE INDEX IF NOT EXISTS idx_conv_members_user ON conversation_members(user_id);
CREATE INDEX IF NOT EXISTS idx_messages_conv ON messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at);
CREATE INDEX IF NOT EXISTS idx_message_reports_status ON message_reports(status);
