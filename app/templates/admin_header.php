<?php
/**
 * Verwacht: $pageTitle (string). Alleen gebruiken op pagina's achter require_admin().
 */
$pageTitle = $pageTitle ?? 'Beheer';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · Beheer</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%96%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>
<body class="admin">
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url('admin/')) ?>">
      <span class="brand-name">Beheer</span>
      <span class="brand-sub">Publicaties van Lachman Soedamah</span>
    </a>
    <nav class="site-nav">
      <a href="<?= e(url('admin/')) ?>">Boeken</a>
      <a href="<?= e(url('admin/bestellingen.php')) ?>">Bestellingen</a>
      <a href="<?= e(url()) ?>">Shop bekijken</a>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="link-button">Uitloggen</button>
      </form>
    </nav>
  </div>
</header>
<main class="wrap">
