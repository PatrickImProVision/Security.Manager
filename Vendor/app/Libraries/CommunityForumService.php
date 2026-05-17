<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * phpBB-style community forum: categories = forums, rows with parent_id null = topics, replies = children.
 */
final class CommunityForumService
{
    /** @var array<string, array{topic_count: int, post_count: int, last_topic_id: int, last_title: string, last_author_name: string, last_at: string}>|null */
    private ?array $preloadedCategoryStats = null;

    public function ensureSchema(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('community_contents')) {
            return;
        }

        $table = $db->escapeIdentifiers($db->prefixTable('community_contents'));
        $fields = $db->getFieldNames('community_contents');
        $driver = (string) ($db->DBDriver ?? '');

        $additions = [
            'parent_id'          => match ($driver) {
                'Postgre' => 'INTEGER NULL',
                'SQLite3' => 'INTEGER NULL',
                default => 'INT UNSIGNED NULL',
            },
            'is_locked'          => match ($driver) {
                'Postgre' => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'SQLite3' => 'INTEGER NOT NULL DEFAULT 0',
                default => 'TINYINT(1) NOT NULL DEFAULT 0',
            },
            'is_sticky'          => match ($driver) {
                'Postgre' => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'SQLite3' => 'INTEGER NOT NULL DEFAULT 0',
                default => 'TINYINT(1) NOT NULL DEFAULT 0',
            },
            'view_count'         => match ($driver) {
                'Postgre', 'SQLite3' => 'INTEGER NOT NULL DEFAULT 0',
                default => 'INT UNSIGNED NOT NULL DEFAULT 0',
            },
            'last_reply_at'      => match ($driver) {
                'Postgre' => 'TIMESTAMP NULL',
                'SQLite3' => 'TEXT NULL',
                default => 'DATETIME NULL',
            },
            'last_reply_user_id' => match ($driver) {
                'Postgre', 'SQLite3' => 'INTEGER NULL',
                default => 'INT UNSIGNED NULL',
            },
        ];

        foreach ($additions as $column => $definition) {
            if (! in_array($column, $fields, true)) {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }

        $this->backfillTopicLastReply($db, $table, $driver);
    }

    public static function categorySlug(string $category): string
    {
        return rawurlencode(trim($category));
    }

    public static function categoryFromSlug(string $slug): string
    {
        return trim(rawurldecode($slug));
    }

    public static function isTopic(array $row): bool
    {
        $parentId = (int) ($row['parent_id'] ?? 0);

        return $parentId <= 0;
    }

    public static function topicId(array $row): int
    {
        return self::isTopic($row) ? (int) ($row['id'] ?? 0) : (int) ($row['parent_id'] ?? 0);
    }

