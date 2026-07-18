<?php
/**
 * Verwacht: $pageTitle (string).
 * Optioneel: $metaDescription (string), $ogImage (string, absolute URL),
 *            $metaRefresh (int, seconden tot automatisch verversen).
 */
$pageTitle = $pageTitle ?? 'Publicaties';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($metaRefresh)): ?>
<meta http-equiv="refresh" content="<?= (int) $metaRefresh ?>">
<?php endif; ?>
<title><?= e($pageTitle) ?> · Publicaties van Lachman Soedamah</title>
<?php if (!empty($metaDescription)): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($pageTitle) ?> · Publicaties van Lachman Soedamah">
<meta property="og:type" content="website">
<?php if (!empty($ogImage)): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-name">Lachman Soedamah</span>
      <span class="brand-sub">Publicaties</span>
    </a>
    <nav class="site-nav">
      <a href="<?= e(url('#publicaties')) ?>">Boeken</a>
      <a href="<?= e(url('#over-de-auteur')) ?>">Over de auteur</a>
      <a href="https://soedamah.nl/publicaties" rel="noopener">Artikelen</a>
    </nav>
  </div>
</header>
<main class="wrap">
