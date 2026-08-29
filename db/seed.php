<?php
// Seed script: creates a default admin account and realistic Zambian mock data.
// Idempotent — safe to run on every app boot (Farms table check + ON CONFLICT).
require_once __DIR__ . '/../config/database.php';

// --- Default admin account (runs every boot, resets password to admin123) ---
try {
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, email, role)
         VALUES ('admin', ?, ?, 'admin')
         ON CONFLICT (username) DO UPDATE
            SET password_hash = EXCLUDED.password_hash,
                role = 'admin',
                email = EXCLUDED.email"
    );
    $stmt->execute([$adminHash, 'admin@ffms.local']);
} catch (PDOException $e) {
    error_log("Seed (admin) failed: " . $e->getMessage());
}

// --- Market Analysis: 2 years of historical price history (idempotent) -----
// Runs every boot (BEFORE the mock-data early return) but only inserts once:
// guarded by "is there already a market record older than ~13 months?".
// This adds the 2-year window the Price Prediction module needs even on a DB
// that is already seeded.
try {
    $hasHistory = (int) $pdo->query(
        "SELECT COUNT(*) FROM market_data WHERE market_date < (CURRENT_DATE - INTERVAL '400 days')"
    )->fetchColumn();
    if ($hasHistory === 0) {
        // Per-crop base price (ZMW/kg) + a 12-month seasonal factor.
        // Zambian pattern: prices firm up in the hot/dry-short-supply months and
        // soften at the main harvest gluts.
        $crops = [
            'Tomato'     => [6.00, [1.32, 1.25, 1.15, 0.78, 0.70, 0.72, 0.80, 0.95, 1.10, 1.18, 1.28, 1.34]],
            'Maize'      => [3.50, [1.15, 1.18, 1.20, 1.12, 0.95, 0.82, 0.78, 0.82, 0.88, 0.95, 1.05, 1.10]],
            'Soybeans'   => [7.20, [1.08, 1.10, 1.05, 0.98, 0.92, 0.88, 0.90, 0.95, 1.00, 1.05, 1.10, 1.08]],
            'Groundnuts' => [12.00, [1.10, 1.15, 1.20, 1.05, 0.92, 0.85, 0.88, 0.95, 1.00, 1.05, 1.10, 1.12]],
            'Wheat'      => [4.80, [1.02, 1.03, 1.05, 1.05, 1.04, 1.02, 1.00, 0.97, 0.95, 0.96, 0.98, 1.00]],
            'Cabbage'    => [2.50, [1.30, 1.26, 1.10, 0.85, 0.78, 0.80, 0.90, 1.00, 1.10, 1.18, 1.26, 1.32]],
            'Onion'      => [3.50, [1.18, 1.20, 1.12, 0.98, 0.90, 0.85, 0.88, 0.95, 1.02, 1.10, 1.16, 1.20]],
            'Sunflower'  => [5.50, [1.05, 1.07, 1.10, 1.05, 0.98, 0.92, 0.90, 0.94, 1.00, 1.04, 1.06, 1.08]],
        ];
        $rows = [];
        $start = new DateTime('2024-01-01');
        $now = new DateTime('now');
        $i = 0;
        foreach ($crops as $crop => [$base, $factors]) {
            $cursor = clone $start;
            while ($cursor <= $now) {
                $m = (int) $cursor->format('n');
                $y = (int) $cursor->format('Y');
                // Small upward drift (~4%/yr) + gentle month-to-month noise for realism.
                $drift = 1.0 + 0.04 * ($y - 2024);
                $price = round($base * $factors[$m - 1] * $drift * (1 + (($i % 7) - 3) / 140), 2);
                $rows[] = "('" . $crop . "', " . $price . ", '" . $cursor->format('Y-m-15') . "')";
                $i++;
                $cursor->modify('+1 month');
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            $pdo->exec("INSERT INTO market_data (crop_name, price, market_date) VALUES " . implode(', ', $chunk));
        }
        error_log("FFMS: Market Analysis historical prices seeded (" . count($rows) . " rows).");
    }
} catch (PDOException $e) {
    error_log("Seed (market prices) failed: " . $e->getMessage());
}

// --- Market Analysis: historical demand (sales) series (idempotent) --------
// Runs every boot before the mock-data early return; guarded idempotently.
try {
    $hasOldSales = (int) $pdo->query(
        "SELECT COUNT(*) FROM sales_records WHERE sale_date < (CURRENT_DATE - INTERVAL '300 days')"
    )->fetchColumn();
    if ($hasOldSales === 0) {
        $rows = [];
        $cursor = new DateTime('2024-01-01');
        $now = new DateTime('now');
        $i = 0;
        // Monthly demand shape: higher buyer activity in May (post-harvest trade)
        // and Nov–Dec (market-goer holidays), dips in Jan–Feb.
        $shape = [0.7, 0.6, 0.8, 0.9, 1.5, 1.3, 1.2, 1.0, 1.1, 1.2, 1.4, 1.5];
        $customers = ['Zambia National Millers', 'Africa Commodity Exchange', 'Local Market - Chisamba', 'Sunbird Trading', 'City Grocer Chain'];
        while ($cursor <= $now) {
            $m = (int) $cursor->format('n');
            $y = (int) $cursor->format('Y');
            $amount = round(80000 * $shape[$m - 1] * (1 + 0.10 * ($y - 2024)), 2);
            $rows[] = "('" . $customers[$i % count($customers)] . "', " . $amount . ", '" . $cursor->format('Y-m-18') . "')";
            $i++;
            $cursor->modify('+1 month');
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            $pdo->exec("INSERT INTO sales_records (customer_name, amount, sale_date) VALUES " . implode(', ', $chunk));
        }
        error_log("FFMS: Market Analysis demand history seeded (" . count($rows) . " rows).");
    }
} catch (PDOException $e) {
    error_log("Seed (market demand) failed: " . $e->getMessage());
}

// --- Mock data: only seed once, when the farms table is empty ---
try {
    $farmCount = (int) $pdo->query("SELECT COUNT(*) FROM farms")->fetchColumn();
    if ($farmCount > 0) {
        return; // already seeded
    }

    // Farms
    $pdo->exec("INSERT INTO farms (name, location, latitude, longitude) VALUES
        ('Green Valley Farm', 'Lusaka', -15.3875, 28.3228),
        ('Kabwe Grain Estates', 'Kabwe', -14.4490, 28.4465),
        ('Mazabuka Dairy Ridge', 'Mazabuka', -15.8560, 27.7595)");

    // Fields
    $pdo->exec("INSERT INTO fields (farm_id, name, size, soil_type) VALUES
        (1, 'Field A', 12.5, 'Loam'),
        (1, 'Field B', 8.0, 'Sandy loam'),
        (2, 'Field C', 10.0, 'Clay'),
        (3, 'Field D', 15.0, 'Black soil')");

    // Crops
    $pdo->exec("INSERT INTO crops (farm_id, name, variety, planting_date, expected_harvest_date) VALUES
        (1, 'Maize', 'SC719 Hybrid', '2025-11-15', '2026-04-20'),
        (1, 'Soybeans', 'Kafue', '2025-12-01', '2026-05-05'),
        (2, 'Wheat', 'Kabwe Star', '2026-05-10', '2026-09-25'),
        (2, 'Sunflower', 'Avanza', '2025-11-20', '2026-03-30'),
        (3, 'Groundnuts', 'Chalimbana', '2025-12-15', '2026-05-10')");

    // Livestock
    $pdo->exec("INSERT INTO livestock (farm_id, type, breed, dob) VALUES
        (3, 'Cattle', 'Brahman', '2022-03-10'),
        (3, 'Cattle', 'Hereford', '2021-06-01'),
        (3, 'Goats', 'Boer', '2023-01-15'),
        (1, 'Poultry', 'Ross 308 (Broilers)', '2026-07-01')");

    // Irrigation
    $pdo->exec("INSERT INTO irrigation_systems (farm_id, type, status) VALUES
        (1, 'Drip', 'Operational'),
        (2, 'Sprinkler', 'Maintenance Required'),
        (3, 'Pivot', 'Operational')");

    // Equipment
    $pdo->exec("INSERT INTO equipment (name, maintenance_status) VALUES
        ('John Deere 5065E Tractor', 'Operational'),
        ('Maize Planter 4-Row', 'Operational'),
        ('Boom Sprayer 2000L', 'Maintenance Required'),
        ('Trailer 10-Tonne', 'Operational')");

    // Inventory
    $pdo->exec("INSERT INTO inventory (name, quantity, unit) VALUES
        ('Hybrid Maize Seed (SC719)', 120, 'Bags'),
        ('D-Compound Fertilizer', 85, 'Bags'),
        ('Urea Fertilizer', 60, 'Bags'),
        ('Diesel', 2000, 'Litres'),
        ('Acephate Insecticide', 40, 'Litres'),
        ('Veterinary Vaccines', 150, 'Doses')");

    // Labour
    $pdo->exec("INSERT INTO labour_records (farm_id, name, role, attendance_date) VALUES
        (1, 'Mwewa Banda', 'Field Supervisor', '2026-08-20'),
        (1, 'Chanda Phiri', 'General Worker', '2026-08-20'),
        (2, 'Tembo Mulenga', 'Irrigation Operator', '2026-08-21'),
        (3, 'Natasha Zulu', 'Vet Technician', '2026-08-22')");

    // Pests & diseases
    $pdo->exec("INSERT INTO pest_records (farm_id, pest_name, detected_date, action_taken) VALUES
        (1, 'Fall Armyworm', '2026-01-15', 'Applied Coragen sprayed 200ml/ha'),
        (2, 'Birds (Quelea)', '2026-02-10', 'Bird scaring + netting deployed'),
        (3, 'Tick Infestation', '2026-03-02', 'Dipping conducted, acaricide applied')");

    // Harvest records
    $pdo->exec("INSERT INTO harvest_records (crop_id, harvest_date, quantity, quality) VALUES
        (1, '2026-04-18', 8.5, 'Grade A'),
        (1, '2026-04-25', 7.2, 'Grade A'),
        (5, '2026-05-09', 3.1, 'Grade B')");

    // Suppliers
    $pdo->exec("INSERT INTO suppliers (name, contact_info) VALUES
        ('Zamseed Ltd', 'zamseed@co.zm | +260 211 250 000'),
        ('Zambia Fertilizer Co', 'zfc@co.zm | +260 211 220 111'),
        ('AgroFarm Supplies', 'agrofarm@co.zm | +260 977 000 000')");

    // Procurement (cost in ZMW Kwacha)
    $pdo->exec("INSERT INTO procurement_records (supplier_id, item_name, quantity, cost, purchase_date) VALUES
        (1, 'Hybrid Maize Seed', 120, 96000, '2025-10-10'),
        (2, 'D-Compound Fertilizer', 85, 127500, '2025-10-12'),
        (2, 'Urea Fertilizer', 60, 108000, '2025-10-12'),
        (3, 'Acephate Insecticide', 40, 18000, '2026-01-05'),
        (1, 'Diesel (farm tenant)', 2000, 58700, '2026-03-15')");

    // Sales (amount in ZMW Kwacha)
    $pdo->exec("INSERT INTO sales_records (customer_name, amount, sale_date) VALUES
        ('Zambia National Millers', 584000, '2026-05-02'),
        ('Africa Commodity Exchange', 231000, '2026-05-28'),
        ('Local Market - Chisamba', 45200, '2026-06-15')");

    // Finance records (ZMW)
    $pdo->exec("INSERT INTO finance_records (type, amount, description, date) VALUES
        ('expense', 96000, 'Seed purchase - Zamseed', '2025-10-10'),
        ('expense', 235500, 'Fertilizer purchase', '2025-10-12'),
        ('expense', 58700, 'Diesel top-up', '2026-03-15'),
        ('income', 584000, 'Maize sale - ZNM', '2026-05-02'),
        ('income', 231000, 'Soybean sale - ACE', '2026-05-28')");

    // Market data (price per kg in ZMW)
    $pdo->exec("INSERT INTO market_data (crop_name, price, market_date) VALUES
        ('Maize', 3.50, '2026-08-20'),
        ('Soybeans', 7.20, '2026-08-20'),
        ('Wheat', 4.80, '2026-08-20'),
        ('Groundnuts', 12.00, '2026-08-20')");

    // Weather records
    $pdo->exec("INSERT INTO weather_records (farm_id, temperature, humidity, weather_date) VALUES
        (1, 22.5, 45.0, NOW()),
        (2, 21.0, 50.0, NOW()),
        (3, 24.3, 40.0, NOW())");

    // Storage
    $pdo->exec("INSERT INTO storage_records (name, capacity, current_stock) VALUES
        ('Grain Silo 1', 500.0, 320.0),
        ('Grain Silo 2', 500.0, 0.0),
        ('Cold Store', 120.0, 40.0)");

    // Notifications
    $pdo->exec("INSERT INTO notifications (message, is_read) VALUES
        ('Inventory: D-Compound Fertilizer is below 100 bags.', FALSE),
        ('Maintenance due: Boom Sprayer 2000L.', FALSE),
        ('Harvest window expected soon for Kabwe Wheat.', FALSE)");

        error_log("FFMS: Mock data seeded successfully.");
} catch (PDOException $e) {
    error_log("Seed (mock data) failed: " . $e->getMessage());
}
?>