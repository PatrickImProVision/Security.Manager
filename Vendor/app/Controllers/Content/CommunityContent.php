<?php

declare(strict_types=1);

namespace App\Controllers\Content;

use App\Libraries\RichHtml;

use App\Controllers\BaseController;
use App\Libraries\AppDatabase;
use App\Libraries\CommunityCategoryService;
use App\Libraries\CommunityContentUrls;
use App\Libraries\CommunityForumService;
use App\Libraries\CommunityForumUrls;
use App\Libraries\ModuleSettings;
use App\Libraries\RoleService;
use App\Libraries\SecurityCangService;
use App\Libraries\SitePageTitle;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;

class CommunityContent extends BaseController
{
    protected $helpers = ['form', 'url'];

    private const TOPICS_PER_PAGE = 15;

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
        $forum = new CommunityForumService();
        $categories = new CommunityCategoryService();
        $listBuilder = fn (bool $manageAll, ?array $user): object => $this->contentListBuilder($manageAll, $user);

        return view('content/community/index', [
            'title'               => SitePageTitle::format('Community'),
            'pageHeading'         => SitePageTitle::trail('Community'),
            'breadcrumbItems'     => SitePageTitle::breadcrumbs([['label' => 'Community']]),
            'wideLayout'          => true,
            'sections'            => $categories->boardSections($listBuilder, $canManageAll, $current, $forum),
            'uncategorizedBoard'  => $forum->uncategorizedBoard($listBuilder, $canManageAll, $current),
            'current'             => $current,
            'canCreate'           => $current !== null,
            'canManageCategories' => $current !== null && $this->canManageCommunityCategories($current),
            'errors'              => $this->flashErrors(),
        ]);
    }

    public function forum(string $forumRef): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $categoryRow = CommunityForumUrls::resolveForumRef($forumRef);
        if ($categoryRow === null) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['forum' => 'Forum not found.']);
        }

        $canonical = CommunityForumUrls::canonicalForumRedirectIfNeeded($categoryRow, $forumRef);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        $categories = new CommunityCategoryService();

        $current = $this->currentUser();
        $canManageAll = $current !== null && $this->canManageAll($current);
        if (! $categoryRow['is_active'] && ! $canManageAll) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['forum' => 'Forum not found.']);
        }

        $forum = new CommunityForumService();
        $listBuilder = fn (bool $manageAll, ?array $user): object => $this->contentListBuilder($manageAll, $user);
        $total = $forum->topicCountInCategory($listBuilder, $canManageAll, $current, $categoryRow);
        $totalPages = max(1, (int) ceil($total / self::TOPICS_PER_PAGE));
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $page = min($page, $totalPages);

        $forumName = (string) ($categoryRow['name'] ?? 'Forum');

        return view('content/community/forum', [
            'title'            => SitePageTitle::format('Community', $forumName),
            'pageHeading'      => SitePageTitle::trail('Community', $forumName),
            'breadcrumbItems'  => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => $forumName],
            ]),
            'wideLayout' => true,
            'forum'      => $categoryRow,
            'subforums'  => $categories->childBoardsForForum($categoryRow, $listBuilder, $canManageAll, $current, $forum),
            'topics'     => $forum->topicsInCategory($listBuilder, $canManageAll, $current, $categoryRow, self::TOPICS_PER_PAGE, ($page - 1) * self::TOPICS_PER_PAGE),
            'current'    => $current,
            'canCreate'  => $current !== null,
            'canManage'  => $canManageAll,
            'pagination' => [
                'page'       => $page,
                'perPage'    => self::TOPICS_PER_PAGE,
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

        $presetCategoryId = 0;
        $forumRef = trim((string) ($this->request->getGet('forum') ?? ''));
        if ($forumRef !== '') {
            $preset = CommunityForumUrls::resolveForumRef($forumRef);
            if (is_array($preset)) {
                $presetCategoryId = (int) ($preset['id'] ?? 0);
            }
        }

        return view('content/community/form', [
            'title'           => SitePageTitle::format('Community', 'New Topic'),
            'pageHeading'     => SitePageTitle::trail('Community', 'New Topic'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => 'New Topic'],
            ]),
            'wideLayout' => true,
            'mode'       => 'create',
            'post'       => $presetCategoryId > 0 ? ['category_id' => $presetCategoryId] : [],
            'categories' => $this->categoryOptions($presetCategoryId),
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
        $now = date('Y-m-d H:i:s');
        $data['parent_id'] = null;
        $data['is_locked'] = false;
        $data['is_sticky'] = false;
        $data['view_count'] = 0;
        $data['last_reply_at'] = $now;
        $data['last_reply_user_id'] = (int) ($current['id'] ?? 0);

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

        $data['id'] = $id;

        return redirect()->to(CommunityContentUrls::topicUrl($data))->with('message', 'Topic created.');
    }

    public function view(string $topicRef): ResponseInterface|string
    {
        return $this->topic($topicRef);
    }

    public function topic(string $topicRef): ResponseInterface|string
    {
        $disabled = $this->requireCommunityContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $this->ensureContentTable();
        $this->ensureCategoryTable();

        $post = CommunityContentUrls::resolveTopicRef($topicRef);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Topic not found.']);
        }

        $canonical = CommunityContentUrls::canonicalTopicRedirectIfNeeded($post, $topicRef);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        $id = (int) ($post['id'] ?? 0);

        if (! CommunityForumService::isTopic($post)) {
            $topicId = CommunityForumService::topicId($post);
            $topicRow = $this->findContent($topicId);

            return redirect()->to(CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId]) . '#post-' . $id);
        }

        $current = $this->currentUser();
        if (! $this->canViewPost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Topic not found.']);
        }

        (new CommunityForumService())->incrementTopicViews($id);

        $forum = new CommunityForumService();
        $listBuilder = fn (bool $manageAll, ?array $user): object => $this->contentListBuilder($manageAll, $user);
        $canManageAll = $current !== null && $this->canManageAll($current);
        $replies = $forum->repliesForTopic($listBuilder, $id, $canManageAll, $current);
        $replyBodies = [];
        foreach ($replies as $reply) {
            $replyBodies[(int) ($reply['id'] ?? 0)] = RichHtml::render((string) ($reply['body'] ?? ''));
        }

        $post['reply_count'] = count($replies);
        $post['is_locked'] = $this->booleanField($post['is_locked'] ?? false);
        $post['is_sticky'] = $this->booleanField($post['is_sticky'] ?? false);

        $forumName = (string) ($post['category'] ?? 'Forum');
        $topicTitle = (string) ($post['title'] ?? 'Topic');
        $forumUrl = $this->forumUrlForPost($post);

        return view('content/community/topic', [
            'title'           => SitePageTitle::format('Community', $forumName, $topicTitle),
            'pageHeading'     => SitePageTitle::trail('Community', $forumName, $topicTitle),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => $forumName, 'url' => $forumUrl],
                ['label' => $topicTitle],
            ]),
            'wideLayout'  => true,
            'topic'       => $post,
            'topicHtml'   => RichHtml::render((string) ($post['body'] ?? '')),
            'replies'     => $replies,
            'replyBodies' => $replyBodies,
            'forumUrl'    => $forumUrl,
            'topicRef'    => CommunityContentUrls::topicRefForUrl($post),
            'canManage'   => $this->canManagePost($post, $current),
            'canReply'    => $current !== null && ! $post['is_locked'],
            'canModerate' => $canManageAll,
            'errors'      => $this->flashErrors(),
        ]);
    }

    public function storeReply(string $topicRef): ResponseInterface
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

        $topic = CommunityContentUrls::resolveTopicRef($topicRef);
        if (! is_array($topic) || ! CommunityForumService::isTopic($topic)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Topic not found.']);
        }

        $id = (int) ($topic['id'] ?? 0);
        $topicUrl = CommunityContentUrls::topicUrl($topic);

        if ($this->booleanField($topic['is_locked'] ?? false) && ! $this->canManageAll($current)) {
            return redirect()->to($topicUrl)->with('errors', ['reply' => 'This topic is locked.']);
        }

        $rules = ['body' => 'required|min_length[3]'];
        if (! $this->validate($rules)) {
            return redirect()->to($topicUrl)->withInput();
        }

        $title = (string) ($topic['title'] ?? 'Topic');
        if (! str_starts_with(strtolower($title), 're:')) {
            $title = 'Re: ' . $title;
        }
        if (strlen($title) > 180) {
            $title = substr($title, 0, 177) . '…';
        }

        $now = date('Y-m-d H:i:s');
        $db = AppDatabase::connection();
        $db->table('community_contents')->insert([
            'c_id'               => (new SecurityCangService())->generateFor(SecurityCangService::COMMUNITY_CONTENT_URL_ID, 'community_contents'),
            'title'              => $title,
            'category'           => (string) ($topic['category'] ?? 'Unknown'),
            'category_id'        => (int) ($topic['category_id'] ?? 0) ?: null,
            'body'               => RichHtml::sanitize((string) $this->request->getPost('body')),
            'status'             => 'published',
            'author_id'          => (int) ($current['id'] ?? 0),
            'parent_id'          => $id,
            'is_locked'          => false,
            'is_sticky'          => false,
            'view_count'         => 0,
            'last_reply_at'      => null,
            'last_reply_user_id' => null,
            'created_at'         => $now,
            'updated_at'         => null,
        ]);

        $replyId = (int) $db->insertID();
        $forum = new CommunityForumService();
        $forum->refreshTopicLastReply($id);

        $anchor = $replyId > 0 ? '#post-' . $replyId : '';

        return redirect()->to($topicUrl . $anchor)->with('message', 'Reply posted.');
    }

    public function toggleLock(string $topicRef): ResponseInterface
    {
        return $this->toggleTopicFlag($topicRef, 'is_locked', 'Topic lock updated.');
    }

    public function toggleSticky(string $topicRef): ResponseInterface
    {
        return $this->toggleTopicFlag($topicRef, 'is_sticky', 'Topic sticky status updated.');
    }

    public function edit(string $ref): ResponseInterface|string
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

        $post = CommunityContentUrls::resolveTopicRef($ref);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        $canonical = CommunityContentUrls::canonicalEditRedirectIfNeeded($post, $ref);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only edit your own posts.']);
        }

        $isReply = ! CommunityForumService::isTopic($post);
        if ($isReply) {
            $topicId = CommunityForumService::topicId($post);
            $topicRow = $this->findContent($topicId);
            $topicTitle = is_array($topicRow) ? (string) ($topicRow['title'] ?? 'Topic') : 'Topic';

            return view('content/community/form', [
                'title'           => SitePageTitle::format('Community', $topicTitle, 'Edit Reply'),
                'pageHeading'     => SitePageTitle::trail('Community', $topicTitle, 'Edit Reply'),
                'breadcrumbItems' => SitePageTitle::breadcrumbs([
                    ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                    ['label' => $topicTitle, 'url' => CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId])],
                    ['label' => 'Edit Reply'],
                ]),
                'wideLayout' => true,
                'mode'       => 'edit-reply',
                'post'       => $post,
                'topicId'    => $topicId,
                'topicUrl'   => CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId]),
                'categories' => [],
                'errors'     => $this->flashErrors(),
            ]);
        }

        $topicTitle = (string) ($post['title'] ?? 'Topic');
        $forumName = (string) ($post['category'] ?? 'Forum');

        return view('content/community/form', [
            'title'           => SitePageTitle::format('Community', $forumName, $topicTitle, 'Edit Topic'),
            'pageHeading'     => SitePageTitle::trail('Community', $forumName, $topicTitle, 'Edit Topic'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => $forumName, 'url' => $this->forumUrlForPost($post)],
                ['label' => $topicTitle, 'url' => CommunityContentUrls::topicUrl($post)],
                ['label' => 'Edit Topic'],
            ]),
            'wideLayout' => true,
            'mode'       => 'edit',
            'post'       => $post,
            'categories' => $this->categoryOptions((int) ($post['category_id'] ?? 0)),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function update(string $ref): ResponseInterface
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

        $post = CommunityContentUrls::resolveTopicRef($ref);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        $id = (int) ($post['id'] ?? 0);

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only edit your own posts.']);
        }

        if (! CommunityForumService::isTopic($post)) {
            $rules = ['body' => 'required|min_length[3]'];
            if (! $this->validate($rules)) {
                return redirect()->back()->withInput();
            }

            $topicId = CommunityForumService::topicId($post);
            AppDatabase::connection()->table('community_contents')->where('id', $id)->update([
                'body'       => RichHtml::sanitize((string) $this->request->getPost('body')),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            (new CommunityForumService())->refreshTopicLastReply($topicId);

            $topicRow = $this->findContent($topicId);

            return redirect()->to(CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId]) . '#post-' . $id)->with('message', 'Reply updated.');
        }

        $data = $this->contentPayload($current, true);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        AppDatabase::connection()->table('community_contents')->where('id', $id)->update($data);

        return redirect()->to(CommunityContentUrls::topicUrl($post))->with('message', 'Topic updated.');
    }

    public function confirmDelete(string $ref): ResponseInterface|string
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

        $post = CommunityContentUrls::resolveTopicRef($ref);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        $canonical = CommunityContentUrls::canonicalDeleteRedirectIfNeeded($post, $ref);
        if ($canonical !== null) {
            return redirect()->to($canonical);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only delete your own posts.']);
        }

        $isTopic = CommunityForumService::isTopic($post);
        $topicId = CommunityForumService::topicId($post);
        $postId = (int) ($post['id'] ?? 0);
        $forumName = (string) ($post['category'] ?? 'Forum');
        $topicTitle = $isTopic ? (string) ($post['title'] ?? 'Topic') : '';
        $topicRow = $isTopic ? $post : $this->findContent($topicId);
        if (! $isTopic && is_array($topicRow)) {
            $topicTitle = (string) ($topicRow['title'] ?? 'Topic');
            $forumName = (string) ($topicRow['category'] ?? $forumName);
        }
        $deleteLabel = $isTopic ? 'Delete Topic' : 'Delete Reply';

        return view('content/community/delete', [
            'title'           => SitePageTitle::format('Community', $forumName, $topicTitle, $deleteLabel),
            'pageHeading'     => SitePageTitle::trail('Community', $forumName, $topicTitle, $deleteLabel),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => $forumName, 'url' => $this->forumUrlForPost($post)],
                ['label' => $topicTitle, 'url' => CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId])],
                ['label' => $deleteLabel],
            ]),
            'wideLayout'  => true,
            'post'        => $post,
            'isTopic'     => $isTopic,
            'topicId'     => $topicId,
            'replyCount'  => $isTopic ? (new CommunityForumService())->replyCount($postId) : 0,
            'errors'      => $this->flashErrors(),
        ]);
    }

    public function delete(string $ref): ResponseInterface
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

        $post = CommunityContentUrls::resolveTopicRef($ref);
        if (! is_array($post)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Post not found.']);
        }

        if (! $this->canManagePost($post, $current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'You can only delete your own posts.']);
        }

        $id = (int) ($post['id'] ?? 0);
        $isTopic = CommunityForumService::isTopic($post);
        $topicId = CommunityForumService::topicId($post);
        (new CommunityForumService())->deletePostCascade($id, $isTopic);

        if ($isTopic) {
            return redirect()->to($this->forumUrlForPost($post))->with('message', 'Topic and replies deleted.');
        }

        (new CommunityForumService())->refreshTopicLastReply($topicId);

        $topicRow = $this->findContent($topicId);

        return redirect()->to(CommunityContentUrls::topicUrl(is_array($topicRow) ? $topicRow : ['id' => $topicId]))->with('message', 'Reply deleted.');
    }

    public function categories(): ResponseInterface|string
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();

        $categories = new CommunityCategoryService();
        $forum = new CommunityForumService();
        $listBuilder = fn (bool $manageAll, ?array $user): object => $this->contentListBuilder($manageAll, $user);

        return view('content/community/categories', [
            'title'           => SitePageTitle::format('Community', 'Forum Administration'),
            'pageHeading'     => SitePageTitle::trail('Community', 'Forum Administration'),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => 'Forum Administration'],
            ]),
            'wideLayout' => true,
            'forumRows'  => $categories->adminFlattenTree($listBuilder, $forum),
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function createCategory(): ResponseInterface|string
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();

        $type = (string) ($this->request->getGet('type') ?? 'forum');
        $type = $type === 'category' ? 'category' : 'forum';
        $parentId = (int) ($this->request->getGet('parent') ?? 0);
        if ($type === 'category') {
            $parentId = 0;
        }

        $categories = new CommunityCategoryService();

        $formLabel = $type === 'category' ? 'Create Category' : 'Create Forum';

        return view('content/community/category_form', [
            'title'           => SitePageTitle::format('Community', 'Forum Administration', $formLabel),
            'pageHeading'     => SitePageTitle::trail('Community', 'Forum Administration', $formLabel),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => 'Forum Administration', 'url' => site_url('Content/Community/Categories/Index')],
                ['label' => $formLabel],
            ]),
            'wideLayout'     => true,
            'mode'           => 'create',
            'nodeType'       => $type,
            'category'       => [
                'parent_id' => $parentId > 0 ? $parentId : null,
                'is_active' => true,
                'sort_order' => 0,
            ],
            'parentOptions'  => $categories->optionsForSelect(true, 0, 0),
            'errors'         => $this->flashErrors(),
        ]);
    }

    public function editCategory(int $id): ResponseInterface|string
    {
        $current = $this->requireCategoryManager();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureCategoryTable();

        $category = $this->findCategory($id);
        if (! is_array($category)) {
            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('errors', ['category' => 'Forum not found.']);
        }

        $categories = new CommunityCategoryService();
        $category = $categories->normalizeRow($category);
        $hasChildren = $categories->hasChildren($id);
        $parentId = (int) ($category['parent_id'] ?? 0);
        $nodeType = $parentId <= 0 && $hasChildren ? 'category' : ($parentId > 0 ? 'subforum' : 'forum');

        $categoryName = (string) ($category['name'] ?? 'Forum');

        return view('content/community/category_form', [
            'title'           => SitePageTitle::format('Community', 'Forum Administration', $categoryName),
            'pageHeading'     => SitePageTitle::trail('Community', 'Forum Administration', $categoryName),
            'breadcrumbItems' => SitePageTitle::breadcrumbs([
                ['label' => 'Community', 'url' => site_url('Content/Community/Index')],
                ['label' => 'Forum Administration', 'url' => site_url('Content/Community/Categories/Index')],
                ['label' => $categoryName],
            ]),
            'wideLayout'     => true,
            'mode'           => 'edit',
            'nodeType'       => $nodeType,
            'category'       => $category,
            'parentOptions'  => $categories->optionsForSelect(true, $id, $id),
            'errors'         => $this->flashErrors(),
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
            'parent_id'   => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $db = AppDatabase::connection();
        $categories = new CommunityCategoryService();
        $id = (int) ($this->request->getPost('id') ?? 0);
        $name = $this->normalizeCategory((string) $this->request->getPost('name'));
        $parentId = (int) ($this->request->getPost('parent_id') ?? 0);
        if ((string) ($this->request->getPost('node_type') ?? '') === 'category') {
            $parentId = 0;
        }

        $existing = $this->findCategoryByName($name);
        if (is_array($existing) && (int) ($existing['id'] ?? 0) !== $id) {
            return redirect()->back()->withInput()->with('errors', ['category' => 'That forum name already exists.']);
        }

        if (! $categories->isValidParent($id, $parentId)) {
            return redirect()->back()->withInput()->with('errors', ['category' => 'Invalid parent category.']);
        }

        $data = [
            'name'        => $name,
            'description' => trim((string) $this->request->getPost('description')),
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?: 0),
            'parent_id'   => $parentId > 0 ? $parentId : null,
            'is_active'   => $this->request->getPost('is_active') !== null,
            'slug'        => $categories->uniqueSlug($name, $id),
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
                $data['parent_id'] = null;
            }

            if (trim((string) ($category['c_id'] ?? '')) === '') {
                $cangId = (new SecurityCangService())->generateFor(SecurityCangService::COMMUNITY_CATEGORY_URL_ID, 'community_categories');
                if ($cangId !== null) {
                    $data['c_id'] = $cangId;
                }
            }

            $db->table('community_categories')->where('id', $id)->update($data);
            if ((string) ($category['name'] ?? '') !== $data['name']) {
                $categories->syncContentCategoryName($id, $data['name']);
            }

            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Forum updated successfully.');
        }

        $data['is_system'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = null;
        $cangId = (new SecurityCangService())->generateFor(SecurityCangService::COMMUNITY_CATEGORY_URL_ID, 'community_categories');
        if ($cangId !== null) {
            $data['c_id'] = $cangId;
        }
        $db->table('community_categories')->insert($data);

        return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Forum created successfully.');
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

        $categories = new CommunityCategoryService();
        if ($categories->hasChildren($id)) {
            return redirect()->to(site_url('Content/Community/Categories/Index'))->with('errors', ['category' => 'Remove or reassign sub-forums before deleting this category.']);
        }

        $unknown = $this->findCategoryByName('Unknown');
        $unknownId = is_array($unknown) ? (int) ($unknown['id'] ?? 0) : 0;

        $db = AppDatabase::connection();
        $update = ['category' => 'Unknown'];
        if ($unknownId > 0) {
            $update['category_id'] = $unknownId;
        }
        $db->table('community_contents')->where('category_id', $id)->update($update);
        $db->table('community_contents')->where('category', (string) $category['name'])->update($update);
        $db->table('community_categories')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Community/Categories/Index'))->with('message', 'Forum category deleted. Topics were moved to Unknown.');
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
     * @return array<int, string>
     */
    private function categoryOptions(int $selectedCategoryId = 0): array
    {
        $this->ensureCategoryTable();

        $options = (new CommunityCategoryService())->optionsForSelect(false, $selectedCategoryId);

        if ($options === []) {
            $unknown = $this->findCategoryByName('Unknown');

            return is_array($unknown) ? [(int) ($unknown['id'] ?? 0) => 'Unknown'] : [];
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

        $categoryId = (int) ($this->request->getPost('category_id') ?? 0);
        $categoryRow = (new CommunityCategoryService())->findById($categoryId);
        if ($categoryRow === null || (! $categoryRow['is_active'] && (string) ($categoryRow['name'] ?? '') !== 'Unknown')) {
            return redirect()->back()->withInput()->with('errors', ['category' => 'Choose an active forum.']);
        }

        $data = [
            'title'        => trim((string) $this->request->getPost('title')),
            'category'     => (string) ($categoryRow['name'] ?? 'Unknown'),
            'category_id'  => (int) ($categoryRow['id'] ?? 0),
            'body'       => RichHtml::sanitize((string) $this->request->getPost('body')),
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

        if (! $this->canManageCommunityCategories($current)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['category' => 'Only Administrator, Manager, or Owner accounts can manage community categories.']);
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canManageCommunityCategories(array $user): bool
    {
        return (new RoleService())->canManageCommunityCategories((string) ($user['role'] ?? ''));
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

        (new CommunityForumService())->ensureSchema();

        $security = new SecurityCangService();
        $security->ensureCangColumn('community_contents');
        $security->backfillCangColumn(SecurityCangService::COMMUNITY_CONTENT_URL_ID, 'community_contents');
    }

    private function toggleTopicFlag(string $topicRef, string $column, string $message): ResponseInterface
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
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['moderation' => 'Only moderators can change topic options.']);
        }

        $this->ensureContentTable();
        $topic = CommunityContentUrls::resolveTopicRef($topicRef);
        if (! is_array($topic) || ! CommunityForumService::isTopic($topic)) {
            return redirect()->to(site_url('Content/Community/Index'))->with('errors', ['post' => 'Topic not found.']);
        }

        $id = (int) ($topic['id'] ?? 0);
        $next = ! $this->booleanField($topic[$column] ?? false);
        AppDatabase::connection()->table('community_contents')->where('id', $id)->update([
            $column     => $next,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to(CommunityContentUrls::topicUrl($topic))->with('message', $message);
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
                'slug'        => 'unknown',
                'description' => 'Posts created without a category.',
                'sort_order'  => 0,
                'parent_id'   => null,
                'is_active'   => true,
                'is_system'   => true,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => null,
            ]);
        }

        (new CommunityCategoryService())->ensureSchema();

        $security = new SecurityCangService();
        $security->ensureCangColumn('community_categories');
        $security->backfillCangColumn(SecurityCangService::COMMUNITY_CATEGORY_URL_ID, 'community_categories');
    }

    /**
     * @param array<string, mixed> $post
     */
    private function forumUrlForPost(array $post): string
    {
        $categoryId = (int) ($post['category_id'] ?? 0);
        $categories = new CommunityCategoryService();

        if ($categoryId > 0) {
            $row = $categories->findById($categoryId);
            if (is_array($row)) {
                return CommunityForumUrls::forumUrl($row);
            }
        }

        $name = trim((string) ($post['category'] ?? 'Unknown'));
        $row = $categories->findBySlugOrName($name !== '' ? $name : 'Unknown');

        return is_array($row) ? CommunityForumUrls::forumUrl($row) : site_url('Content/Community/Index');
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
