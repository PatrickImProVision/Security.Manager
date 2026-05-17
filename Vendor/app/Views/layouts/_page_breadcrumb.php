<?php
$breadcrumbItems = is_array($breadcrumbItems ?? null) ? $breadcrumbItems : [];
if ($breadcrumbItems === []) {
    return;
}
?>
<nav class="forum-breadcrumb" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbItems as $index => $item) : ?>
        <?php if ($index > 0) : ?>
            <span aria-hidden="true">›</span>
        <?php endif ?>
        <?php
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        ?>
        <?php if ($url !== '' && $index < count($breadcrumbItems) - 1) : ?>
            <a href="<?= esc($url) ?>"><?= esc($label) ?></a>
        <?php else : ?>
            <span><?= esc($label) ?></span>
        <?php endif ?>
    <?php endforeach ?>
</nav>
