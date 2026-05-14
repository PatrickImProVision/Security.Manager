<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Delete Personal Message</h1>
<p class="lead">Confirm deletion of this personal message.</p>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <h2><?= esc((string) $message['subject']) ?></h2>
    <p>Message ID: <code><?= esc((string) $message['id']) ?></code></p>
    <p>From: <code><?= esc((string) ($message['sender_name'] ?? '-')) ?></code></p>
    <p>To: <code><?= esc((string) ($message['recipient_name'] ?? '-')) ?></code></p>

    <form method="post" action="<?= esc(site_url('Content/Personal/Delete/' . (int) $message['id'])) ?>">
        <?= csrf_field() ?>
        <div class="actions">
            <button type="submit" class="btn btn-danger">Delete message</button>
            <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/View/' . (int) $message['id'])) ?>">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