    /**
     * @param callable(bool, ?array): object $listBuilder factory
     *
     * @return list<array<string, mixed>>
     */
    public function boardIndex(callable $listBuilder, bool $canManageAll, ?array $current): array
    {
        $db = AppDatabase::connection();
        $boards = [];

        if ($db->tableExists('community_categories')) {
            foreach ($db->table('community_categories')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray() as $cat) {
                if (! $this->booleanField($cat['is_active'] ?? false) && ! $canManageAll) {
                    continue;
                }

                $name = trim((string) ($cat['name'] ?? ''));
                if ($name === '' || $name === 'Unknown') {
                    continue;
                }

                $boards[] = array_merge($cat, $this->categoryStats($listBuilder, $canManageAll, $current, $name));
            }
        }

        return $boards;
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return array<string, mixed>|null
     */
    public function uncategorizedBoard(callable $listBuilder, bool $canManageAll, ?array $current): ?array
    {
        $stats = $this->categoryStats($listBuilder, $canManageAll, $current, 'Unknown');
        if ($stats['topic_count'] === 0) {
            return null;
        }

        $unknownId = 0;
        $unknown = (new CommunityCategoryService())->findBySlugOrName('Unknown');
        if (is_array($unknown)) {
            $unknownId = (int) ($unknown['id'] ?? 0);
        }

        return [
            'id'          => $unknownId,
            'name'        => 'Uncategorized',
            'description' => 'Topics without a forum category.',
            'slug'        => 'unknown',
            'forum_url'   => is_array($unknown) ? CommunityCategoryService::forumUrl($unknown) : CommunityCategoryService::forumUrl($unknownId),
        ] + $stats;
    }

    /**
     * @param array<string, mixed>|string $category category row or legacy name
     *
     * @return array{topic_count: int, post_count: int, last_topic_id: int, last_title: string, last_author_name: string, last_at: string}
     */
    /**
     * @param list<array<string, mixed>> $categories
     */
    public function preloadCategoryStats(array $categories, callable $listBuilder, bool $canManageAll, ?array $current): void
    {
        if ($categories === []) {
            return;
        }

        $stats = [];
        $useCategoryId = $this->hasContentColumn('category_id');
        $categoryIds = [];
        $categoryNames = [];

        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            $categoryName = (string) ($category['name'] ?? 'Unknown');
            if ($categoryId > 0) {
                $categoryIds[] = $categoryId;
            }
            if ($categoryName !== '') {
                $categoryNames[] = $categoryName;
            }
            $stats[$this->categoryStatsKey($categoryId, $categoryName)] = $this->formatLastActivity(null, 0, 0);
        }

        $topicGroups = [];
        $topicBuilder = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($topicBuilder);
        if ($useCategoryId && $categoryIds !== []) {
            $topicBuilder->select('category_id, category, COUNT(*) as cnt', false);
            $topicBuilder->groupStart()
                ->whereIn('category_id', array_values(array_unique($categoryIds)))
                ->orGroupStart()
                    ->whereIn('category', array_values(array_unique($categoryNames)))
                    ->where('category_id', null)
                ->groupEnd()
            ->groupEnd();
            $topicBuilder->groupBy('category_id, category');
            $topicGroups = $topicBuilder->get()->getResultArray();
        } else {
            foreach (array_values(array_unique($categoryNames)) as $categoryName) {
                $builder = $listBuilder($canManageAll, $current);
                $this->applyTopicOnly($builder);
                $this->applyCategoryFilter($builder, 0, $categoryName);
                $key = $this->categoryStatsKey(0, $categoryName);
                if (isset($stats[$key])) {
                    $stats[$key]['topic_count'] = (int) $builder->countAllResults();
                }
            }
        }

        $postGroups = [];
        $postBuilder = $listBuilder($canManageAll, $current);
        if ($useCategoryId && $categoryIds !== []) {
            $postBuilder->select('category_id, category, COUNT(*) as cnt', false);
            $postBuilder->groupStart()
                ->whereIn('category_id', array_values(array_unique($categoryIds)))
                ->orGroupStart()
                    ->whereIn('category', array_values(array_unique($categoryNames)))
                    ->where('category_id', null)
                ->groupEnd()
            ->groupEnd();
            $postBuilder->groupBy('category_id, category');
            $postGroups = $postBuilder->get()->getResultArray();
        } else {
            foreach (array_values(array_unique($categoryNames)) as $categoryName) {
                $builder = $listBuilder($canManageAll, $current);
                $this->applyCategoryFilter($builder, 0, $categoryName);
                $key = $this->categoryStatsKey(0, $categoryName);
                if (isset($stats[$key])) {
                    $stats[$key]['post_count'] = (int) $builder->countAllResults();
                }
            }
        }

        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            $categoryName = (string) ($category['name'] ?? 'Unknown');
            $key = $this->categoryStatsKey($categoryId, $categoryName);
            if ($topicGroups !== []) {
                $stats[$key]['topic_count'] = $this->sumGroupedCount($topicGroups, $categoryId, $categoryName);
            }
            if ($postGroups !== []) {
                $stats[$key]['post_count'] = $this->sumGroupedCount($postGroups, $categoryId, $categoryName);
            }
        }

        $lastBuilder = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($lastBuilder);
        if ($useCategoryId && $categoryIds !== []) {
            $lastBuilder->groupStart()
                ->whereIn('category_id', array_values(array_unique($categoryIds)))
                ->orGroupStart()
                    ->whereIn('category', array_values(array_unique($categoryNames)))
                    ->where('category_id', null)
                ->groupEnd()
            ->groupEnd();
        } elseif ($categoryNames !== []) {
            $lastBuilder->whereIn('category', array_values(array_unique($categoryNames)));
        }

