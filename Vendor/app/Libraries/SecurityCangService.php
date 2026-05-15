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

    /** Max UTF-8 length stored for `split_by` (separator between character groups). */
    public const SPLIT_BY_MAX_LENGTH = 16;

    /** Max group size for `split_length` (characters per segment before inserting `split_by`). */
    public const SPLIT_LENGTH_MAX = 64;

    /** Max stored length for Password.Id tokens (`activation_guid`, `deactivation_guid`, `reset_guid`). */
    public const PASSWORD_TOKEN_MAX_LENGTH = 64;

    /** Max stored length for persistent `c_id` style identifiers. */
    public const STORED_CANG_ID_MAX_LENGTH = 128;

    /** Lowest valid `language_id` in `security_cang_profiles` (must match {@see self::LANGUAGES}). */
    public static function cangLanguageMinId(): int
    {
        static $min = null;
        if ($min !== null) {
            return $min;
        }

        return $min = min(array_keys(self::LANGUAGES));
    }

    /** Highest valid `language_id` in `security_cang_profiles` (must match {@see self::LANGUAGES}). */
    public static function cangLanguageMaxId(): int
    {
        static $max = null;
        if ($max !== null) {
            return $max;
        }

        return $max = max(array_keys(self::LANGUAGES));
    }

    /**
     * CANG language metadata (selector key, pattern label, human description).
     *
     * @var array<int, array{name: string, type: string, description: string}>
     */
    private const LANGUAGES = [
        1 => [
            'name'        => 'Alphabet_Upper',
            'type'        => '[A-Z]',
            'description' => 'Alphabetical -> Simple: Capital letters',
        ],
        2 => [
            'name'        => 'Alphabet_Lower',
            'type'        => '[a-z]',
            'description' => 'Alphabetical -> Simple: Small letters',
        ],
        3 => [
            'name'        => 'Alphabet_Mix',
            'type'        => '[A-Z,a-z]',
            'description' => 'Alphabetical -> Mix: Capital and Small letters',
        ],
        4 => [
            'name'        => 'Numeric',
            'type'        => '[0-9]',
            'description' => 'Numerical -> Simple',
        ],
        5 => [
            'name'        => 'Alphabet_Upper_Num',
            'type'        => '[A-Z,0-9]',
            'description' => 'Alphabetical And Numerical -> Simple: Capital letters (Microsoft/Megaupload.com)',
        ],
        6 => [
            'name'        => 'Alphabet_Lower_Num',
            'type'        => '[a-z,0-9]',
            'description' => 'Alphabetical and Numerical -> Simple: Small letters',
        ],
        7 => [
            'name'        => 'Alphabet_Mix_Num',
            'type'        => '[A-Z,a-z,0-9]',
            'description' => 'Alphabetical and Numerical -> Mix: Capital and Small letters',
        ],
        8 => [
            'name'        => 'Alphabet_Mix_Num_SpecialShort',
            'type'        => '[A-Z,a-z,0-9,-_]',
            'description' => 'Alphabetical and Numerical -> Mix: Capital/Small letters plus Short Special chars (YouTube.com)',
        ],
        9 => [
            'name'        => 'Alphabet_Mix_Num_SpecialFull',
            'type'        => '[A-Z,a-z,0-9,@-?]',
            'description' => 'Alphabetical and Numerical -> Mix: Capital/Small letters plus Full Special chars (Safe Password)',
        ],
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
            'description'     => 'Reserved product key identifier (not wired into the app yet).',
            'language_id'     => 5,
            'code_length'     => 20,
            'generation_mode' => 'random',
        ],
    ];

    /**
     * CANG targets that persist generated IDs in a table column (bulk refresh after profile edits).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const STORED_CANG_ID_TARGETS = [
        self::USER_URL_ID => ['users', 'c_id'],
        self::PUBLIC_CONTENT_URL_ID => ['public_contents', 'c_id'],
        self::COMMUNITY_CONTENT_URL_ID => ['community_contents', 'c_id'],
        self::PERSONAL_CONTENT_URL_ID => ['personal_messages', 'c_id'],
    ];

    /** @return array<int, array{name: string, type: string, description: string}> */
    public function languages(): array
    {
        return self::LANGUAGES;
    }

    /**
     * Normalizes `split_by` for storage and formatting (trim, max length).
     */
    public static function normalizeSplitBy(string $splitBy): string
    {
        $splitBy = trim($splitBy);

        return mb_substr($splitBy, 0, self::SPLIT_BY_MAX_LENGTH, 'UTF-8');
    }

    /**
     * Normalizes `split_length` (0 = no grouping).
     */
    public static function normalizeSplitLength(int $splitLength): int
    {
        return max(0, min(self::SPLIT_LENGTH_MAX, $splitLength));
    }

    /**
     * Inserts `splitBy` between every `splitLength` characters of the raw code (ASCII body).
     * When `splitBy` is empty or `splitLength` &lt; 1, returns {@code $raw} unchanged.
     */
    public static function formatGeneratedCode(string $raw, string $splitBy, int $splitLength): string
    {
        $splitBy = self::normalizeSplitBy($splitBy);
        $splitLength = self::normalizeSplitLength($splitLength);
        if ($splitBy === '' || $splitLength < 1 || $raw === '') {
            return $raw;
        }

        $chunks = [];
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i += $splitLength) {
            $chunks[] = substr($raw, $i, $splitLength);
        }

        return implode($splitBy, $chunks);
    }

    /**
     * Character length of the formatted string (raw code length plus separators).
     */
    public static function estimateFormattedCodeLength(int $codeLength, string $splitBy, int $splitLength): int
    {
        $splitBy = self::normalizeSplitBy($splitBy);
        $splitLength = self::normalizeSplitLength($splitLength);
        $codeLength = max(1, min(128, $codeLength));
        if ($splitBy === '' || $splitLength < 1) {
            return $codeLength;
        }

        $chunks = (int) ceil($codeLength / $splitLength);
        $sepLen = mb_strlen($splitBy, 'UTF-8');

        return $codeLength + ($chunks - 1) * $sepLen;
    }

    public static function maxFormattedLengthForTarget(string $targetKey): int
    {
        return $targetKey === self::PASSWORD_ID
            ? self::PASSWORD_TOKEN_MAX_LENGTH
            : self::STORED_CANG_ID_MAX_LENGTH;
    }

    public static function formattedLengthWithinLimit(string $targetKey, int $codeLength, string $splitBy, int $splitLength): bool
    {
        return self::estimateFormattedCodeLength($codeLength, $splitBy, $splitLength)
            <= self::maxFormattedLengthForTarget($targetKey);
    }

    public static function formattedLengthLimitMessage(string $targetKey): string
    {
        $max = self::maxFormattedLengthForTarget($targetKey);

        if ($targetKey === self::PASSWORD_ID) {
            return "With this length and split settings, the formatted token would exceed {$max} characters (activation, deactivation, and reset columns). Use a shorter code, a shorter separator, a larger split length, or fewer groups.";
        }

        return "With this length and split settings, the formatted string would exceed {$max} characters. Use a shorter code, a shorter separator, a larger split length, or fewer groups.";
    }

    /**
     * Sample codes for the CANG profile editor (read-only; does not write to the database).
     * Sequential previews use {@code $sequenceBase} as the profile's current counter so the next
     * issued values match {@see encodeSequential()} after {@code sequence_value + n}.
     *
     * @return list<string>
     */
    public function previewSamples(int $languageId, int $codeLength, string $generationMode, int $sampleCount = 5, int $sequenceBase = 0, string $splitBy = '', int $splitLength = 0): array
    {
        $sampleCount = max(1, min(20, $sampleCount));
        $codeLength = max(1, min(128, $codeLength));
        if (! isset(self::LANGUAGES[$languageId])) {
            $languageId = 7;
        }

        $characters = $this->charactersForLanguage($languageId);
        $mode = $generationMode === 'sequential' ? 'sequential' : 'random';
        $splitBy = self::normalizeSplitBy($splitBy);
        $splitLength = self::normalizeSplitLength($splitLength);
        if ($splitLength < 1) {
            $splitBy = '';
        }
        if ($splitBy === '') {
            $splitLength = 0;
        }

        $out = [];
        if ($mode === 'random') {
            for ($i = 0; $i < $sampleCount; $i++) {
                $raw = $this->randomCode($characters, $codeLength);
                $out[] = self::formatGeneratedCode($raw, $splitBy, $splitLength);
            }

            return $out;
        }

        $base = max(0, $sequenceBase);
        for ($i = 1; $i <= $sampleCount; $i++) {
            $raw = $this->encodeSequential($base + $i, $characters, $codeLength);
            $out[] = self::formatGeneratedCode($raw, $splitBy, $splitLength);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function profiles(): array
    {
        $this->ensureProfiles();

        $rows = AppDatabase::connection()
            ->table('security_cang_profiles')
            ->select('id, target_key, label, description, language_id, code_length, generation_mode, split_by, split_length, is_active, updated_at')
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

    /**
     * @param array<string, mixed> $data
     *
     * @return string Optional flash suffix after regenerating stored IDs (empty if none).
     */
    public function saveProfile(int $id, array $data): string
    {
        $this->ensureProfiles();

        $db = AppDatabase::connection();
        $before = $db->table('security_cang_profiles')->where('id', $id)->get()->getRowArray();
        if (! is_array($before)) {
            return '';
        }

        $oldLanguageId = (int) ($before['language_id'] ?? 7);
        $targetKey = (string) ($before['target_key'] ?? '');

        $languageId = (int) ($data['language_id'] ?? 7);
        if (! isset(self::LANGUAGES[$languageId])) {
            $languageId = 7;
        }

        $mode = (string) ($data['generation_mode'] ?? 'random');
        if (! in_array($mode, ['random', 'sequential'], true)) {
            $mode = 'random';
        }

        $languageChanged = $oldLanguageId !== $languageId;
        $isActive = ! empty($data['is_active']);
        $splitBy = self::normalizeSplitBy((string) ($data['split_by'] ?? ''));
        $splitLength = self::normalizeSplitLength((int) ($data['split_length'] ?? 0));
        if ($splitLength < 1) {
            $splitBy = '';
        }
        if ($splitBy === '') {
            $splitLength = 0;
        }

        $db->table('security_cang_profiles')
            ->where('id', $id)
            ->update([
                'language_id'     => $languageId,
                'code_length'     => max(1, min(128, (int) ($data['code_length'] ?? 12))),
                'generation_mode' => $mode,
                'split_by'        => $splitBy,
                'split_length'    => $splitLength,
                'is_active'       => $isActive,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

        if (! $isActive || ! isset(self::STORED_CANG_ID_TARGETS[$targetKey])) {
            return '';
        }

        [$tableName, $columnName] = self::STORED_CANG_ID_TARGETS[$targetKey];

        if ($languageChanged) {
            $count = $this->regenerateAllStoredCangIds($targetKey, $tableName, $columnName);
        } else {
            $count = $this->backfillCangColumn($targetKey, $tableName, $columnName);
        }

        if ($count <= 0) {
            return '';
        }

        return $languageChanged
            ? "Regenerated {$count} stored public ID(s) after the CANG language change."
            : "Filled {$count} missing public ID(s).";
    }

    /**
     * Assigns a new CANG value for every row (e.g. after {@code language_id} changes).
     *
     * @return int Number of rows updated
     */
    private function regenerateAllStoredCangIds(string $targetKey, string $tableName, string $columnName): int
    {
        if (! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return 0;
        }

        $this->ensureCangColumn($tableName, $columnName);
        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName) || ! in_array($columnName, $db->getFieldNames($tableName), true)) {
            return 0;
        }

        $rows = $db->table($tableName)
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return 0;
        }

        $db->transStart();
        $updated = 0;

        try {
            foreach ($rows as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId <= 0) {
                    continue;
                }

                if ($targetKey === self::USER_URL_ID) {
                    $value = $this->generateUserUrlIdentifier($tableName, $columnName);
                } else {
                    if (! $this->securityManagerEnabled()) {
                        $db->transRollback();

                        return 0;
                    }

                    $value = $this->generateFor($targetKey, $tableName, $columnName);
                    if ($value === null) {
                        $db->transRollback();

                        return 0;
                    }
                }

                $db->table($tableName)->where('id', $rowId)->update([$columnName => $value]);
                $updated++;
            }
        } catch (\Throwable) {
            $db->transRollback();

            return 0;
        }

        if (! $db->transComplete()) {
            return 0;
        }

        return $updated;
    }

    /**
     * Ensures missing CANG profile rows exist, then updates each shipped `target_key` row
     * to match {@see self::DEFAULT_PROFILES} (label, description, language_id, code_length, generation_mode).
     * Does not change `is_active` or `sequence_value`. Uses {@see AppDatabase::connection()} (default group in Config\Database).
     *
     * @return int Sum of driver affected-rows counts per update (0 if nothing changed or table missing)
     */
    public function syncSeededProfileDefaults(): int
    {
        $this->ensureProfiles();

        $db = AppDatabase::connection();
        if (! $db->tableExists('security_cang_profiles')) {
            return 0;
        }

        $totalAffected = 0;

        foreach (self::DEFAULT_PROFILES as $targetKey => $defaults) {
            $db->table('security_cang_profiles')
                ->where('target_key', $targetKey)
                ->update([
                    'label'           => $defaults['label'],
                    'description'     => $defaults['description'],
                    'language_id'     => $defaults['language_id'],
                    'code_length'     => $defaults['code_length'],
                    'generation_mode' => $defaults['generation_mode'],
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);

            $totalAffected += $db->affectedRows();
        }

        return $totalAffected;
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

    /**
     * Public user URL identifier for `users.c_id` (and the same target elsewhere).
     * Uses CANG when Security Manager and the `user_url_id` profile allow it; otherwise
     * generates a unique URL-safe string using the profile's length/alphabet when possible.
     */
    public function generateUserUrlIdentifier(string $tableName = 'users', string $columnName = 'c_id'): string
    {
        if (! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return $this->randomCode($this->charactersForLanguage(7), 12);
        }

        $db = AppDatabase::connection();
        $hasColumn = $db->tableExists($tableName) && in_array($columnName, $db->getFieldNames($tableName), true);
        if ($hasColumn) {
            $preferred = $this->generateFor(self::USER_URL_ID, $tableName, $columnName);
            if ($preferred !== null) {
                return $preferred;
            }
        }

        $this->ensureProfiles();
        $profile = $this->profileByTargetKey(self::USER_URL_ID);
        $length = 12;
        $languageId = 7;
        $splitBy = '';
        $splitLen = 0;
        if (is_array($profile)) {
            $length = max(1, min(128, (int) ($profile['code_length'] ?? 12)));
            $languageId = (int) ($profile['language_id'] ?? 7);
            $splitBy = self::normalizeSplitBy((string) ($profile['split_by'] ?? ''));
            $splitLen = self::normalizeSplitLength((int) ($profile['split_length'] ?? 0));
        }

        if (! isset(self::LANGUAGES[$languageId])) {
            $languageId = 7;
        }

        $characters = $this->charactersForLanguage($languageId);

        if (! $hasColumn) {
            $raw = $this->randomCode($characters, $length);

            return self::formatGeneratedCode($raw, $splitBy, $splitLen);
        }

        for ($attempt = 0; $attempt < 64; $attempt++) {
            $raw = $this->randomCode($characters, $length);
            $candidate = self::formatGeneratedCode($raw, $splitBy, $splitLen);
            if ($candidate !== '' && ! $this->valueExists($tableName, $columnName, $candidate)) {
                return $candidate;
            }
        }

        return self::formatGeneratedCode($this->randomCode($characters, $length), $splitBy, $splitLen);
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
            $raw = $mode === 'sequential'
                ? $this->nextSequentialCode($profile, $characters, $length)
                : $this->randomCode($characters, $length);
            $candidate = self::formatGeneratedCode($raw, self::normalizeSplitBy((string) ($profile['split_by'] ?? '')), self::normalizeSplitLength((int) ($profile['split_length'] ?? 0)));
            $maxLen = self::maxFormattedLengthForTarget($targetKey);

            if ($candidate !== '' && strlen($candidate) <= $maxLen && ! $this->valueExists($tableName, $columnName, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function backfillCangColumn(string $targetKey, string $tableName, string $columnName = 'c_id'): int
    {
        if (! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return 0;
        }

        if ($targetKey !== self::USER_URL_ID && ! $this->securityManagerEnabled()) {
            return 0;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName) || ! in_array($columnName, $db->getFieldNames($tableName), true)) {
            return 0;
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

        $updated = 0;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if ($targetKey === self::USER_URL_ID) {
                $value = $this->generateUserUrlIdentifier($tableName, $columnName);
            } else {
                $value = $this->generateFor($targetKey, $tableName, $columnName);
                if ($value === null) {
                    return $updated;
                }
            }

            $db->table($tableName)->where('id', $id)->update([$columnName => $value]);
            $updated++;
        }

        return $updated;
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

        $this->ensureCangProfileSplitColumns();

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
                'split_by'        => '',
                'split_length'    => 0,
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
        $row['integration_note'] = $this->profileIntegrationNote((string) ($row['target_key'] ?? ''));

        return $row;
    }

    /**
     * Human note when a profile is seeded but not used by application code yet.
     */
    public function profileIntegrationNote(string $targetKey): ?string
    {
        if ($targetKey === self::PRODUCT_KEY_ID) {
            return 'Not plugged into anything yet. You can edit and preview settings here, but no part of the store generates or stores Product Key.Id values until a feature is built.';
        }

        return null;
    }

    public function languageCharacterPoolSize(int $languageId): int
    {
        return strlen($this->charactersForLanguage($languageId));
    }

    /**
     * @return array{bound: bool, table: ?string, column: ?string, usage: string, stored_count: ?int}
     */
    public function storageBinding(string $targetKey): array
    {
        if (! isset(self::STORED_CANG_ID_TARGETS[$targetKey])) {
            return match ($targetKey) {
                self::PASSWORD_ID => [
                    'bound'         => false,
                    'table'         => null,
                    'column'        => null,
                    'usage'         => 'Generated on demand for activation, reset, and password flows (not stored in a shared column).',
                    'stored_count'  => null,
                ],
                self::PRODUCT_KEY_ID => [
                    'bound'         => false,
                    'table'         => null,
                    'column'        => null,
                    'usage'         => 'Not plugged into anything yet — no table, API, or feature calls this profile.',
                    'stored_count'  => null,
                ],
                default => [
                    'bound'         => false,
                    'table'         => null,
                    'column'        => null,
                    'usage'         => 'No persistent storage mapping for this profile.',
                    'stored_count'  => null,
                ],
            };
        }

        [$tableName, $columnName] = self::STORED_CANG_ID_TARGETS[$targetKey];
        $usage = match ($targetKey) {
            self::USER_URL_ID => 'Public user URL identifier',
            self::PUBLIC_CONTENT_URL_ID => 'Public content URL identifier',
            self::COMMUNITY_CONTENT_URL_ID => 'Community content URL identifier',
            self::PERSONAL_CONTENT_URL_ID => 'Personal message URL identifier',
            default => 'Stored public identifier',
        };

        return [
            'bound'        => true,
            'table'        => $tableName,
            'column'       => $columnName,
            'usage'        => $usage,
            'stored_count' => $this->countStoredIds($tableName, $columnName),
        ];
    }

    /**
     * Full editor summary for the CANG profile form (server render or AJAX).
     *
     * @param array<string, mixed>      $profile Decorated row from {@see profile()}
     * @param array<string, mixed>|null $draft   Optional overrides: language_id, code_length, generation_mode, split_by, split_length, is_active
     *
     * @return array<string, mixed>
     */
    public function editorDetails(array $profile, ?array $draft = null): array
    {
        $draft = $draft ?? [];
        $languageId = (int) ($draft['language_id'] ?? $profile['language_id'] ?? 7);
        if (! isset(self::LANGUAGES[$languageId])) {
            $languageId = 7;
        }
        $language = self::LANGUAGES[$languageId];

        $codeLength = max(1, min(128, (int) ($draft['code_length'] ?? $profile['code_length'] ?? 12)));
        $mode = (string) ($draft['generation_mode'] ?? $profile['generation_mode'] ?? 'random');
        if (! in_array($mode, ['random', 'sequential'], true)) {
            $mode = 'random';
        }

        $splitBy = self::normalizeSplitBy((string) ($draft['split_by'] ?? $profile['split_by'] ?? ''));
        $splitLength = self::normalizeSplitLength((int) ($draft['split_length'] ?? $profile['split_length'] ?? 0));
        if ($splitLength < 1) {
            $splitBy = '';
            $splitLength = 0;
        }
        if ($splitBy === '') {
            $splitLength = 0;
        }

        $isActive = array_key_exists('is_active', $draft)
            ? ! empty($draft['is_active'])
            : $this->booleanField($profile['is_active'] ?? false);

        $formattedLength = self::estimateFormattedCodeLength($codeLength, $splitBy, $splitLength);
        $targetKey = (string) ($profile['target_key'] ?? '');
        $maxFormatted = self::maxFormattedLengthForTarget($targetKey);
        $storage = $this->storageBinding($targetKey);
        $sequenceValue = (int) ($profile['sequence_value'] ?? 0);

        $splitSummary = ($splitBy !== '' && $splitLength > 0)
            ? $splitBy . ' every ' . $splitLength . ' character(s)'
            : 'None';

        $rows = [
            ['label' => 'Profile ID', 'value' => (string) ((int) ($profile['id'] ?? 0))],
            ['label' => 'Target key', 'value' => $targetKey, 'code' => true],
            ['label' => 'Label', 'value' => (string) ($profile['label'] ?? '')],
            ['label' => 'Description', 'value' => (string) ($profile['description'] ?? '')],
            ['label' => 'Status', 'value' => $isActive ? 'Active' : 'Inactive', 'pill' => $isActive ? 'active' : 'inactive'],
            ['label' => 'Language', 'value' => $language['name'] . ' (#' . $languageId . ')'],
            ['label' => 'Character set', 'value' => $language['type'], 'code' => true],
            ['label' => 'Language notes', 'value' => $language['description']],
            ['label' => 'Character pool size', 'value' => (string) $this->languageCharacterPoolSize($languageId)],
            ['label' => 'Code length (raw)', 'value' => (string) $codeLength],
            ['label' => 'Split formatting', 'value' => $splitSummary, 'code' => $splitBy !== '' && $splitLength > 0],
            ['label' => 'Generation mode', 'value' => ucfirst($mode), 'pill' => 'system'],
            ['label' => 'Formatted length (est.)', 'value' => $formattedLength . ' / ' . $maxFormatted . ' max', 'warn' => $formattedLength > $maxFormatted],
            ['label' => 'Storage', 'value' => $storage['bound']
                ? $storage['table'] . '.' . $storage['column'] . ' — ' . $storage['usage']
                : $storage['usage']],
        ];

        if ($storage['bound'] && $storage['stored_count'] !== null) {
            $rows[] = ['label' => 'Stored IDs', 'value' => (string) $storage['stored_count']];
        }

        if ($mode === 'sequential') {
            $rows[] = ['label' => 'Sequence counter', 'value' => (string) $sequenceValue];
        }

        $createdAt = (string) ($profile['created_at'] ?? '');
        $updatedAt = (string) ($profile['updated_at'] ?? '');
        if ($createdAt !== '') {
            $rows[] = ['label' => 'Created', 'value' => $createdAt];
        }
        if ($updatedAt !== '') {
            $rows[] = ['label' => 'Last updated', 'value' => $updatedAt];
        }

        $integrationNote = $this->profileIntegrationNote($targetKey);
        if ($integrationNote !== null) {
            $rows[] = ['label' => 'Integration', 'value' => $integrationNote];
        }

        return [
            'rows'                 => $rows,
            'integration_note'     => $integrationNote,
            'formatted_length'     => $formattedLength,
            'max_formatted_length' => $maxFormatted,
            'length_exceeds'       => $formattedLength > $maxFormatted,
            'length_error'         => $formattedLength > $maxFormatted
                ? self::formattedLengthLimitMessage($targetKey)
                : '',
            'language_id'          => $languageId,
            'generation_mode'      => $mode,
        ];
    }

    private function countStoredIds(string $tableName, string $columnName): ?int
    {
        if (! $this->isSafeIdentifier($tableName) || ! $this->isSafeIdentifier($columnName)) {
            return null;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists($tableName) || ! in_array($columnName, $db->getFieldNames($tableName), true)) {
            return null;
        }

        return $db->table($tableName)
            ->where($columnName . ' !=', '')
            ->countAllResults();
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

    /**
     * Character pool per CANG language id (matches $CANG_Symbols ordering).
     */
    private function charactersForLanguage(int $languageId): string
    {
        $aZ = range('A', 'Z');
        $az = range('a', 'z');
        $n09 = range('0', '9');

        return match ($languageId) {
            1 => implode('', $aZ),
            2 => implode('', $az),
            3 => implode('', array_merge($aZ, $az)),
            4 => implode('', $n09),
            5 => implode('', array_merge($aZ, $n09)),
            6 => implode('', array_merge($az, $n09)),
            7 => implode('', array_merge($aZ, $az, $n09)),
            8 => implode('', array_merge($aZ, $az, $n09, ['-', '_'])),
            9 => implode('', array_merge($aZ, $az, $n09, str_split('@#$%&*-_=+:?'))),
            default => implode('', array_merge($aZ, $az, $n09)),
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

    private function securityCangProfilesHasColumn(BaseConnection $db, string $column): bool
    {
        $fields = $db->getFieldNames('security_cang_profiles');
        foreach ($fields as $f) {
            if (strcasecmp((string) $f, $column) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds `split_by` / `split_length` to older installs that pre-date grouped formatting.
     */
    private function ensureCangProfileSplitColumns(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('security_cang_profiles')) {
            return;
        }

        $table = $db->escapeIdentifiers($db->prefixTable('security_cang_profiles'));
        $driver = (string) ($db->DBDriver ?? '');

        if (! $this->securityCangProfilesHasColumn($db, 'split_by')) {
            if ($driver === 'Postgre') {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN split_by VARCHAR(16) NOT NULL DEFAULT ''");
            } elseif ($driver === 'SQLite3') {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN split_by TEXT NOT NULL DEFAULT ''");
            } else {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN `split_by` VARCHAR(16) NOT NULL DEFAULT ''");
            }
        }

        if (! $this->securityCangProfilesHasColumn($db, 'split_length')) {
            if ($driver === 'Postgre') {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN split_length INTEGER NOT NULL DEFAULT 0");
            } elseif ($driver === 'SQLite3') {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN split_length INTEGER NOT NULL DEFAULT 0");
            } else {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN `split_length` INT NOT NULL DEFAULT 0");
            }
        }
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
                    split_by VARCHAR(16) NOT NULL DEFAULT \'\',
                    split_length INTEGER NOT NULL DEFAULT 0,
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
                    split_by TEXT NOT NULL DEFAULT \'\',
                    split_length INTEGER NOT NULL DEFAULT 0,
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
                `split_by` VARCHAR(16) NOT NULL DEFAULT \'\',
                `split_length` INT NOT NULL DEFAULT 0,
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
