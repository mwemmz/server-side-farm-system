<?php
/**
 * OpsRegistry — one declarative catalogue for the operational records that
 * close the remaining FFMS requirements (soil, planting, fertilizer, spraying,
 * livestock weights/breeding/production, equipment costing, tasks, harvest
 * quality/losses, customers/orders/invoices, supplier quotations/reviews).
 *
 * A definition is trusted configuration: table and column names never come
 * from user input. Everything user-supplied is validated by OpsController.
 */
class OpsRegistry
{
    /** @var array<string,array>|null */
    private static $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;

        $status = function (array $options) {
            return ['type' => 'select', 'options' => $options, 'required' => false];
        };
        $date = function ($required = false, $notFuture = true, $label = null) {
            return ['type' => 'date', 'required' => $required, 'not_future' => $notFuture, 'label' => $label];
        };
        $num = function ($opts = []) {
            return array_merge(['type' => 'number', 'required' => false, 'step' => '0.01', 'min' => '0'], $opts);
        };
        $text = function ($required = false, $opts = []) {
            return array_merge(['type' => 'text', 'required' => $required], $opts);
        };
        $area = function ($required = false, $label = 'Notes') {
            return ['type' => 'textarea', 'required' => $required, 'label' => $label];
        };

        self::$cache = [

            // ---------------------------------------------------------
            // Field & land
            // ---------------------------------------------------------
            'soil_conditions' => [
                'label' => 'Soil Conditions', 'group' => 'Field & Land', 'table' => 'soil_conditions',
                'permission' => 'field.write', 'icon' => 'soil',
                'summary' => 'Soil samples, pH, nutrients and moisture per field.',
                'fields' => [
                    'field_id' => self::fk('fields', true, 'Field'),
                    'sample_date' => $date(true, true, 'Sample date'),
                    'ph' => $num(['label' => 'pH', 'max' => '14']),
                    'organic_matter_pct' => $num(['label' => 'Organic matter %', 'max' => '100']),
                    'nitrogen_ppm' => $num(['label' => 'Nitrogen (ppm)']),
                    'phosphorus_ppm' => $num(['label' => 'Phosphorus (ppm)']),
                    'potassium_ppm' => $num(['label' => 'Potassium (ppm)']),
                    'moisture_pct' => $num(['label' => 'Moisture %', 'max' => '100']),
                    'texture' => $text(false, ['label' => 'Texture']),
                    'lab_reference' => $text(false, ['label' => 'Lab reference']),
                    'notes' => $area(),
                ],
                'list' => ['field_id', 'sample_date', 'ph', 'organic_matter_pct', 'nitrogen_ppm', 'phosphorus_ppm'],
            ],
            'field_history' => [
                'label' => 'Crop Rotation / Field History', 'group' => 'Field & Land', 'table' => 'field_history',
                'permission' => 'field.write', 'icon' => 'history',
                'summary' => 'What was grown on each field, season by season — the basis for rotation planning.',
                'fields' => [
                    'field_id' => self::fk('fields', true, 'Field'),
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'crop_name' => $text(false, ['label' => 'Crop name (if not registered)']),
                    'season' => $text(false),
                    'start_date' => $date(false, true, 'Start date'),
                    'end_date' => $date(false, true, 'End date'),
                    'yield_quantity' => $num(['label' => 'Yield quantity']),
                    'yield_unit' => $text(false, ['label' => 'Yield unit']),
                    'notes' => $area(),
                ],
                'list' => ['field_id', 'crop_name', 'season', 'start_date', 'end_date', 'yield_quantity'],
            ],
            'land_use_plans' => [
                'label' => 'Land-Use Plans', 'group' => 'Field & Land', 'table' => 'land_use_plans',
                'permission' => 'field.write', 'icon' => 'plan',
                'summary' => 'Planned crop allocations and seasons per field.',
                'fields' => [
                    'farm_id' => self::fk('farms', false, 'Farm'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'planned_crop' => $text(true, ['label' => 'Planned crop']),
                    'season' => $text(false),
                    'planned_area_ha' => $num(['label' => 'Planned area (ha)']),
                    'start_date' => $date(true, false, 'Start date'),
                    'end_date' => $date(false, false, 'End date'),
                    'status' => $status(['planned' => 'Planned', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled']),
                    'notes' => $area(),
                ],
                'list' => ['field_id', 'planned_crop', 'season', 'planned_area_ha', 'start_date', 'status'],
            ],

            // ---------------------------------------------------------
            // Crop production
            // ---------------------------------------------------------
            'seed_records' => [
                'label' => 'Seed & Seedling Records', 'group' => 'Crop Production', 'table' => 'seed_records',
                'permission' => 'crop.write', 'icon' => 'seed',
                'summary' => 'Seed lots, quantities, suppliers and costs.',
                'fields' => [
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'seed_name' => $text(true, ['label' => 'Seed / seedling']),
                    'variety' => $text(false),
                    'source' => $text(false),
                    'lot_number' => $text(false, ['label' => 'Lot number']),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'unit_cost' => $num(['label' => 'Unit cost']),
                    'total_cost' => $num(['label' => 'Total cost']),
                    'received_date' => $date(false, true, 'Received date'),
                    'expiry_date' => $date(false, false, 'Expiry date'),
                    'notes' => $area(),
                ],
                'list' => ['seed_name', 'variety', 'quantity', 'unit', 'total_cost', 'received_date'],
                'finance' => ['amount' => 'total_cost', 'category' => 'Seeds', 'date' => 'received_date'],
            ],
            'planting_records' => [
                'label' => 'Planting Records', 'group' => 'Crop Production', 'table' => 'planting_records',
                'permission' => 'crop.write', 'icon' => 'plant',
                'summary' => 'Planting dates, density, spacing and area for each crop.',
                'fields' => [
                    'crop_id' => self::fk('crops', true, 'Crop'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'seed_record_id' => self::fk('seed_records', false, 'Seed lot'),
                    'planting_date' => $date(true, true, 'Planting date'),
                    'area_ha' => $num(['label' => 'Area (ha)']),
                    'planting_density' => $num(['label' => 'Planting density (plants/ha)']),
                    'spacing_cm' => $text(false, ['label' => 'Spacing (cm)']),
                    'method' => $status(['direct' => 'Direct seeding', 'transplant' => 'Transplanting', 'broadcast' => 'Broadcast', 'row' => 'Row planting', 'other' => 'Other']),
                    'notes' => $area(),
                ],
                'list' => ['crop_id', 'field_id', 'planting_date', 'area_ha', 'planting_density', 'method'],
            ],
            'fertilizer_applications' => [
                'label' => 'Fertilizer & Manure', 'group' => 'Crop Production', 'table' => 'fertilizer_applications',
                'permission' => 'crop.write', 'icon' => 'fertilizer',
                'summary' => 'Fertilizer, manure and compost applications with cost.',
                'fields' => [
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'inventory_item_id' => self::fk('inventory', false, 'Inventory item'),
                    'application_date' => $date(true, true, 'Application date'),
                    'product' => $text(true),
                    'application_type' => $status(['fertilizer' => 'Fertilizer', 'manure' => 'Manure', 'compost' => 'Compost', 'foliar' => 'Foliar feed', 'lime' => 'Lime', 'other' => 'Other']),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'method' => $status(['broadcast' => 'Broadcast', 'banding' => 'Banding', 'side_dress' => 'Side dress', 'foliar' => 'Foliar', 'fertigation' => 'Fertigation', 'other' => 'Other']),
                    'cost' => $num(['label' => 'Cost']),
                    'applied_by' => self::fk('employees', false, 'Applied by'),
                    'notes' => $area(),
                ],
                'list' => ['product', 'crop_id', 'field_id', 'application_date', 'quantity', 'unit', 'cost'],
                'require_any' => ['crop_id', 'field_id'],
                'finance' => ['amount' => 'cost', 'category' => 'Fertiliser', 'date' => 'application_date'],
            ],
            'crop_growth_stages' => [
                'label' => 'Crop Growth Monitoring', 'group' => 'Crop Production', 'table' => 'crop_growth_stages',
                'permission' => 'crop.write', 'icon' => 'growth',
                'summary' => 'Stage observations from germination to maturity.',
                'fields' => [
                    'crop_id' => self::fk('crops', true, 'Crop'),
                    'stage' => $text(true, ['label' => 'Growth stage']),
                    'stage_date' => $date(true, true, 'Stage date'),
                    'notes' => $area(),
                ],
                'list' => ['crop_id', 'stage', 'stage_date', 'notes'],
            ],
            'spray_schedules' => [
                'label' => 'Spray Schedules', 'group' => 'Crop Protection', 'table' => 'spray_schedules',
                'permission' => 'crop.write', 'icon' => 'spray',
                'summary' => 'Planned crop-protection sprays and their status.',
                'fields' => [
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'planned_date' => $date(true, false, 'Planned date'),
                    'product' => $text(false),
                    'target' => $text(false, ['label' => 'Target pest/disease']),
                    'dose' => $text(false),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'status' => $status(['scheduled' => 'Scheduled', 'completed' => 'Completed', 'skipped' => 'Skipped']),
                    'notes' => $area(),
                ],
                'list' => ['crop_id', 'field_id', 'planned_date', 'product', 'target', 'status'],
                'require_any' => ['crop_id', 'field_id'],
            ],
            'chemical_applications' => [
                'label' => 'Chemical Applications', 'group' => 'Crop Protection', 'table' => 'chemical_applications',
                'permission' => 'crop.write', 'icon' => 'chemical',
                'summary' => 'Actual pesticide/chemical use, doses and cost.',
                'fields' => [
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'spray_schedule_id' => self::fk('spray_schedules', false, 'Spray schedule'),
                    'health_incident_id' => self::fk('health_incidents', false, 'Health incident'),
                    'inventory_item_id' => self::fk('inventory', false, 'Inventory item'),
                    'application_date' => $date(true, true, 'Application date'),
                    'product' => $text(true),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'cost' => $num(['label' => 'Cost']),
                    'applied_by' => self::fk('employees', false, 'Applied by'),
                    'notes' => $area(),
                ],
                'list' => ['product', 'crop_id', 'field_id', 'application_date', 'quantity', 'unit', 'cost'],
                'require_any' => ['crop_id', 'field_id'],
                'finance' => ['amount' => 'cost', 'category' => 'Chemicals & pesticides', 'date' => 'application_date'],
            ],
            'treatment_followups' => [
                'label' => 'Treatment Follow-ups', 'group' => 'Crop Protection', 'table' => 'treatment_followups',
                'permission' => 'pest.write', 'icon' => 'followup',
                'summary' => 'Effectiveness checks after a pest/disease treatment.',
                'fields' => [
                    'health_incident_id' => self::fk('health_incidents', true, 'Health incident'),
                    'followup_date' => $date(true, true, 'Follow-up date'),
                    'effectiveness' => $num(['label' => 'Effectiveness (1–5)', 'integer' => true, 'min' => '1', 'max' => '5']),
                    'status' => $status(['monitoring' => 'Monitoring', 'improved' => 'Improved', 'resolved' => 'Resolved', 'failed' => 'Failed']),
                    'notes' => $area(),
                ],
                'list' => ['health_incident_id', 'followup_date', 'effectiveness', 'status', 'notes'],
            ],

            // ---------------------------------------------------------
            // Livestock
            // ---------------------------------------------------------
            'animal_weights' => [
                'label' => 'Weight & Growth', 'group' => 'Livestock', 'table' => 'animal_weights',
                'permission' => 'livestock.write', 'icon' => 'weight',
                'summary' => 'Weight measurements and growth tracking per animal.',
                'fields' => [
                    'livestock_id' => self::fk('livestock', true, 'Animal'),
                    'weight_kg' => $num(['label' => 'Weight (kg)', 'required' => true, 'positive' => true, 'max' => '5000']),
                    'measured_date' => $date(true, true, 'Measured date'),
                    'notes' => $area(),
                ],
                'list' => ['livestock_id', 'weight_kg', 'measured_date', 'notes'],
            ],
            'breeding_records' => [
                'label' => 'Breeding Records', 'group' => 'Livestock', 'table' => 'breeding_records',
                'permission' => 'livestock.write', 'icon' => 'breeding',
                'summary' => 'Service dates, pregnancy checks and births.',
                'fields' => [
                    'livestock_id' => self::fk('livestock', true, 'Dam / female'),
                    'sire_id' => self::fk('livestock', false, 'Sire'),
                    'service_date' => $date(true, true, 'Service date'),
                    'method' => $status(['natural' => 'Natural', 'ai' => 'Artificial insemination', 'embryo_transfer' => 'Embryo transfer']),
                    'pregnancy_check_date' => $date(false, true, 'Pregnancy check date'),
                    'result' => $status(['pending' => 'Pending', 'pregnant' => 'Pregnant', 'not_pregnant' => 'Not pregnant', 'aborted' => 'Aborted']),
                    'expected_birth_date' => $date(false, false, 'Expected birth date'),
                    'actual_birth_date' => $date(false, true, 'Actual birth date'),
                    'offspring_count' => $num(['label' => 'Offspring count', 'integer' => true, 'max' => '50']),
                    'notes' => $area(),
                ],
                'list' => ['livestock_id', 'sire_id', 'service_date', 'result', 'expected_birth_date', 'offspring_count'],
            ],
            'production_records' => [
                'label' => 'Production Records', 'group' => 'Livestock', 'table' => 'production_records',
                'permission' => 'livestock.write', 'icon' => 'production',
                'summary' => 'Milk, eggs, wool and other output per animal.',
                'fields' => [
                    'livestock_id' => self::fk('livestock', true, 'Animal'),
                    'production_type' => ['type' => 'select', 'options' => ['milk' => 'Milk', 'eggs' => 'Eggs', 'wool' => 'Wool', 'honey' => 'Honey', 'meat' => 'Meat', 'other' => 'Other'], 'required' => true],
                    'quantity' => $num(['required' => true, 'positive' => true]),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'record_date' => $date(true, true, 'Record date'),
                    'quality' => $status(['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C', 'ungraded' => 'Ungraded']),
                    'notes' => $area(),
                ],
                'list' => ['livestock_id', 'production_type', 'quantity', 'unit', 'record_date', 'quality'],
            ],
            'mortality_records' => [
                'label' => 'Mortality Records', 'group' => 'Livestock', 'table' => 'mortality_records',
                'permission' => 'livestock.write', 'icon' => 'mortality',
                'summary' => 'Deaths, causes and diagnoses for mortality analysis.',
                'fields' => [
                    'livestock_id' => self::fk('livestock', true, 'Animal'),
                    'death_date' => $date(true, true, 'Death date'),
                    'cause' => $text(false),
                    'diagnosis' => $text(false),
                    'notes' => $area(),
                ],
                'list' => ['livestock_id', 'death_date', 'cause', 'diagnosis'],
                'hook' => 'markAnimalDead',
            ],
            'feed_records' => [
                'label' => 'Feed Records', 'group' => 'Livestock', 'table' => 'feed_records',
                'permission' => 'livestock.write', 'icon' => 'feed',
                'summary' => 'Feed issued, quantities and cost.',
                'fields' => [
                    'livestock_id' => self::fk('livestock', false, 'Animal / herd'),
                    'feed_date' => $date(true, true, 'Feed date'),
                    'feed_name' => $text(true, ['label' => 'Feed']),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'cost' => $num(['label' => 'Cost']),
                    'inventory_item_id' => self::fk('inventory', false, 'Inventory item'),
                    'notes' => $area(),
                ],
                'list' => ['feed_date', 'feed_name', 'livestock_id', 'quantity', 'unit', 'cost'],
                'finance' => ['amount' => 'cost', 'category' => 'Animal feed', 'date' => 'feed_date'],
            ],

            // ---------------------------------------------------------
            // Equipment
            // ---------------------------------------------------------
            'equipment_allocations' => [
                'label' => 'Equipment Allocation', 'group' => 'Equipment', 'table' => 'equipment_allocations',
                'permission' => 'equipment.write', 'icon' => 'alloc',
                'summary' => 'Which machine is assigned to which field and operator.',
                'fields' => [
                    'equipment_id' => self::fk('equipment', true, 'Equipment'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'employee_id' => self::fk('employees', false, 'Operator'),
                    'assigned_date' => $date(true, true, 'Assigned date'),
                    'released_date' => $date(false, true, 'Released date'),
                    'notes' => $area(),
                ],
                'list' => ['equipment_id', 'field_id', 'employee_id', 'assigned_date', 'released_date'],
            ],
            'fuel_records' => [
                'label' => 'Fuel Consumption', 'group' => 'Equipment', 'table' => 'fuel_records',
                'permission' => 'equipment.write', 'icon' => 'fuel',
                'summary' => 'Fuel issued to machinery, with odometer readings and cost.',
                'fields' => [
                    'equipment_id' => self::fk('equipment', true, 'Equipment'),
                    'fuel_date' => $date(true, true, 'Fuel date'),
                    'litres' => $num(['label' => 'Litres', 'required' => true, 'positive' => true, 'max' => '100000']),
                    'cost' => $num(['label' => 'Cost']),
                    'odometer' => $num(['label' => 'Odometer / hour meter']),
                    'notes' => $area(),
                ],
                'list' => ['equipment_id', 'fuel_date', 'litres', 'cost', 'odometer'],
                'finance' => ['amount' => 'cost', 'category' => 'Fuel', 'date' => 'fuel_date'],
                'hook' => 'updateEquipmentMeter',
            ],
            'equipment_hours' => [
                'label' => 'Operating Hours', 'group' => 'Equipment', 'table' => 'equipment_hours',
                'permission' => 'equipment.write', 'icon' => 'hours',
                'summary' => 'Machine hours worked and operators.',
                'fields' => [
                    'equipment_id' => self::fk('equipment', true, 'Equipment'),
                    'work_date' => $date(true, true, 'Work date'),
                    'hours' => $num(['label' => 'Hours', 'required' => true, 'positive' => true, 'max' => '24']),
                    'operator_id' => self::fk('employees', false, 'Operator'),
                    'notes' => $area(),
                ],
                'list' => ['equipment_id', 'work_date', 'hours', 'operator_id'],
            ],
            'equipment_repairs' => [
                'label' => 'Repair History', 'group' => 'Equipment', 'table' => 'equipment_repairs',
                'permission' => 'equipment.write', 'icon' => 'repair',
                'summary' => 'Breakdowns, repairs, downtime and repair cost.',
                'fields' => [
                    'equipment_id' => self::fk('equipment', true, 'Equipment'),
                    'repair_date' => $date(true, true, 'Repair date'),
                    'description' => $text(true),
                    'cost' => $num(['label' => 'Cost']),
                    'downtime_days' => $num(['label' => 'Downtime (days)', 'integer' => true, 'max' => '365']),
                    'vendor' => $text(false),
                    'notes' => $area(),
                ],
                'list' => ['equipment_id', 'repair_date', 'description', 'cost', 'downtime_days', 'vendor'],
                'finance' => ['amount' => 'cost', 'category' => 'Equipment repairs', 'date' => 'repair_date'],
                'hook' => 'markEquipmentServiced',
            ],
            'equipment_depreciation' => [
                'label' => 'Equipment Depreciation', 'group' => 'Equipment', 'table' => 'equipment_depreciation',
                'permission' => 'equipment.write', 'icon' => 'depreciation',
                'summary' => 'Depreciation basis and annual charge per machine.',
                'fields' => [
                    'equipment_id' => self::fk('equipment', true, 'Equipment'),
                    'method' => $status(['straight_line' => 'Straight line', 'reducing_balance' => 'Reducing balance', 'units_of_use' => 'Units of use']),
                    'purchase_value' => $num(['label' => 'Purchase value']),
                    'salvage_value' => $num(['label' => 'Salvage value']),
                    'useful_life_years' => $num(['label' => 'Useful life (years)', 'integer' => true, 'positive' => true, 'max' => '100']),
                    'annual_depreciation' => $num(['label' => 'Annual depreciation']),
                    'as_of_date' => $date(true, true, 'As of date'),
                    'notes' => $area(),
                ],
                'list' => ['equipment_id', 'method', 'purchase_value', 'salvage_value', 'annual_depreciation', 'as_of_date'],
            ],

            // ---------------------------------------------------------
            // Labour / tasks
            // ---------------------------------------------------------
            'tasks' => [
                'label' => 'Daily Tasks & Work Allocation', 'group' => 'Labour', 'table' => 'tasks',
                'permission' => 'labour.write', 'icon' => 'task',
                'summary' => 'Assign work to employees, track hours and labour cost.',
                'fields' => [
                    'farm_id' => self::fk('farms', false, 'Farm'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'livestock_id' => self::fk('livestock', false, 'Animal'),
                    'equipment_id' => self::fk('equipment', false, 'Equipment'),
                    'employee_id' => self::fk('employees', false, 'Assigned to'),
                    'title' => $text(true),
                    'description' => $area(false, 'Description'),
                    'due_date' => $date(false, false, 'Due date'),
                    'priority' => $status(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent']),
                    'status' => $status(['open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed', 'blocked' => 'Blocked', 'cancelled' => 'Cancelled']),
                    'estimated_hours' => $num(['label' => 'Estimated hours', 'max' => '24']),
                    'actual_hours' => $num(['label' => 'Actual hours', 'max' => '24']),
                    'labour_cost' => $num(['label' => 'Labour cost']),
                ],
                'list' => ['title', 'employee_id', 'due_date', 'priority', 'status', 'actual_hours', 'labour_cost'],
                'finance' => ['amount' => 'labour_cost', 'category' => 'Labour & wages', 'date' => 'due_date', 'when' => ['status' => ['completed']]],
                'hook' => 'stampTaskCompletion',
            ],

            // ---------------------------------------------------------
            // Harvest & post-harvest
            // ---------------------------------------------------------
            'harvest_schedules' => [
                'label' => 'Harvest Scheduling', 'group' => 'Harvest & Storage', 'table' => 'harvest_schedules',
                'permission' => 'harvest.write', 'icon' => 'schedule',
                'summary' => 'Planned harvest dates and expected quantities.',
                'fields' => [
                    'crop_id' => self::fk('crops', true, 'Crop'),
                    'field_id' => self::fk('fields', false, 'Field'),
                    'expected_date' => $date(true, false, 'Expected harvest date'),
                    'expected_quantity' => $num(['label' => 'Expected quantity']),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'status' => $status(['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled']),
                    'notes' => $area(),
                ],
                'list' => ['crop_id', 'field_id', 'expected_date', 'expected_quantity', 'unit', 'status'],
            ],
            'harvest_losses' => [
                'label' => 'Harvest Losses', 'group' => 'Harvest & Storage', 'table' => 'harvest_losses',
                'permission' => 'harvest.write', 'icon' => 'loss',
                'summary' => 'Post-harvest losses by cause (spillage, rot, pests).',
                'fields' => [
                    'harvest_id' => self::fk('harvest_records', true, 'Harvest'),
                    'loss_date' => $date(true, true, 'Loss date'),
                    'quantity' => $num(['required' => true, 'positive' => true]),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'cause' => $text(false),
                    'notes' => $area(),
                ],
                'list' => ['harvest_id', 'loss_date', 'quantity', 'unit', 'cause'],
            ],
            'packaging_records' => [
                'label' => 'Packaging', 'group' => 'Harvest & Storage', 'table' => 'packaging_records',
                'permission' => 'harvest.write', 'icon' => 'package',
                'summary' => 'Packs, bag weights and packaging cost.',
                'fields' => [
                    'harvest_id' => self::fk('harvest_records', true, 'Harvest'),
                    'packaging_date' => $date(true, true, 'Packaging date'),
                    'package_type' => $text(false, ['label' => 'Package type']),
                    'units' => $num(['label' => 'Number of packs', 'integer' => true]),
                    'unit_weight_kg' => $num(['label' => 'Unit weight (kg)']),
                    'cost' => $num(['label' => 'Cost']),
                    'notes' => $area(),
                ],
                'list' => ['harvest_id', 'packaging_date', 'package_type', 'units', 'unit_weight_kg', 'cost'],
                'finance' => ['amount' => 'cost', 'category' => 'Packaging', 'date' => 'packaging_date'],
            ],
            'quality_grades' => [
                'label' => 'Produce Quality & Grading', 'group' => 'Harvest & Storage', 'table' => 'quality_grades',
                'permission' => 'harvest.write', 'icon' => 'quality',
                'summary' => 'Grades, moisture and defect levels for harvested produce.',
                'fields' => [
                    'harvest_id' => self::fk('harvest_records', true, 'Harvest'),
                    'grade_date' => $date(true, true, 'Grade date'),
                    'grade' => ['type' => 'select', 'options' => ['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C', 'reject' => 'Reject'], 'required' => true],
                    'moisture_pct' => $num(['label' => 'Moisture %', 'max' => '100']),
                    'defects_pct' => $num(['label' => 'Defects %', 'max' => '100']),
                    'notes' => $area(),
                ],
                'list' => ['harvest_id', 'grade_date', 'grade', 'moisture_pct', 'defects_pct'],
                'hook' => 'syncHarvestGrade',
            ],
            'produce_dispatches' => [
                'label' => 'Dispatch Management', 'group' => 'Harvest & Storage', 'table' => 'produce_dispatches',
                'permission' => 'harvest.write', 'icon' => 'dispatch',
                'summary' => 'Produce leaving storage for a buyer or market.',
                'fields' => [
                    'harvest_id' => self::fk('harvest_records', false, 'Harvest'),
                    'stored_produce_id' => self::fk('stored_produce', false, 'Stored produce'),
                    'customer_id' => self::fk('customers', false, 'Customer'),
                    'dispatch_date' => $date(true, true, 'Dispatch date'),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'destination' => $text(false),
                    'vehicle' => $text(false),
                    'notes' => $area(),
                ],
                'list' => ['dispatch_date', 'harvest_id', 'customer_id', 'quantity', 'unit', 'destination'],
                'require_any' => ['harvest_id', 'stored_produce_id'],
            ],

            // ---------------------------------------------------------
            // Market, customers & sales
            // ---------------------------------------------------------
            'customers' => [
                'label' => 'Customers & Buyers', 'group' => 'Market & Sales', 'table' => 'customers',
                'permission' => 'sales.write', 'icon' => 'customer',
                'summary' => 'Buyer registry with contact details and type.',
                'fields' => [
                    'name' => $text(true),
                    'customer_type' => $status(['buyer' => 'Buyer', 'wholesaler' => 'Wholesaler', 'retailer' => 'Retailer', 'processor' => 'Processor', 'export' => 'Exporter', 'other' => 'Other']),
                    'phone' => $text(false),
                    'email' => ['type' => 'email', 'required' => false, 'validate' => 'email'],
                    'address' => $text(false),
                    'market_destination_id' => self::fk('market_destinations', false, 'Market destination'),
                    'notes' => $area(),
                ],
                'list' => ['name', 'customer_type', 'phone', 'email', 'address'],
            ],
            'market_destinations' => [
                'label' => 'Market Destinations', 'group' => 'Market & Sales', 'table' => 'market_destinations',
                'permission' => 'sales.write', 'icon' => 'market',
                'summary' => 'Where produce is sold: local, wholesale, export.',
                'fields' => [
                    'name' => $text(true),
                    'location' => $text(false),
                    'market_type' => $status(['local' => 'Local market', 'wholesale' => 'Wholesale market', 'export' => 'Export', 'contract' => 'Contract buyer', 'retail' => 'Retail']),
                    'distance_km' => $num(['label' => 'Distance (km)']),
                    'notes' => $area(),
                ],
                'list' => ['name', 'location', 'market_type', 'distance_km'],
            ],
            'sales_orders' => [
                'label' => 'Sales Orders', 'group' => 'Market & Sales', 'table' => 'sales_orders',
                'permission' => 'sales.write', 'icon' => 'order',
                'summary' => 'Orders from buyers, before invoicing.',
                'fields' => [
                    'customer_id' => self::fk('customers', true, 'Customer'),
                    'order_date' => $date(true, true, 'Order date'),
                    'delivery_date' => $date(false, false, 'Delivery date'),
                    'status' => $status(['open' => 'Open', 'confirmed' => 'Confirmed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled']),
                    'total_amount' => $num(['label' => 'Total amount']),
                    'notes' => $area(),
                ],
                'list' => ['customer_id', 'order_date', 'delivery_date', 'status', 'total_amount'],
            ],
            'sales_order_items' => [
                'label' => 'Sales Order Items', 'group' => 'Market & Sales', 'table' => 'sales_order_items',
                'permission' => 'sales.write', 'icon' => 'item',
                'summary' => 'Line items for a sales order.',
                'fields' => [
                    'order_id' => self::fk('sales_orders', true, 'Sales order'),
                    'crop_id' => self::fk('crops', false, 'Crop'),
                    'description' => $text(true),
                    'quantity' => $num(['positive' => true]),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'unit_price' => $num(['label' => 'Unit price']),
                    'amount' => $num(['label' => 'Amount']),
                ],
                'list' => ['order_id', 'description', 'quantity', 'unit', 'unit_price', 'amount'],
            ],
            'invoices' => [
                'label' => 'Invoices', 'group' => 'Market & Sales', 'table' => 'invoices',
                'permission' => 'sales.write', 'icon' => 'invoice',
                'summary' => 'Customer invoices, amounts paid and status.',
                'fields' => [
                    'invoice_no' => $text(false, ['label' => 'Invoice number']),
                    'sales_order_id' => self::fk('sales_orders', false, 'Sales order'),
                    'customer_id' => self::fk('customers', false, 'Customer'),
                    'invoice_date' => $date(true, true, 'Invoice date'),
                    'due_date' => $date(false, false, 'Due date'),
                    'total_amount' => $num(['label' => 'Total amount']),
                    'amount_paid' => $num(['label' => 'Amount paid']),
                    'status' => $status(['unpaid' => 'Unpaid', 'partial' => 'Part paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled']),
                    'notes' => $area(),
                ],
                'list' => ['invoice_no', 'customer_id', 'invoice_date', 'due_date', 'total_amount', 'amount_paid', 'status'],
                'hook' => 'stampInvoice',
            ],
            'invoice_items' => [
                'label' => 'Invoice Items', 'group' => 'Market & Sales', 'table' => 'invoice_items',
                'permission' => 'sales.write', 'icon' => 'item',
                'summary' => 'Line items for an invoice.',
                'fields' => [
                    'invoice_id' => self::fk('invoices', true, 'Invoice'),
                    'description' => $text(true),
                    'quantity' => $num(['positive' => true]),
                    'unit_price' => $num(['label' => 'Unit price']),
                    'amount' => $num(['label' => 'Amount']),
                ],
                'list' => ['invoice_id', 'description', 'quantity', 'unit_price', 'amount'],
                'hook' => 'recalculateInvoice',
            ],
            'contracts' => [
                'label' => 'Contracts & Agreements', 'group' => 'Market & Sales', 'table' => 'contracts',
                'permission' => 'sales.write', 'icon' => 'contract',
                'summary' => 'Buyer and supplier agreements with validity dates.',
                'fields' => [
                    'contract_type' => ['type' => 'select', 'options' => ['buyer' => 'Buyer contract', 'supplier' => 'Supplier agreement', 'lease' => 'Lease', 'service' => 'Service'], 'required' => true],
                    'party_name' => $text(true, ['label' => 'Party name']),
                    'customer_id' => self::fk('customers', false, 'Customer'),
                    'supplier_id' => self::fk('suppliers', false, 'Supplier'),
                    'title' => $text(true),
                    'start_date' => $date(false, false, 'Start date'),
                    'end_date' => $date(false, false, 'End date'),
                    'value' => $num(['label' => 'Contract value']),
                    'status' => $status(['draft' => 'Draft', 'active' => 'Active', 'completed' => 'Completed', 'expired' => 'Expired', 'cancelled' => 'Cancelled']),
                    'terms' => $area(false, 'Terms'),
                    'notes' => $area(),
                ],
                'list' => ['title', 'contract_type', 'party_name', 'start_date', 'end_date', 'value', 'status'],
            ],

            // ---------------------------------------------------------
            // Supplier & procurement
            // ---------------------------------------------------------
            'supplier_quotations' => [
                'label' => 'Supplier Quotations', 'group' => 'Supplier & Procurement', 'table' => 'supplier_quotations',
                'permission' => 'procurement.write', 'icon' => 'quote',
                'summary' => 'Quotes received from suppliers, with validity.',
                'fields' => [
                    'supplier_id' => self::fk('suppliers', true, 'Supplier'),
                    'item_name' => $text(true),
                    'quantity' => $num(),
                    'unit' => $text(false, ['label' => 'Unit']),
                    'unit_price' => $num(['label' => 'Unit price']),
                    'quote_date' => $date(true, true, 'Quote date'),
                    'valid_until' => $date(false, false, 'Valid until'),
                    'status' => $status(['received' => 'Received', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'expired' => 'Expired']),
                    'notes' => $area(),
                ],
                'list' => ['supplier_id', 'item_name', 'quantity', 'unit_price', 'quote_date', 'valid_until', 'status'],
            ],
            'supplier_reviews' => [
                'label' => 'Supplier Performance', 'group' => 'Supplier & Procurement', 'table' => 'supplier_reviews',
                'permission' => 'procurement.write', 'icon' => 'review',
                'summary' => 'Score suppliers on quality, delivery, price and reliability.',
                'fields' => [
                    'supplier_id' => self::fk('suppliers', true, 'Supplier'),
                    'review_date' => $date(true, true, 'Review date'),
                    'quality_score' => $num(['label' => 'Quality (1–5)', 'integer' => true, 'min' => '1', 'max' => '5']),
                    'delivery_score' => $num(['label' => 'Delivery (1–5)', 'integer' => true, 'min' => '1', 'max' => '5']),
                    'price_score' => $num(['label' => 'Price (1–5)', 'integer' => true, 'min' => '1', 'max' => '5']),
                    'reliability_score' => $num(['label' => 'Reliability (1–5)', 'integer' => true, 'min' => '1', 'max' => '5']),
                    'notes' => $area(),
                ],
                'list' => ['supplier_id', 'review_date', 'quality_score', 'delivery_score', 'price_score', 'reliability_score'],
                'hook' => 'updateSupplierRating',
            ],
        ];
        return self::$cache;
    }

    public static function get(?string $key): ?array
    {
        if ($key === null || $key === '') return null;
        $all = self::all();
        return $all[$key] ?? null;
    }

    /** group label => list of record keys */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::all() as $key => $def) {
            $group = $def['group'] ?? 'Other';
            $groups[$group][] = $key;
        }
        return $groups;
    }

    /**
     * Build a foreign-key field definition.
     * Table names are trusted constants declared here, never user input.
     */
    private static function fk(string $table, bool $required = false, string $label = null): array
    {
        $display = [
            'fields'            => "COALESCE({a}.name, 'Field')",
            'crops'             => "COALESCE({a}.name, 'Crop')",
            'farms'             => "COALESCE({a}.name, 'Farm')",
            'livestock'         => "COALESCE(NULLIF({a}.tag_no, ''), {a}.type, 'Animal #' || {a}.id)",
            'equipment'         => "COALESCE({a}.name, 'Equipment')",
            'employees'         => "COALESCE({a}.name, 'Employee')",
            'inventory'         => "COALESCE({a}.name, 'Item')",
            'suppliers'         => "COALESCE({a}.name, 'Supplier')",
            'customers'         => "COALESCE({a}.name, 'Customer')",
            'health_incidents'  => "'Incident #' || {a}.id || ' · ' || COALESCE({a}.issue_name, '')",
            'harvest_records'   => "'Harvest #' || {a}.id || ' · ' || COALESCE({a}.harvest_date::text, '')",
            'stored_produce'    => "'Stored #' || {a}.id || ' · ' || COALESCE({a}.grade, '')",
            'sales_orders'      => "'Order #' || {a}.id || ' · ' || COALESCE({a}.order_date::text, '')",
            'invoices'          => "COALESCE({a}.invoice_no, 'Invoice #' || {a}.id)",
            'spray_schedules'   => "'Spray #' || {a}.id || ' · ' || COALESCE({a}.planned_date::text, '')",
            'seed_records'      => "COALESCE({a}.seed_name, 'Seed #' || {a}.id)",
            'market_destinations' => "COALESCE({a}.name, 'Market')",
        ][$table] ?? "('#' || {a}.id)";

        $options = [
            'fields'            => "SELECT id, name FROM fields ORDER BY name",
            'crops'             => "SELECT id, name FROM crops ORDER BY name",
            'farms'             => "SELECT id, name FROM farms ORDER BY name",
            'livestock'         => "SELECT id, COALESCE(NULLIF(tag_no, ''), type, CONCAT('Animal #', id)) FROM livestock WHERE COALESCE(status, 'active') = 'active' ORDER BY type, tag_no NULLS LAST, id",
            'equipment'         => "SELECT id, name FROM equipment ORDER BY name",
            'employees'         => "SELECT id, name FROM employees ORDER BY name",
            'inventory'         => "SELECT id, name FROM inventory ORDER BY name",
            'suppliers'         => "SELECT id, name FROM suppliers ORDER BY name",
            'customers'         => "SELECT id, name FROM customers ORDER BY name",
            'health_incidents'  => "SELECT id, CONCAT('Incident #', id, ' · ', issue_name) FROM health_incidents ORDER BY id DESC",
            'harvest_records'   => "SELECT id, CONCAT('Harvest #', id, ' · ', COALESCE(harvest_date::text, '')) FROM harvest_records ORDER BY id DESC",
            'stored_produce'    => "SELECT id, CONCAT('Stored #', id, ' · ', COALESCE(grade, '')) FROM stored_produce ORDER BY id DESC",
            'sales_orders'      => "SELECT id, CONCAT('Order #', id, ' · ', COALESCE(order_date::text, '')) FROM sales_orders ORDER BY id DESC",
            'invoices'          => "SELECT id, COALESCE(invoice_no, CONCAT('Invoice #', id)) FROM invoices ORDER BY id DESC",
            'spray_schedules'   => "SELECT id, CONCAT('Spray #', id, ' · ', COALESCE(planned_date::text, '')) FROM spray_schedules ORDER BY id DESC",
            'seed_records'      => "SELECT id, COALESCE(seed_name, CONCAT('Seed #', id)) FROM seed_records ORDER BY id DESC",
            'market_destinations' => "SELECT id, name FROM market_destinations ORDER BY name",
        ][$table] ?? "SELECT id, CONCAT('#', id) FROM {$table} ORDER BY id DESC";

        return [
            'type'        => 'fk',
            'table'       => $table,
            'required'    => $required,
            'label'       => $label,
            'options_sql' => $options,
            'display'     => $display,
        ];
    }
}
