<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Site-wide default SEO settings (meta tags, robots, canonical base).
 */
final class SeoSettings
{
    public const META_TITLE = 'seo_meta_title';
    public const META_DESCRIPTION = 'seo_meta_description';
    public const META_KEYWORDS = 'seo_meta_keywords';
    public const CANONICAL_URL = 'seo_canonical_url';
    public const ROBOTS = 'seo_robots';
    public const OG_IMAGE = 'seo_og_image';

    /** @var list<string> */
    public const ROBOTS_OPTIONS = [
        'index,follow',
        'index,nofollow',
        'noindex,follow',
        'noindex,nofollow',
    ];

    /** @var array<string, string> */
    private const DEFAULTS = [
        self::META_TITLE       => '',
        self::META_DESCRIPTION => '',
        self::META_KEYWORDS    => '',
        self::CANONICAL_URL    => '',
        self::ROBOTS           => 'index,follow',
        self::OG_IMAGE         => '',
    ];

    private static bool $ensured = false;

    /** @var array<string, string>|null */
    private static ?array $mainSettingsCache = null;

    /**
     * @return array{
     *     seo_meta_title: string,
     *     seo_meta_description: string,
     *     seo_meta_keywords: string,
     *     seo_canonical_url: string,
     *     seo_robots: string,
     *     seo_og_image: string
     * }
     */
    public function mainSettings(): array
    {
        if (self::$mainSettingsCache !== null) {
            return self::$mainSettingsCache;
        }

        $settings = $this->defaultsFromWeb();

        if (! InstallationState::isInstalled()) {
            return self::$mainSettingsCache = $settings;
        }

        try {
            $this->ensureTable();

            foreach (
                AppDatabase::connection()
                    ->table('seo_settings')
                    ->select('setting_key, setting_value')
                    ->whereIn('setting_key', array_keys(self::DEFAULTS))
                    ->get()
                    ->getResultArray() as $row
            ) {
                $key = (string) ($row['setting_key'] ?? '');
                if (! array_key_exists($key, self::DEFAULTS)) {
                    continue;
                }

                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable) {
            return self::$mainSettingsCache = $settings;
        }

        $settings[self::META_TITLE] = trim($settings[self::META_TITLE]);
        $settings[self::META_DESCRIPTION] = trim($settings[self::META_DESCRIPTION]);
        $settings[self::META_KEYWORDS] = trim($settings[self::META_KEYWORDS]);
        $settings[self::CANONICAL_URL] = $this->normalizeUrl($settings[self::CANONICAL_URL]);
        $settings[self::ROBOTS] = $this->normalizeRobots($settings[self::ROBOTS]);
        $settings[self::OG_IMAGE] = $this->normalizeUrl($settings[self::OG_IMAGE]);

        if ($settings[self::META_TITLE] === '') {
            $settings[self::META_TITLE] = $this->defaultsFromWeb()[self::META_TITLE];
        }

        if ($settings[self::META_DESCRIPTION] === '') {
            $settings[self::META_DESCRIPTION] = $this->defaultsFromWeb()[self::META_DESCRIPTION];
        }

        if ($settings[self::CANONICAL_URL] === '') {
            $settings[self::CANONICAL_URL] = rtrim(site_url('/'), '/');
        }

        return self::$mainSettingsCache = $settings;
    }

    /**
     * @param array<string, string> $values
     */
    public function saveMainSettings(array $values): void
    {
        $this->ensureTable();

        $payload = [
            self::META_TITLE       => $this->limit(trim($values[self::META_TITLE] ?? ''), 120),
            self::META_DESCRIPTION => $this->limit(trim($values[self::META_DESCRIPTION] ?? ''), 320),
            self::META_KEYWORDS    => $this->limit(trim($values[self::META_KEYWORDS] ?? ''), 255),
            self::CANONICAL_URL    => $this->limit($this->normalizeUrl(trim($values[self::CANONICAL_URL] ?? '')), 255),
            self::ROBOTS           => $this->normalizeRobots(trim($values[self::ROBOTS] ?? '')),
            self::OG_IMAGE         => $this->limit($this->normalizeUrl(trim($values[self::OG_IMAGE] ?? '')), 255),
        ];

        if ($payload[self::META_TITLE] === '' || $payload[self::META_DESCRIPTION] === '') {
            throw new \InvalidArgumentException('Meta title and meta description are required.');
        }

        if ($payload[self::CANONICAL_URL] === '') {
            $payload[self::CANONICAL_URL] = rtrim(site_url('/'), '/');
        }

        $db = AppDatabase::connection();
        foreach ($payload as $key => $value) {
            $exists = $db->table('seo_settings')->where('setting_key', $key)->countAllResults() > 0;
            $row = [
                'setting_value' => $value,
                'updated_at'    => date('Y-m-d H:i:s'),
            ];

            if ($exists) {
                $db->table('seo_settings')->where('setting_key', $key)->update($row);
                continue;
            }

            $row['setting_key'] = $key;
            $row['created_at'] = date('Y-m-d H:i:s');
            $db->table('seo_settings')->insert($row);
        }

        self::$mainSettingsCache = null;
        SiteLayoutData::clearCache();
    }

    /**
     * @return array{
     *     description: string,
     *     keywords: string,
     *     robots: string,
     *     canonical: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string,
     *     og_url: string
     * }
     */
    public function pageMeta(?string $documentTitle = null, ?string $description = null, ?string $canonical = null): array
    {
        $settings = $this->mainSettings();
        $title = trim((string) $documentTitle);
        if ($title === '') {
            $title = $settings[self::META_TITLE];
        }

        $metaDescription = trim((string) $description);
        if ($metaDescription === '') {
            $metaDescription = $settings[self::META_DESCRIPTION];
        }

        $canonicalUrl = trim((string) $canonical);
        if ($canonicalUrl === '') {
            $canonicalUrl = $settings[self::CANONICAL_URL];
        }

        return [
            'description'    => $metaDescription,
            'keywords'       => $settings[self::META_KEYWORDS],
            'robots'         => $settings[self::ROBOTS],
            'canonical'      => $canonicalUrl,
            'og_title'       => $title,
            'og_description' => $metaDescription,
            'og_image'       => $settings[self::OG_IMAGE],
            'og_url'         => $canonicalUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultsFromWeb(): array
    {
        $defaults = self::DEFAULTS;
        $defaults[self::CANONICAL_URL] = rtrim(site_url('/'), '/');
        $defaults[self::ROBOTS] = 'index,follow';

        if (! InstallationState::isInstalled()) {
            $defaults[self::META_TITLE] = WebSettings::defaultWebName();
            $defaults[self::META_DESCRIPTION] = 'Change Description';

            return $defaults;
        }

        try {
            $web = (new WebSettings())->homeSettings();
            $defaults[self::META_TITLE] = trim((string) ($web['web_name'] ?? '')) ?: WebSettings::defaultWebName();
            $defaults[self::META_DESCRIPTION] = trim((string) ($web['web_description'] ?? '')) ?: 'Change Description';
        } catch (Throwable) {
            $defaults[self::META_TITLE] = WebSettings::defaultWebName();
            $defaults[self::META_DESCRIPTION] = 'Change Description';
        }

        return $defaults;
    }

    private function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('seo_settings')) {
            foreach ($this->tableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        if ($db->tableExists('seo_settings')) {
            $existingKeys = [];
            foreach ($db->table('seo_settings')->select('setting_key')->get()->getResultArray() as $row) {
                $existingKeys[(string) ($row['setting_key'] ?? '')] = true;
            }

            $seed = $this->defaultsFromWeb();
            $seed[self::ROBOTS] = 'index,follow';
            $seed[self::CANONICAL_URL] = rtrim(site_url('/'), '/');

            foreach ($seed as $key => $value) {
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $db->table('seo_settings')->insert([
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
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'seo_settings');
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

    private function normalizeRobots(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::ROBOTS_OPTIONS, true) ? $value : 'index,follow';
    }

    private function normalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $value)) {
            return '';
        }

        return rtrim($value, '/');
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
