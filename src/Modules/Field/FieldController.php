<?php
require_once __DIR__ . '/FieldModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/GeoJsonHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';

class FieldController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new FieldModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $boundary = GeoJsonHelper::normalise($data['boundary_geojson'] ?? null);
            if ($boundary['error'] !== null) {
                return ['errors' => ['boundary_geojson' => $boundary['error']], 'data' => $data, 'records' => $this->model->getAll()];
            }

            // A drawn boundary is authoritative for the area when no manual size
            // was supplied; otherwise both are stored (manual size wins for
            // planning, computed hectares for map maths).
            if (($data['size'] ?? '') === '' && $boundary['hectares'] !== null && $boundary['hectares'] > 0) {
                $data['size'] = (string) $boundary['hectares'];
            }

            $errors = ValidationHelper::validateRequired(['farm_id', 'name'], $data);

            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }

            if (($data['size'] ?? '') === '') {
                $errors['size'] = 'Size is required (or draw a boundary).';
            } elseif (!is_numeric($data['size'])) {
                $errors['size'] = "Size must be a number.";
            } elseif ((float) $data['size'] <= 0) {
                $errors['size'] = "Size must be greater than 0.";
            }

            if (!empty($data['latitude']) && (!is_numeric($data['latitude']) || (float) $data['latitude'] < -90 || (float) $data['latitude'] > 90)) {
                $errors['latitude'] = 'Latitude must be between -90 and 90.';
            }
            if (!empty($data['longitude']) && (!is_numeric($data['longitude']) || (float) $data['longitude'] < -180 || (float) $data['longitude'] > 180)) {
                $errors['longitude'] = 'Longitude must be between -180 and 180.';
            }

            if (empty($errors)) {
                $centroid = $boundary['centroid'];
                $row = [
                    'farm_id'   => (int) $data['farm_id'],
                    'name'      => trim($data['name']),
                    'size'      => $data['size'],
                    'soil_type' => $data['soil_type'] ?? null,
                ];
                if ($boundary['geojson'] !== null) {
                    $row['boundary_geojson']  = $boundary['geojson'];
                    $row['computed_hectares'] = $boundary['hectares'];
                }
                if (!empty($data['latitude']) && is_numeric($data['latitude'])) {
                    $row['latitude'] = (float) $data['latitude'];
                } elseif ($centroid) {
                    $row['latitude'] = $centroid['lat'];
                }
                if (!empty($data['longitude']) && is_numeric($data['longitude'])) {
                    $row['longitude'] = (float) $data['longitude'];
                } elseif ($centroid) {
                    $row['longitude'] = $centroid['lng'];
                }
                try {
                    if ($this->model->create($row)) {
                        $id = $this->model->lastId();
                        AuditHelper::log($this->pdo, 'human', 'field_created', 'field', $id, [
                            'name'      => $row['name'],
                            'farm_id'   => $row['farm_id'],
                            'hectares'  => $boundary['hectares'],
                            'boundary'  => $boundary['geojson'] !== null,
                        ], (int) ($_SESSION['user_id'] ?? 0) ?: null);
                        SessionHelper::setFlash('success', 'Field added successfully!');
                        SessionHelper::flashCreated('Field', $id, trim($data['name']), [
                            'Size (ha)'      => $data['size'],
                            'Boundary area'  => $boundary['hectares'] !== null ? GeoJsonHelper::formatHectares($boundary['hectares']) : 'Not drawn',
                        ]);
                        header('Location: index.php?module=Field&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add field.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save field: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    /**
     * Save / replace / clear a field boundary from the map editor.
     * Always POST, always re-rendered through a redirect so the browser can't
     * re-submit a stale polygon.
     */
    public function boundary() {
        $fieldId = ValidationHelper::id($_POST['field_id'] ?? null);
        $back = 'index.php?module=Field';
        if ($fieldId) {
            $stmt = $this->pdo->prepare("SELECT farm_id FROM fields WHERE id = ?");
            $stmt->execute([$fieldId]);
            $farmId = $stmt->fetchColumn();
            if ($farmId !== false) $back .= '&farm_id=' . (int) $farmId;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . $back);
            exit;
        }
        if (!$fieldId) {
            SessionHelper::setFlash('error', 'Pick a field before saving a boundary.');
            header('Location: ' . $back);
            exit;
        }

        if (!empty($_POST['clear_boundary'])) {
            $stmt = $this->pdo->prepare("UPDATE fields SET boundary_geojson = NULL, computed_hectares = NULL WHERE id = ?");
            $stmt->execute([$fieldId]);
            AuditHelper::log($this->pdo, 'human', 'field_boundary_cleared', 'field', $fieldId, null, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            SessionHelper::setFlash('success', 'Field boundary cleared.');
            header('Location: ' . $back);
            exit;
        }

        $boundary = GeoJsonHelper::normalise($_POST['boundary_geojson'] ?? null);
        if ($boundary['error'] !== null || $boundary['geojson'] === null) {
            SessionHelper::setFlash('error', $boundary['error'] ?: 'Draw at least three points before saving.');
            header('Location: ' . $back);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE fields
                 SET boundary_geojson = ?, computed_hectares = ?, size = ?,
                     latitude = COALESCE(latitude, ?), longitude = COALESCE(longitude, ?)
                 WHERE id = ?"
            );
            $centroid = $boundary['centroid'];
            $stmt->execute([
                $boundary['geojson'],
                $boundary['hectares'],
                $boundary['hectares'],
                $centroid['lat'] ?? null,
                $centroid['lng'] ?? null,
                $fieldId,
            ]);
            AuditHelper::log($this->pdo, 'human', 'field_boundary_saved', 'field', $fieldId, [
                'hectares' => $boundary['hectares'],
            ], (int) ($_SESSION['user_id'] ?? 0) ?: null);
            SessionHelper::setFlash('success', 'Boundary saved — ' . GeoJsonHelper::formatHectares($boundary['hectares']) . '.');
        } catch (PDOException $e) {
            error_log('Field boundary save failed: ' . $e->getMessage());
            SessionHelper::setFlash('error', 'Could not save that boundary. Try a smaller polygon.');
        }
        header('Location: ' . $back);
        exit;
    }
}
?>
