<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

final class SecurityCangService
{
    public const USER_URL_ID = 'user_url_id';
    public const PASSWORD_ID = 'password_id';
    public const PUBLIC_CONTENT_URL_ID = 'public_content_url_id';
    public const COMMUNITY_CONTENT_URL_ID = 'community_content_url_id';
    public const PERSONAL_CONTENT_URL_ID = 'personal_content_url_id';
    public const PRODUCT_KEY_ID = 'product_key_id';

    /** @var array<int, array{name: string, type: string, description: string}> */
    private const LANGUAGES = [
        1 => ['name' => 'Alphabet Upper', 'type' => '[A-Z]', 'description' => 'Alphabetical simple capital letters.'],
        2 => ['name' => 'Alphabet Lower', 'type' => '[a-z]', 'description' => 'Alphabetical simple small letters.'],
        3 => ['name' => 'Alphabet Mix', 'type' => '[A-Z,a-z]', 'description' => 'Alphabetical mixed capital and small letters.'],
        4 => ['name' => 'Numeric', 'type' => '[0-9]', 'description' => 'Numerical simple digits.'],
        5 => ['name' => 'Alphabet Upper Num', 'type' => '[A-Z,0-9]', 'description' => 'Capital letters and digits.'],
        6 => ['name' => 'Alphabet Lower Num', 'type' => '[a-z,0-9]', 'description' => 'Small letters and digits.'],
        7 => ['name' => 'Alphabet Mix Num', 'type' => '[A-Z,a-z,0-9]', 'description' => 'Mixed letters and digits.'],
        8 => ['name' => 'Alphabet Mix Num Special Short', 'type' => '[A-Z,a-z,0-9,-_]', 'description' => 'Mixed letters, digits, dash, and underscore.'],
        9 => ['name' => 'Alphabet Mix Num Special Full', 'type' => '[A-Z,a-z,0-9,-_]', 'description' => 'Mixed letters, digits, and safe special characters.'],
    ];

    /** @var array<string, array{label: string, description: string, language_id: int, code_length: int, generation_mode: string}> */
    private const DEFAULT_PROFILES = [
        self::USER_URL_ID => [
            'label'           => 'User Url.Id',
            'description'     => 'Public user URL identifier.',
            'language_id'     => 5,
            'code_length'     => 12,
            'generation_mode' => 'random',
        ],
        self::PASSWORD_ID => [
            'label'           => 'Password.Id',
            'description'     => 'Activation, reset, and password related security identifiers.',
            'language_id'     => 9,
            'code_length'     => 32,
            'generation_mode' => 'random',
        ],
        self::PUBLIC_CONTENT_URL_ID => [
            'label'           => 'Public Content Url.Id',
            'description'     => 'Public content URL identifier.',
            'language_id'     => 8,
            'code_length'     => 12,
            'generation_mode' => 'random',
        ],
        self::COMMUNITY_CONTENT_URL_ID => [
            'label'           => 'Community Content Url.Id',
            'description'     => 'Community content URL identifier.',
            'language_id'     => 8,
            'code_length'     => 12,
            'generation_mode' => 'random',
        ],
        self::PERSONAL_CONTENT_URL_ID => [
            'label'           => 'Personal Content Url.Id',
            'description'     => 'Personal content/message URL identifier.',
            'language_id'     => 8,
            'code_length'     => 12,
            'generation_mode' => 'random',
        ],
        self::PRODUCT_KEY_ID => [
            'label'           => 'Product Key.Id',
            'description'     => 'Future product key identifier.',
            'language_id'     => 5,
            'code_length'     => 20,
            'generation_mode' => 'random',
        ],
    ];

    /** @return array<int, array{name: string, type: string, description: string}> */
    public function languages(): array
    {
        return self::LANGUAGES;
    }

