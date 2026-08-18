CREATE TABLE IF NOT EXISTS farms (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS crops (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    name VARCHAR(255) NOT NULL,
    variety VARCHAR(255),
    planting_date DATE,
    expected_harvest_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
