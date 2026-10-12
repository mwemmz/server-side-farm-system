<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class PestModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'pest_records'); }

    /** Pest/disease records linked to a crop (shown inside the Crop module). */
    public function getCropPests() {
        return $this->pdo->query(
            "SELECT p.*, c.name AS crop_name
             FROM pest_records p
             JOIN crops c ON c.id = p.crop_id
             WHERE p.crop_id IS NOT NULL
             ORDER BY p.detected_date DESC NULLS LAST, p.id DESC"
        )->fetchAll();
    }

    /** Pest/disease records linked to an animal (shown inside the Livestock module). */
    public function getLivestockPests() {
        return $this->pdo->query(
            "SELECT p.*, l.type AS animal_type, l.breed AS animal_breed, l.tag_no
             FROM pest_records p
             JOIN livestock l ON l.id = p.livestock_id
             WHERE p.livestock_id IS NOT NULL
             ORDER BY p.detected_date DESC NULLS LAST, p.id DESC"
        )->fetchAll();
    }
}
?>
