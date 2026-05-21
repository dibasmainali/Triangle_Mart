<?php
/**
 * Triangle Mart - Trader area header (partial)
 *
 * Navigation for dashboard, products, discounts, profile, and logout.
 */
require_once __DIR__ . '/config.php';

$u = current_user();
?>

<header class="header">

    <div class="header-left logo">
        <a href="<?= app_url('trader/dashboard.php') ?>">
            <img src="<?= app_url('assets/images/logo3comp.png') ?>" alt="Triangle Mart logo">
        </a>
    </div>

    <nav class="main-nav">
        <a href="<?= app_url('trader/dashboard.php') ?>">Dashboard</a>
        <a href="<?= app_url('trader/products.php') ?>">Manage Products</a>
        <a href="<?= app_url('trader/discounts.php') ?>">Discounts</a>
    </nav>

    <div class="header-right">

        <a class="profile-link" href="<?= app_url('trader/profile.php') ?>">
            👤 <?= e($u['first_name']) ?>
        </a>

        <a class="logout-link" href="<?= app_url('actions/logout.php') ?>">
            Logout
        </a>

    </div>

</header>
