<?php

declare(strict_types=1);

namespace App\Controllers\Content;

use App\Controllers\BaseController;
use App\Libraries\AppDatabase;
use App\Libraries\ModuleSettings;
use App\Libraries\RoleService;
use App\Libraries\SecurityCangService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

class CommunityContent extends BaseController
{
    protected $helpers = ['form', 'url'];

    private const POSTS_PER_PAGE = 10;

    public function index(): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $current = $this->currentUser();
        $canManageAll = $current !== null && $this->canManageAll($current);
        $total = $this->contentCount($canManageAll, $current);
        $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $page = min($page, $totalPages);

        return view('content/community/index', [
            'title'      => 'Community Content',
            'wideLayout' => true,
            'posts'      => $this->contentRows($canManageAll, $current, self::POSTS_PER_PAGE, ($page - 1) * self::POSTS_PER_PAGE),
            'current'    => $current,
            'canCreate'  => $current !== null,
            'canManageCategories' => $canManageAll,
            'pagination' => [
                'page'       => $page,
                'perPage'    => self::POSTS_PER_PAGE,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function create(): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        return view('content/community/form', [
            'title'      => 'Create Community Post',
            'wideLayout' => true,
            'mode'       => 'create',
            'post'       => [],
            'categories' => $this->categoryOptions(),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function store(): ResponseInterface
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $data = $this->contentPayload($current);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $db = AppDatabase::connection();
        $db->table('community_contents')->insert($data);
        $id = (int) $db->insertID();
        if ($id <= 0) {
            $created = $db->table('community_contents')
                ->select('id')
                ->where('author_id', (int) $current['id'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            $id = is_array($created) ? (int) ($created['id'] ?? 0) : 0;
        }

        return redirect()->to(site_url('Content/Community/View/' . $id))->with('message', 'Community post created.');
    }

    public function view(int $id): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $post = $this->findContent($id);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        $current = $this->currentUser();
        if (! $this->canViewPost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        return view('content/community/detail', [
            'title'     => (string) ($post['title'] ?? 'Community Post'),
            'post'      => $post,
            'bodyHtml'  => $this->renderedBodyHtml((string) ($post['body'] ?? '')),
            'canManage' => $this->canManagePost($post, $current),
            'errors'    => $this->flashErrors(),
        ]);
    }

    public function edit(int $id): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $post = $this->findContent($id);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only edit your own posts.']);
        }

        return view('content/community/form', [
            'title'      => 'Edit Community Post',
            'wideLayout' => true,
            'mode'       => 'edit',
            'post'       => $post,
            'categories' => $this->categoryOptions((string) ($post['category'] ?? 'Unknown')),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $post = $this->findContent($id);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only edit your own posts.']);
        }

        $data = $this->contentPayload($current, true);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        AppDatabase::connection()->table('community_contents')->where('id', $id)->update($data);

        return redirect()->to(site_url('Content/Community/View/' . $id))->with('message', 'Community post updated.');
    }

    public function confirmDelete(int $id): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $post = $this->findContent($id);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only delete your own posts.']);
        }

        return view('content/community/delete', [
            'title'  => 'Delete Community Post',
            'post'   => $post,
            'errors' => $this->flashErrors(),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $post = $this->findContent($id);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only delete your own posts.']);
        }

        AppDatabase::connection()->table('community_contents')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Community/Index'))->with('message', 'Community post deleted.');
    }

    public function categories(): ResponseInterface|string
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();

        return view('content/community/categories', [
            'title'      => 'Community Categories',
            'wideLayout' => true,
            'categories' => $this->categoryRows(),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function saveCategory(): ResponseInterface
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();

        $rules = [
            'name'        => 'required|min_length[2]|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
            'sort_order'  => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $db = AppDatabase::connection();
        $id = (int) ($this->request->getPost('id') ?? 0);
        $name = $this->normalizeCategory((string) $this->request->getPost('name'));
        $existing = $this->findCategoryByName($name);
        if (is_array($existing) && (int) ($existing['id'] ?? 0) !== $id) {
            return redirect()->back()->withInput()->with('errors', ['category' => 'That category already exists.']);
        }

        $data = [
            'name'        => $name,
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active'   => $this->request->getPost('is_active') !== null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $category = $this->findCategory($id);
            if (! is_array($category)) {
                return redirect()->to(site_url('Content/Community/Categories/Index'))->with('errors', ['category' => 'Category not found.']);
            }

            if (! empty($category['is_system'])) {
                $data['name'] = (string) $category['name'];
                $data['is_active'] = true;
            }

            $db->table('community_categories')->where('id', $id)->update($data);
            if ((string) ($category['name'] ?? '') !== $data['name']) {
                $db->table('community_contents')->where('category', (string) $category['name'])->update(['category' => $data['name']]);
            }

            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Community category updated.');
        }

        $data['is_system'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = null;
        $db->table('community_categories')->insert($data);

        return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Community category created.');
    }

    public function deleteCategory(int $id): ResponseInterface
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();
        $this->ensureContentTable();

        $category = $this->findCategory($id);
        if (! is_array($category)) {
            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('errors', ['category' => 'Category not found.']);
        }

        if (! empty($category['is_system'])) {
            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('errors', ['category' => 'System categories cannot be deleted.']);
        }

        $db = AppDatabase::connection();
        $db->table('community_contents')->where('category', (string) $category['name'])->update(['category' => 'Unknown']);
        $db->table('community_categories')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Community category deleted. Posts were moved to Unknown.');
    }

    /**
     * @param array<string, mixed>|null $current
     *
     * @return list<array<string, mixed>>
     */
    private function contentRows(bool $canManageAll, ?array $current, int $limit, int $offset): array
    {
        $rows = $this->contentListBuilder($canManageAll, $current)
            ->select('id, title, category, body, status, author_id, created_at, updated_at')
            ->orderBy('category', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['category'] = $this->normalizeCategory((string) ($row['category'] ?? ''));
        }
        unset($row);

        return $this->decorateAuthors($rows);
    }

    /**
     * @param array<string, mixed>|null $current
     */
    private function contentCount(bool $canManageAll, ?array $current): int
    {
        return $this->contentListBuilder($canManageAll, $current)->countAllResults();
    }

    /**
     * @param array<string, mixed>|null $current
     */
    private function contentListBuilder(bool $canManageAll, ?array $current): object
    {
        $builder = AppDatabase::connection()->table('community_contents');
        if (! $canManageAll) {
            $builder
                ->groupStart()
                ->where('status', 'published');
            if (is_array($current)) {
                $builder->orWhere('author_id', (int) ($current['id'] ?? 0));
            }
            $builder->groupEnd();
        }

        return $builder;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryRows(): array
    {
        $rows = AppDatabase::connection()
            ->table('community_categories')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['is_active'] = $this->booleanField($row['is_active'] ?? false);
            $row['is_system'] = $this->booleanField($row['is_system'] ?? false);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(string $selectedCategory = ''): array
    {
        $this->ensureCategoryTable();

        $options = [];
        foreach ($this->categoryRows() as $row) {
            if (! $this->booleanField($row['is_active'] ?? false) && (string) ($row['name'] ?? '') !== $selectedCategory) {
                continue;
            }

            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $options[$name] = $name;
            }
        }

        $selectedCategory = $this->normalizeCategory($selectedCategory);
        if (! isset($options[$selectedCategory])) {
            $options[$selectedCategory] = $selectedCategory;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCategory(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = AppDatabase::connection()->table('community_categories')->where('id', $id)->get()->getRowArray();
        if (is_array($row)) {
            $row['is_active'] = $this->booleanField($row['is_active'] ?? false);
            $row['is_system'] = $this->booleanField($row['is_system'] ?? false);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCategoryByName(string $name): ?array
    {
        $row = AppDatabase::connection()->table('community_categories')->where('name', $name)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContent(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = AppDatabase::connection()->table('community_contents')->where('id', $id)->get()->getRowArray();
        if (is_array($row)) {
            $row['category'] = $this->normalizeCategory((string) ($row['category'] ?? ''));
            $row = $this->decorateAuthors([$row])[0];
        }

        return is_array($row) ? $row : null;
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

    /**
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>|ResponseInterface
     */
    private function contentPayload(array $current, bool $isUpdate = false): array|ResponseInterface
    {
        $rules = [
            'title'  => 'required|min_length[3]|max_length[180]',
            'body'   => 'required|min_length[3]',
            'status' => 'required|in_list[draft,published]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $category = $this->normalizeCategory((string) $this->request->getPost('category'));
        if (! array_key_exists($category, $this->categoryOptions($category))) {
            return redirect()->back()->withInput()->with('errors', ['category' => 'Choose an active category.']);
        }

        $data = [
            'title'      => trim((string) $this->request->getPost('title')),
            'category'   => $category,
            'body'       => $this->sanitizePublicHtml((string) $this->request->getPost('body')),
            'status'     => (string) $this->request->getPost('status'),
            'author_id'  => (int) ($current['id'] ?? 0),
            'updated_at' => null,
        ];

        if (! $isUpdate) {
            $data['c_id'] = (new SecurityCangService())->generateFor(SecurityCangService::COMMUNITY_CONTENT_URL_ID, 'community_contents');
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    private function normalizeCategory(string $category): string
    {
        $category = trim(preg_replace('/\s+/', ' ', $category) ?? '');

        return $category !== '' ? substr($category, 0, 100) : 'Unknown';
    }

    /**
     * @return array<string, mixed>|ResponseInterface
     */
    private function requireLogin(): array|ResponseInterface
    {
        $current = $this->currentUser();
        if ($current === null) {
            return redirect()->to(site_url('Member/User/Login'))->with('errors', ['auth' => 'Log in to continue.']);
        }

        return $current;
    }

    private function requireCommunityContentEnabled(): ?ResponseInterface
    {
        if ((new ModuleSettings())->isEnabled(ModuleSettings::CONTENT_COMMUNITY)) {
            return null;
        }

        return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['content' => 'Community content is disabled.']);
    }

    /**
     * @return array<string, mixed>|ResponseInterface
     */
    private function requireCategoryManager(): array|ResponseInterface
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        if (! $this->canManageAll($current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['category' => 'Only Administrator, Manager, or Owner accounts can manage community categories.']);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $id = session()->get('member_user_id');
        if (! is_numeric($id)) {
            return null;
        }

        $user = AppDatabase::connection()->table('users')->where('id', (int) $id)->get()->getRowArray();
        if (! is_array($user) || ! (bool) ($user['is_active'] ?? false)) {
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canManageAll(array $user): bool
    {
        if ((bool) session()->get('member_can_manage_roles')) {
            return true;
        }

        return (new RoleService())->isAdministrator((string) ($user['role'] ?? ''));
    }

    /**
     * @param array<string, mixed>      $post
     * @param array<string, mixed>|null $current
     */
    private function canViewPost(array $post, ?array $current): bool
    {
        if ((string) ($post['status'] ?? '') === 'published') {
            return true;
        }

        return $this->canManagePost($post, $current);
    }

    /**
     * @param array<string, mixed>      $post
     * @param array<string, mixed>|null $current
     */
    private function canManagePost(array $post, ?array $current): bool
    {
        if ($current === null) {
            return false;
        }

        if ($this->canManageAll($current)) {
            return true;
        }

        return (int) ($post['author_id'] ?? 0) === (int) ($current['id'] ?? 0);
    }

    private function renderedBodyHtml(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        if ($body === strip_tags($body)) {
            return $this->plainTextToHtml($body);
        }

        return $this->sanitizePublicHtml($body);
    }

    private function sanitizePublicHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if ($html === strip_tags($html)) {
            return $this->plainTextToHtml($html);
        }

        $allowedTags = '<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><blockquote><pre><code><a><img><hr><div><span>';
        $html = strip_tags($html, $allowedTags);

        return trim($html);
    }

    private function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($escaped !== '') {
                $html[] = '<p>' . nl2br($escaped, false) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    private function ensureContentTable(): void
    {
        $db = AppDatabase::connection();
        if ($db->tableExists('community_contents')) {
            $this->ensureContentColumns($db);

            return;
        }

        foreach ($this->contentTableSql($db) as $sql) {
            $db->simpleQuery($sql);
        }
    }

    private function ensureContentColumns(BaseConnection $db): void
    {
        $fields = $db->getFieldNames('community_contents');
        $table = $db->escapeIdentifiers($db->prefixTable('community_contents'));

        if (! in_array('category', $fields, true)) {
            $definition = match ((string) ($db->DBDriver ?? '')) {
                'Postgre' => "VARCHAR(100) NOT NULL DEFAULT 'Unknown'",
                'SQLite3' => "TEXT NOT NULL DEFAULT 'Unknown'",
                default => "VARCHAR(100) NOT NULL DEFAULT 'Unknown'",
            };

            $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN category {$definition}");
        }

        $security = new SecurityCangService();
        $security->ensureCangColumn('community_contents');
        $security->backfillCangColumn(SecurityCangService::COMMUNITY_CONTENT_URL_ID, 'community_contents');
    }

    private function ensureCategoryTable(): void
    {
        $db = AppDatabase::connection();
        if (! $db->tableExists('community_categories')) {
            foreach ($this->categoryTableSql($db) as $sql) {
                $db->simpleQuery($sql);
            }
        }

        if (! $db->tableExists('community_categories')) {
            return;
        }

        if (! is_array($this->findCategoryByName('Unknown'))) {
            $db->table('community_categories')->insert([
                'name'        => 'Unknown',
                'description' => 'Posts created without a category.',
                'sort_order'  => 0,
                'is_active'   => true,
                'is_system'   => true,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => null,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function categoryTableSql(BaseConnection $db): array
    {
        $table = $db->escapeIdentifiers($db->prefixTable('community_categories'));
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($db->DBPrefix ?? '')) ?: '';
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, is_active BOOLEAN NOT NULL DEFAULT TRUE, is_system BOOLEAN NOT NULL DEFAULT FALSE, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_categories_active_idx ON {$table} (is_active, sort_order)",
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT '', sort_order INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, is_system INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_categories_active_idx ON {$table} (is_active, sort_order)",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, description VARCHAR(255) NOT NULL DEFAULT '', sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, is_system TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY {$prefix}community_categories_name_unique (name), KEY {$prefix}community_categories_active_idx (is_active, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];
    }

    /**
     * @return list<string>
     */
    private function contentTableSql(BaseConnection $db): array
    {
        $table = $db->escapeIdentifiers($db->prefixTable('community_contents'));
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($db->DBPrefix ?? '')) ?: '';
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id SERIAL PRIMARY KEY, c_id VARCHAR(128) NULL UNIQUE, title VARCHAR(180) NOT NULL, category VARCHAR(100) NOT NULL DEFAULT 'Unknown', body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'published', author_id INTEGER NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_category_idx ON {$table} (category)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_author_idx ON {$table} (author_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_created_idx ON {$table} (created_at)",
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, c_id TEXT UNIQUE, title TEXT NOT NULL, category TEXT NOT NULL DEFAULT 'Unknown', body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'published', author_id INTEGER, created_at TEXT NOT NULL, updated_at TEXT)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_category_idx ON {$table} (category)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_author_idx ON {$table} (author_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}community_contents_created_idx ON {$table} (created_at)",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, c_id VARCHAR(128) NULL DEFAULT NULL, title VARCHAR(180) NOT NULL, category VARCHAR(100) NOT NULL DEFAULT 'Unknown', body MEDIUMTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'published', author_id INT UNSIGNED NULL DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY {$prefix}community_contents_c_id_unique (c_id), KEY {$prefix}community_contents_category_idx (category), KEY {$prefix}community_contents_status_idx (status), KEY {$prefix}community_contents_author_idx (author_id), KEY {$prefix}community_contents_created_idx (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function flashErrors(): array
    {
        $flash = session()->getFlashdata('errors');

        return is_array($flash) ? $flash : [];
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
