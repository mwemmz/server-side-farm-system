<?php

class SessionHelper {
    public static function setFlash($key, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash($key) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }

    /**
     * Queue the "registration saved" pop-up (Phase 2): shows the new record's
     * reference code with a Copy button. $extra is an optional label => value map.
     */
    public static function flashCreated($module, $id, $label, $extra = []) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $module), 0, 4));
        $_SESSION['flash']['created'] = [
            'module' => (string) $module,
            'id'     => (int) $id,
            'label'  => (string) $label,
            'ref'    => ($letters !== '' ? $letters : 'REC') . '-' . str_pad((string) (int) $id, 4, '0', STR_PAD_LEFT),
            'extra'  => is_array($extra) ? $extra : [],
        ];
    }

    public static function getCreated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash']['created'])) {
            $created = $_SESSION['flash']['created'];
            unset($_SESSION['flash']['created']);
            return $created;
        }
        return null;
    }
}
?>