    /** @return list<array<string, mixed>> */
    public function profiles(): array
    {
        $this->ensureProfiles();

        $rows = AppDatabase::connection()
            ->table('security_cang_profiles')
            ->select('id, target_key, label, description, language_id, code_length, generation_mode, is_active, updated_at')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorateProfile($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function profile(int $id): ?array
    {
        $this->ensureProfiles();

        $row = AppDatabase::connection()
            ->table('security_cang_profiles')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return is_array($row) ? $this->decorateProfile($row) : null;
    }

    /** @param array<string, mixed> $data */
    public function saveProfile(int $id, array $data): void
    {
        $this->ensureProfiles();

        $languageId = (int) ($data['language_id'] ?? 7);
        if (! isset(self::LANGUAGES[$languageId])) {
            $languageId = 7;
        }

        $mode = (string) ($data['generation_mode'] ?? 'random');
        if (! in_array($mode, ['random', 'sequential'], true)) {
            $mode = 'random';
        }

        AppDatabase::connection()
            ->table('security_cang_profiles')
            ->where('id', $id)
            ->update([
                'language_id'     => $languageId,
                'code_length'     => max(1, min(128, (int) ($data['code_length'] ?? 12))),
                'generation_mode' => $mode,
                'is_active'       => ! empty($data['is_active']),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
    }

    public function ensureCangColumn(string $tableName, string $columnName = 'c_id'): void
    {
        if (! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName)) {
            return;
        }

        $fields = $db->getFieldNames($tableName);
        if (in_array($columnName, $fields, true)) {
            return;
        }

        $table = $db->escapeIdentifiers($db->prefixTable($tableName));
        $column = $db->escapeIdentifiers($columnName);
        $definition = match ((string) ($db->DBDriver ?? '')) {
            'Postgre' => 'VARCHAR(128) NULL',
            'SQLite3' => 'TEXT',
            default => 'VARCHAR(128) NULL DEFAULT NULL',
        };

        $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        $this->ensureCangColumnIndex($db, $tableName, $columnName);
    }

    public function generateFor(string $targetKey, string $tableName, string $columnName = 'c_id'): ?string
    {
        if (! $this->securityManagerEnabled() || ! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return null;
        }

        $this->ensureProfiles();
        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName) || ! in_array($columnName, $db->getFieldNames($tableName), true)) {
            return null;
        }

        $profile = $this->profileByTargetKey($targetKey);
        if ($profile === null || ! $this->booleanField($profile['is_active'] ?? false)) {
            return null;
        }

        $length = max(1, min(128, (int) ($profile['code_length'] ?? 12)));
        $characters = $this->charactersForLanguage((int) ($profile['language_id'] ?? 7));
        $mode = (string) ($profile['generation_mode'] ?? 'random');

        for ($attempt = 0; $attempt < 32; $attempt++) {
            $candidate = $mode === 'sequential'
                ? $this->nextSequentialCode($profile, $characters, $length)
                : $this->randomCode($characters, $length);

            if ($candidate !== '' && ! $this->valueExists($tableName, $columnName, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function backfillCangColumn(string $targetKey, string $tableName, string $columnName = 'c_id'): void
    {
        if (! $this->securityManagerEnabled() || ! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName) || ! in_array($columnName, $db->getFieldNames($tableName), true)) {
            return;
        }

        $rows = $db->table($tableName)
            ->select('id')
            ->groupStart()
                ->where($columnName, null)
                ->orWhere($columnName, '')
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $value = $this->generateFor($targetKey, $tableName, $columnName);
            if ($value === null) {
                return;
            }

            $db->table($tableName)->where('id', $id)->update([$columnName => $value]);
        }
    }

    public function ensureApplicationCangColumns(): void
    {
        $targets = [
            self::USER_URL_ID => 'users',
            self::PUBLIC_CONTENT_URL_ID => 'public_contents',
            self::COMMUNITY_CONTENT_URL_ID => 'community_contents',
            self::PERSONAL_CONTENT_URL_ID => 'personal_messages',
        ];

        foreach ($targets as $targetKey => $tableName) {
            $this->ensureCangColumn($tableName);
            $this->backfillCangColumn($targetKey, $tableName);
        }
    }

    public function ensureProfiles(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('security_cang_profiles')) {
            foreach ($this->tableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        if (! $db->tableExists('security_cang_profiles')) {
            return;
        }

        foreach (self::DEFAULT_PROFILES as $targetKey => $profile) {
            $exists = $db->table('security_cang_profiles')->where('target_key', $targetKey)->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $db->table('security_cang_profiles')->insert([
                'target_key'      => $targetKey,
                'label'           => $profile['label'],
                'description'     => $profile['description'],
                'language_id'     => $profile['language_id'],
                'code_length'     => $profile['code_length'],
                'generation_mode' => $profile['generation_mode'],
                'is_active'       => true,
                'sequence_value'  => 0,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => null,
            ]);
        }
    }

    /** @param array<string, mixed> $row */
    private function decorateProfile(array $row): array
    {
        $languageId = (int) ($row['language_id'] ?? 7);
        $language = self::LANGUAGES[$languageId] ?? self::LANGUAGES[7];

        $row['language_id'] = $languageId;
        $row['language_name'] = $language['name'];
        $row['language_type'] = $language['type'];
        $row['language_description'] = $language['description'];
        $row['is_active'] = $this->booleanField($row['is_active'] ?? false);

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function profileByTargetKey(string $targetKey): ?array
    {
        $row = AppDatabase::connection()
            ->table('security_cang_profiles')
            ->where('target_key', $targetKey)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    private function securityManagerEnabled(): bool
    {
        try {
            return (new ModuleSettings())->isEnabled(ModuleSettings::SECURITY_MANAGER);
        } catch (\Throwable) {
            return true;
        }
    }

    private function randomCode(string $characters, int $length): string
    {
        $code = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }

        return $code;
    }

    /** @param array<string, mixed> $profile */
    private function nextSequentialCode(array &$profile, string $characters, int $length): string
    {
        $next = max(0, (int) ($profile['sequence_value'] ?? 0)) + 1;
        AppDatabase::connection()
            ->table('security_cang_profiles')
            ->where('target_key', (string) ($profile['target_key'] ?? ''))
            ->update([
                'sequence_value' => $next,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        $profile['sequence_value'] = $next;

        return $this->encodeSequential($next, $characters, $length);
    }

    private function encodeSequential(int $value, string $characters, int $length): string
    {
        $base = strlen($characters);
        $encoded = '';
        do {
            $encoded = $characters[$value % $base] . $encoded;
            $value = intdiv($value, $base);
        } while ($value > 0);

        return str_pad($encoded, $length, $characters[0], STR_PAD_LEFT);
    }

    private function valueExists(string $tableName, string $columnName, string $value): bool
    {
        return AppDatabase::connection()
            ->table($tableName)
            ->where($columnName, $value)
            ->countAllResults() > 0;
    }

    private function charactersForLanguage(int $languageId): string
    {
        return match ($languageId) {
            1 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            2 => 'abcdefghijklmnopqrstuvwxyz',
            3 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
            4 => '0123456789',
            5 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            6 => 'abcdefghijklmnopqrstuvwxyz0123456789',
            8, 9 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_',
            default => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
        };
    }

    private function ensureCangColumnIndex(BaseConnection $db, string $tableName, string $columnName): void
    {
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($db->DBPrefix ?? '')) ?: '';
        $index = substr($prefix . $tableName . '_' . $columnName . '_unique', 0, 60);
        $table = $db->escapeIdentifiers($db->prefixTable($tableName));
        $column = $db->escapeIdentifiers($columnName);

        try {
            if (($db->DBDriver ?? '') === 'MySQLi') {
                $db->simpleQuery("ALTER TABLE {$table} ADD UNIQUE KEY `{$index}` ({$column})");

                return;
            }

            $db->simpleQuery("CREATE UNIQUE INDEX IF NOT EXISTS {$index} ON {$table} ({$column})");
        } catch (\Throwable) {
            // Existing databases may already have an index; generation still checks uniqueness.
        }
    }

    private function isSafeIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1;
    }

    /** @return list<string> */
    private function tableSql(BaseConnection $db): array
    {
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'security_cang_profiles');
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    id SERIAL PRIMARY KEY,
                    target_key VARCHAR(80) NOT NULL UNIQUE,
                    label VARCHAR(140) NOT NULL,
                    description VARCHAR(255) NOT NULL DEFAULT \'\',
                    language_id INTEGER NOT NULL DEFAULT 7,
                    code_length INTEGER NOT NULL DEFAULT 12,
                    generation_mode VARCHAR(20) NOT NULL DEFAULT \'random\',
                    is_active BOOLEAN NOT NULL DEFAULT TRUE,
                    sequence_value INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL
                )',
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    target_key TEXT NOT NULL UNIQUE,
                    label TEXT NOT NULL,
                    description TEXT NOT NULL DEFAULT \'\',
                    language_id INTEGER NOT NULL DEFAULT 7,
                    code_length INTEGER NOT NULL DEFAULT 12,
                    generation_mode TEXT NOT NULL DEFAULT \'random\',
                    is_active INTEGER NOT NULL DEFAULT 1,
                    sequence_value INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL,
                    updated_at TEXT
                )',
            ];
        }

        return [
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `target_key` VARCHAR(80) NOT NULL,
                `label` VARCHAR(140) NOT NULL,
                `description` VARCHAR(255) NOT NULL DEFAULT \'\',
                `language_id` INT NOT NULL DEFAULT 7,
                `code_length` INT NOT NULL DEFAULT 12,
                `generation_mode` VARCHAR(20) NOT NULL DEFAULT \'random\',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sequence_value` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `security_cang_profiles_target_unique` (`target_key`)
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
