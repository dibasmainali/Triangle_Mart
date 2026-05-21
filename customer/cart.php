<?php
/**
 * Triangle Mart - Shopping basket
 *
 * View and update cart quantities before checkout.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Load active basket and line items ---

$user = current_user();

$items = fetch_all("
    SELECT
        bi.product_id,
        bi.quantity,
        p.name,
        p.price AS original_price,
        p.stock_available,
        p.min_order,
        p.max_order,
        s.shop_name,
        NVL(d.discount_rate, 0) AS discount_rate,
        ROUND(
            p.price - (p.price * NVL(d.discount_rate, 0) / 100),
            2
        ) AS final_price
    FROM basket b
    JOIN basket_item bi ON b.basket_id = bi.basket_id
    JOIN product p ON bi.product_id = p.product_id
    JOIN shop s ON p.shop_id = s.shop_id
    LEFT JOIN (
        SELECT
            product_id,
            MAX(discount_rate) AS discount_rate
        FROM discount
        WHERE discount_status = 'ACTIVE'
        AND start_date <= SYSDATE
        AND end_date >= TRUNC(SYSDATE)
        GROUP BY product_id
    ) d ON p.product_id = d.product_id
    WHERE b.user_id = :uid
    AND b.basket_status = 'ACTIVE'
", [
    'uid' => $user['user_id']
]);

$total = 0;

foreach ($items as $it) {
    $total += (int)$it['quantity'] * (float)$it['final_price'];
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basket</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">
        <h1 class="page-title">Your Basket</h1>

        <div class="container">
            <div class="cart">

                <?php if (!$items): ?>
                    <p>Your basket is empty.</p>
                <?php endif; ?>

                <?php foreach ($items as $it): ?>
                    <div class="cart-item">
                        <div class="img-box">
                            <img src="<?= app_url('actions/product-image.php?id=' . urlencode($it['product_id'])) ?>" alt="<?= e($it['name']) ?>" class="product-img" onerror="this.style.display='none'; this.parentElement.classList.add('product-placeholder'); this.parentElement.innerHTML='<?= strtoupper(substr(e($it['name']), 0, 1)) ?>';">
                        </div>
                        <div class="item-name">
                            <?= e($it['name']) ?>
                            <small>
                                <?= e($it['shop_name']) ?> ·
                                <?php if ((float)$it['discount_rate'] > 0): ?>
                                    <span class="old-price">£<?= number_format((float)$it['original_price'], 2) ?></span>
                                    <span class="new-price">£<?= number_format((float)$it['final_price'], 2) ?></span>
                                    <span class="offer-badge"><?= number_format((float)$it['discount_rate'], 0) ?>% OFF</span>
                                <?php else: ?>
                                    £<?= number_format((float)$it['final_price'], 2) ?>
                                <?php endif; ?>
                                · Stock <?= (int)$it['stock_available'] ?>
                            </small>
                        </div>
                        <form action="<?= app_url('actions/cart-update.php') ?>" method="post" class="quantity">
                            <input type="hidden" name="product_id" value="<?= e($it['product_id']) ?>">
                            <input type="number" name="quantity" value="<?= (int)$it['quantity'] ?>" min="0" max="<?= (int)$it['max_order'] ?>">
                            <button type="submit">Update</button>
                        </form>
                    </div>
                <?php endforeach; ?>

            </div>

            <div class="summary">
                <h2>Order Summary</h2>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>£<?= number_format($total, 2) ?></span>
                </div>

                <div class="summary-row">
                    <span>Collection</span>
                    <span>Free</span>
                </div>

                <hr>

                <div class="summary-row">
                    <strong>Total</strong>
                    <strong>£<?= number_format($total, 2) ?></strong>
                </div>

                <?php if ($items): ?>
                    <a class="checkout" href="<?= app_url('customer/checkout.php') ?>">Go to Checkout</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>

</html>
