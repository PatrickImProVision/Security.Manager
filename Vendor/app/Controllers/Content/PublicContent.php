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

class PublicContent extends BaseController
{
    protected $helpers = ['form', 'url'];

    private const CONTENT_PER_PAGE = 10;

    public function index(): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();

        $current = $this->currentUser();
        $canManage = $current !== null && $this->canManageContent($current);
        $total = $this->contentCount($canManage);
        $totalPages = max(1, (int) ceil($total / self::CONTENT_PER_PAGE));
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $page = min($page, $totalPages);

        return view('content/public/index', [
            'title'      => 'Public Content',
            'wideLayout' => true,
            'contents'   => $this->contentRows($canManage, self::CONTENT_PER_PAGE, ($page - 1) * self::CONTENT_PER_PAGE),
            'canManage'  => $canManage,
            'pagination' => [
                'page'       => $page,
                'perPage'    => self::CONTENT_PER_PAGE,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function create(): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        return view('content/public/form', [
            'title'      => 'Create Public Content',
            'wideLayout' => true,
            'mode'       => 'create',
            'content'    => [],
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function store(): ResponseInterface
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $data = $this->contentPayload($current);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $db = AppDatabase::connection();
        $db->table('public_contents')->insert($data);
        $id = (int) $db->insertID();
        if ($id <= 0) {
            $created = $db->table('public_contents')->select('id')->where('slug', (string) $data['slug'])->get()->getRowArray();
            $id = is_array($created) ? (int) ($created['id'] ?? 0) : 0;
        }

        return redirect()->to($this->contentViewUrl($data, $id))->with('message', 'Public content created.');
    }

    public function view(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();

        $content = $this->findContent($id);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        $current = $this->currentUser();
        $canManage = $current !== null && $this->canManageContent($current);
        if (! $canManage && ! $this->isPublished($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        if (! empty($content['show_in_nav'])) {
            return redirect()->to(site_url('Content/Public/View/' . (string) $content['slug']));
        }

        return $this->renderContent($content, $canManage);
    }

    public function viewSlug(string $slug): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();

        $slug = $this->normalizeSlug($slug);
        $content = AppDatabase::connection()->table('public_contents')->where('slug', $slug)->get()->getRowArray();
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }
        $content = $this->normalizeContentRow($content);

        $current = $this->currentUser();
        $canManage = $current !== null && $this->canManageContent($current);
        if (! $canManage && ! $this->isPublished($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        if (empty($content['show_in_nav'])) {
            return redirect()->to(site_url('Content/Public/View/' . (int) $content['id']));
        }

        return $this->renderContent($content, $canManage);
    }

    public function edit(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $content = $this->findContent($id);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        return view('content/public/form', [
            'title'      => 'Edit Public Content',
            'wideLayout' => true,
            'mode'       => 'edit',
            'content'    => $content,
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $content = $this->findContent($id);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        $data = $this->contentPayload($current, $id);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        AppDatabase::connection()->table('public_contents')->where('id', $id)->update($data);

        return redirect()->to($this->contentViewUrl($data, $id))->with('message', 'Public content updated.');
    }

    public function confirmDelete(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $content = $this->findContent($id);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        return view('content/public/delete', [
            'title'   => 'Delete Public Content',
            'content' => $content,
            'errors'  => $this->flashErrors(),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireContentManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureContentTable();

        $content = $this->findContent($id);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        AppDatabase::connection()->table('public_contents')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Public/Index'))->with('message', 'Public content deleted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentRows(bool $includeDrafts, int $limit, int $offset): array
    {
        $builder = $this->contentListBuilder($includeDrafts)
            ->select('id, title, slug, summary, status, show_in_nav, nav_label, nav_order, author_id, published_at, created_at, updated_at')
            ->orderBy('show_in_nav', 'DESC')
            ->orderBy('nav_order', 'ASC')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset);

        $rows = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $rows[] = $this->normalizeContentRow($row);
        }

        return $rows;
    }

    private function contentCount(bool $includeDrafts): int
    {
        return $this->contentListBuilder($includeDrafts)->countAllResults();
    }

    private function contentListBuilder(bool $includeDrafts): object
    {
        $builder = AppDatabase::connection()->table('public_contents');
        if (! $includeDrafts) {
            $builder
                ->where('status', 'published')
                ->groupStart()
                ->where('published_at', null)
                ->orWhere('published_at <=', date('Y-m-d H:i:s'))
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContent(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = AppDatabase::connection()->table('public_contents')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $this->normalizeContentRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeContentRow(array $row): array
    {
        $row['show_in_nav'] = $this->booleanValue($row['show_in_nav'] ?? false);

        return $row;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 't', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $content
     */
    private function renderContent(array $content, bool $canManage): string
    {
        return view('content/public/detail', [
            'title'     => (string) ($content['title'] ?? 'Public Content'),
            'content'   => $content,
            'bodyHtml'  => $this->renderedBodyHtml((string) ($content['body'] ?? '')),
            'canManage' => $canManage,
            'errors'    => $this->flashErrors(),
        ]);
    }

    /**
     * @param array<string, mixed> $content
     */
    private function contentViewUrl(array $content, int $id): string
    {
        if (! empty($content['show_in_nav'])) {
            return site_url('Content/Public/View/' . (string) $content['slug']);
        }

        if ($id <= 0) {
            return site_url('Content/Public/Index');
        }

        return site_url('Content/Public/View/' . $id);
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>|ResponseInterface
     */
    private function contentPayload(array $current, int $ignoreId = 0): array|ResponseInterface
    {
        $rules = [
            'title'        => 'required|min_length[3]|max_length[180]',
            'slug'         => 'permit_empty|max_length[191]',
            'summary'      => 'permit_empty|max_length[500]',
            'body'         => 'required|min_length[3]',
            'status'       => 'required|in_list[draft,published]',
            'published_at' => 'permit_empty|max_length[30]',
            'nav_label'    => 'permit_empty|max_length[100]',
            'nav_order'    => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $postedPublishedAt = trim((string) $this->request->getPost('published_at'));
        $publishedAt = $this->normalizeDateTime($postedPublishedAt);
        if ($postedPublishedAt !== '' && $publishedAt === null) {
            return redirect()->back()->withInput()->with('errors', ['published_at' => 'Use a valid publish date and time.']);
        }

        $title = trim((string) $this->request->getPost('title'));
        $slug = $this->normalizeSlug((string) ($this->request->getPost('slug') ?: $title));
        $status = (string) $this->request->getPost('status');
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $data = [
            'title'        => $title,
            'slug'         => $this->uniqueSlug($slug, $ignoreId),
            'summary'      => trim((string) $this->request->getPost('summary')) ?: null,
            'body'         => $this->sanitizePublicHtml((string) $this->request->getPost('body')),
            'status'       => $status,
            'show_in_nav'  => $this->request->getPost('show_in_nav') !== null,
            'nav_label'    => trim((string) $this->request->getPost('nav_label')) ?: null,
            'nav_order'    => (int) ($this->request->getPost('nav_order') ?: 0),
            'author_id'    => (int) ($current['id'] ?? 0),
            'published_at' => $publishedAt,
            'updated_at'   => null,
        ];

        if ($ignoreId <= 0) {
            $data['c_id'] = (new SecurityCangService())->generateFor(SecurityCangService::PUBLIC_CONTENT_URL_ID, 'public_contents');
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    private function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'content-' . time();
    }

    private function uniqueSlug(string $slug, int $ignoreId = 0): string
    {
        $base = substr($slug, 0, 180);
        $slug = $base;
        $i = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $suffix = '-' . $i;
            $slug = substr($base, 0, 191 - strlen($suffix)) . $suffix;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, int $ignoreId): bool
    {
        $builder = AppDatabase::connection()->table('public_contents')->where('slug', $slug);
        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    private function normalizeDateTime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
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

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="public-content-fragment">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $doc->getElementById('public-content-fragment');
        if ($wrapper === null) {
            return '';
        }

        foreach ($doc->getElementsByTagName('*') as $node) {
            if (! $node instanceof \DOMElement || $node->getAttribute('id') === 'public-content-fragment') {
                continue;
            }

            $this->sanitizeElementAttributes($node);
        }

        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitizeElementAttributes(\DOMElement $node): void
    {
        $tag = strtolower($node->nodeName);
        $allowed = match ($tag) {
            'a'     => ['href', 'title', 'target', 'rel'],
            'img'   => ['src', 'alt', 'title'],
            'p', 'div', 'h2', 'h3', 'h4', 'blockquote' => ['style'],
            default => [],
        };

        $remove = [];
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (! in_array($name, $allowed, true)) {
                $remove[] = $attribute->nodeName;
                continue;
            }

            $value = trim($attribute->nodeValue ?? '');
            if (($name === 'href' || $name === 'src') && ! $this->isSafeContentUrl($value)) {
                $remove[] = $attribute->nodeName;
                continue;
            }

            if ($name === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                $remove[] = $attribute->nodeName;
                continue;
            }

            if ($name === 'style') {
                $style = $this->sanitizeTextAlignStyle($value);
                if ($style === '') {
                    $remove[] = $attribute->nodeName;
                } else {
                    $node->setAttribute('style', $style);
                }
            }
        }

        foreach ($remove as $attributeName) {
            $node->removeAttribute($attributeName);
        }

        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function sanitizeTextAlignStyle(string $style): string
    {
        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\s*(?:;|$)/i', $style, $match) !== 1) {
            return '';
        }

        return 'text-align: ' . strtolower($match[1]) . ';';
    }

    private function isSafeContentUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null) {
            return ! str_contains($url, ':');
        }

        return in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true);
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

    /**
     * @return array<string, mixed>|ResponseInterface
     */
    private function requireContentManager(): array|ResponseInterface
    {
        $current = $this->currentUser();
        if ($current === null) {
            return redirect()->to(site_url('Member/User/Login'))->with('errors', ['auth' => 'Log in to continue.']);
        }

        if (! $this->canManageContent($current)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Only Administrator, Manager, or Owner accounts can manage public content.']);
        }

        return $current;
    }

    private function requirePublicContentEnabled(): ?ResponseInterface
    {
        if ((new ModuleSettings())->isEnabled(ModuleSettings::CONTENT_PUBLIC)) {
            return null;
        }

        return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['content' => 'Public content is disabled.']);
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
    private function canManageContent(array $user): bool
    {
        if ((bool) session()->get('member_can_manage_roles')) {
            return true;
        }

        return (new RoleService())->isAdministrator((string) ($user['role'] ?? ''));
    }

    /**
     * @param array<string, mixed> $content
     */
    private function isPublished(array $content): bool
    {
        if ((string) ($content['status'] ?? '') !== 'published') {
            return false;
        }

        $publishedAt = trim((string) ($content['published_at'] ?? ''));

        return $publishedAt === '' || strtotime($publishedAt) <= time();
    }

    private function ensureContentTable(): void
    {
        $db = AppDatabase::connection();
        if ($db->tableExists('public_contents')) {
            $this->ensureContentColumns($db);

            return;
        }

        foreach ($this->contentTableSql($db) as $sql) {
            $db->simpleQuery($sql);
        }
    }

    private function ensureContentColumns(BaseConnection $db): void
    {
        $fields = $db->getFieldNames('public_contents');
        $table = $db->escapeIdentifiers($db->prefixTable('public_contents'));
        $driver = (string) ($db->DBDriver ?? '');

        if (! in_array('show_in_nav', $fields, true)) {
            $definition = $driver === 'Postgre'
                ? 'BOOLEAN NOT NULL DEFAULT FALSE'
                : ($driver === 'SQLite3' ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0');
            $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN show_in_nav {$definition}");
        }

        if (! in_array('nav_label', $fields, true)) {
            $definition = $driver === 'Postgre'
                ? 'VARCHAR(100) NULL'
                : ($driver === 'SQLite3' ? 'TEXT' : 'VARCHAR(100) NULL DEFAULT NULL');
            $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN nav_label {$definition}");
        }

        if (! in_array('nav_order', $fields, true)) {
            $definition = $driver === 'Postgre'
                ? 'INTEGER NOT NULL DEFAULT 0'
                : 'INT NOT NULL DEFAULT 0';
            $db->simpleQuery("ALTER TABLE {$table} ADD COLUMN nav_order {$definition}");
        }

        $security = new SecurityCangService();
        $security->ensureCangColumn('public_contents');
        $security->backfillCangColumn(SecurityCangService::PUBLIC_CONTENT_URL_ID, 'public_contents');
    }

    /**
     * @return list<string>
     */
    private function contentTableSql(BaseConnection $db): array
    {
        $table = $db->escapeIdentifiers($db->prefixTable('public_contents'));
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($db->DBPrefix ?? '')) ?: '';
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id SERIAL PRIMARY KEY, c_id VARCHAR(128) NULL UNIQUE, title VARCHAR(180) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, summary VARCHAR(500) NULL, body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', show_in_nav BOOLEAN NOT NULL DEFAULT FALSE, nav_label VARCHAR(100) NULL, nav_order INTEGER NOT NULL DEFAULT 0, author_id INTEGER NULL, published_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_nav_idx ON {$table} (show_in_nav, nav_order)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_published_at_idx ON {$table} (published_at)",
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, c_id TEXT UNIQUE, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, summary TEXT, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'draft', show_in_nav INTEGER NOT NULL DEFAULT 0, nav_label TEXT, nav_order INTEGER NOT NULL DEFAULT 0, author_id INTEGER, published_at TEXT, created_at TEXT NOT NULL, updated_at TEXT)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_nav_idx ON {$table} (show_in_nav, nav_order)",
                "CREATE INDEX IF NOT EXISTS {$prefix}public_contents_published_at_idx ON {$table} (published_at)",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, c_id VARCHAR(128) NULL DEFAULT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(191) NOT NULL, summary VARCHAR(500) NULL DEFAULT NULL, body MEDIUMTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', show_in_nav TINYINT(1) NOT NULL DEFAULT 0, nav_label VARCHAR(100) NULL DEFAULT NULL, nav_order INT NOT NULL DEFAULT 0, author_id INT UNSIGNED NULL DEFAULT NULL, published_at DATETIME NULL DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY {$prefix}public_contents_c_id_unique (c_id), UNIQUE KEY {$prefix}public_contents_slug_unique (slug), KEY {$prefix}public_contents_status_idx (status), KEY {$prefix}public_contents_nav_idx (show_in_nav, nav_order), KEY {$prefix}public_contents_published_at_idx (published_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
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
}
