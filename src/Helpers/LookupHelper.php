<?php
// Shared reference-data helper (Phase 1).
// Every "pick or type" dropdown is backed by rows in lookup_options.
class LookupHelper {

    /**
     * Fetch value => label options for a category.
     * $filter supports: ['parent' => 'Cattle'] to restrict rows whose
     * meta->>'species' (or meta->>$parentKey) matches.
     */
    public static function options($pdo, $category, $filter = []) {
        $sql = "SELECT value, label FROM lookup_options WHERE category = ? AND is_active = TRUE";
        $args = [$category];
        if (!empty($filter['parent']) && !empty($filter['parent_key'])) {
            $sql .= " AND (meta->>? = ? OR meta IS NULL)";
            $args[] = $filter['parent_key'];
            $args[] = $filter['parent'];
        } elseif (!empty($filter['parent'])) {
            $sql .= " AND meta->>'species' = ?";
            $args[] = $filter['parent'];
        }
        $sql .= " ORDER BY sort_order, label";
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[$row['value']] = $row['label'];
        }
        return $out;
    }

    /** Options as a JSON-friendly list of {value,label}. */
    public static function listOptions($pdo, $category, $filter = []) {
        $out = [];
        foreach (self::options($pdo, $category, $filter) as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }
        return $out;
    }

    /** Human label for a stored value, falling back to the raw value. */
    public static function label($pdo, $category, $value) {
        if ($value === null || $value === '') return '';
        $st = $pdo->prepare("SELECT label FROM lookup_options WHERE category = ? AND value = ? LIMIT 1");
        $st->execute([$category, $value]);
        $label = $st->fetchColumn();
        return $label !== false ? $label : $value;
    }

    /** True when a value exists (case-insensitively) in a category. */
    public static function exists($pdo, $category, $value) {
        if ($value === null) return false;
        $st = $pdo->prepare("SELECT 1 FROM lookup_options WHERE category = ? AND LOWER(value) = LOWER(?) AND is_active = TRUE LIMIT 1");
        $st->execute([$category, trim((string) $value)]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Accept/remember a custom value on an open list.
     * Trims, length-limits, strips control chars, de-duplicates case-insensitively
     * and stores a new row with needs_review = TRUE. Returns the canonical value
     * to save on the record (the existing one when a case-insensitive match exists).
     */
    public static function remember($pdo, $category, $value, $meta = null) {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value));
        if ($value === '') return '';
        if (function_exists('mb_substr')) { $value = mb_substr($value, 0, 80); } else { $value = substr($value, 0, 80); }

        $st = $pdo->prepare("SELECT value FROM lookup_options WHERE category = ? AND LOWER(value) = LOWER(?) LIMIT 1");
        $st->execute([$category, $value]);
        $existing = $st->fetchColumn();
        if ($existing !== false) return $existing;

        try {
            $ins = $pdo->prepare(
                "INSERT INTO lookup_options (category, value, label, sort_order, meta, needs_review)
                 VALUES (?, ?, ?, 900, ?, TRUE) ON CONFLICT (category, value) DO NOTHING"
            );
            $ins->execute([$category, $value, $value, $meta ? json_encode($meta) : null]);
        } catch (PDOException $e) {
            error_log('Lookup remember failed: ' . $e->getMessage());
        }
        return $value;
    }
}
?>
