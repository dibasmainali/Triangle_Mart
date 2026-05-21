<?php
/**
 * Triangle Mart - Checkout
 *
 * Select collection slot and place order (redirects to PayPal payment).
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Checkout: validate cart and collection slot ---

$user = current_user();

$items = fetch_all("
    SELECT
        bi.product_id,
        bi.quantity,
        p.name,
        p.price AS original_price,
        p.shop_id,
        s.shop_name,
        NVL(d.discount_rate, 0) AS discount_rate,
        ROUND(
            p.price - (p.price * NVL(d.discount_rate, 0) / 100),
            2
        ) AS final_price,
        (
            bi.quantity *
            ROUND(
                p.price - (p.price * NVL(d.discount_rate, 0) / 100),
                2
            )
        ) AS line_total
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

if (!$items) {
    redirect_to('customer/cart.php');
    exit;
}

$total = 0;

foreach ($items as $it) {
    $total += (float)$it['line_total'];
}

$slots = fetch_all("
    SELECT
        collection_slot_id,
        TO_CHAR(collection_date, 'YYYY-MM-DD') collection_date,
        TO_CHAR(collection_date, 'Day') day_name,
        time_range,
        max_orders,
        current_orders
    FROM collection_slot
    WHERE collection_date >= TRUNC(SYSDATE + 1)
    AND TO_CHAR(collection_date, 'DY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
    AND time_range IN ('10-13','13-16','16-19')
    AND current_orders < max_orders
    ORDER BY collection_date, time_range
");
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">
        <h1 class="page-title">Select a Collection Slot</h1>

        <form action="<?= app_url('actions/place-order.php') ?>" method="post" class="container">

            <div class="slot-box">
                <h2>Available collection slots</h2>

                <?php foreach ($slots as $s): ?>
                    <label class="slot-radio">
                        <input
                            type="radio"
                            name="slot_id"
                            required
                            value="<?= e($s['collection_slot_id']) ?>">

                        <?= e(trim($s['day_name'])) ?>,
                        <?= e($s['collection_date']) ?>
                        · <?= e($s['time_range']) ?>
                        · <?= (int)$s['max_orders'] - (int)$s['current_orders'] ?> spaces left
                    </label>
                <?php endforeach; ?>

                <?php if (!$slots): ?>
                    <p>No available slots. Collection slots are only Wed, Thu, Fri and must be at least 24 hours after ordering.</p>
                <?php endif; ?>
            </div>

            <div class="summary">
                <h3>Order Summary</h3>

                <?php foreach ($items as $it): ?>
                    <div class="summary-row">
                        <span>
                            <?= e($it['name']) ?> x <?= (int)$it['quantity'] ?>

                            <?php if ((float)$it['discount_rate'] > 0): ?>
                                <small>
                                    <?= number_format((float)$it['discount_rate'], 0) ?>% off
                                </small>
                            <?php endif; ?>
                        </span>

                        <span>
                            £<?= number_format((float)$it['line_total'], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <hr>

                <div class="summary-row">
                    <span>Items</span>
                    <span><?= count($items) ?></span>
                </div>

                <div class="summary-row total">
                    <strong>Order Total</strong>
                    <strong>£<?= number_format($total, 2) ?></strong>
                </div>

                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="PAYPAL">PayPal</option>
                </select>

                <button class="pay-btn" type="submit">Place Order & Pay</button>
            </div>

        </form>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>

</html>