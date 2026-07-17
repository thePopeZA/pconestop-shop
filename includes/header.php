<?php
/** Shared page header. Set $pageTitle, optionally $metaDesc, $activeCat before including. */
if (!defined('BASE_PATH')) { require_once dirname(__DIR__) . '/config/config.php'; }
require_once BASE_PATH . '/includes/shop.php';

$pageTitle = $pageTitle ?? APP_NAME;
$fullTitle = $pageTitle === APP_NAME ? APP_NAME : $pageTitle . ' — ' . APP_NAME;
$metaDesc  = $metaDesc ?? 'PC hardware, components, peripherals & tech — fast delivery across South Africa.';
$activeCat = $activeCat ?? '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <link rel="preconnect" href="https://www.syntech.co.za">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
</head>
<body>
<header class="site-header">
    <div class="topbar">
        <div class="container">
            <span class="topbar-promo"><img src="<?= e(asset('img/flag-za.svg')) ?>" alt="South Africa" class="za-flag"> <span>Nationwide courier delivery · FREE on larger orders</span></span>
            <span><a href="<?= e(url('track.php')) ?>">Track order</a> &nbsp;·&nbsp; <a href="mailto:orders@pconestop.co.za">orders@pconestop.co.za</a></span>
        </div>
    </div>
    <div class="container header-main">
        <a class="logo" href="<?= e(url('/')) ?>" aria-label="PC One Stop home">
            <img class="logo-img" src="<?= e(asset('img/logo.png')) ?>" alt="PC One Stop — Your Trusted IT Solutions" width="174" height="46">
        </a>
        <form class="search-form" action="<?= e(url('search.php')) ?>" method="get">
            <input type="text" name="q" placeholder="Search products, brands, SKUs…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
            <button type="submit" aria-label="Search">Search</button>
        </form>
        <div class="header-actions">
            <a class="cart-link" href="<?= e(url('cart.php')) ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Cart</span>
                <?php $cc = cart_count(); if ($cc > 0): ?><span class="cart-badge"><?= $cc ?></span><?php endif; ?>
            </a>
        </div>
    </div>
    <nav class="catnav">
        <div class="container">
            <a href="<?= e(url('shop.php')) ?>" class="<?= $activeCat === 'all' ? 'active' : '' ?>">All Products</a>
            <?php foreach (nav_categories(10) as $c): ?>
                <a href="<?= e(category_url($c)) ?>" class="<?= $activeCat === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="page">
    <div class="container">
        <?php foreach (get_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>
