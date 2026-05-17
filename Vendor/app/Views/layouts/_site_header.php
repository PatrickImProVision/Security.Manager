<?php
$siteHeaderTitle = trim((string) ($siteHeaderTitle ?? ''));
$siteHeaderDescription = trim((string) ($siteHeaderDescription ?? ''));
$siteHeaderImage = trim((string) ($siteHeaderImage ?? 'Vendor/public/assets/content-manager-header.png'));
$siteHeaderClass = trim((string) ($siteHeaderClass ?? ''));
$siteHeaderImageOnly = ! empty($siteHeaderImageOnly);

if (! $siteHeaderImageOnly && ($siteHeaderTitle === '' || $siteHeaderDescription === '')) {
    try {
        $webSettings = (new \App\Libraries\WebSettings())->homeSettings();
        $siteHeaderTitle = $siteHeaderTitle !== '' ? $siteHeaderTitle : trim((string) ($webSettings['web_name'] ?? ''));
        $siteHeaderDescription = $siteHeaderDescription !== '' ? $siteHeaderDescription : trim((string) ($webSettings['web_description'] ?? ''));
    } catch (\Throwable) {
        $siteHeaderTitle = $siteHeaderTitle !== '' ? $siteHeaderTitle : \App\Libraries\WebSettings::defaultWebName();
        $siteHeaderDescription = $siteHeaderDescription !== '' ? $siteHeaderDescription : 'Change Description';
    }
}

if (! $siteHeaderImageOnly) {
    $siteHeaderTitle = $siteHeaderTitle !== '' ? $siteHeaderTitle : 'Change Name';
    $siteHeaderDescription = $siteHeaderDescription !== '' ? $siteHeaderDescription : 'Change Description';
}
?>

<header class="site-hero <?= esc($siteHeaderClass) ?><?= $siteHeaderImageOnly ? ' site-hero-image-only' : '' ?>">
    <?php if ($siteHeaderImage !== '') : ?>
        <img class="site-hero-art" src="<?= esc(base_url($siteHeaderImage)) ?>" alt="" aria-hidden="true">
    <?php endif ?>

    <?php if (! $siteHeaderImageOnly) : ?>
        <div class="site-hero-copy">
            <?php if ($siteHeaderTitle !== '') : ?>
                <h1><?= esc($siteHeaderTitle) ?></h1>
            <?php endif ?>

            <?php if ($siteHeaderDescription !== '') : ?>
                <p><?= esc($siteHeaderDescription) ?></p>
            <?php endif ?>
        </div>
    <?php endif ?>
</header>
