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
        'manager' => [
            'farm.read', 'field.read', 'field.write', 'crop.read', 'crop.write',
            'livestock.read', 'livestock.write', 'inventory.read', 'inventory.write',
            'equipment.read', 'equipment.write', 'finance.read', 'sales.read', 'sales.write',
            'labour.read', 'labour.write', 'harvest.read', 'harvest.write',
            'pest.read', 'pest.write', 'procurement.read', 'procurement.write',
            'reports.read', 'analytics.read', 'profit.read', 'qr.use', 'assistant.use', 'alerts.read',
            'communications.read', 'communications.write', 'connections.read', 'connections.write',
            'credit.read', 'credit.share', 'marketplace.read', 'marketplace.write',
        ],
        'accountant' => [
            'finance.read', 'sales.read', 'sales.write', 'procurement.read',
            'profit.read', 'reports.read', 'analytics.read', 'alerts.read',
            'communications.read', 'communications.write',
        ],
        'worker' => [
            'farm.read', 'field.read', 'field.write', 'crop.read', 'livestock.read', 'livestock.write',
            'inventory.read', 'labour.read', 'labour.write', 'harvest.read', 'harvest.write',
            'qr.use', 'assistant.use', 'alerts.read',
            'communications.read', 'communications.write', 'marketplace.read', 'marketplace.write',
        ],
        'farm-owner' => [
            'farm.read', 'field.read', 'field.write', 'crop.read', 'crop.write',
            'livestock.read', 'livestock.write', 'inventory.read', 'inventory.write',
            'equipment.read', 'equipment.write', 'finance.read', 'sales.read', 'sales.write',
            'labour.read', 'labour.write', 'harvest.read', 'harvest.write',
            'pest.read', 'pest.write', 'procurement.read', 'procurement.write',
            'profit.read', 'reports.read', 'analytics.read', 'alerts.read', 'qr.use', 'assistant.use',
            'communications.read', 'communications.write', 'connections.read', 'connections.write',
            'credit.read', 'credit.share', 'marketplace.read', 'marketplace.write',
        ],
        'agronomist' => [
            'farm.read', 'field.read', 'field.write', 'crop.read', 'crop.write',
            'irrigation.read', 'pest.read', 'pest.write', 'harvest.read', 'harvest.write',
            'insights.read', 'livestock.read', 'analytics.read', 'qr.use', 'assistant.use', 'alerts.read',
            'communications.read', 'communications.write', 'marketplace.read', 'marketplace.write',
        ],
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
