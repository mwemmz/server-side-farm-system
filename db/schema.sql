-- Core Entities
CREATE TABLE IF NOT EXISTS farms (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fields (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    name VARCHAR(255) NOT NULL,
    size DECIMAL(10,2),
    soil_type VARCHAR(100)
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

CREATE TABLE IF NOT EXISTS livestock (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    type VARCHAR(100),
    breed VARCHAR(100),
    dob DATE
);

-- Operations
CREATE TABLE IF NOT EXISTS irrigation_systems (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    type VARCHAR(100),
    status VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS equipment (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    maintenance_status VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS harvest_records (
    id SERIAL PRIMARY KEY,
    crop_id INT REFERENCES crops(id),
    harvest_date DATE,
    quantity DECIMAL(10,2),
    quality VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS labour_records (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    name VARCHAR(255),
    role VARCHAR(100),
    attendance_date DATE
);

CREATE TABLE IF NOT EXISTS pest_records (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    pest_name VARCHAR(100),
    detected_date DATE,
    action_taken TEXT
);

-- Resources & Inventory
CREATE TABLE IF NOT EXISTS inventory (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    quantity INT,
    unit VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS storage_records (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    capacity DECIMAL(10,2),
    current_stock DECIMAL(10,2)
);

CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    contact_info VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS procurement_records (
    id SERIAL PRIMARY KEY,
    supplier_id INT REFERENCES suppliers(id),
    item_name VARCHAR(255),
    quantity INT,
    cost DECIMAL(10,2),
    purchase_date DATE
);

-- Finance & Market
CREATE TABLE IF NOT EXISTS finance_records (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50), -- income/expense
    amount DECIMAL(10,2),
    description TEXT,
    date DATE
);

CREATE TABLE IF NOT EXISTS sales_records (
    id SERIAL PRIMARY KEY,
    customer_name VARCHAR(255),
    amount DECIMAL(10,2),
    sale_date DATE
);

CREATE TABLE IF NOT EXISTS market_data (
    id SERIAL PRIMARY KEY,
    crop_name VARCHAR(255),
    price DECIMAL(10,2),
    market_date DATE
);

-- Environmental
CREATE TABLE IF NOT EXISTS weather_records (
    id SERIAL PRIMARY KEY,
    farm_id INT REFERENCES farms(id),
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    weather_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    report_type VARCHAR(100),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data TEXT
);

CREATE TABLE IF NOT EXISTS analytics_data (
    id SERIAL PRIMARY KEY,
    module_name VARCHAR(100),
    data_points TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS security_logs (
    id SERIAL PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
