<?php
class LivestockModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllAnimals() {
        return $this->pdo->query("SELECT * FROM livestock ORDER BY type, tag_no NULLS LAST, id")->fetchAll();
    }
    /** Animals still on the farm (not sold/dead/culled) — for every picker. */
    public function getActiveAnimals() {
        return $this->pdo->query("SELECT * FROM livestock WHERE COALESCE(status,'active') = 'active' ORDER BY type, tag_no NULLS LAST, id")->fetchAll();
    }
    public function getAnimal($id) {
        $stmt = $this->pdo->prepare("SELECT l.*, f.name AS farm_name FROM livestock l LEFT JOIN farms f ON f.id = l.farm_id WHERE l.id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch() ?: null;
    }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }

    /** Tag prefix for a species label (COW, GOAT, SHP, PIG, CHK; else first 3 letters). */
    public function tagPrefix($type) {
        switch ($this->speciesKey($type)) {
            case 'cattle':  return 'COW';
            case 'goat':    return 'GOAT';
            case 'sheep':   return 'SHP';
            case 'pig':     return 'PIG';
            case 'poultry': return 'CHK';
        }
        $l = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $type));
        return $l === '' ? 'ANM' : substr($l, 0, 3);
    }

    /** Next free PREFIX-NNN for a farm (highest used number + 1, so numbers are never reused). */
    public function nextTag($farmId, $type) {
        $prefix = $this->tagPrefix($type);
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(substring(tag_no from '^' || ? || '-([0-9]+)$')::int), 0)
             FROM livestock WHERE COALESCE(farm_id, 0) = ? AND tag_no ~ ('^' || ? || '-[0-9]+$')"
        );
        $stmt->execute([$prefix, (int) $farmId, $prefix]);
        return $prefix . '-' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Valid tag: letters, digits, dash, space; 1-30 chars. */
    public static function tagValid($tag) {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{0,29}$/', $tag);
    }

    public function tagTaken($farmId, $tag, $exceptId = 0) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM livestock WHERE COALESCE(farm_id,0) = ? AND lower(tag_no) = lower(?) AND id <> ? LIMIT 1");
        $stmt->execute([(int) $farmId, $tag, (int) $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Insert an animal. An empty tag is generated under an advisory lock so two
     * simultaneous adds can never get the same number. Returns the tag used.
     */
    public function createAnimal($farm_id, $type, $breed, $dob, $tag = '', $name = '', $sex = '', $notes = '') {
        $own = $this->pdo->inTransaction() ? false : $this->pdo->beginTransaction();
        try {
            if ($tag === '') {
                $this->pdo->prepare("SELECT pg_advisory_xact_lock(hashtext(?))")->execute(['animal-tag:' . (int) $farm_id . ':' . $this->tagPrefix($type)]);
                $tag = $this->nextTag($farm_id, $type);
            }
            $stmt = $this->pdo->prepare("INSERT INTO livestock (farm_id, type, breed, dob, tag_no, name, sex, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$farm_id, $type, $breed, $dob, $tag, $name !== '' ? $name : null, $sex !== '' ? $sex : null, $notes !== '' ? $notes : null]);
            if ($own) $this->pdo->commit();
            $this->lastTag = $tag;
            return true;
        } catch (\Throwable $e) {
            if ($own && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    public $lastTag = '';

    public function updateAnimal($id, array $d) {
        $stmt = $this->pdo->prepare("UPDATE livestock SET farm_id = ?, type = ?, breed = ?, dob = ?, tag_no = ?, name = ?, sex = ?, status = ?, notes = ? WHERE id = ?");
        return $stmt->execute([$d['farm_id'], $d['type'], $d['breed'], $d['dob'], $d['tag_no'],
            $d['name'] !== '' ? $d['name'] : null, $d['sex'] !== '' ? $d['sex'] : null, $d['status'],
            $d['notes'] !== '' ? $d['notes'] : null, (int) $id]);
    }

    /** Human label used in every picker/list: "COW-007 · Brahman · Bella". */
    public static function label(array $a) {
        $tag = trim((string) ($a['tag_no'] ?? ''));
        $parts = [$tag !== '' ? $tag : ucfirst((string) ($a['type'] ?? 'Animal')) . ' #' . (int) ($a['id'] ?? 0)];
        if (!empty($a['breed'])) $parts[] = $a['breed'];
        if (!empty($a['name'])) $parts[] = $a['name'];
        return implode(' · ', $parts);
    }

    /** Age as "2y 3m" (or "5m" / "3w"). */
    public static function age($dob) {
        if (!$dob) return '—';
        try { $d = (new DateTime($dob))->diff(new DateTime('today')); } catch (\Throwable $e) { return '—'; }
        if ($d->invert) return '—';
        if ($d->y > 0) return $d->y . 'y ' . $d->m . 'm';
        if ($d->m > 0) return $d->m . 'm';
        return max(1, (int) floor($d->days / 7)) . 'w';
    }

    public function getVaccinations() {
        return $this->pdo->query(
            "SELECT v.*, l.type AS animal_type, l.breed AS animal_breed, l.tag_no AS animal_tag, l.name AS animal_name
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
        $wd = $data['withdrawal_days'] ?? null;
        $declared = function ($v) { return $v !== null && $v !== ''; };
        return $stmt->execute([
            (int) $data['livestock_id'],
            $data['treatment_name'],
            $data['treatment_date'],
            $data['dose'] ?? null,
            $data['dose_unit'] ?? null,
            $declared($wd) ? (int) $wd : null,
            $data['withdrawal_ends'] ?? null,
            $declared($data['inventory_item_id'] ?? null) ? (int) $data['inventory_item_id'] : null,
            $declared($data['quantity_used'] ?? null) ? (float) $data['quantity_used'] : null,
            $declared($data['cost'] ?? null) ? (float) $data['cost'] : null,
            $data['notes'] ?? null,
        ]);
    }

    public function getTreatments() {
        return $this->pdo->query(
            "SELECT t.*, l.type AS animal_type, l.breed AS animal_breed, l.tag_no AS animal_tag, l.name AS animal_name
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