        $lastRows = $lastBuilder
            ->select('id, c_id, title, category_id, category, last_reply_at, last_reply_user_id, author_id, created_at, is_sticky')
            ->orderBy('is_sticky', 'DESC')
            ->orderBy('last_reply_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $lastByKey = [];
        foreach ($lastRows as $row) {
            foreach ($categories as $category) {
                $categoryId = (int) ($category['id'] ?? 0);
                $categoryName = (string) ($category['name'] ?? 'Unknown');
                if (! $this->contentRowMatchesCategory($row, $categoryId, $categoryName)) {
                    continue;
                }
                $key = $this->categoryStatsKey($categoryId, $categoryName);
                if (! isset($lastByKey[$key])) {
                    $lastByKey[$key] = $row;
                }
            }
        }

        $authorIds = [];
        foreach ($lastByKey as $row) {
            $authorId = (int) ($row['last_reply_user_id'] ?? 0);
            if ($authorId <= 0) {
                $authorId = (int) ($row['author_id'] ?? 0);
            }
            if ($authorId > 0) {
                $authorIds[] = $authorId;
            }
        }

        $authors = [];
        if ($authorIds !== []) {
            foreach (AppDatabase::connection()->table('users')->select('id, username')->whereIn('id', array_values(array_unique($authorIds)))->get()->getResultArray() as $user) {
                $authors[(int) ($user['id'] ?? 0)] = (string) ($user['username'] ?? '');
            }
        }

        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            $categoryName = (string) ($category['name'] ?? 'Unknown');
            $key = $this->categoryStatsKey($categoryId, $categoryName);
            $topicCount = $stats[$key]['topic_count'] ?? 0;
            $postCount = $stats[$key]['post_count'] ?? 0;
            $lastRow = $lastByKey[$key] ?? null;
            $stats[$key] = $this->formatLastActivity(is_array($lastRow) ? $lastRow : null, $topicCount, $postCount);
        }

