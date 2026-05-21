<?php
/**
 * Triangle Mart - Special offers
 *
 * Products with active discounts.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';

// --- Products with an active discount offer ---
$offers = fetch_all("
    SELECT
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.stock_available,
        s.shop_name,
        d.discount_rate,
        d.start_date,
        d.end_date,
        ROUND(p.price - (p.price * d.discount_rate / 100), 2) AS discounted_price
    FROM discount d
    JOIN product p ON d.product_id = p.product_id
    JOIN shop s ON p.shop_id = s.shop_id
    WHERE d.discount_status = 'ACTIVE'
    AND p.product_status = 'ACTIVE'
    AND d.start_date <= SYSDATE
    AND d.end_date >= TRUNC(SYSDATE)
    ORDER BY d.discount_rate DESC
");
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Offers - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">

        <h1 class="page-title">Special Offers</h1>

        <?php if (!$offers): ?>
            <div class="summary">
                <p>No active offers available right now.</p>
                <a class="checkout" href="<?= app_url('customer/products.php') ?>">Browse Products</a>
            </div>
        <?php endif; ?>

        <div class="product-grid">
            <?php foreach ($offers as $p): ?>
                <div class="card offer-card">

                    <a href="<?= app_url('customer/product_detail.php?id=' . urlencode($p['product_id'])) ?>">
                        <div class="image">
                            <img
                                src="<?= app_url('actions/product-image.php?id=' . urlencode($p['product_id'])) ?>"
                                alt="<?= e($p['name']) ?>"
                                class="product-img"
                                onerror="this.style.display='none'; this.parentElement.classList.add('product-placeholder'); this.parentElement.innerHTML='<?= strtoupper(substr(e($p['name']), 0, 1)) ?>';">
                        </div>

                        <div class="card-info">
                            <div class="offer-badge">
                                <?= number_format((float)$p['discount_rate'], 0) ?>% OFF
                            </div>

                            <div class="title-price">
                                <h3><?= e($p['name']) ?></h3>
                            </div>

                            <p><?= e(substr($p['description'], 0, 90)) ?></p>
                            <small><?= e($p['shop_name']) ?></small>

                            <div class="offer-prices">
                                <span class="old-price">
                                    £<?= number_format((float)$p['price'], 2) ?>
                                </span>

                                <span class="new-price">
                                    £<?= number_format((float)$p['discounted_price'], 2) ?>
                                </span>
                            </div>
                        </div>
                    </a>

                    <?php if (current_user() && current_user()['user_type'] === 'CUSTOMER'): ?>
                        <form action="<?= app_url('actions/cart-add.php') ?>" method="post" class="card-actions">
                            <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-primary" type="submit">Add to Basket</button>
                        </form>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

</body>

</html>
