<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $documentTitle = \App\Libraries\SitePageTitle::documentTitle(isset($title) ? (string) $title : null);
    $requestSegments = service('request')->getUri()->getSegments();
    $isInstallRouteHead = ($requestSegments[0] ?? '') === 'install';
    $canonicalForPage = isset($canonicalUrl) ? trim((string) $canonicalUrl) : '';
    if ($canonicalForPage === '' && ! $isInstallRouteHead) {
        $canonicalForPage = current_url();
    }
    $pageMeta = \App\Libraries\InstallationState::isInstalled() && ! $isInstallRouteHead
        ? (new \App\Libraries\SeoSettings())->pageMeta(
            $documentTitle,
            isset($metaDescription) ? (string) $metaDescription : null,
            $canonicalForPage !== '' ? $canonicalForPage : null,
        )
        : [
            'description'    => '',
            'keywords'       => '',
            'robots'         => 'noindex,nofollow',
            'canonical'      => rtrim(site_url('/'), '/'),
            'og_title'       => $documentTitle,
            'og_description' => '',
            'og_image'       => '',
            'og_url'         => rtrim(site_url('/'), '/'),
        ];
    ?>
    <title><?= esc($documentTitle) ?></title>
    <?php if ($pageMeta['description'] !== '') : ?>
        <meta name="description" content="<?= esc($pageMeta['description'], 'attr') ?>">
    <?php endif ?>
    <?php if ($pageMeta['keywords'] !== '') : ?>
        <meta name="keywords" content="<?= esc($pageMeta['keywords'], 'attr') ?>">
    <?php endif ?>
    <meta name="robots" content="<?= esc($pageMeta['robots'], 'attr') ?>">
    <?php if ($pageMeta['canonical'] !== '') : ?>
        <link rel="canonical" href="<?= esc($pageMeta['canonical'], 'attr') ?>">
    <?php endif ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= esc($pageMeta['og_title'], 'attr') ?>">
    <?php if ($pageMeta['og_description'] !== '') : ?>
        <meta property="og:description" content="<?= esc($pageMeta['og_description'], 'attr') ?>">
    <?php endif ?>
    <?php if ($pageMeta['og_url'] !== '') : ?>
        <meta property="og:url" content="<?= esc($pageMeta['og_url'], 'attr') ?>">
    <?php endif ?>
    <?php if ($pageMeta['og_image'] !== '') : ?>
        <meta property="og:image" content="<?= esc($pageMeta['og_image'], 'attr') ?>">
    <?php endif ?>
    <?= view('layouts/_site_styles') ?>
</head>
<body class="<?= ! empty($lightPage) ? 'page-light' : '' ?>">
<?php
helper('form');

$requestSegments = service('request')->getUri()->getSegments();
$isInstallRoute = ($requestSegments[0] ?? '') === 'install';
$installed = \App\Libraries\InstallationState::isInstalled();
$layoutData = $installed && ! $isInstallRoute
    ? \App\Libraries\SiteLayoutData::get()
    : [
        'web_name'                 => \App\Libraries\WebSettings::defaultWebName(),
        'public_content_enabled'   => false,
        'public_content_nav_items' => [],
    ];
$webName = trim((string) ($layoutData['web_name'] ?? '')) ?: \App\Libraries\WebSettings::defaultWebName();
$memberUserId = session()->get('member_user_id');
$memberUsername = (string) (session()->get('member_username') ?? '');
$memberLoggedIn = is_numeric($memberUserId);
$memberCanManageRoles = $memberLoggedIn && (bool) session()->get('member_can_manage_roles');
if ($memberLoggedIn && session()->get('member_can_manage_roles') === null) {
    $memberRole = (string) (session()->get('member_role') ?? '');
    $memberCanManageRoles = $memberRole !== '' && (new \App\Libraries\RoleService())->isAdministrator($memberRole);
}
$publicContentNavItems = $layoutData['public_content_nav_items'] ?? [];
$publicContentEnabled = (bool) ($layoutData['public_content_enabled'] ?? true);
if ($isInstallRoute) {
    $publicContentEnabled = false;
    $publicContentNavItems = [];
}
?>
<header class="topbar">
    <div class="nav-wrap">
        <a class="brand" href="<?= esc(site_url('/')) ?>"><?= esc($webName) ?></a>
        <nav class="nav" aria-label="Main navigation">
            <a href="<?= esc(site_url('/')) ?>">Home</a>
            <?php foreach ($publicContentNavItems as $item) : ?>
                <a href="<?= esc(\App\Libraries\PublicContentUrls::postUrl($item)) ?>">
                    <?= esc((string) (($item['nav_label'] ?? '') ?: $item['title'])) ?>
                </a>
            <?php endforeach ?>
            <?php if ($installed) : ?>
                <details>
                    <summary><?= $memberLoggedIn ? esc($memberUsername !== '' ? $memberUsername : 'Account') : 'Member' ?></summary>
                    <div class="dropdown">
                        <?php if ($memberLoggedIn) : ?>
                            <a href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>
                            <a href="<?= esc(site_url('Member/List')) ?>">Member List</a>
                            <form method="post" action="<?= esc(site_url('Member/User/Logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="nav-link" type="submit">Logout</button>
                            </form>
                        <?php else : ?>
                            <a href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
                            <a href="<?= esc(site_url('Member/User/Register')) ?>">Register</a>
                            <a href="<?= esc(site_url('Member/User/ForgotPassword')) ?>">Forgot Password</a>
                        <?php endif ?>
                    </div>
                </details>
                <?php if ($memberLoggedIn) : ?>
                    <a href="<?= esc(site_url('DashBoard/Index')) ?>">Dashboard</a>
                <?php endif ?>
            <?php endif ?>
        </nav>
    </div>
</header>
<div class="wrap <?= ! empty($wideLayout) ? 'wrap-wide' : '' ?>">
    <?= $this->renderSection('main') ?>
</div>
<footer class="site-footer">
    Environment: <code><?= esc(ENVIRONMENT) ?></code>
    · Rendered in <code>{elapsed_time}</code> seconds
    · Memory: <code>{memory_usage}</code> MB
</footer>
<script>
(function () {
    document.querySelectorAll('.nav details').forEach(function (details) {
        details.addEventListener('mouseenter', function () {
            details.open = true;
        });
        details.addEventListener('mouseleave', function () {
            details.open = false;
        });
        details.addEventListener('focusin', function () {
            details.open = true;
        });
        details.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (! details.contains(document.activeElement)) {
                    details.open = false;
                }
            }, 0);
        });
    });
})();
</script>
</body>
</html>
