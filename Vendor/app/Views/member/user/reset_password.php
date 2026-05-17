<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Reset Password</h1>

<?= $this->include('member/user/_flash') ?>

<?php if (empty($valid)) : ?>
    <p class="lead"><?= esc((string) ($message ?? 'Reset link is invalid or already used.')) ?></p>
    <div class="actions">
        <a class="btn btn-primary" href="<?= esc(site_url('Member/User/ForgotPassword')) ?>">Request a new link</a>
        <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Login')) ?>">Back to login</a>
    </div>
<?php else : ?>
    <p class="lead">Choose a new password for your account.</p>

    <form
        id="reset-password-form"
        method="post"
        action="<?= esc(\App\Libraries\MemberProfileUrls::resetPasswordUrl((string) ($token ?? ''))) ?>"
        class="card"
        <?php if (! empty($cangPasswordProposal)) : ?>
            data-propose-url="<?= esc(\App\Libraries\MemberProfileUrls::resetPasswordProposeUrl((string) ($token ?? '')), 'attr') ?>"
        <?php endif ?>
    >
        <?= csrf_field() ?>

        <label for="password">New password</label>
        <div class="field-password">
            <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle" id="reset-password-toggle" aria-label="Show password" aria-pressed="false">Show</button>
        </div>

        <label for="password_confirm">Confirm new password</label>
        <div class="field-password">
            <input type="password" name="password_confirm" id="password_confirm" required minlength="8" autocomplete="new-password">
            <button type="button" class="password-toggle" id="reset-password-confirm-toggle" aria-label="Show confirm password" aria-pressed="false">Show</button>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Update password</button>
            <?php if (! empty($cangPasswordProposal)) : ?>
                <button type="button" class="btn btn-secondary" id="cang-propose-password">Propose password</button>
            <?php endif ?>
            <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/Login')) ?>">Back to login</a>
            <?php if (! empty($cangPasswordProposal)) : ?>
                <span class="cang-preview-status" id="cang-propose-status" aria-live="polite"></span>
            <?php endif ?>
        </div>
    </form>
    <script>
    (function () {
        var form = document.getElementById('reset-password-form');
        var proposeBtn = document.getElementById('cang-propose-password');
        var proposeStatus = document.getElementById('cang-propose-status');
        var passwordInput = document.getElementById('password');
        var confirmInput = document.getElementById('password_confirm');

        if (form && proposeBtn && proposeStatus && form.getAttribute('data-propose-url')) {
            proposeBtn.addEventListener('click', function () {
                var proposeUrl = form.getAttribute('data-propose-url');
                var csrf = form.querySelector('input[name="<?= esc(csrf_token(), 'js') ?>"]');
                if (! proposeUrl || ! csrf) {
                    proposeStatus.textContent = 'Could not request a password.';
                    return;
                }

                proposeBtn.disabled = true;
                proposeStatus.textContent = '';

                var body = new FormData();
                body.append(csrf.name, csrf.value);

                fetch(proposeUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: body,
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function (wrapped) {
                        if (! wrapped.data || ! wrapped.data.ok) {
                            proposeStatus.textContent = (wrapped.data && wrapped.data.error)
                                ? wrapped.data.error
                                : 'Could not generate a password.';
                            return;
                        }

                        var pwd = wrapped.data.password || '';
                        if (passwordInput) {
                            passwordInput.value = pwd;
                            passwordInput.type = 'text';
                        }
                        if (confirmInput) {
                            confirmInput.value = pwd;
                            confirmInput.type = 'text';
                        }

                        var togglePwd = document.getElementById('reset-password-toggle');
                        var toggleConfirm = document.getElementById('reset-password-confirm-toggle');
                        if (togglePwd) {
                            togglePwd.textContent = 'Hide';
                            togglePwd.setAttribute('aria-pressed', 'true');
                        }
                        if (toggleConfirm) {
                            toggleConfirm.textContent = 'Hide';
                            toggleConfirm.setAttribute('aria-pressed', 'true');
                        }

                        proposeStatus.textContent = '';
                    })
                    .catch(function () {
                        proposeStatus.textContent = 'Could not reach the server.';
                    })
                    .finally(function () {
                        proposeBtn.disabled = false;
                    });
            });
        }

        function wire(toggleId, inputId, phrase) {
            var btn = document.getElementById(toggleId);
            var input = document.getElementById(inputId);
            if (! btn || ! input) {
                return;
            }
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + phrase);
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        }
        wire('reset-password-toggle', 'password', 'password');
        wire('reset-password-confirm-toggle', 'password_confirm', 'confirm password');
    })();
    </script>
<?php endif ?>
<?= $this->endSection() ?>
