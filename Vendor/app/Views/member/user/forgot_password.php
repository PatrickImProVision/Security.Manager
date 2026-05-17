<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Forgot Password</h1>
<p class="lead">Request a password reset for an existing member account. E-mail is not configured yet, so the reset link is shown here after you submit.</p>

<?= $this->include('member/user/_flash') ?>

<?php if (! empty($resetUrl)) : ?>
    <div class="card activation-pending">
        <h2>Reset your password</h2>
        <p class="hint">Use this link to choose a new password.</p>
        <div class="actions">
            <a class="btn btn-primary" href="<?= esc($resetUrl) ?>">Open reset page</a>
        </div>
        <p class="activation-link-url"><code><?= esc($resetUrl) ?></code></p>
    </div>
<?php endif ?>

<form method="post" action="<?= esc(site_url('Member/User/ForgotPassword')) ?>" class="card">
    <?= csrf_field() ?>
    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" value="<?= old('email', '', 'attr') ?>" required>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Create reset link</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Login')) ?>">Back to login</a>
    </div>
</form>
<?= $this->endSection() ?>
