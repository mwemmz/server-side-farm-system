<?php
/**
 * RoleHelper — one place that decides where each role lands after login and
 * which sidebar items it sees. Server-side access checks stay in index.php;
 * this keeps the menu and the redirect consistent with them.
 */
class RoleHelper {
    const ROLES = ['admin', 'manager', 'accountant', 'worker'];

    public static function normalise($role) {
        $role = strtolower((string) $role);
        return in_array($role, self::ROLES, true) ? $role : 'worker';
    }

    /** Relative URL of a role's home screen. */
    public static function homeFor($role) {
        switch (self::normalise($role)) {
            case 'accountant': return 'index.php?module=Finance';
            case 'worker':     return 'index.php?module=Dashboard&view=today';
            default:           return 'index.php?module=Dashboard';
        }
    }

    /** Sidebar item names (as used in layout.php) each role may see; null = all. */
    private static function menu($role) {
        switch (self::normalise($role)) {
            case 'admin':
                return null;
            case 'manager':
                return ['!Finance', '!Users', '!Audit Log'];
            case 'accountant':
                return ['Dashboard', 'Finance', 'Sales', 'Procurement', 'Supplier', 'Market', 'Market Analysis',
                        'Analytics', 'Reports', 'Notifications'];
            default:
                return ['Dashboard', 'Farm', 'Field', 'Crop', 'Irrigation', 'Livestock', 'Inventory', 'Equipment',
                        'Harvest', 'Storage', 'Weather', 'Notifications'];
        }
    }

    public static function canSee($role, $item) {
        $m = self::menu($role);
        if ($m === null) return true;
        if (isset($m[0]) && $m[0][0] === '!') return !in_array('!' . $item, $m, true);
        return in_array($item, $m, true);
    }

    /** Finance figures (totals, unpaid items, loans) are for admin and accountant. */
    public static function seesFinance($role) {
        return in_array(self::normalise($role), ['admin', 'accountant'], true);
    }

    /** Leave requests and HR items are for admin and manager. */
    public static function seesHr($role) {
        return in_array(self::normalise($role), ['admin', 'manager'], true);
    }

    /** A return_to value is honoured only when it is a plain internal index.php URL. */
    public static function safeReturn($url) {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 300) return null;
        if (preg_match('/[\x00-\x1f\\\\]/', $url)) return null;
        $url = ltrim($url, '/');
        if (strpos($url, '//') !== false || stripos($url, 'http') === 0) return null;
        if (!preg_match('/^index\.php\?module=[A-Za-z]+[A-Za-z0-9_&=%.\-+]*$/', $url)) return null;
        if (preg_match('/module=Security(&|$)/', $url)) return null;
        return $url;
    }
}
