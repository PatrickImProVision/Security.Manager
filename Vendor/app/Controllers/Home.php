<?php

namespace App\Controllers;

use App\Libraries\AppDatabase;
use App\Libraries\InstallationState;
use App\Libraries\ModuleSettings;
use App\Libraries\WebAnalytics;
use App\Libraries\WebSettings;
use Throwable;

class Home extends BaseController
{
    public function index(): string
    {
        $modules = $this->moduleStates();
        $analytics = $modules['analytics'] ? (new WebAnalytics())->onlineSummary() : null;
        $webSettings = (new WebSettings())->homeSettings();

        return view('welcome_message', [
            'title'                 => $webSettings['web_name'],
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
                ->select('id, title, slug, summary, nav_label, show_in_nav, nav_order, published_at')
                ->where('status', 'published')
                ->where('show_in_nav', true)
                ->orderBy('nav_order', 'ASC')
                ->orderBy('title', 'ASC')
                ->limit(4)
                ->get()
                ->getResultArray();
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as &$row) {
            $slug = (string) ($row['slug'] ?? '');
            $row['label'] = trim((string) ($row['nav_label'] ?? '')) ?: (string) ($row['title'] ?? 'Public Page');
            $row['url'] = site_url('Content/Public/View/' . ($slug !== '' ? $slug : (int) ($row['id'] ?? 0)));
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
                ->select('id, title, category, author_id, created_at')
                ->where('status', 'published')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
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
        return [
            'publicPages'    => $modules['public'] ? $this->countRows('public_contents', ['status' => 'published']) : 0,
            'communityPosts' => $modules['community'] ? $this->countRows('community_contents', ['status' => 'published']) : 0,
            'activeMembers'  => $this->countRows('users', ['is_active' => true]),
        ];
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
            $row['url'] = site_url('Content/Community/View/' . (int) ($row['id'] ?? 0));
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
