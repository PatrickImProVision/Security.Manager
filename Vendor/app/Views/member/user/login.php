<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Login</h1>
<p class="lead">Sign in to view your member profile.</p>

<?= $this->include('member/user/_flash') ?>

<form method="post" action="<?= esc(site_url('Member/User/Login')) ?>" class="card">
    <?= csrf_field() ?>
    <label for="login">Username or e-mail</label>
    <input type="text" name="login" id="login" value="<?= old('login', '', 'attr') ?>" required autocomplete="username">

    <label for="password">Password</label>
    <div class="field-password">
        <input type="password" name="password" id="password" required autocomplete="current-password">
        <button type="button" class="password-toggle" id="login-password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Login</button>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Register')) ?>">Register</a>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/ForgotPassword')) ?>">Forgot password</a>
    </div>
</form>
<script>
(function () {
    var btn = document.getElementById('login-password-toggle');
    var input = document.getElementById('password');
    if (! btn || ! input) {
        return;
    }

    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-label', (show ? 'Hide' : 'Show') + ' password');
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
})();
</script>
<?= $this->endSection() ?>
