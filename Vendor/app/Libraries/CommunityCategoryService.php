<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Hierarchical community forums (phpBB-style): categories can contain sub-forums and topics.
 */
final class CommunityCategoryService
{
    /** @var array<int, list<array<string, mixed>>>|null */
    private ?array $childrenByParent = null;

    public function ensureSchema(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('community_categories')) {
            return;
        }

        $this->ensureCategoryColumns($db);
        $this->ensureContentCategoryId($db);
        $this->backfillSlugs($db);
        $this->backfillContentCategoryIds($db);
        $this->backfillCategoryCangIds($db);
    }

    public static function slugFromName(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? substr($slug, 0, 100) : 'forum';
    }

    /**
     * @param array<string, mixed>|int $category Category row or numeric id
     */
    public static function forumUrl(array|int $category): string
    {
        if (is_int($category)) {
            $row = (new self())->findById($category);

            return CommunityForumUrls::forumUrl(is_array($row) ? $row : ['id' => $category]);
        }

        return CommunityForumUrls::forumUrl($category);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlugOrName(string $segment): ?array
    {
        $segment = trim(rawurldecode($segment));
        if ($segment === '') {
            return null;
        }

        $db = AppDatabase::connection();
        if (! $db->tableExists('community_categories')) {
            return null;
        }

        $row = $db->table('community_categories')->where('slug', $segment)->get()->getRowArray();
        if (! is_array($row)) {
            $row = $db->table('community_categories')->where('name', $segment)->get()->getRowArray();
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = AppDatabase::connection()->table('community_categories')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allRows(bool $includeInactive = false): array
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('community_categories')) {
            return [];
        }

        $rows = $db->table('community_categories')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $normalized = [];
        foreach ($rows as $row) {
            $row = $this->normalizeRow($row);
            if (! $includeInactive && ! $row['is_active'] && ! $row['is_system']) {
                continue;
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildTree(bool $includeInactive = false): array
    {
        $byParent = [];
        foreach ($this->allRows(true) as $row) {
            if (! $includeInactive && ! $row['is_active'] && ! $row['is_system']) {
                continue;
            }
            $parentId = (int) ($row['parent_id'] ?? 0);
            $byParent[$parentId][] = $row;
        }

        $attach = static function (int $parentId) use (&$attach, $byParent): array {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $row['children'] = $attach($id);
                $nodes[] = $row;
            }

            return $nodes;
        };

        return $attach(0);
    }

    /**
     * Flat list for admin / select: id => indented label.
     *
     * @return array<int, string>
     */
    public function optionsForSelect(bool $includeInactive, int $selectedId = 0, int $excludeId = 0): array
    {
        $options = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$options, $includeInactive, $selectedId, $excludeId): void {
            foreach ($nodes as $node) {
                $id = (int) ($node['id'] ?? 0);
                if ($id <= 0 || $id === $excludeId) {
                    continue;
                }
                if (! $includeInactive && ! $node['is_active'] && $id !== $selectedId) {
                    continue;
                }
                if ((string) ($node['name'] ?? '') === 'Unknown') {
                    continue;
                }

                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $options[$id] = $prefix . (string) ($node['name'] ?? 'Forum');
                $walk($node['children'] ?? [], $depth + 1);
            }
        };

        $walk($this->buildTree($includeInactive), 0);

        return $options;
    }

    public function hasChildren(int $categoryId): bool
    {
        return $this->childRows($categoryId) !== [];
    }

    /**
     * @return list<int>
     */
    public function descendantIds(int $categoryId): array
    {
        $ids = [];
        $walk = function (int $parentId) use (&$walk, &$ids): void {
            foreach ($this->childRows($parentId) as $child) {
                $id = (int) ($child['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $ids[] = $id;
                $walk($id);
            }
        };
        $walk($categoryId);

        return $ids;
    }

    public function isValidParent(int $categoryId, int $parentId): bool
    {
        if ($parentId <= 0) {
            return true;
        }

        if ($categoryId > 0 && ($parentId === $categoryId || in_array($parentId, $this->descendantIds($categoryId), true))) {
            return false;
        }

        return $this->findById($parentId) !== null;
    }

    public function uniqueSlug(string $name, int $excludeId = 0): string
    {
        $base = self::slugFromName($name);
        $slug = $base;
        $suffix = 2;
        $db = AppDatabase::connection();

        while (true) {
            $builder = $db->table('community_categories')->where('slug', $slug);
            if ($excludeId > 0) {
                $builder->where('id !=', $excludeId);
            }
            if ((int) $builder->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    /**
     * Board index sections for phpBB-style layout.
     *
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array{title: ?string, description: string, boards: list<array<string, mixed>>}>
     */
    public function boardSections(callable $listBuilder, bool $canManageAll, ?array $current, CommunityForumService $forum): array
    {
        $this->childrenByParent = $this->buildChildrenMap($canManageAll);
        $tree = $this->buildTree($canManageAll);
        $forum->preloadCategoryStats($this->collectBoardNodes($tree), $listBuilder, $canManageAll, $current);

        $sections = [];
        $standalone = [];

        foreach ($tree as $root) {
            if ((string) ($root['name'] ?? '') === 'Unknown') {
                continue;
            }

            $boards = $this->flattenBoardRows($root, $listBuilder, $canManageAll, $current, $forum, 0);
            if ($boards === []) {
                continue;
            }

            $hasChildren = ($root['children'] ?? []) !== [];
            if ($hasChildren) {
                $sections[] = [
                    'title'       => (string) ($root['name'] ?? ''),
                    'description' => (string) ($root['description'] ?? ''),
                    'boards'      => $boards,
                ];
            } else {
                $standalone = array_merge($standalone, $boards);
            }
        }

        if ($standalone !== []) {
            $sections[] = [
                'title'       => null,
                'description' => '',
                'boards'      => $standalone,
            ];
        }

        $this->childrenByParent = null;
        $forum->clearPreloadedCategoryStats();

        return $sections;
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array<string, mixed>>
     */
    public function childBoardsForForum(array $category, callable $listBuilder, bool $canManageAll, ?array $current, CommunityForumService $forum): array
    {
        $boards = [];
        foreach ($this->childRows((int) ($category['id'] ?? 0)) as $child) {
            if (! $child['is_active'] && ! $canManageAll) {
                continue;
            }
            $boards[] = $this->boardRow($child, $listBuilder, $canManageAll, $current, $forum, 0);
        }

        return $boards;
    }

    /**
     * Flat forum list for ACP (phpBB-style administration index).
     *
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array<string, mixed>>
     */
    public function adminFlattenTree(callable $listBuilder, CommunityForumService $forum): array
    {
        $rows = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$rows, $listBuilder, $forum): void {
            foreach ($nodes as $node) {
                if ((string) ($node['name'] ?? '') === 'Unknown') {
                    continue;
                }

                $children = $node['children'] ?? [];
                $childCount = count($children);
                $nodeType = $this->adminNodeType($depth, $childCount);
                $stats = $forum->categoryStats($listBuilder, true, null, $node);

                $rows[] = array_merge($node, $stats, [
                    'depth'        => $depth,
                    'child_count'  => $childCount,
                    'node_type'    => $nodeType,
                    'type_label'   => match ($nodeType) {
                        'category' => 'Category',
                        'subforum' => 'Sub-forum',
                        default    => 'Forum',
                    },
                    'forum_url'    => self::forumUrl($node),
                ]);

                $walk($children, $depth + 1);
            }
        };

        $walk($this->buildTree(true), 0);

        return $rows;
    }

    /**
     * @param array<string, mixed> $category
     */
    public function syncContentCategoryName(int $categoryId, string $name): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('community_contents')) {
            return;
        }

        $db->table('community_contents')->where('category_id', $categoryId)->update(['category' => $name]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function normalizeRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['parent_id'] = (int) ($row['parent_id'] ?? 0) ?: null;
        $row['is_active'] = $this->booleanField($row['is_active'] ?? false);
        $row['is_system'] = $this->booleanField($row['is_system'] ?? false);
        $slug = trim((string) ($row['slug'] ?? ''));
        $row['slug'] = $slug !== '' ? $slug : self::slugFromName((string) ($row['name'] ?? 'forum'));

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function childRows(int $parentId): array
    {
        if ($this->childrenByParent !== null) {
            return $this->childrenByParent[$parentId] ?? [];
        }

        $rows = [];
        foreach ($this->allRows(true) as $row) {
            if ((int) ($row['parent_id'] ?? 0) === $parentId) {
                $rows[] = $row;
            }
        }

        usort($rows, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
            ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $rows;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function buildChildrenMap(bool $includeInactive): array
    {
        $map = [];
        foreach ($this->allRows($includeInactive) as $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            $map[$parentId][] = $row;
        }

        foreach ($map as &$rows) {
            usort($rows, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        }
        unset($rows);

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    private function collectBoardNodes(array $tree): array
    {
        $nodes = [];
        $walk = function (array $items) use (&$walk, &$nodes): void {
            foreach ($items as $item) {
                if ((string) ($item['name'] ?? '') !== 'Unknown') {
                    $nodes[] = $item;
                }
                $walk($item['children'] ?? []);
            }
        };
        $walk($tree);

        return $nodes;
    }

    /**
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return list<array<string, mixed>>
     */
    private function flattenBoardRows(array $node, callable $listBuilder, bool $canManageAll, ?array $current, CommunityForumService $forum, int $depth): array
    {
        $rows = [];
        if ($this->shouldShowBoard($node, $canManageAll)) {
            $rows[] = $this->boardRow($node, $listBuilder, $canManageAll, $current, $forum, $depth);
        }

        foreach ($node['children'] ?? [] as $child) {
            $rows = array_merge($rows, $this->flattenBoardRows($child, $listBuilder, $canManageAll, $current, $forum, $depth + 1));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function shouldShowBoard(array $node, bool $canManageAll): bool
    {
        if ((string) ($node['name'] ?? '') === 'Unknown') {
            return false;
        }

        return $node['is_active'] || $canManageAll;
    }

    /**
     * @param array<string, mixed> $node
     * @param callable(bool, ?array): object $listBuilder
     *
     * @return array<string, mixed>
     */
    private function boardRow(array $node, callable $listBuilder, bool $canManageAll, ?array $current, CommunityForumService $forum, int $depth): array
    {
        $stats = $forum->categoryStats($listBuilder, $canManageAll, $current, $node);

        return array_merge($node, $stats, [
            'depth'        => $depth,
            'child_count'  => count($this->childRows((int) ($node['id'] ?? 0))),
            'forum_url'    => self::forumUrl($node),
        ]);
    }

    private function ensureCategoryColumns(BaseConnection $db): void
    {
        $table = $db->escapeIdentifiers($db->prefixTable('community_categories'));
        $fields = $db->getFieldNames('community_categories');
        $driver = (string) ($db->DBDriver ?? '');

        $additions = [
            'parent_id' => match ($driver) {
                'Postgre', 'SQLite3' => 'INTEGER NULL',
                default => 'INT UNSIGNED NULL',
            },
            'slug' => match ($driver) {
                'Postgre' => 'VARCHAR(100) NULL',
                'SQLite3' => 'TEXT NULL',
                default => 'VARCHAR(100) NULL',
            },
            'c_id' => match ($driver) {
                'Postgre' => 'VARCHAR(128) NULL',
                'SQLite3' => 'TEXT NULL',
                default => 'VARCHAR(128) NULL',
            },
        ];

        foreach ($additions as $column => $definition) {
            if (! in_array($column, $fields, true)) {
                $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    private function ensureContentCategoryId(BaseConnection $db): void
    {
        if (! $db->tableExists('community_contents')) {
            return;
        }

        $fields = $db->getFieldNames('community_contents');
        if (in_array('category_id', $fields, true)) {
            return;
        }

        $table = $db->escapeIdentifiers($db->prefixTable('community_contents'));
        $definition = match ((string) ($db->DBDriver ?? '')) {
            'Postgre', 'SQLite3' => 'INTEGER NULL',
            default => 'INT UNSIGNED NULL',
        };
        $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN category_id {$definition}");
    }

    private function backfillSlugs(BaseConnection $db): void
    {
        foreach ($db->table('community_categories')->get()->getResultArray() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                $db->table('community_categories')->where('id', $id)->update([
                    'slug' => $this->uniqueSlug((string) ($row['name'] ?? 'forum'), $id),
                ]);
            }
        }
    }

    private function backfillCategoryCangIds(BaseConnection $db): void
    {
        if (! in_array('c_id', $db->getFieldNames('community_categories'), true)) {
            return;
        }

        $security = new SecurityCangService();
        $security->ensureCangColumn('community_categories');
        $security->backfillCangColumn(SecurityCangService::COMMUNITY_CATEGORY_URL_ID, 'community_categories');
    }

    private function backfillContentCategoryIds(BaseConnection $db): void
    {
        if (! $db->tableExists('community_contents')) {
            return;
        }

        $categories = [];
        foreach ($db->table('community_categories')->get()->getResultArray() as $cat) {
            $categories[(string) ($cat['name'] ?? '')] = (int) ($cat['id'] ?? 0);
        }

        $unknownId = $categories['Unknown'] ?? 0;

        foreach ($db->table('community_contents')->select('id, category, category_id')->get()->getResultArray() as $post) {
            $postId = (int) ($post['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $categoryId = (int) ($post['category_id'] ?? 0);
            if ($categoryId > 0) {
                continue;
            }

            $name = trim((string) ($post['category'] ?? 'Unknown'));
            $resolved = $categories[$name] ?? $unknownId;
            if ($resolved > 0) {
                $db->table('community_contents')->where('id', $postId)->update(['category_id' => $resolved]);
            }
        }
    }

    private function adminNodeType(int $depth, int $childCount): string
    {
        if ($depth === 0 && $childCount > 0) {
            return 'category';
        }

        if ($depth > 0) {
            return 'subforum';
        }

        return 'forum';
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
