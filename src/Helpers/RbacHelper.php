<?php

class RbacHelper
{
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const ACCOUNTANT = 'accountant';
    public const WORKER = 'worker';
    public const FARM_OWNER = 'farm-owner';
    public const AGRONOMIST = 'agronomist';

    private const PERMISSIONS = [
        'admin' => ['*'],
        'manager' => ['farm.read', 'field.read', 'crop.read', 'livestock.read', 'inventory.read', 'sales.write', 'qr.use', 'assistant.use', 'alerts.read'],
        'accountant' => ['finance.read', 'sales.read', 'sales.write', 'profit.read', 'alerts.read'],
        'worker' => ['farm.read', 'field.read', 'inventory.read', 'livestock.read', 'qr.use', 'assistant.use', 'alerts.read'],
        'farm-owner' => ['farm.read', 'field.read', 'crop.read', 'livestock.read', 'inventory.read', 'equipment.read', 'finance.read', 'sales.read', 'profit.read', 'reports.read', 'alerts.read', 'qr.use', 'assistant.use'],
        'agronomist' => ['farm.read', 'field.read', 'crop.read', 'irrigation.read', 'pest.read', 'insights.read', 'livestock.read', 'qr.use', 'assistant.use', 'alerts.read'],
    ];

    public static function can(string $role, string $permission): bool
    {
        return in_array('*', self::PERMISSIONS[$role] ?? [], true)
            || in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can((string) ($_SESSION['role'] ?? ''), $permission)) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    public static function roles(): array
    {
        return array_keys(self::PERMISSIONS);
    }
}
