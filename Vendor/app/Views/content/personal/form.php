<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
use App\Libraries\MemberProfileUrls;

$isEdit = ($mode ?? '') === 'edit';
$bulkMode = ! empty($bulkMode);
$messageId = (int) ($message['id'] ?? 0);
$recipientRow = is_array($recipientUser ?? null) ? $recipientUser : ['id' => (int) ($message['recipient_id'] ?? 0)];
$action = $isEdit
    ? site_url('Content/Personal/Edit/' . $messageId)
    : ($bulkMode ? site_url('Content/Personal/Create') : MemberProfileUrls::personalMessageCreateUrl($recipientRow));
$subjectValue = (string) ($message['subject'] ?? '');
$bodyValue = (string) ($message['body'] ?? '');
$recipientValue = (string) ($message['recipient_id'] ?? '');
$recipientLabel = (string) (($recipientOptions ?? [])[(int) $recipientValue] ?? 'Selected user');
?>

<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc($action) ?>" class="card">
    <?= csrf_field() ?>

    <label>Recipient</label>
    <?php if ($bulkMode) : ?>
        <input type="hidden" name="bulk_mode" value="all">
        <div class="readonly-field">All active users (<?= esc((string) ($bulkRecipientCount ?? 0)) ?> recipients)</div>
        <p class="hint">Bulk messaging creates one personal database message for each active user.</p>
    <?php else : ?>
        <input type="hidden" name="recipient_id" value="<?= esc($recipientValue, 'attr') ?>">
        <div class="readonly-field"><?= esc($recipientLabel) ?></div>
        <p class="hint">Personal messages are sent to one selected user, not to all users.</p>
    <?php endif ?>

    <label for="subject">Subject</label>
    <input type="text" name="subject" id="subject" value="<?= old('subject', $subjectValue, 'attr') ?>" required maxlength="180">
    <?= view('layouts/_wysiwyg_editor', [
    'wysiwygValue' => old('body', $bodyValue),
    'wysiwygLabel' => 'Message',
    'wysiwygToolbarLabel' => 'Personal message editor toolbar',
    'wysiwygHint' => 'Use the toolbar for rich messages. HTML source mode is available for fine adjustments.',
    'wysiwygPreviewTitleField' => 'subject',
    'wysiwygPreviewMetaField' => '',
    'wysiwygPreviewMetaSelector' => '.readonly-field',
    'wysiwygPreviewMetaId' => 'preview-recipient',
    'wysiwygPreviewMetaPrefix' => 'Recipient: ',
    'wysiwygPreviewEmptyBody' => '<p>No message content yet.</p>',
]) ?>
<div class="actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save message' : ($bulkMode ? 'Send bulk message' : 'Create message') ?></button>
        <button type="button" class="btn btn-secondary" id="btn-preview">Preview</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Content/Personal/Index')) ?>">Back to messages</a>
        <?php if ($isEdit) : ?>
            <a class="btn btn-danger" href="<?= esc(site_url('Content/Personal/Delete/' . $messageId)) ?>">Delete message</a>
        <?php endif ?>
    </div>
    <?= view('layouts/_wysiwyg_preview', ['wysiwygShowMeta' => true, 'wysiwygPreviewMetaId' => 'preview-recipient']) ?>
</form>
<?= $this->endSection() ?>
