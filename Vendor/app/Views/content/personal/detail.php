<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1><?= esc((string) $message['subject']) ?></h1>
<p class="lead">Personal message detail.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <p>Message ID: <code><?= esc((string) $message['id']) ?></code></p>
    <p>Status: <code><?= esc((string) $message['status']) ?></code></p>
    <p>From: <code><?= esc((string) ($message['sender_name'] ?? '-')) ?></code></p>
    <p>To: <code><?= esc((string) ($message['recipient_name'] ?? '-')) ?></code></p>
    <?php if (! empty($message['created_at'])) : ?>
        <p>Created: <code><?= esc((string) $message['created_at']) ?></code></p>
    <?php endif ?>
    <?php if (! empty($message['updated_at'])) : ?>
        <p>Updated: <code><?= esc((string) $message['updated_at']) ?></code></p>
    <?php endif ?>

    <h2>Message</h2>
    <div class="content-body">
        <?= $bodyHtml ?? '' ?>
    </div>

    <div class="actions">
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/Index')) ?>">Back to messages</a>
        <?php if (! empty($canEdit)) : ?>
            <a class="btn btn-primary" href="<?= esc(site_url('Content/Personal/Edit/' . (int) $message['id'])) ?>">Edit message</a>
        <?php endif ?>
        <?php if (! empty($canDelete)) : ?>
            <a class="btn btn-danger" href="<?= esc(site_url('Content/Personal/Delete/' . (int) $message['id'])) ?>">Delete message</a>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
