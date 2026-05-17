<?php

namespace App\Controllers;

use App\Libraries\AppDatabase;
use App\Libraries\CommunityContentUrls;
use App\Libraries\PublicContentUrls;
use App\Libraries\InstallationState;
use App\Libraries\ModuleSettings;
use App\Libraries\SitePageTitle;
use App\Libraries\WebAnalytics;
use App\Libraries\WebSettings;
use Throwable;

class Home extends BaseController
{
    /**
     * Short-lived cache for home page COUNT queries (per PHP-FPM worker).
     * Reduces repeated full-table scans when the site is busy.
     *
     * @var array{at: int, key: string, data: array{publicPages: int, communityPosts: int, activeMembers: int}}|null
     */
    private static ?array $siteStatsCache = null;

    public function index(): string
    {
        $modules = $this->moduleStates();
        $analytics = $modules['analytics'] ? (new WebAnalytics())->onlineSummary() : null;
        $webSettings = (new WebSettings())->homeSettings();

        return view('welcome_message', [
            'title'                 => SitePageTitle::format('Home'),
            'pageHeading'           => SitePageTitle::trail('Home'),
            'breadcrumbItems'       => SitePageTitle::breadcrumbs([['label' => 'Home']]),
            'wideLayout'            => true,
            'lightPage'             => true,
            'webName'               => $webSettings['web_name'],
            'webDescription'        => $webSettings['web_description'],
            'memberLoggedIn'        => is_numeric(session()->get('member_user_id')),
            'memberUsername'        => (string) (session()->get('member_username') ?? ''),
            'publicContentEnabled'  => $modules['public'],
            'communityEnabled'      => $modules['community'],
            'personalEnabled'       => $modules['personal'],
            'analyticsEnabled'      => $modules['analytics'],
            'featuredPages'         => $modules['public'] ? $this->featuredPublicPages() : [],
            'latestCommunityPosts'  => $modules['community'] ? $this->latestCommunityPosts() : [],
            'siteStats'             => $this->siteStats($modules),
            'onlineSummary'         => $analytics,
        ]);
    }

    /**
     * @return array{public: bool, community: bool, personal: bool, analytics: bool}
     */
    private function moduleStates(): array
    {
        $states = [
            'public'    => true,
            'community' => true,
            'personal'  => true,
            'analytics' => false,
        ];

        if (! InstallationState::isInstalled()) {
            return $states;
        }

        try {
            $settings = new ModuleSettings();

            return [
                'public'    => $settings->isEnabled(ModuleSettings::CONTENT_PUBLIC),
                'community' => $settings->isEnabled(ModuleSettings::CONTENT_COMMUNITY),
                'personal'  => $settings->isEnabled(ModuleSettings::CONTENT_PERSONAL),
                'analytics' => $settings->isEnabled(ModuleSettings::WEB_ANALYTICS),
            ];
        } catch (Throwable) {
            return $states;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function featuredPublicPages(): array
    {
        if (! $this->tableExists('public_contents')) {
            return [];
        }

        try {
            $rows = AppDatabase::connection()
                ->table('public_contents')
                ->select('id, c_id, title, slug, summary, nav_label, show_in_nav, nav_order, published_at')
                ->where('status', 'published')
                ->groupStart()
                    ->where('published_at', null)
                    ->orWhere('published_at <=', date('Y-m-d H:i:s'))
                ->groupEnd()
                ->orderBy('show_in_nav', 'DESC')
                ->orderBy('nav_order', 'ASC')
                ->orderBy('published_at', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->limit(4)
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['label'] = trim((string) ($row['nav_label'] ?? '')) ?: (string) ($row['title'] ?? 'Public Page');
            $row['url'] = PublicContentUrls::postUrl($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestCommunityPosts(): array
    {
        if (! $this->tableExists('community_contents')) {
            return [];
        }

        try {
            $rows = AppDatabase::connection()
                ->table('community_contents')
                ->select('id, c_id, title, category, author_id, created_at')
                ->where('status', 'published')
                ->groupStart()
                    ->where('parent_id', null)
                    ->orWhere('parent_id', 0)
                ->groupEnd()
                ->orderBy('last_reply_at', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->limit(4)
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }

        return $this->withAuthorNames($rows);
    }

    /**
     * @param array{public: bool, community: bool, personal: bool, analytics: bool} $modules
     *
     * @return array{publicPages: int, communityPosts: int, activeMembers: int}
     */
    private function siteStats(array $modules): array
    {
        $cacheKey = ($modules['public'] ? '1' : '0') . ($modules['community'] ? '1' : '0');
        $now = time();
        if (
            self::$siteStatsCache !== null
            && self::$siteStatsCache['key'] === $cacheKey
            && ($now - self::$siteStatsCache['at']) < 45
        ) {
            return self::$siteStatsCache['data'];
        }

        $data = [
            'publicPages'    => $modules['public'] ? $this->countRows('public_contents', ['status' => 'published']) : 0,
            'communityPosts' => $modules['community'] ? $this->countRows('community_contents', ['status' => 'published']) : 0,
            'activeMembers'  => $this->countRows('users', ['is_active' => true]),
        ];

        self::$siteStatsCache = [
            'at'   => $now,
            'key'  => $cacheKey,
            'data' => $data,
        ];

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withAuthorNames(array $rows): array
    {
        $authorIds = [];
        foreach ($rows as $row) {
            $authorId = (int) ($row['author_id'] ?? 0);
            if ($authorId > 0) {
                $authorIds[$authorId] = $authorId;
            }
        }

        $authors = [];
        if ($authorIds !== [] && $this->tableExists('users')) {
            try {
                foreach (AppDatabase::connection()->table('users')->select('id, username')->whereIn('id', array_values($authorIds))->get()->getResultArray() as $user) {
                    $authors[(int) ($user['id'] ?? 0)] = (string) ($user['username'] ?? 'Member');
                }
            } catch (Throwable) {
                $authors = [];
            }
        }

        foreach ($rows as &$row) {
            $authorId = (int) ($row['author_id'] ?? 0);
            $row['author_name'] = $authors[$authorId] ?? 'Unknown';
            $row['url'] = CommunityContentUrls::topicUrl($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $where
     */
    private function countRows(string $table, array $where = []): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        try {
            $builder = AppDatabase::connection()->table($table);
            foreach ($where as $field => $value) {
                $builder->where($field, $value);
            }

            return (int) $builder->countAllResults();
        } catch (Throwable) {
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        if (! InstallationState::isInstalled()) {
            return false;
        }

        try {
            return AppDatabase::connection()->tableExists($table);
        } catch (Throwable) {
            return false;
        }
    }
}
