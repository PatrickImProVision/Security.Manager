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

class PersonalContent extends BaseController
{
    protected $helpers = ['form', 'url'];

    private const MESSAGES_PER_PAGE = 10;

    public function index(): ResponseInterface|string
    {
        return $this->mailbox('all');
    }

    public function inbox(): ResponseInterface|string
    {
        return $this->mailbox('inbox');
    }

    public function sent(): ResponseInterface|string
    {
        return $this->mailbox('sent');
    }

    private function mailbox(string $mailbox): ResponseInterface|string
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $canManageAll = $this->canManageAll($current);
        $mailbox = in_array($mailbox, ['all', 'inbox', 'sent'], true) ? $mailbox : 'all';
        $total = $this->messageCount($canManageAll, $current, $mailbox);
        $totalPages = max(1, (int) ceil($total / self::MESSAGES_PER_PAGE));
        $page = max(1, (int) ($this->request->getGet('page') ?: 1));
        $page = min($page, $totalPages);
        $mailboxTitles = [
            'all'   => 'All Messages',
            'inbox' => 'Inbox',
            'sent'  => 'Sent',
        ];

        return view('content/personal/index', [
            'title'        => $mailboxTitles[$mailbox],
            'wideLayout'   => true,
            'messages'     => $this->messageRows($canManageAll, $current, self::MESSAGES_PER_PAGE, ($page - 1) * self::MESSAGES_PER_PAGE, $mailbox),
            'current'      => $current,
            'canBulk'      => $canManageAll,
            'activeMailbox'=> $mailbox,
            'mailboxTitle' => $mailboxTitles[$mailbox],
            'pagination'   => [
                'page'       => $page,
                'perPage'    => self::MESSAGES_PER_PAGE,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'errors'     => $this->flashErrors(),
        ]);
    }

    public function create(): ResponseInterface|string
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();
        $recipientOptions = $this->recipientOptions((int) ($current['id'] ?? 0));
        $selectedRecipientId = (int) ($this->request->getGet('recipient_id') ?? 0);
        if (! array_key_exists($selectedRecipientId, $recipientOptions)) {
            if ($this->canManageAll($current) && $this->request->getGet('recipient_id') === null) {
                return view('content/personal/form', [
                    'title'              => 'Create Bulk Personal Message',
                    'wideLayout'         => true,
                    'mode'               => 'create',
                    'message'            => [],
                    'recipientOptions'   => [],
                    'bulkMode'           => true,
                    'bulkRecipientCount' => count($recipientOptions),
                    'errors'             => $this->flashErrors(),
                ]);
            }

            return redirect()->to(site_url('Member/List'))->with('errors', ['recipient' => 'Choose a member from the list before creating a personal message.']);
        }
        $recipientOptions = [$selectedRecipientId => $recipientOptions[$selectedRecipientId]];

