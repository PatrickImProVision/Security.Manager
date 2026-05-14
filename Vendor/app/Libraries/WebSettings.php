<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

final class WebSettings
{
    public const WEB_NAME = 'web_name';
    public const WEB_DESCRIPTION = 'web_description';

    /** @var array<string, string> */
    private const DEFAULTS = [
        self::WEB_NAME        => 'Change Name',
        self::WEB_DESCRIPTION => 'Change Description',
    ];

    private static bool $ensured = false;

    /**
     * @return array{web_name: string, web_description: string}
     */
    public function homeSettings(): array
    {
        $settings = self::DEFAULTS;

        if (! InstallationState::isInstalled()) {
            return $settings;
        }

        try {
            $this->ensureTable();

            foreach (
                AppDatabase::connection()
                    ->table('web_settings')
                    ->select('setting_key, setting_value')
                    ->whereIn('setting_key', array_keys(self::DEFAULTS))
                    ->get()
                    ->getResultArray() as $row
            ) {
                $key = (string) ($row['setting_key'] ?? '');
                if (array_key_exists($key, self::DEFAULTS)) {
                    $settings[$key] = (string) ($row['setting_value'] ?? self::DEFAULTS[$key]);
                }
            }
        } catch (Throwable) {
            return self::DEFAULTS;
        }

        return [
            self::WEB_NAME        => trim($settings[self::WEB_NAME]) ?: self::DEFAULTS[self::WEB_NAME],
            self::WEB_DESCRIPTION => trim($settings[self::WEB_DESCRIPTION]) ?: self::DEFAULTS[self::WEB_DESCRIPTION],
        ];
    }

    public function saveHomeSettings(string $webName, string $webDescription): void
    {
        $this->ensureTable();

        $values = [
            self::WEB_NAME        => $this->limit(trim($webName), 120) ?: self::DEFAULTS[self::WEB_NAME],
            self::WEB_DESCRIPTION => $this->limit(trim($webDescription), 500) ?: self::DEFAULTS[self::WEB_DESCRIPTION],
        ];

        $db = AppDatabase::connection();
        foreach ($values as $key => $value) {
            $exists = $db->table('web_settings')->where('setting_key', $key)->countAllResults() > 0;
            $payload = [
                'setting_value' => $value,
                'updated_at'    => date('Y-m-d H:i:s'),
            ];

            if ($exists) {
                $db->table('web_settings')->where('setting_key', $key)->update($payload);
                continue;
            }

            $payload['setting_key'] = $key;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $db->table('web_settings')->insert($payload);
        }
    }

    private function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('web_settings')) {
            foreach ($this->tableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        if ($db->tableExists('web_settings')) {
            foreach (self::DEFAULTS as $key => $value) {
                $exists = $db->table('web_settings')->where('setting_key', $key)->countAllResults() > 0;
                if ($exists) {
                    continue;
                }

                $db->table('web_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => null,
                ]);
            }
        }

        self::$ensured = true;
    }

    /**
     * @return list<string>
     */
    private function tableSql(BaseConnection $db): array
    {
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'web_settings');
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    setting_key VARCHAR(80) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL
                )',
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT
                )',
            ];
        }

        return [
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `setting_key` VARCHAR(80) NOT NULL,
                `setting_value` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
        ];
    }

    private function limit(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit);
    }

    private function quoteTable(BaseConnection $db, string $table): string
    {
        if (($db->DBDriver ?? '') === 'MySQLi') {
            return '`' . str_replace('`', '``', $table) . '`';
        }

        return '"' . str_replace('"', '""', $table) . '"';
    }
}
