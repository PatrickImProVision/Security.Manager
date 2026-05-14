<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

final class ModuleSettings
{
    public const CONTENT_PUBLIC = 'content_public';
    public const CONTENT_COMMUNITY = 'content_community';
    public const CONTENT_PERSONAL = 'content_personal';
    public const WEB_ANALYTICS = 'web_analytics';
    public const SECURITY_MANAGER = 'security_manager';

    /** @var array<string, array{label: string, description: string, is_enabled: bool}> */
    private const DEFAULT_MODULES = [
        self::CONTENT_PUBLIC => [
            'label'       => 'Content Manager - Public',
            'description' => 'Public pages, navigation pages, and public content display.',
            'is_enabled'  => true,
        ],
        self::CONTENT_COMMUNITY => [
            'label'       => 'Content Manager - Community',
            'description' => 'Community posts shared between members and public visitors.',
            'is_enabled'  => true,
        ],
        self::CONTENT_PERSONAL => [
            'label'       => 'Content Manager - Personal',
            'description' => 'Private messages sent between registered users.',
            'is_enabled'  => true,
        ],
        self::WEB_ANALYTICS => [
            'label'       => 'Web Usage Analytics',
            'description' => 'Whole-site request tracking and Dashboard usage graph.',
            'is_enabled'  => true,
        ],
        self::SECURITY_MANAGER => [
            'label'       => 'Security Manager',
            'description' => 'Security tools, CANG profiles, and application ID generator settings.',
            'is_enabled'  => true,
        ],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $moduleCache = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function contentModules(): array
    {
        $this->ensureModuleSettings();
        $rows = $this->loadRows();
        $modules = [];

        foreach (self::DEFAULT_MODULES as $moduleKey => $defaults) {
            $row = $rows[$moduleKey] ?? [];
            $modules[] = [
                'module_key'  => $moduleKey,
                'label'       => (string) ($row['label'] ?? $defaults['label']),
                'description' => (string) ($row['description'] ?? $defaults['description']),
                'is_enabled'  => $this->booleanField($row['is_enabled'] ?? $defaults['is_enabled']),
            ];
        }

        return $modules;
    }

    public function isEnabled(string $moduleKey): bool
    {
        foreach ($this->contentModules() as $module) {
            if ((string) $module['module_key'] === $moduleKey) {
                return $this->booleanField($module['is_enabled'] ?? false);
            }
        }

        return false;
    }

    /**
     * @param list<string> $enabledModuleKeys
     */
    public function saveContentModules(array $enabledModuleKeys): void
    {
        $this->ensureModuleSettings();
        $enabledLookup = array_fill_keys($enabledModuleKeys, true);
        $db = AppDatabase::connection();

        foreach (array_keys(self::DEFAULT_MODULES) as $moduleKey) {
            $db->table('module_settings')
                ->where('module_key', $moduleKey)
                ->update([
                    'is_enabled' => isset($enabledLookup[$moduleKey]),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        self::$moduleCache = null;
    }

    private function ensureModuleSettings(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('module_settings')) {
            foreach ($this->moduleTableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        if (! $db->tableExists('module_settings')) {
            return;
        }

        $existing = [];
        foreach ($db->table('module_settings')->select('module_key')->get()->getResultArray() as $row) {
            $existing[(string) ($row['module_key'] ?? '')] = true;
        }

        foreach (self::DEFAULT_MODULES as $moduleKey => $defaults) {
            if (isset($existing[$moduleKey])) {
                continue;
            }

            $db->table('module_settings')->insert([
                'module_key'  => $moduleKey,
                'label'       => $defaults['label'],
                'description' => $defaults['description'],
                'is_enabled'  => $defaults['is_enabled'],
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => null,
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadRows(): array
    {
        if (self::$moduleCache !== null) {
            return self::$moduleCache;
        }

        self::$moduleCache = [];
        foreach (
            AppDatabase::connection()
                ->table('module_settings')
                ->select('module_key, label, description, is_enabled')
                ->get()
                ->getResultArray() as $row
        ) {
            self::$moduleCache[(string) ($row['module_key'] ?? '')] = $row;
        }

        return self::$moduleCache;
    }

    /**
     * @return list<string>
     */
    private function moduleTableSql(BaseConnection $db): array
    {
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'module_settings');
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    module_key VARCHAR(80) PRIMARY KEY,
                    label VARCHAR(120) NOT NULL,
                    description VARCHAR(255) NOT NULL DEFAULT \'\',
                    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL
                )',
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    module_key TEXT PRIMARY KEY,
                    label TEXT NOT NULL,
                    description TEXT NOT NULL DEFAULT \'\',
                    is_enabled INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL,
                    updated_at TEXT
                )',
            ];
        }

        return [
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `module_key` VARCHAR(80) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `description` VARCHAR(255) NOT NULL DEFAULT \'\',
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`module_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
        ];
    }

    private function quoteTable(BaseConnection $db, string $table): string
    {
        if (($db->DBDriver ?? '') === 'MySQLi') {
            return '`' . str_replace('`', '``', $table) . '`';
        }

        return '"' . str_replace('"', '""', $table) . '"';
    }

    private function booleanField(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