        return view('content/personal/form', [
            'title'            => 'Create Personal Message',
            'wideLayout'       => true,
            'mode'             => 'create',
            'message'          => ['recipient_id' => $selectedRecipientId],
            'recipientOptions' => $recipientOptions,
            'bulkMode'         => false,
            'errors'           => $this->flashErrors(),
        ]);
    }

    public function store(): ResponseInterface
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        if ((string) $this->request->getPost('bulk_mode') === 'all') {
            if (! $this->canManageAll($current)) {
                return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'Only Administrator, Manager, or Owner accounts can send bulk messages.']);
            }

            return $this->storeBulkMessage($current);
        }

        $data = $this->messagePayload($current);
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $db = AppDatabase::connection();
        $db->table('personal_messages')->insert($data);
        $id = (int) $db->insertID();
        if ($id <= 0) {
            $created = $db->table('personal_messages')
                ->select('id')
                ->where('sender_id', (int) ($current['id'] ?? 0))
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            $id = is_array($created) ? (int) ($created['id'] ?? 0) : 0;
        }

        return redirect()->to(site_url('Content/Personal/View/' . $id))->with('message', 'Personal message created.');
    }

    /**
     * @param array<string, mixed> $current
     */
    private function storeBulkMessage(array $current): ResponseInterface
    {
        $rules = [
            'subject' => 'required|min_length[3]|max_length[180]',
            'body'    => 'required|min_length[3]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $recipientIds = array_keys($this->recipientOptions((int) ($current['id'] ?? 0)));
        if ($recipientIds === []) {
            return redirect()->back()->withInput()->with('errors', ['recipient' => 'No active recipients are available.']);
        }

        $now = date('Y-m-d H:i:s');
        $base = [
            'subject'    => trim((string) $this->request->getPost('subject')),
            'body'       => $this->sanitizeMessageHtml((string) $this->request->getPost('body')),
            'sender_id'  => (int) ($current['id'] ?? 0),
            'status'     => 'sent',
            'created_at' => $now,
            'updated_at' => null,
        ];

        $db = AppDatabase::connection();
        $security = new SecurityCangService();
        foreach ($recipientIds as $recipientId) {
            $db->table('personal_messages')->insert($base + [
                'c_id'         => $security->generateFor(SecurityCangService::PERSONAL_CONTENT_URL_ID, 'personal_messages'),
                'recipient_id' => (int) $recipientId,
            ]);
        }

        return redirect()->to(site_url('Content/Personal/Index'))->with('message', 'Bulk personal message sent to ' . count($recipientIds) . ' active user(s).');
    }

    public function view(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $message = $this->findMessage($id);
        if (! is_array($message) || ! $this->canViewMessage($message, $current)) {
            return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'Message not found.']);
        }

        return view('content/personal/detail', [
            'title'     => (string) ($message['subject'] ?? 'Personal Message'),
            'message'   => $this->decorateMessages([$message])[0],
            'bodyHtml'  => $this->renderedBodyHtml((string) ($message['body'] ?? '')),
            'canEdit'   => $this->canEditMessage($message, $current),
            'canDelete' => $this->canDeleteMessage($message, $current),
            'errors'    => $this->flashErrors(),
        ]);
    }

    public function edit(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $message = $this->findMessage($id);
        if (! is_array($message) || ! $this->canEditMessage($message, $current)) {
            return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'You can only edit messages you sent.']);
        }

        return view('content/personal/form', [
            'title'            => 'Edit Personal Message',
            'wideLayout'       => true,
            'mode'             => 'edit',
            'message'          => $message,
            'recipientOptions' => $this->selectedRecipientOption((int) ($message['recipient_id'] ?? 0)),
            'errors'           => $this->flashErrors(),
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $message = $this->findMessage($id);
        if (! is_array($message) || ! $this->canEditMessage($message, $current)) {
            return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'You can only edit messages you sent.']);
        }

        $data = $this->messagePayload($current, true, (int) ($message['recipient_id'] ?? 0));
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        $data['sender_id'] = (int) ($message['sender_id'] ?? 0);
        $data['updated_at'] = date('Y-m-d H:i:s');
        AppDatabase::connection()->table('personal_messages')->where('id', $id)->update($data);

        return redirect()->to(site_url('Content/Personal/View/' . $id))->with('message', 'Personal message updated.');
    }

    public function confirmDelete(int $id): ResponseInterface|string
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $message = $this->findMessage($id);
        if (! is_array($message) || ! $this->canDeleteMessage($message, $current)) {
            return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'Message not found.']);
        }

        return view('content/personal/delete', [
            'title'   => 'Delete Personal Message',
            'message' => $this->decorateMessages([$message])[0],
            'errors'  => $this->flashErrors(),
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $disabled = $this->requirePersonalContentEnabled();
        if ($disabled instanceof ResponseInterface) {
            return $disabled;
        }

        $current = $this->requireLogin();
        if ($current instanceof ResponseInterface) {
            return $current;
        }

        $this->ensureMessageTable();

        $message = $this->findMessage($id);
        if (! is_array($message) || ! $this->canDeleteMessage($message, $current)) {
            return redirect()->to(site_url('Content/Personal/Index'))->with('errors', ['message' => 'Message not found.']);
        }

        AppDatabase::connection()->table('personal_messages')->where('id', $id)->delete();

        return redirect()->to(site_url('Content/Personal/Index'))->with('message', 'Personal message deleted.');
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return list<array<string, mixed>>
     */
    private function messageRows(bool $canManageAll, array $current, int $limit, int $offset, string $mailbox): array
    {
        $rows = $this->messageListBuilder($canManageAll, $current, $mailbox)
            ->select('id, subject, body, sender_id, recipient_id, status, created_at, updated_at')
            ->orderBy('id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return $this->groupMessagesByType($this->decorateMessages($rows), (int) ($current['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $current
     */
    private function messageCount(bool $canManageAll, array $current, string $mailbox): int
    {
        return $this->messageListBuilder($canManageAll, $current, $mailbox)->countAllResults();
    }

    /**
     * @param array<string, mixed> $current
     */
    private function messageListBuilder(bool $canManageAll, array $current, string $mailbox): object
    {
        $builder = AppDatabase::connection()->table('personal_messages');
        $currentId = (int) ($current['id'] ?? 0);

        if ($mailbox === 'inbox') {
            $builder->where('recipient_id', $currentId);
        } elseif ($mailbox === 'sent') {
            $builder->where('sender_id', $currentId);
        } elseif (! $canManageAll) {
            $builder
                ->groupStart()
                ->where('sender_id', $currentId)
                ->orWhere('recipient_id', $currentId)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMessage(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = AppDatabase::connection()->table('personal_messages')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function decorateMessages(array $rows): array
    {
        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) ($row['sender_id'] ?? 0);
            $userIds[] = (int) ($row['recipient_id'] ?? 0);
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $users = [];
        if ($userIds !== []) {
            foreach (AppDatabase::connection()->table('users')->select('id, username')->whereIn('id', $userIds)->get()->getResultArray() as $user) {
                $users[(int) ($user['id'] ?? 0)] = (string) ($user['username'] ?? '');
            }
        }

        foreach ($rows as &$row) {
            $senderId = (int) ($row['sender_id'] ?? 0);
            $recipientId = (int) ($row['recipient_id'] ?? 0);
            $row['sender_name'] = $users[$senderId] ?? ('User #' . $senderId);
            $row['recipient_name'] = $users[$recipientId] ?? ('User #' . $recipientId);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function groupMessagesByType(array $rows, int $currentUserId): array
    {
        foreach ($rows as &$row) {
            if ((int) ($row['recipient_id'] ?? 0) === $currentUserId) {
                $row['message_group'] = 'Inbox';
                $row['message_group_order'] = 1;
            } elseif ((int) ($row['sender_id'] ?? 0) === $currentUserId) {
                $row['message_group'] = 'Sent';
                $row['message_group_order'] = 2;
            } else {
                $row['message_group'] = 'Other Messages';
                $row['message_group_order'] = 3;
            }
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            $group = ((int) ($left['message_group_order'] ?? 99)) <=> ((int) ($right['message_group_order'] ?? 99));
            if ($group !== 0) {
                return $group;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function recipientOptions(int $currentUserId): array
    {
        $rows = AppDatabase::connection()
            ->table('users')
            ->select('id, username, email')
            ->where('is_active', true)
            ->where('id !=', $currentUserId)
            ->orderBy('username', 'ASC')
            ->get()
            ->getResultArray();

        $options = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $username = (string) ($row['username'] ?? ('User #' . $id));
            $email = (string) ($row['email'] ?? '');
            $options[$id] = $email !== '' ? $username . ' <' . $email . '>' : $username;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function selectedRecipientOption(int $recipientId): array
    {
        if ($recipientId <= 0) {
            return [];
        }

        $row = AppDatabase::connection()
            ->table('users')
            ->select('id, username, email')
            ->where('id', $recipientId)
            ->get()
            ->getRowArray();
        if (! is_array($row)) {
            return [];
        }

        $username = (string) ($row['username'] ?? ('User #' . $recipientId));
        $email = (string) ($row['email'] ?? '');

        return [$recipientId => $email !== '' ? $username . ' <' . $email . '>' : $username];
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>|ResponseInterface
     */
    private function messagePayload(array $current, bool $isUpdate = false, int $fixedRecipientId = 0): array|ResponseInterface
    {
        $rules = [
            'subject'      => 'required|min_length[3]|max_length[180]',
            'body'         => 'required|min_length[3]',
        ];
        if ($fixedRecipientId <= 0) {
            $rules['recipient_id'] = 'required|is_natural_no_zero';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput();
        }

        $currentId = (int) ($current['id'] ?? 0);
        $recipientId = $fixedRecipientId > 0 ? $fixedRecipientId : (int) $this->request->getPost('recipient_id');
        $validRecipient = $fixedRecipientId > 0
            ? $this->selectedRecipientOption($recipientId) !== []
            : $recipientId !== $currentId && array_key_exists($recipientId, $this->recipientOptions($currentId));
        if (! $validRecipient) {
            return redirect()->back()->withInput()->with('errors', ['recipient' => 'Choose an active recipient.']);
        }

        $data = [
            'subject'     => trim((string) $this->request->getPost('subject')),
            'body'        => $this->sanitizeMessageHtml((string) $this->request->getPost('body')),
            'sender_id'   => $currentId,
            'recipient_id'=> $recipientId,
            'status'      => 'sent',
            'updated_at'  => null,
        ];

        if (! $isUpdate) {
            $data['c_id'] = (new SecurityCangService())->generateFor(SecurityCangService::PERSONAL_CONTENT_URL_ID, 'personal_messages');
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $data;
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

    private function requirePersonalContentEnabled(): ?ResponseInterface
    {
        if ((new ModuleSettings())->isEnabled(ModuleSettings::CONTENT_PERSONAL)) {
            return null;
        }

        return redirect()->to(site_url('DashBoard/Index'))->with('errors', ['content' => 'Personal content is disabled.']);
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
        if (! is_array($user) || ! $this->booleanField($user['is_active'] ?? false)) {
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
     * @param array<string, mixed> $message
     * @param array<string, mixed> $current
     */
    private function canViewMessage(array $message, array $current): bool
    {
        if ($this->canManageAll($current)) {
            return true;
        }

        $currentId = (int) ($current['id'] ?? 0);

        return in_array($currentId, [(int) ($message['sender_id'] ?? 0), (int) ($message['recipient_id'] ?? 0)], true);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $current
     */
    private function canEditMessage(array $message, array $current): bool
    {
        return $this->canManageAll($current) || (int) ($message['sender_id'] ?? 0) === (int) ($current['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $current
     */
    private function canDeleteMessage(array $message, array $current): bool
    {
        return $this->canViewMessage($message, $current);
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

        return $this->sanitizeMessageHtml($body);
    }

    private function sanitizeMessageHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if ($html === strip_tags($html)) {
            return $this->plainTextToHtml($html);
        }

        $allowedTags = '<p><br><strong><b><em><i><u><s><h2><h3><h4><ul><ol><li><blockquote><pre><code><a><img><hr><div><span>';

        return trim(strip_tags($html, $allowedTags));
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

    private function ensureMessageTable(): void
    {
        $db = AppDatabase::connection();
        if ($db->tableExists('personal_messages')) {
            $this->ensureMessageColumns($db);

            return;
        }

        foreach ($this->messageTableSql($db) as $sql) {
            $db->simpleQuery($sql);
        }
    }

    private function ensureMessageColumns(BaseConnection $db): void
    {
        $security = new SecurityCangService();
        $security->ensureCangColumn('personal_messages');
        $security->backfillCangColumn(SecurityCangService::PERSONAL_CONTENT_URL_ID, 'personal_messages');
    }

    /**
     * @return list<string>
     */
    private function messageTableSql(BaseConnection $db): array
    {
        $table = $db->escapeIdentifiers($db->prefixTable('personal_messages'));
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($db->DBPrefix ?? '')) ?: '';
        $driver = (string) ($db->DBDriver ?? '');

        if ($driver === 'Postgre') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id SERIAL PRIMARY KEY, c_id VARCHAR(128) NULL UNIQUE, subject VARCHAR(180) NOT NULL, body TEXT NOT NULL, sender_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'sent', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_sender_idx ON {$table} (sender_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_recipient_idx ON {$table} (recipient_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_created_idx ON {$table} (created_at)",
            ];
        }

        if ($driver === 'SQLite3') {
            return [
                "CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, c_id TEXT UNIQUE, subject TEXT NOT NULL, body TEXT NOT NULL, sender_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'sent', created_at TEXT NOT NULL, updated_at TEXT)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_sender_idx ON {$table} (sender_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_recipient_idx ON {$table} (recipient_id)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_status_idx ON {$table} (status)",
                "CREATE INDEX IF NOT EXISTS {$prefix}personal_messages_created_idx ON {$table} (created_at)",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS {$table} (id INT UNSIGNED NOT NULL AUTO_INCREMENT, c_id VARCHAR(128) NULL DEFAULT NULL, subject VARCHAR(180) NOT NULL, body MEDIUMTEXT NOT NULL, sender_id INT UNSIGNED NOT NULL, recipient_id INT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'sent', created_at DATETIME NOT NULL, updated_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY {$prefix}personal_messages_c_id_unique (c_id), KEY {$prefix}personal_messages_sender_idx (sender_id), KEY {$prefix}personal_messages_recipient_idx (recipient_id), KEY {$prefix}personal_messages_status_idx (status), KEY {$prefix}personal_messages_created_idx (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];
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

    /**
     * @return array<string, string>
     */
    private function flashErrors(): array
    {
        $flash = session()->getFlashdata('errors');

        return is_array($flash) ? $flash : [];
    }
}
