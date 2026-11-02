-- Marketplace (farmer-to-farmer trading)
CREATE TABLE IF NOT EXISTS marketplace_listings (
    id SERIAL PRIMARY KEY,
    seller_farm_id INT REFERENCES farms(id) ON DELETE CASCADE,
    seller_user_id INT REFERENCES users(id) ON DELETE SET NULL,
    item_name VARCHAR(120) NOT NULL,
    category VARCHAR(60),
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    price_per_unit DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active','sold','cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marketplace_orders (
    id SERIAL PRIMARY KEY,
    listing_id INT NOT NULL REFERENCES marketplace_listings(id) ON DELETE CASCADE,
    buyer_farm_id INT REFERENCES farms(id) ON DELETE SET NULL,
    buyer_user_id INT REFERENCES users(id) ON DELETE SET NULL,
    quantity DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','completed','cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_listings_status ON marketplace_listings(status);
CREATE INDEX IF NOT EXISTS idx_listings_item ON marketplace_listings(item_name);
CREATE INDEX IF NOT EXISTS idx_listings_seller ON marketplace_listings(seller_farm_id);
CREATE INDEX IF NOT EXISTS idx_orders_listing ON marketplace_orders(listing_id);
CREATE INDEX IF NOT EXISTS idx_orders_buyer ON marketplace_orders(buyer_user_id);
