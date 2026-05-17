<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<?php
$settings = is_array($seoSettings ?? null) ? $seoSettings : [];
$metaTitle = (string) old('seo_meta_title', $settings['seo_meta_title'] ?? '');
$metaDescription = (string) old('seo_meta_description', $settings['seo_meta_description'] ?? '');
$metaKeywords = (string) old('seo_meta_keywords', $settings['seo_meta_keywords'] ?? '');
$canonicalUrl = (string) old('seo_canonical_url', $settings['seo_canonical_url'] ?? '');
$robots = (string) old('seo_robots', $settings['seo_robots'] ?? 'index,follow');
$ogImage = (string) old('seo_og_image', $settings['seo_og_image'] ?? '');
$robotsOptions = \App\Libraries\SeoSettings::ROBOTS_OPTIONS;
?>
<?= view('layouts/_site_header', [
    'siteHeaderImage' => 'Vendor/public/assets/content-manager-header.png',
]) ?>

<div class="dashboard-shell">
    <?= $this->include('dashboard/_sidebar') ?>

    <section class="dashboard-main">
        <?= $this->include('member/user/_flash') ?>

        <form class="card prose" method="post" action="<?= esc(site_url('DashBoard/SEO_Settings')) ?>">
            <?= csrf_field() ?>

            <h2>Main SEO</h2>
            <p class="hint">Default meta tags for the public site. Individual content pages can still override description and canonical URLs later.</p>

            <label for="seo_meta_title">Meta title</label>
            <input
                type="text"
                name="seo_meta_title"
                id="seo_meta_title"
                value="<?= esc($metaTitle, 'attr') ?>"
                maxlength="120"
                required
            >
            <p class="hint">Used as the default document title and Open Graph title when a page does not set its own.</p>

            <label for="seo_meta_description">Meta description</label>
            <textarea
                name="seo_meta_description"
                id="seo_meta_description"
                rows="4"
                maxlength="320"
                required
            ><?= esc($metaDescription) ?></textarea>
            <p class="hint">Default description for search engines and social previews (about 150–160 characters recommended).</p>

            <label for="seo_meta_keywords">Meta keywords</label>
            <input
                type="text"
                name="seo_meta_keywords"
                id="seo_meta_keywords"
                value="<?= esc($metaKeywords, 'attr') ?>"
                maxlength="255"
                placeholder="optional, comma-separated"
            >

            <label for="seo_canonical_url">Canonical base URL</label>
            <input
                type="url"
                name="seo_canonical_url"
                id="seo_canonical_url"
                value="<?= esc($canonicalUrl, 'attr') ?>"
                maxlength="255"
                placeholder="<?= esc(rtrim(site_url('/'), '/'), 'attr') ?>"
            >
            <p class="hint">Preferred public site URL (https://your-domain.example). Leave empty to use the current application URL.</p>

            <label for="seo_robots">Robots</label>
            <select name="seo_robots" id="seo_robots" aria-label="Robots indexing">
                <?php foreach ($robotsOptions as $option) : ?>
                    <option value="<?= esc($option, 'attr') ?>"<?= $robots === $option ? ' selected' : '' ?>><?= esc($option) ?></option>
                <?php endforeach ?>
            </select>
            <p class="hint">Default index/follow policy for pages that do not specify their own robots meta tag.</p>

            <label for="seo_og_image">Default share image URL</label>
            <input
                type="url"
                name="seo_og_image"
                id="seo_og_image"
                value="<?= esc($ogImage, 'attr') ?>"
                maxlength="255"
                placeholder="https://example.com/share-image.jpg"
            >
            <p class="hint">Optional Open Graph image for social link previews.</p>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Save SEO Settings</button>
                <a class="btn btn-secondary" href="<?= esc(site_url('/')) ?>">View Home Page</a>
            </div>
        </form>
    </section>
</div>
<?= $this->endSection() ?>
