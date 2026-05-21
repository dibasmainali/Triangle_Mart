<?php
/**
 * Triangle Mart - Customer site header (partial)
 *
 * Logo, navigation, wishlist/cart icons, and login/logout links.
 * Expects config.php to be loaded (via require in this file).
 */
require_once __DIR__ . '/config.php';

$u = current_user();
?>

<header class="header">

    <div class="header-left logo">
        <a href="<?= app_url('customer/home.php') ?>">
            <img src="<?= app_url('assets/images/logo3comp.png') ?>" alt="Triangle Mart logo">
        </a>
    </div>

    <!-- Hamburger Menu Checkbox (hidden) -->
    <input type="checkbox" id="menuToggle" class="menu-toggle">
    
    <!-- Hamburger Icon Label -->
    <label for="menuToggle" class="hamburger" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </label>

    <div class="header-right">

        <nav class="main-nav">
            <a href="<?= app_url('customer/home.php') ?>">Home</a>
            <a href="<?= app_url('customer/products.php') ?>">Products</a>
            <a href="<?= app_url('customer/offers.php') ?>">Offers</a>
        </nav>

        <?php if ($u && $u['user_type'] === 'CUSTOMER'): ?>
            <a class="header-icon-btn" href="<?= app_url('customer/wishlist.php') ?>" title="Wishlist"><img src="<?= app_url('assets/images/icons8-heart-48.png') ?>"></a>
            <a class="header-icon-btn" href="<?= app_url('customer/cart.php') ?>" title="Basket"><img src="<?= app_url('assets/images/icons8-cart-24.png') ?>"></a>
        <?php endif; ?>

        <?php if ($u): ?>
            <a class="header-user" href="<?= app_url($u['user_type'] === 'TRADER' ? 'trader/profile.php' : 'customer/customer-profile.php') ?>">
                👤 <?= e($u['first_name']) ?>
            </a>

            <a class="header-logout" href="<?= app_url('actions/logout.php') ?>">Logout</a>
        <?php else: ?>
            <a class="header-user" href="<?= app_url('customer/login.php') ?>">👤 Log in</a>
        <?php endif; ?>

    </div>

</header>
<div class="menu-overlay"></div>
