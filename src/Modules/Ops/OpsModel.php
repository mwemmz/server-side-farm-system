<?php
/**
 * OpsModel — generic data access for the metadata-driven operational records.
 *
 * Table and column names always come from OpsRegistry (trusted configuration),
 * never from request input. Every value is bound through PDO placeholders.
 */
class OpsModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function lastId()
    {
        return (int) $this->pdo->lastInsertId();
    }

    /** Record count for the landing grid. */
    public function count(array $def): int
    {
        $table = $def['table'];
        try {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Latest records, with readable labels for every foreign key that appears
     * in the definition's list columns.
     */
    public function all(array $def, int $limit = 200): array
    {
        $sql = 'SELECT m.*';
        $joins = [];
        $i = 0;
        foreach ($def['fields'] as $col => $field) {
            if (($field['type'] ?? '') !== 'fk') continue;
            $alias = 'j' . $i++;
            $display = str_replace('{a}', $alias, (string) ($field['display'] ?? "{$alias}.id"));
            $sql .= ", {$display} AS {$col}_label";
            $joins[] = "LEFT JOIN {$field['table']} {$alias} ON {$alias}.id = m.{$col}";
        }
        $sql .= " FROM {$def['table']} m";
        if ($joins) $sql .= ' ' . implode(' ', $joins);
        $order = !empty($def['order']) ? $def['order'] : 'm.id DESC';
        $sql .= " ORDER BY {$order} LIMIT " . (int) $limit;

        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('OpsModel::all(' . $def['table'] . '): ' . $e->getMessage());
            return [];
        }
    }

    public function find(array $def, int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$def['table']} WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('OpsModel::find(' . $def['table'] . '): ' . $e->getMessage());
            return null;
        }
    }

    /** Insert the supplied, already-validated row. */
    public function create(array $def, array $row): int
    {
        $row = $this->onlyKnownColumns($def, $row);
        if (empty($row)) {
            throw new InvalidArgumentException('Nothing to save.');
        }
        $cols = array_keys($row);
        $sql = "INSERT INTO {$def['table']} (" . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($row));
        return (int) $this->pdo->lastInsertId();
    }

    /** Update every known column (nulls clear optional values). */
    public function update(array $def, int $id, array $row): bool
    {
        $row = $this->onlyKnownColumns($def, $row);
        if (empty($row)) return false;
        $sets = [];
        foreach (array_keys($row) as $col) {
            $sets[] = "{$col} = ?";
        }
        $vals = array_values($row);
        $vals[] = $id;
        $stmt = $this->pdo->prepare("UPDATE {$def['table']} SET " . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public function delete(array $def, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$def['table']} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Drop anything that is not declared in the registry definition. */
    private function onlyKnownColumns(array $def, array $row): array
    {
        $allowed = array_keys($def['fields']);
        $out = [];
        foreach ($row as $col => $value) {
            if (in_array($col, $allowed, true)) $out[$col] = $value;
        }
        return $out;
    }
}
