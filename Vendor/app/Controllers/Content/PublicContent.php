<?php

declare(strict_types=1);

namespace App\Controllers\Content;

use App\Libraries\RichHtml;

use App\Controllers\BaseController;
use App\Libraries\AppDatabase;
use App\Libraries\ModuleSettings;
use App\Libraries\PublicContentUrls;
use App\Libraries\RoleService;
use App\Libraries\SitePageTitle;
use App\Libraries\SecurityCangService;
use App\Libraries\WebSettings;
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
        $posts = $this->prepareBlogPosts($this->contentRows($canManage, self::CONTENT_PER_PAGE, ($page - 1) * self::CONTENT_PER_PAGE));
        $web = (new WebSettings())->homeSettings();

        return view('content/public/index', [
            'title'           => SitePageTitle::format('Blog'),
            'pageHeading'     => SitePageTitle::trail('Blog'),
            'blogTagline'     => trim((string) ($web['web_description'] ?? '')),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([['label' => 'Blog']]),
            'wideLayout'      => true,
            'posts'       => $posts,
            'recentPosts' => $this->recentBlogPosts($canManage, 8),
            'canManage'   => $canManage,
            'pagination'  => [
                'page'       => $page,
                'perPage'    => self::CONTENT_PER_PAGE,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'errors'      => $this->flashErrors(),
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
            'title'           => SitePageTitle::format('Blog', 'Create Post'),
            'pageHeading'     => SitePageTitle::trail('Blog', 'Create Post'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Blog', 'url' => site_url('Content/Public/Index')],
                ['label' => 'Create Post'],
            ]),
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

        $created = $id > 0 ? $this->findContent($id) : null;

        return redirect()->to(PublicContentUrls::postUrl(is_array($created) ? $created : array_merge($data, ['id' => $id])))->with('message', 'Public content created.');
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

        return redirect()->to(PublicContentUrls::postUrl($content));
    }

    public function viewSlug(string $ref): ResponseInterface|string
    {
        $disabled = $this->requirePublicContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();

        $content = PublicContentUrls::resolveRef($ref);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }
        $content = $this->normalizeContentRow($content);

        $canonical = PublicContentUrls::canonicalPostRedirectIfNeeded($content, $ref);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        $current = $this->currentUser();
        $canManage = $current !== null && $this->canManageContent($current);
        if (! $canManage && ! $this->isPublished($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        return $this->renderContent($content, $canManage);
    }

    public function edit(string $ref): ResponseInterface|string
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

        $content = PublicContentUrls::resolveRef($ref);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }
        $content = $this->normalizeContentRow($content);

        $canonical = PublicContentUrls::canonicalEditRedirectIfNeeded($content, $ref);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        $postTitle = (string) ($content['title'] ?? 'Post');

        return view('content/public/form', [
            'title'           => SitePageTitle::format('Blog', $postTitle, 'Edit Post'),
            'pageHeading'     => SitePageTitle::trail('Blog', $postTitle, 'Edit Post'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Blog', 'url' => site_url('Content/Public/Index')],
                ['label' => $postTitle, 'url' => PublicContentUrls::postUrl($content)],
                ['label' => 'Edit Post'],
            ]),
            'wideLayout' => true,
            'mode'       => 'edit',
            'content'    => $content,
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function update(string $ref): ResponseInterface
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

        $content = PublicContentUrls::resolveRef($ref);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }
        $content = $this->normalizeContentRow($content);
        $id = (int) ($content['id'] ?? 0);

        $data = $this->contentPayload($current, $id);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        AppDatabase::connection()->table('public_contents')->where('id', $id)->update($data);

        $updated = $this->findContent($id);

        return redirect()->to(PublicContentUrls::postUrl(is_array($updated) ? $updated : array_merge($content, $data)))->with('message', 'Public content updated.');
    }

    public function confirmDelete(string $ref): ResponseInterface|string
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

        $content = PublicContentUrls::resolveRef($ref);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }
        $content = $this->normalizeContentRow($content);

        $canonical = PublicContentUrls::canonicalDeleteRedirectIfNeeded($content, $ref);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        $postTitle = (string) ($content['title'] ?? 'Post');

        return view('content/public/delete', [
            'title'           => SitePageTitle::format('Blog', $postTitle, 'Delete Post'),
            'pageHeading'     => SitePageTitle::trail('Blog', $postTitle, 'Delete Post'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Blog', 'url' => site_url('Content/Public/Index')],
                ['label' => $postTitle, 'url' => PublicContentUrls::postUrl($content)],
                ['label' => 'Delete Post'],
            ]),
            'wideLayout' => true,
            'content'    => $content,
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function delete(string $ref): ResponseInterface
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

        $content = PublicContentUrls::resolveRef($ref);
        if (! is_array($content)) {
            return redirect()->to(site_url('Content/Public/Index'))->with('errors', ['content' => 'Content not found.']);
        }

        $id = (int) ($content['id'] ?? 0);
        AppDatabase::connection()->table('public_contents')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Public/Index'))->with('message', 'Public content deleted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentRows(bool $includeDrafts, int $limit, int $offset): array
    {
        $builder = $this->contentListBuilder($includeDrafts)
            ->select('id, c_id, title, slug, summary, body, status, show_in_nav, nav_label, nav_order, author_id, published_at, created_at, updated_at')
            ->orderBy('published_at', 'DESC')
            ->orderBy('created_at', 'DESC')
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
        $prepared = $this->prepareBlogPosts([$content])[0] ?? $content;

        $postTitle = (string) ($content['title'] ?? 'Post');

        return view('content/public/detail', [
            'title'           => SitePageTitle::format('Blog', $postTitle),
            'pageHeading'     => SitePageTitle::trail('Blog', $postTitle),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Blog', 'url' => site_url('Content/Public/Index')],
                ['label' => $postTitle],
            ]),
            'wideLayout' => true,
            'content'    => $prepared,
            'bodyHtml'   => RichHtml::render((string) ($content['body'] ?? '')),
            'canManage'  => $canManage,
            'errors'     => $this->flashErrors(),
        ]);
    }

    /**
     * @param array<string, mixed> $content
     */
    private function contentViewUrl(array $content, int $id): string
    {
        if ($id > 0 && ! isset($content['id'])) {
            $content['id'] = $id;
        }

        return PublicContentUrls::postUrl($content);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function prepareBlogPosts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $rows = $this->withAuthorNames($rows);
        foreach ($rows as &$row) {
            $row['post_url'] = PublicContentUrls::postUrl($row);
            $row['date_label'] = $this->formatBlogDate($row);
            $row['excerpt_html'] = $this->excerptHtml($row);
            $row['is_published'] = $this->isPublished($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentBlogPosts(bool $includeDrafts, int $limit): array
    {
        $rows = $this->contentListBuilder($includeDrafts)
            ->select('id, c_id, title, slug, published_at, created_at, status')
            ->orderBy('published_at', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        $posts = [];
        foreach ($rows as $row) {
            $row = $this->normalizeContentRow($row);
            $row['post_url'] = PublicContentUrls::postUrl($row);
            $row['date_label'] = $this->formatBlogDate($row);
            $posts[] = $row;
        }

        return $posts;
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
        if ($authorIds !== []) {
            foreach (AppDatabase::connection()->table('users')->select('id, username')->whereIn('id', array_values($authorIds))->get()->getResultArray() as $user) {
                $authors[(int) ($user['id'] ?? 0)] = (string) ($user['username'] ?? 'Member');
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
     * @param array<string, mixed> $content
     */
    private function formatBlogDate(array $content): string
    {
        $raw = trim((string) ($content['published_at'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($content['created_at'] ?? ''));
        }

        if ($raw === '') {
            return '';
        }

        $timestamp = strtotime($raw);

        return $timestamp !== false ? date('F j, Y', $timestamp) : $raw;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function excerptHtml(array $content): string
    {
        $body = (string) ($content['body'] ?? '');
        $summary = trim((string) ($content['summary'] ?? ''));

        if ($summary !== '') {
            return RichHtml::render($summary);
        }

        $moreMarkers = ['<!--more-->', '<!-- more -->', '[more]'];
        foreach ($moreMarkers as $marker) {
            $pos = stripos($body, $marker);
            if ($pos !== false) {
                $body = substr($body, 0, $pos);
                break;
            }
        }

        $plain = trim(strip_tags($body));
        if ($plain === '') {
            return '';
        }

        if (strlen($plain) > 360) {
            $plain = rtrim(substr($plain, 0, 360));
            $plain = preg_replace('/\s+\S*$/', '', $plain) ?: $plain;
            $plain .= '…';
        }

        return '<p>' . nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
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
            'body'         => RichHtml::sanitize((string) $this->request->getPost('body')),
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
