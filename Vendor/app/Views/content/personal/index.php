<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<?php $activeMailbox = (string) ($activeMailbox ?? 'all'); ?>

<div class="mailbox-shell">
    <aside class="mailbox-sidebar card" aria-label="Personal message folders">
        <a class="mailbox-folder <?= $activeMailbox === 'all' ? 'active' : '' ?>" href="<?= esc(site_url('Content/Personal/Index')) ?>">
            <span>All Messages</span>
        </a>
        <a class="mailbox-folder <?= $activeMailbox === 'inbox' ? 'active' : '' ?>" href="<?= esc(site_url('Content/Personal/Inbox')) ?>">
            <span>Inbox</span>
        </a>
        <a class="mailbox-folder <?= $activeMailbox === 'sent' ? 'active' : '' ?>" href="<?= esc(site_url('Content/Personal/Sent')) ?>">
            <span>Sent</span>
        </a>
        <a class="btn btn-primary" href="<?= esc(site_url('Member/List')) ?>">Compose</a>
        <?php if (! empty($canBulk)) : ?>
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/Create')) ?>">Bulk Message</a>
        <?php endif ?>
    </aside>

<div class="card prose mailbox-main">
    <div class="role-list-head">
        <h2><?= esc((string) ($mailboxTitle ?? 'Messages')) ?></h2>
        <div class="role-list-head-actions">
            <a class="btn btn-primary" href="<?= esc(site_url('Member/List')) ?>">Choose Member</a>
            <?php if (! empty($canBulk)) : ?>
                <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/Create')) ?>">Bulk Message</a>
            <?php endif ?>
        </div>
    </div>

    <?php if (empty($messages)) : ?>
        <p>No messages found in this mailbox.</p>
    <?php else : ?>
        <div class="mailbox-list">
            <?php $currentGroup = null; ?>
            <?php foreach ($messages as $message) : ?>
                <?php
                $currentId = (int) ($current['id'] ?? 0);
                $group = (string) ($message['message_group'] ?? 'Inbox');
                $canEdit = (bool) session()->get('member_can_manage_roles') || (int) ($message['sender_id'] ?? 0) === $currentId;
                $created = trim((string) ($message['created_at'] ?? ''));
                ?>
                <?php if ($activeMailbox === 'all' && $group !== $currentGroup) : ?>
                    <?php $currentGroup = $group; ?>
                    <div class="mailbox-group"><?= esc($group) ?></div>
                <?php endif ?>
                <article class="mailbox-row">
                    <a class="mailbox-row-main" href="<?= esc(site_url('Content/Personal/View/' . (int) $message['id'])) ?>">
                        <span class="mailbox-row-subject"><?= esc((string) $message['subject']) ?></span>
                        <span class="mailbox-row-meta">
                            From: <?= esc((string) ($message['sender_name'] ?? '-')) ?>
                            <span aria-hidden="true">•</span>
                            To: <?= esc((string) ($message['recipient_name'] ?? '-')) ?>
                        </span>
                    </a>
                    <div class="mailbox-row-side">
                        <span class="status-pill status-active"><?= esc(ucfirst((string) ($message['status'] ?? 'sent'))) ?></span>
                        <?php if ($created !== '') : ?>
                            <code><?= esc($created) ?></code>
                        <?php endif ?>
                    </div>
                    <div class="mailbox-row-actions">
                        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/View/' . (int) $message['id'])) ?>">Open</a>
                        <?php if ($canEdit) : ?>
                            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/Edit/' . (int) $message['id'])) ?>">Edit</a>
                        <?php endif ?>
                        <a class="btn btn-danger" href="<?= esc(site_url('Content/Personal/Delete/' . (int) $message['id'])) ?>">Delete</a>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
        <?php
        $pagination = is_array($pagination ?? null) ? $pagination : [];
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['totalPages'] ?? 1);
        $total = (int) ($pagination['total'] ?? count($messages));
        $perPage = (int) ($pagination['perPage'] ?? count($messages));
        $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $end = $total > 0 ? min($total, $page * $perPage) : 0;
        $mailboxPath = match ($activeMailbox) {
            'inbox' => 'Content/Personal/Inbox',
            'sent' => 'Content/Personal/Sent',
            default => 'Content/Personal/Index',
        };
        $pageUrl = static fn (int $p): string => site_url($mailboxPath) . '?page=' . $p;
        ?>
        <div class="content-pagination">
            <span>Showing <?= esc((string) $start) ?>-<?= esc((string) $end) ?> of <?= esc((string) $total) ?></span>
            <?php if ($totalPages > 1) : ?>
                <nav class="pagination-links" aria-label="Personal message pagination">
                    <a class="btn btn-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= esc($page <= 1 ? '#' : $pageUrl($page - 1)) ?>">Previous</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                        <a class="btn btn-secondary <?= $i === $page ? 'active' : '' ?>" href="<?= esc($pageUrl($i)) ?>"><?= esc((string) $i) ?></a>
                    <?php endfor ?>
                    <a class="btn btn-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= esc($page >= $totalPages ? '#' : $pageUrl($page + 1)) ?>">Next</a>
                </nav>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
</div>
<?= $this->endSection() ?>
