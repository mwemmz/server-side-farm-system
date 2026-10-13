<?php
class LivestockModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllAnimals() { return $this->pdo->query("SELECT * FROM livestock")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function createAnimal($farm_id, $type, $breed, $dob) {
        $stmt = $this->pdo->prepare("INSERT INTO livestock (farm_id, type, breed, dob) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $type, $breed, $dob]);
    }
    public function getVaccinations() {
        return $this->pdo->query(
            "SELECT v.*, l.type AS animal_type, l.breed AS animal_breed
             FROM vaccinations v
             JOIN livestock l ON l.id = v.livestock_id
             ORDER BY v.vaccination_date DESC"
        )->fetchAll();
    }
    public function addVaccination($livestock_id, $vaccine_name, $vaccination_date, $next_due_date, $extra = []) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vaccinations
                (livestock_id, vaccine_name, vaccination_date, next_due_date, dose_number, batch_number, cost, vaccine_catalog_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $livestock_id,
            $vaccine_name,
            $vaccination_date,
            $next_due_date,
            $extra['dose_number'] ?? null,
            $extra['batch_number'] ?? null,
            $extra['cost'] ?? null,
            $extra['vaccine_catalog_id'] ?? null,
        ]);
    }

    /** Species -> [name => interval_days] from the vaccine catalogue. */
    public function getVaccineCatalog() {
        return $this->pdo->query("SELECT * FROM vaccine_catalog ORDER BY species, name")->fetchAll();
    }

    public function getVaccineCatalogByName($name) {
        $stmt = $this->pdo->prepare("SELECT * FROM vaccine_catalog WHERE name = ? ORDER BY species LIMIT 1");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    /** Animals grouped for batch vaccination (all animals, with species). */
    public function getAnimalsWithSpecies() {
        return $this->pdo->query("SELECT * FROM livestock ORDER BY type, id")->fetchAll();
    }

    /** Normalise a species label (from livestock.type or the catalogue) to a fixed key. */
    public function speciesKey($s) {
        $t = strtolower(trim((string) $s));
        if (in_array($t, ['cattle', 'cow', 'cows', 'bull', 'bulls', 'bovine', 'heifer', 'heifers', 'calf', 'calves'], true)) return 'cattle';
        if (in_array($t, ['goat', 'goats', 'caprine', 'kid', 'kids'], true)) return 'goat';
        if (in_array($t, ['sheep', 'ewe', 'ewes', 'ram', 'rams', 'ovine', 'lamb', 'lambs'], true)) return 'sheep';
        if (in_array($t, ['pig', 'pigs', 'swine', 'porcine', 'hog', 'hogs', 'sow', 'sows'], true)) return 'pig';
        if (in_array($t, ['poultry', 'chicken', 'chickens', 'hen', 'hens', 'rooster', 'layer', 'layers', 'broiler', 'broilers', 'duck', 'ducks', 'duckling', 'turkey', 'turkeys', 'goose', 'geese', 'guineafowl'], true)) return 'poultry';
        return $t;
    }

    /** map of livestock id -> type for the given ids (missing ids are omitted). */
    public function getAnimalTypes(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, type FROM livestock WHERE id IN ($in)");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[(int) $r['id']] = (string) $r['type'];
        }
        return $map;
    }

    public function getAnimalType($id) {
        $stmt = $this->pdo->prepare("SELECT type FROM livestock WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Vaccine-catalogue rows for a vaccine name, checked against an animal's
     * species. 'match' is false when every catalogue entry targets a different
     * species (e.g. Newcastle on a cow). When the name isn't in the catalogue
     * at all it is treated as a free-text vaccine and always matches.
     */
    public function vaccineForSpecies($name, $species) {
        $stmt = $this->pdo->prepare("SELECT * FROM vaccine_catalog WHERE name = ? ORDER BY species");
        $stmt->execute([$name]);
        $rows = $stmt->fetchAll();
        if (!$rows) return ['row' => null, 'match' => true, 'species' => []];
        $key = $this->speciesKey($species);
        $speciesList = [];
        $first = null;
        foreach ($rows as $r) {
            $speciesList[] = (string) $r['species'];
            if ($first === null) $first = $r;
            if ($this->speciesKey($r['species']) === $key) return ['row' => $r, 'match' => true, 'species' => $speciesList];
        }
        return ['row' => $first, 'match' => false, 'species' => $speciesList];
    }

    /**
     * Vaccinate many animals in one transaction (one vaccine/date for the batch).
     * Returns the number of animals recorded, or false on failure.
     */
    public function batchVaccinate(array $animalIds, $vaccineName, $date, $nextDue, $extra = []) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vaccinations
                (livestock_id, vaccine_name, vaccination_date, next_due_date, batch_number, vaccine_catalog_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        try {
            $this->pdo->beginTransaction();
            $count = 0;
            foreach ($animalIds as $id) {
                $stmt->execute([
                    (int) $id,
                    $vaccineName,
                    $date,
                    $nextDue,
                    $extra['batch_number'] ?? null,
                    $extra['vaccine_catalog_id'] ?? null,
                ]);
                $count++;
            }
            $this->pdo->commit();
            return $count;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Batch vaccination failed: ' . $e->getMessage());
            return false;
        }
    }

    public function addTreatment($data) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO animal_treatments
                (livestock_id, treatment_name, treatment_date, dose, dose_unit, withdrawal_days, withdrawal_ends,
                 inventory_item_id, quantity_used, cost, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            (int) $data['livestock_id'],
            $data['treatment_name'],
            $data['treatment_date'],
            $data['dose'] ?? null,
            $data['dose_unit'] ?? null,
            $data['withdrawal_days'] !== null && $data['withdrawal_days'] !== '' ? (int) $data['withdrawal_days'] : null,
            $data['withdrawal_ends'] ?? null,
            $data['inventory_item_id'] !== null && $data['inventory_item_id'] !== '' ? (int) $data['inventory_item_id'] : null,
            $data['quantity_used'] !== null && $data['quantity_used'] !== '' ? (float) $data['quantity_used'] : null,
            $data['cost'] !== null && $data['cost'] !== '' ? (float) $data['cost'] : null,
            $data['notes'] ?? null,
        ]);
    }

    public function getTreatments() {
        return $this->pdo->query(
            "SELECT t.*, l.type AS animal_type, l.breed AS animal_breed
             FROM animal_treatments t
             JOIN livestock l ON l.id = t.livestock_id
             ORDER BY t.treatment_date DESC, t.id DESC"
        )->fetchAll();
    }

    /** Animals currently inside a withdrawal window (for sale/slaughter warnings). */
    public function getActiveWithdrawals() {
        return $this->pdo->query(
            "SELECT livestock_id, MAX(withdrawal_ends) AS until
             FROM animal_treatments
             WHERE withdrawal_ends IS NOT NULL AND withdrawal_ends >= CURRENT_DATE
             GROUP BY livestock_id"
        )->fetchAll();
    }
}