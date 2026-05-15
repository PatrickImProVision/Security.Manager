<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use DateTimeImmutable;
use Throwable;

final class WebAnalytics
{
    private static bool $ensured = false;
    private static bool $recorded = false;

    public function recordRequest(RequestInterface $request): void
    {
        if (self::$recorded || is_cli() || ! InstallationState::isInstalled()) {
            return;
        }

        self::$recorded = true;

        try {
            if (! (new ModuleSettings())->isEnabled(ModuleSettings::WEB_ANALYTICS)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $payload = [
            'route_path'     => $this->routePath($request),
            'request_method' => strtoupper((string) $request->getMethod()),
            'member_user_id' => $this->currentUserId(),
            'ip_address'     => $this->stringLimit($this->requestIp($request), 45),
            'user_agent'     => $this->stringLimit($this->serverValue($request, 'HTTP_USER_AGENT'), 255),
            'referrer'       => $this->stringLimit($this->serverValue($request, 'HTTP_REFERER'), 255),
            'occurred_at'    => date('Y-m-d H:i:s'),
        ];

        // Defer INSERT until after the response is sent so page generation is not blocked on disk I/O.
        register_shutdown_function(static function () use ($payload): void {
            try {
                (new self())->insertRowAfterResponse($payload);
            } catch (Throwable) {
                // Analytics must never break shutdown.
            }
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRowAfterResponse(array $row): void
    {
        $this->ensureTable();
        AppDatabase::connection()->table('web_analytics')->insert($row);
    }

    /**
     * @return array{
     *     days: int,
     *     totalViews: int,
     *     uniqueVisitors: int,
     *     registeredViews: int,
     *     maxViews: int,
     *     daily: list<array{date: string, label: string, views: int}>,
     *     topPages: list<array{path: string, views: int}>
     * }
     */
    public function dashboardSummary(int $days = 14, int $topLimit = 6): array
    {
        $days = max(1, min($days, 60));
        $summary = $this->emptySummary($days);

        if (! InstallationState::isInstalled()) {
            return $summary;
        }

        try {
            $this->ensureTable();
            $db = AppDatabase::connection();
            $table = $this->prefixedAnalyticsTable($db);
            $driver = (string) ($db->DBDriver ?? '');
            $startDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
            $startSql = $startDate->format('Y-m-d 00:00:00');

            $dailyMap = $this->aggregateDashboardDaily($db, $table, $driver, $startSql);
            foreach ($summary['daily'] as &$day) {
                $d = $day['date'];
                if (isset($dailyMap[$d])) {
                    $day['views'] = (int) $dailyMap[$d];
                }
            }
            unset($day);

            $totals = $this->aggregateDashboardTotals($db, $table, $driver, $startSql);
            $summary['totalViews'] = $totals['totalViews'];
            $summary['registeredViews'] = $totals['registeredViews'];
            $summary['uniqueVisitors'] = $this->aggregateDistinctVisitors($db, $table, $driver, $startSql);
            $summary['maxViews'] = max(1, ...array_column($summary['daily'], 'views'));
            $summary['topPages'] = $this->aggregateTopPages($db, $table, $driver, $startSql, $topLimit);

            return $summary;
        } catch (Throwable) {
            return $this->emptySummary($days);
        }
    }

    /**
     * @return array{
     *     windowMinutes: int,
     *     guests: int,
     *     members: int,
     *     memberList: list<array<string, mixed>>
     * }
     */
    public function onlineSummary(int $windowMinutes = 10, int $memberLimit = 8): array
    {
        $windowMinutes = max(1, min($windowMinutes, 60));
        $memberLimit = max(1, min($memberLimit, 50));
        $summary = [
            'windowMinutes' => $windowMinutes,
            'guests'        => 0,
            'members'       => 0,
            'memberList'    => [],
        ];

        if (! InstallationState::isInstalled()) {
            return $summary;
        }

        try {
            $this->ensureTable();
            $db = AppDatabase::connection();
            $table = $this->prefixedAnalyticsTable($db);
            $driver = (string) ($db->DBDriver ?? '');
            $cutoff = (new DateTimeImmutable())->modify('-' . $windowMinutes . ' minutes')->format('Y-m-d H:i:s');

            $guestsSql = match ($driver) {
                'Postgre' => "SELECT COUNT(DISTINCT ip_address)::int AS c FROM {$table} WHERE occurred_at >= ? AND (member_user_id IS NULL OR member_user_id = 0) AND ip_address <> ''",
                default => "SELECT COUNT(DISTINCT ip_address) AS c FROM {$table} WHERE occurred_at >= ? AND (member_user_id IS NULL OR member_user_id = 0) AND ip_address <> ''",
            };
            $guestRow = $db->query($guestsSql, [$cutoff])->getRowArray();
            $summary['guests'] = (int) ($guestRow['c'] ?? 0);

            $membersSql = match ($driver) {
                'Postgre' => "SELECT COUNT(DISTINCT member_user_id)::int AS c FROM {$table} WHERE occurred_at >= ? AND member_user_id IS NOT NULL AND member_user_id > 0",
                default => "SELECT COUNT(DISTINCT member_user_id) AS c FROM {$table} WHERE occurred_at >= ? AND member_user_id IS NOT NULL AND member_user_id > 0",
            };
            $memRow = $db->query($membersSql, [$cutoff])->getRowArray();
            $summary['members'] = (int) ($memRow['c'] ?? 0);

            $memberIdsSql = match ($driver) {
                'Postgre' => "
                    SELECT member_user_id::int AS id
                    FROM {$table}
                    WHERE occurred_at >= ? AND member_user_id IS NOT NULL AND member_user_id > 0
                    GROUP BY member_user_id
                    ORDER BY MAX(occurred_at) DESC
                    LIMIT {$memberLimit}
                ",
                default => "
                    SELECT member_user_id AS id
                    FROM {$table}
                    WHERE occurred_at >= ? AND member_user_id IS NOT NULL AND member_user_id > 0
                    GROUP BY member_user_id
                    ORDER BY MAX(occurred_at) DESC
                    LIMIT {$memberLimit}
                ",
            };
            $idRows = $db->query($memberIdsSql, [$cutoff])->getResultArray();
            $memberIds = [];
            foreach ($idRows as $row) {
                $mid = (int) ($row['id'] ?? 0);
                if ($mid > 0) {
                    $memberIds[] = $mid;
                }
            }

            $summary['memberList'] = $this->onlineMembers($memberIds, $memberLimit);

            return $summary;
        } catch (Throwable) {
            return $summary;
        }
    }

    private function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('web_analytics')) {
            foreach ($this->tableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        self::$ensured = true;
    }

    private function prefixedAnalyticsTable(BaseConnection $db): string
    {
        return $db->protectIdentifiers($db->prefixTable('web_analytics'), true, false, false);
    }

    /**
     * @return array<string, int> date Y-m-d => view count
     */
    private function aggregateDashboardDaily(BaseConnection $db, string $table, string $driver, string $startSql): array
    {
        $sql = match ($driver) {
            'Postgre' => "
                SELECT to_char(occurred_at, 'YYYY-MM-DD') AS day_key, COUNT(*)::int AS cnt
                FROM {$table}
                WHERE occurred_at >= ?
                GROUP BY to_char(occurred_at, 'YYYY-MM-DD')
            ",
            'SQLite3' => "
                SELECT strftime('%Y-%m-%d', occurred_at) AS day_key, COUNT(*) AS cnt
                FROM {$table}
                WHERE occurred_at >= ?
                GROUP BY strftime('%Y-%m-%d', occurred_at)
            ",
            default => "
                SELECT DATE(occurred_at) AS day_key, COUNT(*) AS cnt
                FROM {$table}
                WHERE occurred_at >= ?
                GROUP BY DATE(occurred_at)
            ",
        };

        $out = [];
        foreach ($db->query($sql, [$startSql])->getResultArray() as $row) {
            $k = (string) ($row['day_key'] ?? '');
            if ($k !== '') {
                $out[$k] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @return array{totalViews: int, registeredViews: int}
     */
    private function aggregateDashboardTotals(BaseConnection $db, string $table, string $driver, string $startSql): array
    {
        $sumExpr = match ($driver) {
            'Postgre' => 'COALESCE(SUM(CASE WHEN member_user_id IS NOT NULL AND member_user_id > 0 THEN 1 ELSE 0 END), 0)::int',
            default => 'SUM(CASE WHEN member_user_id IS NOT NULL AND member_user_id > 0 THEN 1 ELSE 0 END)',
        };

        $sql = "
            SELECT COUNT(*) AS total_views, {$sumExpr} AS registered_views
            FROM {$table}
            WHERE occurred_at >= ?
        ";

        $row = $db->query($sql, [$startSql])->getRowArray() ?: [];

        return [
            'totalViews'      => (int) ($row['total_views'] ?? 0),
            'registeredViews' => (int) ($row['registered_views'] ?? 0),
        ];
    }

    private function aggregateDistinctVisitors(BaseConnection $db, string $table, string $driver, string $startSql): int
    {
        $inner = match ($driver) {
            'Postgre' => "
                SELECT DISTINCT CASE
                    WHEN COALESCE(member_user_id, 0) > 0 THEN 'u' || member_user_id::text
                    ELSE 'i' || COALESCE(ip_address, '')
                END AS vk
                FROM {$table}
                WHERE occurred_at >= ?
            ",
            'SQLite3' => "
                SELECT DISTINCT CASE
                    WHEN IFNULL(member_user_id, 0) > 0 THEN 'u' || CAST(member_user_id AS TEXT)
                    ELSE 'i' || IFNULL(ip_address, '')
                END AS vk
                FROM {$table}
                WHERE occurred_at >= ?
            ",
            default => "
                SELECT DISTINCT CASE
                    WHEN member_user_id IS NOT NULL AND member_user_id > 0 THEN CONCAT('u', CAST(member_user_id AS CHAR))
                    ELSE CONCAT('i', IFNULL(ip_address, ''))
                END AS vk
                FROM {$table}
                WHERE occurred_at >= ?
            ",
        };

        $countWrap = match ($driver) {
            'Postgre' => "SELECT COUNT(*)::int AS c FROM ({$inner}) t",
            default => "SELECT COUNT(*) AS c FROM ({$inner}) t",
        };

        $row = $db->query($countWrap, [$startSql])->getRowArray() ?: [];

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return list<array{path: string, views: int}>
     */
    private function aggregateTopPages(BaseConnection $db, string $table, string $driver, string $startSql, int $topLimit): array
    {
        $limit = max(1, min(60, $topLimit));
        $cntAlias = match ($driver) {
            'Postgre' => 'COUNT(*)::int',
            default => 'COUNT(*)',
        };

        $sql = "
            SELECT route_path, {$cntAlias} AS cnt
            FROM {$table}
            WHERE occurred_at >= ?
            GROUP BY route_path
            ORDER BY cnt DESC
            LIMIT {$limit}
        ";

        $out = [];
        foreach ($db->query($sql, [$startSql])->getResultArray() as $row) {
            $out[] = [
                'path'  => (string) ($row['route_path'] ?? '/'),
                'views' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *     days: int,
     *     totalViews: int,
     *     uniqueVisitors: int,
     *     registeredViews: int,
     *     maxViews: int,
     *     daily: list<array{date: string, label: string, views: int}>,
     *     topPages: list<array{path: string, views: int}>
     * }
     */
    private function emptySummary(int $days): array
    {
        $startDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
        $daily = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $startDate->modify('+' . $offset . ' days');
            $daily[] = [
                'date'  => $date->format('Y-m-d'),
                'label' => $date->format('M j'),
                'views' => 0,
            ];
        }

        return [
            'days'            => $days,
            'totalViews'      => 0,
            'uniqueVisitors'  => 0,
            'registeredViews' => 0,
            'maxViews'        => 1,
            'daily'           => $daily,
            'topPages'        => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function tableSql(BaseConnection $db): array
    {
        $table = $this->quoteTable($db, (string) ($db->DBPrefix ?? '') . 'web_analytics');
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    id SERIAL PRIMARY KEY,
                    route_path VARCHAR(255) NOT NULL,
                    request_method VARCHAR(12) NOT NULL,
                    member_user_id INTEGER NULL,
                    ip_address VARCHAR(45) NOT NULL DEFAULT \'\',
                    user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
                    referrer VARCHAR(255) NOT NULL DEFAULT \'\',
                    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )',
                'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdentifier($db, (string) ($db->DBPrefix ?? '') . 'web_analytics_occurred_idx') . ' ON ' . $table . ' (occurred_at)',
                'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdentifier($db, (string) ($db->DBPrefix ?? '') . 'web_analytics_route_idx') . ' ON ' . $table . ' (route_path)',
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    route_path TEXT NOT NULL,
                    request_method TEXT NOT NULL,
                    member_user_id INTEGER NULL,
                    ip_address TEXT NOT NULL DEFAULT \'\',
                    user_agent TEXT NOT NULL DEFAULT \'\',
                    referrer TEXT NOT NULL DEFAULT \'\',
                    occurred_at TEXT NOT NULL
                )',
                'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdentifier($db, (string) ($db->DBPrefix ?? '') . 'web_analytics_occurred_idx') . ' ON ' . $table . ' (occurred_at)',
                'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdentifier($db, (string) ($db->DBPrefix ?? '') . 'web_analytics_route_idx') . ' ON ' . $table . ' (route_path)',
            ];
        }

        return [
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `route_path` VARCHAR(255) NOT NULL,
                `request_method` VARCHAR(12) NOT NULL,
                `member_user_id` INT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NOT NULL DEFAULT \'\',
                `user_agent` VARCHAR(255) NOT NULL DEFAULT \'\',
                `referrer` VARCHAR(255) NOT NULL DEFAULT \'\',
                `occurred_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `web_analytics_occurred_idx` (`occurred_at`),
                KEY `web_analytics_route_idx` (`route_path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
        ];
    }

    private function routePath(RequestInterface $request): string
    {
        $path = '/' . trim($request->getUri()->getPath(), '/');

        return $path === '/' ? '/' : $this->stringLimit($path, 255);
    }

    private function currentUserId(): ?int
    {
        $userId = session()->get('member_user_id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function requestIp(RequestInterface $request): string
    {
        if (method_exists($request, 'getIPAddress')) {
            return (string) $request->getIPAddress();
        }

        return $this->serverValue($request, 'REMOTE_ADDR');
    }

    private function serverValue(RequestInterface $request, string $key): string
    {
        if (! method_exists($request, 'getServer')) {
            return '';
        }

        $value = $request->getServer($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @param list<int> $memberIds
     *
     * @return list<array<string, mixed>>
     */
    private function onlineMembers(array $memberIds, int $memberLimit): array
    {
        if ($memberIds === []) {
            return [];
        }

        try {
            $rows = AppDatabase::connection()
                ->table('users')
                ->select('id, c_id, username, profile_image, is_active')
                ->whereIn('id', array_slice($memberIds, 0, max(1, $memberLimit)))
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }

        $usersById = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId > 0 && $this->booleanField($row['is_active'] ?? false)) {
                $usersById[$userId] = [
                    'id'                => $userId,
                    'username'          => (string) ($row['username'] ?? 'Member'),
                    'profile_initial'   => strtoupper(substr(trim((string) ($row['username'] ?? '')), 0, 1) ?: '?'),
                    'profile_image_url' => $this->profileImageUrl((string) ($row['profile_image'] ?? '')),
                    'profile_url'       => MemberProfileUrls::publicProfileUrl($row),
                ];
            }
        }

        $members = [];
        foreach ($memberIds as $memberId) {
            if (isset($usersById[$memberId])) {
                $members[] = $usersById[$memberId];
            }

            if (count($members) >= $memberLimit) {
                break;
            }
        }

        return $members;
    }

    private function profileImageUrl(string $profileImage): string
    {
        $profileImage = trim($profileImage);
        if ($profileImage === '') {
            return '';
        }

        $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $publicRoot = $scriptFile !== '' ? dirname($scriptFile) : FCPATH;

        return is_file(rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profileImage))
            ? base_url($profileImage)
            : base_url('Vendor/public/' . $profileImage);
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

    private function stringLimit(string $value, int $limit): string
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

    private function quoteIdentifier(BaseConnection $db, string $identifier): string
    {
        if (($db->DBDriver ?? '') === 'MySQLi') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
