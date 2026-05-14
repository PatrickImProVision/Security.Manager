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

            $this->ensureTable();

            AppDatabase::connection()->table('web_analytics')->insert([
                'route_path'     => $this->routePath($request),
                'request_method' => strtoupper((string) $request->getMethod()),
                'member_user_id' => $this->currentUserId(),
                'ip_address'     => $this->stringLimit($this->requestIp($request), 45),
                'user_agent'     => $this->stringLimit($this->serverValue($request, 'HTTP_USER_AGENT'), 255),
                'referrer'       => $this->stringLimit($this->serverValue($request, 'HTTP_REFERER'), 255),
                'occurred_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Analytics must never stop the application from rendering.
        }
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

            $startDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
            $rows = AppDatabase::connection()
                ->table('web_analytics')
                ->select('route_path, member_user_id, ip_address, occurred_at')
                ->where('occurred_at >=', $startDate->format('Y-m-d 00:00:00'))
                ->orderBy('occurred_at', 'ASC')
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return $summary;
        }

        $dailyIndex = [];
        foreach ($summary['daily'] as $index => $day) {
            $dailyIndex[$day['date']] = $index;
        }

        $visitors = [];
        $topPages = [];

        foreach ($rows as $row) {
            $date = substr((string) ($row['occurred_at'] ?? ''), 0, 10);
            if (isset($dailyIndex[$date])) {
                $summary['daily'][$dailyIndex[$date]]['views']++;
            }

            $visitorKey = $this->visitorKey($row);
            if ($visitorKey !== '') {
                $visitors[$visitorKey] = true;
            }

            if (is_numeric($row['member_user_id'] ?? null)) {
                $summary['registeredViews']++;
            }

            $path = (string) ($row['route_path'] ?? '/');
            $topPages[$path] = ($topPages[$path] ?? 0) + 1;
        }

        arsort($topPages);

        $summary['totalViews'] = count($rows);
        $summary['uniqueVisitors'] = count($visitors);
        $summary['maxViews'] = max(1, ...array_column($summary['daily'], 'views'));
        $summary['topPages'] = array_slice(
            array_map(
                static fn (string $path, int $views): array => ['path' => $path, 'views' => $views],
                array_keys($topPages),
                array_values($topPages),
            ),
            0,
            $topLimit,
        );

        return $summary;
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

            $cutoff = (new DateTimeImmutable())->modify('-' . $windowMinutes . ' minutes')->format('Y-m-d H:i:s');
            $rows = AppDatabase::connection()
                ->table('web_analytics')
                ->select('member_user_id, ip_address, occurred_at')
                ->where('occurred_at >=', $cutoff)
                ->orderBy('occurred_at', 'DESC')
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return $summary;
        }

        $guestIps = [];
        $memberIds = [];
        $seenMembers = [];

        foreach ($rows as $row) {
            if (is_numeric($row['member_user_id'] ?? null)) {
                $memberId = (int) $row['member_user_id'];
                if ($memberId > 0 && ! isset($seenMembers[$memberId])) {
                    $seenMembers[$memberId] = true;
                    $memberIds[] = $memberId;
                }
                continue;
            }

            $ipAddress = (string) ($row['ip_address'] ?? '');
            if ($ipAddress !== '') {
                $guestIps[$ipAddress] = true;
            }
        }

        $summary['guests'] = count($guestIps);
        $summary['members'] = count($memberIds);
        $summary['memberList'] = $this->onlineMembers($memberIds, $memberLimit);

        return $summary;
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
     * @param array<string, mixed> $row
     */
    private function visitorKey(array $row): string
    {
        if (is_numeric($row['member_user_id'] ?? null)) {
            return 'user:' . (string) $row['member_user_id'];
        }

        return (string) ($row['ip_address'] ?? '');
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
                ->select('id, username, profile_image, is_active')
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
                    'profile_url'       => site_url('Member/User/Profile/' . $userId),
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
