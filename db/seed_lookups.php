<?php
// Seed the shared lookup_options reference data (Phase 1).
// Idempotent — every insert uses ON CONFLICT DO NOTHING, so it is safe to run
// on every boot. Only starter values live here; user-typed values are added at
// runtime with needs_review = TRUE.
require_once __DIR__ . '/../config/database.php';

try {
    $insert = $pdo->prepare(
        "INSERT INTO lookup_options (category, value, label, sort_order, meta, needs_review)
         VALUES (?, ?, ?, ?, ?, FALSE)
         ON CONFLICT (category, value) DO NOTHING"
    );

    // category => [ value1, value2, ... ]  (value === label for plain lists)
    $lists = [
        'irrigation_type'    => ['Drip', 'Sprinkler', 'Centre pivot', 'Furrow/flood', 'Hand watering'],
        'irrigation_status'  => ['Active', 'Idle', 'Under maintenance', 'Faulty'],
        'water_source'       => ['Borehole', 'River', 'Dam', 'Piped/municipal', 'Rainwater harvest'],
        'harvest_quality'    => ['Grade A', 'Grade B', 'Grade C', 'Reject'],
        'unit'               => ['kg', 'g', 'tonne', 'bag', '50 kg bag', '90 kg bag', 'L', 'ml', 'dose', 'birds', 'head', 'crate', 'piece', 'hour', 'ha'],
        'finance_category_expense' => ['Seeds', 'Fertiliser', 'Chemicals & pesticides', 'Animal feed', 'Veterinary & medicines', 'Labour & wages', 'Fuel', 'Equipment & repairs', 'Irrigation & water', 'Transport', 'Storage & packaging', 'Utilities', 'Loan repayment', 'Other'],
        'finance_category_income'  => ['Crop sales', 'Livestock sales', 'Other income'],
        'payment_method'     => ['Cash', 'Mobile money', 'Bank transfer', 'Credit'],
        'supplier_category'  => ['Seeds', 'Fertiliser', 'Chemicals', 'Animal feed', 'Veterinary', 'Equipment & spares', 'Fuel', 'Packaging', 'Services'],
        'storage_facility_type' => ['Grain silo', 'Cold store', 'Warehouse', 'Open shed'],
        'equipment_type'     => ['Tractor', 'Planter', 'Sprayer', 'Pump', 'Harvester', 'Vehicle', 'Hand tools', 'Other'],
        'equipment_status'   => ['Operational', 'Under maintenance', 'Broken', 'Retired'],
        'inventory_category' => ['Seed', 'Fertiliser', 'Chemical/Pesticide', 'Animal feed', 'Veterinary medicine', 'Fuel', 'Packaging', 'Tools & spares', 'Machinery & equipment'],
        'inventory_purpose'  => ['Crops', 'Livestock', 'Irrigation', 'Equipment', 'Storage', 'General'],
        'livestock_type'     => ['Cattle', 'Goats', 'Sheep', 'Pigs', 'Poultry', 'Rabbits', 'Fish'],
        'livestock_sex'      => ['Male', 'Female', 'Mixed'],
        'livestock_purpose'  => ['Meat', 'Dairy', 'Eggs', 'Breeding', 'Draught'],
        'poultry_production' => ['Broilers', 'Layers', 'Village/free-range', 'Breeders'],
        'housing'            => ['Deep litter', 'Cage', 'Free-range'],
        'notification_type'  => ['Info', 'Warning', 'Urgent'],
        'report_type'        => ['Crop performance', 'Livestock health', 'Inventory valuation', 'Finance summary', 'Labour', 'Custom'],
        'stock_move_reason'  => ['Purchase', 'Used on crop', 'Fed to livestock', 'Sprayed on field', 'Fuel for machine', 'Damaged or expired', 'Adjustment', 'Sale'],
        'crop_name'          => ['Maize', 'Soybeans', 'Wheat', 'Groundnuts', 'Tomato', 'Cabbage', 'Onion', 'Sunflower', 'Cotton', 'Tobacco', 'Rice', 'Sorghum', 'Millet', 'Cowpeas', 'Beans', 'Cassava', 'Potato'],
        'crop_variety'       => ['SC 403', 'SC 513', 'PAN 53', 'Dekalb', 'Boron', 'Local', 'Hybrid'],
        'inventory_item'     => ['Maize seed', 'Fertiliser (D-compound)', 'Fertiliser (Urea)', 'Fertiliser (CAN)', 'Herbicide', 'Pesticide', 'Insecticide', 'Animal feed', 'Veterinary drug', 'Vaccine', 'Diesel', 'Petrol', 'Engine oil', 'Twine', 'Packaging bags', 'Spare parts'],
        'pest_name'          => ['Fall armyworm', 'Aphids', 'Stalk borer', 'Bollworm', 'Whitefly', 'Locusts', 'Rodents', 'Weeds', 'Armyworm', 'Termites'],
        'pest_severity'      => ['Low', 'Moderate', 'High', 'Severe'],
        'finance_category'   => ['Seeds', 'Fertiliser', 'Chemicals & pesticides', 'Animal feed', 'Veterinary & medicines', 'Labour & wages', 'Fuel', 'Equipment & repairs', 'Irrigation & water', 'Transport', 'Storage & packaging', 'Utilities', 'Loan repayment', 'Crop sales', 'Livestock sales', 'Other income', 'Other'],
        'disease_name'       => ['Foot and mouth disease', 'East Coast fever', 'Mastitis', 'Newcastle disease', 'Coccidiosis', 'Anthrax', 'Blackleg', 'Lumpy skin disease', 'African swine fever'],
        'vaccine_name'       => ['FMD vaccine', 'Anthrax vaccine', 'Blackleg vaccine', 'Newcastle (NCD)', 'Gumboro (IBD)', 'Fowl pox', 'Rabies', 'Dewormer'],
    ];

    foreach ($lists as $category => $items) {
        $sort = 10;
        foreach ($items as $item) {
            $insert->execute([$category, $item, $item, $sort, null]);
            $sort += 10;
        }
    }

    // Breeds, grouped by species via meta JSON so a child dropdown can filter.
    $breeds = [
        'Cattle'  => ['Brahman', 'Boran', 'Angoni', 'Holstein-Friesian', 'Hereford', 'Other'],
        'Goats'   => ['Boer', 'Kalahari Red', 'Indigenous', 'Other'],
        'Sheep'   => ['Dorper', 'Merino', 'Indigenous', 'Other'],
        'Pigs'    => ['Large White', 'Landrace', 'Duroc', 'Indigenous', 'Other'],
        'Poultry' => ['Ross 308 (Broilers)', 'Cobb 500', 'Boschveld', 'Indigenous', 'Other'],
    ];
    foreach ($breeds as $species => $items) {
        $sort = 10;
        $meta = json_encode(['species' => $species]);
        foreach ($items as $item) {
            $insert->execute(['livestock_breed', $item, $item, $sort, $meta]);
            $sort += 10;
        }
    }

    // ------------------------------------------------------------
    // Phase 3 (B1) — starter vaccine catalogue.
    // Every interval is a STARTING recommendation only and is flagged
    // needs_review = TRUE: a veterinarian must confirm for each farm.
    // ------------------------------------------------------------
    $vaccineInsert = $pdo->prepare(
        "INSERT INTO vaccine_catalog
            (species, name, disease, dose_interval_days, first_dose_age_days, booster_after_days, notes, needs_review)
         VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
         ON CONFLICT (species, name) DO NOTHING"
    );
    // [species, name, disease, dose_interval_days, first_dose_age_days, booster_after_days, notes]
    $vaccines = [
        ['Cattle', 'FMD vaccine', 'Foot and mouth disease', 365, 120, 30, 'Confirm dosage and annual booster timing with a veterinarian.'],
        ['Cattle', 'Anthrax vaccine', 'Anthrax', 365, 90, null, 'Annual in endemic areas. Confirm with a veterinarian.'],
        ['Cattle', 'Blackleg vaccine', 'Black quarter (Blackleg)', 365, 120, null, 'Confirm with a veterinarian.'],
        ['Cattle', 'Lumpy skin vaccine', 'Lumpy skin disease', 365, 120, null, 'Annual before the wet season. Confirm with a veterinarian.'],
        ['Cattle', 'Brucellosis vaccine', 'Brucellosis', 730, 240, null, 'Heifers only (S19/RB51). Confirm with a veterinarian.'],
        ['Cattle', 'Rift Valley Fever vaccine', 'Rift Valley fever', 365, 120, null, 'Where risk is present. Confirm with a veterinarian.'],
        ['Goats', 'PPR vaccine', 'Peste des petits ruminants (PPR)', 365, 90, 21, 'Confirm with a veterinarian.'],
        ['Goats', 'Enterotoxaemia vaccine', 'Enterotoxaemia (Pulpy kidney)', 365, 60, 21, 'Confirm with a veterinarian.'],
        ['Sheep', 'PPR vaccine', 'Peste des petits ruminants (PPR)', 365, 90, 21, 'Confirm with a veterinarian.'],
        ['Sheep', 'Enterotoxaemia vaccine', 'Enterotoxaemia (Pulpy kidney)', 365, 60, 21, 'Confirm with a veterinarian.'],
        ['Pigs', 'Swine fever vaccine', 'Classical swine fever', 365, 60, null, 'Confirm with a veterinarian.'],
        ['Poultry', 'Newcastle (NCD) vaccine', 'Newcastle disease', 365, 21, 21, 'Confirm with a veterinarian.'],
        ['Poultry', 'Gumboro (IBD) vaccine', 'Infectious bursal disease (Gumboro)', 365, 14, 14, 'Confirm with a veterinarian.'],
        ['Poultry', 'Fowl pox vaccine', 'Fowl pox', 365, 56, null, 'Confirm with a veterinarian.'],
        ['Poultry', "Marek's vaccine", "Marek's disease", null, 1, null, 'Day-old administration at the hatchery. Confirm with a veterinarian.'],
        ['Poultry', 'Fowl typhoid vaccine', 'Fowl typhoid', 365, 56, null, 'Confirm with a veterinarian.'],
    ];
    foreach ($vaccines as $v) {
        $vaccineInsert->execute($v);
    }

    // ------------------------------------------------------------
    // Phase 3 (B4) — starter health-issue catalogue, seeded from the
    // existing pest_name (crops) and disease_name (livestock) lists.
    // ------------------------------------------------------------
    $issueInsert = $pdo->prepare(
        "INSERT INTO health_issue_catalog (applies_to, species_or_crop, name, kind, typical_treatment, needs_review)
         VALUES (?, ?, ?, ?, ?, FALSE)
         ON CONFLICT (applies_to, species_or_crop, name) DO NOTHING"
    );
    $cropPests = ['Fall armyworm', 'Aphids', 'Stalk borer', 'Bollworm', 'Whitefly', 'Locusts', 'Rodents', 'Weeds', 'Armyworm', 'Termites'];
    foreach ($cropPests as $pest) {
        $issueInsert->execute(['crop', null, $pest, 'pest', null]);
    }
    $animalDiseases = ['Foot and mouth disease', 'East Coast fever', 'Mastitis', 'Newcastle disease', 'Coccidiosis', 'Anthrax', 'Blackleg', 'Lumpy skin disease', 'African swine fever'];
    foreach ($animalDiseases as $disease) {
        $issueInsert->execute(['livestock', null, $disease, 'disease', null]);
    }
} catch (PDOException $e) {
    error_log("Seed (lookups) failed: " . $e->getMessage());
}
?>