        $this->preloadedCategoryStats = $stats;
    }

    public function clearPreloadedCategoryStats(): void
    {
        $this->preloadedCategoryStats = null;
    }

    public function categoryStats(callable $listBuilder, bool $canManageAll, ?array $current, array|string $category): array
    {
        $categoryId = is_array($category) ? (int) ($category['id'] ?? 0) : 0;
        $categoryName = is_array($category) ? (string) ($category['name'] ?? 'Unknown') : $category;

        if ($this->preloadedCategoryStats !== null) {
            $key = $this->categoryStatsKey($categoryId, $categoryName);

            return $this->preloadedCategoryStats[$key] ?? $this->formatLastActivity(null, 0, 0);
        }

        $topicBuilder = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($topicBuilder);
        $this->applyCategoryFilter($topicBuilder, $categoryId, $categoryName);
        $topicCount = (int) $topicBuilder->countAllResults();

        $postBuilder = $listBuilder($canManageAll, $current);
        $this->applyCategoryFilter($postBuilder, $categoryId, $categoryName);
        $postCount = (int) $postBuilder->countAllResults();

        $last = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($last);
        $this->applyCategoryFilter($last, $categoryId, $categoryName);
        $last->orderBy('is_sticky', 'DESC');
        $last->orderBy('last_reply_at', 'DESC');
        $last->orderBy('id', 'DESC');
        $lastRow = $last->select('id, c_id, title, last_reply_at, last_reply_user_id, author_id, created_at')->limit(1)->get()->getRowArray();

        return $this->formatLastActivity($lastRow, $topicCount, $postCount);
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array<string, mixed>>
     */
    /**
     * @param array<string, mixed> $categoryRow
     *
     * @return list<array<string, mixed>>
     */
    public function topicsInCategory(callable $listBuilder, bool $canManageAll, ?array $current, array $categoryRow, int $limit, int $offset): array
    {
        $builder = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($builder);
        $this->applyCategoryFilter($builder, (int) ($categoryRow['id'] ?? 0), (string) ($categoryRow['name'] ?? 'Unknown'));
        $rows = $builder
            ->select('id, c_id, title, category, body, status, author_id, parent_id, is_locked, is_sticky, view_count, last_reply_at, last_reply_user_id, created_at, updated_at')
            ->orderBy('is_sticky', 'DESC')
            ->orderBy('last_reply_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return $this->decorateTopics($rows, $listBuilder);
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     */
    /**
     * @param array<string, mixed> $categoryRow
     */
    public function topicCountInCategory(callable $listBuilder, bool $canManageAll, ?array $current, array $categoryRow): int
    {
        $builder = $listBuilder($canManageAll, $current);
        $this->applyTopicOnly($builder);
        $this->applyCategoryFilter($builder, (int) ($categoryRow['id'] ?? 0), (string) ($categoryRow['name'] ?? 'Unknown'));

        return (int) $builder->countAllResults();
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array<string, mixed>>
     */
    public function repliesForTopic(callable $listBuilder, int $topicId, bool $canManageAll, ?array $current): array
    {
        $builder = $listBuilder($canManageAll, $current);
        $builder->where('parent_id', $topicId);
        $rows = $builder
            ->select('id, title, category, body, status, author_id, parent_id, is_locked, is_sticky, created_at, updated_at')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->decorateAuthors($rows);
    }

    public function replyCount(int $topicId): int
    {
        return (int) AppDatabase::connection()
            ->table('community_contents')
            ->where('parent_id', $topicId)
            ->countAllResults();
    }

    public function refreshTopicLastReply(int $topicId): void
    {
        $db = AppDatabase::connection();
        $latest = $db->table('community_contents')
            ->select('author_id, created_at')
            ->where('parent_id', $topicId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if (is_array($latest)) {
            $db->table('community_contents')->where('id', $topicId)->update([
                'last_reply_at'       => (string) ($latest['created_at'] ?? date('Y-m-d H:i:s')),
                'last_reply_user_id'  => (int) ($latest['author_id'] ?? 0) ?: null,
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $topic = $db->table('community_contents')->where('id', $topicId)->get()->getRowArray();
        if (! is_array($topic)) {
            return;
        }

        $db->table('community_contents')->where('id', $topicId)->update([
            'last_reply_at'      => (string) ($topic['created_at'] ?? date('Y-m-d H:i:s')),
            'last_reply_user_id' => (int) ($topic['author_id'] ?? 0) ?: null,
        ]);
    }

    public function incrementTopicViews(int $topicId): void
    {
        $db = AppDatabase::connection();
        $table = $db->escapeIdentifiers($db->prefixTable('community_contents'));
        $db->simpleQuery("UPDATE {$table} SET view_count = view_count + 1 WHERE id = " . (int) $topicId);
    }

    public function deletePostCascade(int $id, bool $isTopic): void
    {
        $db = AppDatabase::connection();
        if ($isTopic) {
            $db->table('community_contents')->where('parent_id', $id)->delete();
        }

        $db->table('community_contents')->where('id', $id)->delete();
    }

    /**
     * @param object $builder
     */
    private function applyTopicOnly(object $builder): void
    {
        $builder->groupStart()
            ->where('parent_id', null)
            ->orWhere('parent_id', 0)
            ->groupEnd();
    }

    /**
     * @param object $builder
     */
    private function applyCategoryFilter(object $builder, int $categoryId, string $categoryName): void
    {
        if ($categoryId > 0 && $this->hasContentColumn('category_id')) {
            $builder->groupStart()
                ->where('category_id', $categoryId)
                ->orGroupStart()
                    ->where('category_id', null)
                    ->where('category', $categoryName)
                ->groupEnd()
            ->groupEnd();

            return;
        }

        $builder->where('category', $categoryName);
    }

    private function hasContentColumn(string $column): bool
    {
        static $cache = null;
        if ($cache === null) {
            $db = AppDatabase::connection();
            $cache = $db->tableExists('community_contents')
                ? $db->getFieldNames('community_contents')
                : [];
        }

        return in_array($column, $cache, true);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function decorateTopics(array $rows, callable $listBuilder): array
    {
        $rows = $this->decorateAuthors($rows);
        $topicIds = [];
        foreach ($rows as $row) {
            $topicId = (int) ($row['id'] ?? 0);
            if ($topicId > 0) {
                $topicIds[] = $topicId;
            }
        }
        $replyCounts = $this->replyCountsForTopics($topicIds);

        foreach ($rows as &$row) {
            $topicId = (int) ($row['id'] ?? 0);
            $row['reply_count'] = $replyCounts[$topicId] ?? 0;
            $row['is_locked'] = $this->booleanField($row['is_locked'] ?? false);
            $row['is_sticky'] = $this->booleanField($row['is_sticky'] ?? false);
            $row['last_activity_at'] = (string) ($row['last_reply_at'] ?? $row['created_at'] ?? '');
            $row['topic_url'] = CommunityContentUrls::topicUrl($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<int> $topicIds
     *
     * @return array<int, int>
     */
    private function replyCountsForTopics(array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_filter($topicIds, static fn (int $id): bool => $id > 0)));
        if ($topicIds === []) {
            return [];
        }

        $counts = array_fill_keys($topicIds, 0);
        foreach (
            AppDatabase::connection()
                ->table('community_contents')
                ->select('parent_id, COUNT(*) as cnt', false)
                ->whereIn('parent_id', $topicIds)
                ->groupBy('parent_id')
                ->get()
                ->getResultArray() as $row
        ) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId > 0) {
                $counts[$parentId] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $counts;
    }

    private function categoryStatsKey(int $categoryId, string $categoryName): string
    {
        return $categoryId > 0 ? 'id:' . $categoryId : 'name:' . $categoryName;
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function sumGroupedCount(array $groups, int $categoryId, string $categoryName): int
    {
        $total = 0;
        foreach ($groups as $row) {
            if ($this->contentRowMatchesCategory($row, $categoryId, $categoryName)) {
                $total += (int) ($row['cnt'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function contentRowMatchesCategory(array $row, int $categoryId, string $categoryName): bool
    {
        $rowCategoryId = (int) ($row['category_id'] ?? 0);
        if ($categoryId > 0 && $rowCategoryId === $categoryId) {
            return true;
        }

        return strcasecmp((string) ($row['category'] ?? ''), $categoryName) === 0;
    }

    /**
     * @param array<string, mixed>|null $lastRow
     *
     * @return array{topic_count: int, post_count: int, last_topic_id: int, last_title: string, last_author_name: string, last_at: string}
     */
    private function formatLastActivity(?array $lastRow, int $topicCount, int $postCount): array
    {
        $empty = [
            'topic_count'       => $topicCount,
            'post_count'        => $postCount,
            'last_topic_id'     => 0,
            'last_title'        => '',
            'last_author_name'  => '',
            'last_at'           => '',
        ];

        if (! is_array($lastRow)) {
            return $empty;
        }

        $authorId = (int) ($lastRow['last_reply_user_id'] ?? 0);
        if ($authorId <= 0) {
            $authorId = (int) ($lastRow['author_id'] ?? 0);
        }

        $authorName = '';
        if ($authorId > 0) {
            $user = AppDatabase::connection()->table('users')->select('username')->where('id', $authorId)->get()->getRowArray();
            $authorName = is_array($user) ? (string) ($user['username'] ?? '') : '';
        }

        return [
            'topic_count'      => $topicCount,
            'post_count'       => $postCount,
            'last_topic_id'    => (int) ($lastRow['id'] ?? 0),
            'last_topic_url'   => CommunityContentUrls::topicUrl($lastRow),
            'last_title'       => (string) ($lastRow['title'] ?? ''),
            'last_author_name' => $authorName !== '' ? $authorName : 'Unknown',
            'last_at'          => (string) ($lastRow['last_reply_at'] ?? $lastRow['created_at'] ?? ''),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function decorateAuthors(array $rows): array
    {
        $authorIds = [];
        foreach ($rows as $row) {
            $authorIds[] = (int) ($row['author_id'] ?? 0);
            $authorIds[] = (int) ($row['last_reply_user_id'] ?? 0);
        }

        $authorIds = array_values(array_unique(array_filter($authorIds)));
        $authors = [];
        if ($authorIds !== []) {
            foreach (AppDatabase::connection()->table('users')->select('id, username')->whereIn('id', $authorIds)->get()->getResultArray() as $user) {
                $authors[(int) ($user['id'] ?? 0)] = (string) ($user['username'] ?? '');
            }
        }

        foreach ($rows as &$row) {
            $authorId = (int) ($row['author_id'] ?? 0);
            $row['author_name'] = $authors[$authorId] ?? ($authorId > 0 ? 'User #' . $authorId : 'Unknown');
        }
        unset($row);

        return $rows;
    }

    private function backfillTopicLastReply(BaseConnection $db, string $table, string $driver): void
    {
        if ($driver === 'Postgre') {
            $db->simpleQuery("UPDATE {$table} SET last_reply_at = created_at, last_reply_user_id = author_id WHERE (parent_id IS NULL OR parent_id = 0) AND last_reply_at IS NULL");
        } elseif ($driver === 'SQLite3') {
            $db->simpleQuery("UPDATE {$table} SET last_reply_at = created_at, last_reply_user_id = author_id WHERE (parent_id IS NULL OR parent_id = 0) AND (last_reply_at IS NULL OR last_reply_at = '')");
        } else {
            $db->simpleQuery("UPDATE {$table} SET last_reply_at = created_at, last_reply_user_id = author_id WHERE (parent_id IS NULL OR parent_id = 0) AND last_reply_at IS NULL");
        }
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
