<?php
/**
 * Shared site stylesheet (loaded once per page).
 * @see Vendor/public/assets/site.css
 */
$siteStylesLoad = ! defined('PRODUCT_STORE_SITE_STYLES');
if ($siteStylesLoad) {
    define('PRODUCT_STORE_SITE_STYLES', true);
}
?>
<?php if ($siteStylesLoad) : ?>
    <link rel="stylesheet" href="<?= esc(base_url('Vendor/public/assets/site.css?v=1')) ?>">
<?php endif ?>
