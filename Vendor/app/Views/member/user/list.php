<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<?= $this->include('member/user/_flash') ?>

<div class="card prose">
    <div class="role-list-head">
        <h2>Members</h2>
        <div class="role-list-head-actions">
            <a class="btn btn-secondary" href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>
        </div>
    </div>

    <label for="member-search">Search members</label>
    <input type="search" id="member-search" placeholder="Search by username, e-mail, or role..." autocomplete="off" data-search-url="<?= esc(site_url('Member/List/Search'), 'attr') ?>">
    <p class="hint" id="member-search-status">Type to search the member list without reloading the page.</p>

    <div class="member-list-columns" aria-hidden="true">
        <span>User</span>
        <span>E-mail</span>
        <span>Role</span>
        <span>Status</span>
        <span>Actions</span>
    </div>

    <div id="member-list-results">
        <?php foreach ($users as $user) : ?>
            <div class="member-list-row">
                <div class="member-list-user">
                    <a class="member-list-profile-link" href="<?= esc((string) $user['profile_url']) ?>">
                        <?php if (! empty($user['profile_image_url'])) : ?>
                            <img class="member-list-avatar" src="<?= esc((string) $user['profile_image_url']) ?>" alt="<?= esc((string) $user['username'], 'attr') ?> profile image">
                        <?php else : ?>
                            <div class="member-list-avatar-fallback" aria-label="No profile image"><?= esc((string) ($user['profile_initial'] ?? '?')) ?></div>
                        <?php endif ?>
                        <strong><?= esc((string) $user['username']) ?></strong>
                    </a>
                </div>
                <div><code><?= esc((string) $user['email']) ?></code></div>
                <div>
                    <span class="role-level-badge" title="<?= esc((string) $user['role'], 'attr') ?>">
                        <?= esc((string) $user['role_name']) ?>
                    </span>
                </div>
                <div>
                    <span class="status-pill <?= ! empty($user['is_active']) ? 'status-active' : 'status-inactive' ?>">
                        <?= ! empty($user['is_active']) ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                <div>
                    <div class="member-list-actions">
                        <a class="btn btn-secondary" href="<?= esc((string) $user['profile_url']) ?>">View</a>
                        <?php if (! empty($user['message_url'])) : ?>
                            <a class="btn btn-primary" href="<?= esc((string) $user['message_url']) ?>">Message</a>
                        <?php elseif (! empty($user['message_disabled'])) : ?>
                            <span class="btn btn-secondary disabled" aria-disabled="true" title="<?= esc((string) $user['message_disabled'], 'attr') ?>">Message</span>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<script>
(function () {
    var search = document.getElementById('member-search');
    var results = document.getElementById('member-list-results');
    var status = document.getElementById('member-search-status');
    if (! search || ! results || ! status) {
        return;
    }

    var timer = null;
    var requestId = 0;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function avatar(user) {
        if (user.profile_image_url) {
            return '<img class="member-list-avatar" src="' + escapeHtml(user.profile_image_url) + '" alt="' + escapeHtml(user.username) + ' profile image">';
        }

        return '<div class="member-list-avatar-fallback" aria-label="No profile image">' + escapeHtml(user.profile_initial || '?') + '</div>';
    }

    function profileLink(user) {
        return '<a class="member-list-profile-link" href="' + escapeHtml(user.profile_url) + '">' + avatar(user) + '<strong>' + escapeHtml(user.username) + '</strong></a>';
    }

    function renderUsers(users) {
        if (! Array.isArray(users) || users.length === 0) {
            results.innerHTML = '<div class="err">No users matched your search.</div>';
            return;
        }

        results.innerHTML = users.map(function (user) {
            var active = Boolean(user.is_active);

            return '<div class="member-list-row">'
                + '<div class="member-list-user">' + profileLink(user) + '</div>'
                + '<div><code>' + escapeHtml(user.email) + '</code></div>'
                + '<div><span class="role-level-badge" title="' + escapeHtml(user.role) + '">' + escapeHtml(user.role_name) + '</span></div>'
                + '<div><span class="status-pill ' + (active ? 'status-active' : 'status-inactive') + '">' + (active ? 'Active' : 'Inactive') + '</span></div>'
                + '<div><div class="member-list-actions">'
                + '<a class="btn btn-secondary" href="' + escapeHtml(user.profile_url) + '">View</a>'
                + (user.message_url ? '<a class="btn btn-primary" href="' + escapeHtml(user.message_url) + '">Message</a>' : '')
                + (! user.message_url && user.message_disabled ? '<span class="btn btn-secondary disabled" aria-disabled="true" title="' + escapeHtml(user.message_disabled) + '">Message</span>' : '')
                + '</div></div>'
                + '</div>';
        }).join('');
    }

    function runSearch() {
        var id = ++requestId;
        var term = search.value.trim();
        var url = search.getAttribute('data-search-url') + '?q=' + encodeURIComponent(term);
        status.textContent = 'Searching...';

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Search failed.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (id !== requestId) {
                    return;
                }

                renderUsers(payload.users || []);
                status.textContent = term === ''
                    ? 'Showing all users.'
                    : 'Showing search results for "' + term + '".';
            })
            .catch(function () {
                if (id !== requestId) {
                    return;
                }

                status.textContent = 'Could not search users. Try again.';
            });
    }

    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(runSearch, 250);
    });
})();
</script>
<?= $this->endSection() ?>
