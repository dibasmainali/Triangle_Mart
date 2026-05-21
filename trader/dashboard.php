<?php
/**
 * Triangle Mart - Trader dashboard
 * Overview of shop stats, products, and recent orders.
 * Access: TRADER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['TRADER']);

// --- Dashboard stats for trader's shop ---

$u = current_user();

$shop = fetch_one("
    SELECT shop_id, shop_name, shop_address, shop_status
    FROM shop
    WHERE user_id = :uid
", ['uid' => $u['user_id']]);

if (!$shop) {
    die("No shop assigned to this trader.");
}

$productCount = fetch_one("
    SELECT COUNT(*) total
    FROM product
    WHERE shop_id = :sid
    AND product_status <> 'REMOVED'
", ['sid' => $shop['shop_id']]);

$todayOrders = fetch_one("
    SELECT COUNT(DISTINCT o.order_id) total
    FROM order_item oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE oi.shop_id = :sid
    AND TRUNC(o.order_date) = TRUNC(SYSDATE)
", ['sid' => $shop['shop_id']]);

$revenue = fetch_one("
    SELECT NVL(SUM(oi.line_total),0) total
    FROM order_item oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE oi.shop_id = :sid
    AND o.status IN ('PAID','PROCESSING','READY','COLLECTED')
", ['sid' => $shop['shop_id']]);

$products = fetch_all("
    SELECT product_id, name, price, stock_available, product_status
    FROM product
    WHERE shop_id = :sid
    AND product_status <> 'REMOVED'
    ORDER BY name
    FETCH FIRST 5 ROWS ONLY
", ['sid' => $shop['shop_id']]);

$orders = fetch_all("
    SELECT 
        o.order_id,
        p.name,
        oi.quantity,
        cs.time_range,
        TO_CHAR(cs.collection_date, 'DD Mon YYYY') collection_date
    FROM order_item oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN product p ON oi.product_id = p.product_id
    JOIN collection_slot cs ON o.collection_slot_id = cs.collection_slot_id
    WHERE oi.shop_id = :sid
    AND o.status IN ('PAID','PROCESSING','READY')
    ORDER BY cs.collection_date, cs.time_range
    FETCH FIRST 6 ROWS ONLY
", ['sid' => $shop['shop_id']]);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Trader Dashboard</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/trader-header.php'; ?>

    <main class="page-content">
        <div class="products-header">
            <div>
                <h1 class="page-title">Trader Dashboard</h1>
                <p><?= e($shop['shop_name']) ?> · <?= e($shop['shop_status']) ?></p>
            </div>

            <a class="checkout" style="max-width:180px;" href="<?= app_url('trader/products.php') ?>">+ Add Product</a>
        </div>

        <div class="trader-summary-grid">
            <div class="trader-summary-card">
                <h2><?= (int)$productCount['total'] ?></h2>
                <p>Products Listed</p>
            </div>

            <div class="trader-summary-card">
                <h2><?= (int)$todayOrders['total'] ?></h2>
                <p>Today’s Orders</p>
            </div>

            <div class="trader-summary-card">
                <h2>£<?= number_format((float)$revenue['total'], 2) ?></h2>
                <p>Total Revenue</p>
            </div>
        </div>

        <section class="trader-panel-box">
            <h2>Recent Products</h2>

            <table class="data-table">
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                </tr>

                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td>£<?= number_format((float)$p['price'], 2) ?></td>
                        <td><?= (int)$p['stock_available'] ?></td>
                        <td><?= e($p['product_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>

        <section class="trader-panel-box">
            <h2>Current Orders</h2>

            <table class="data-table">
                <tr>
                    <th>Order</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Collection Slot</th>
                </tr>

                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e($o['order_id']) ?></td>
                        <td><?= e($o['name']) ?></td>
                        <td><?= (int)$o['quantity'] ?></td>
                        <td><?= e($o['collection_date'] . ' ' . $o['time_range']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>
    </main>

    <?php include dirname(__DIR__) . '/includes/trader-footer.php'; ?>
</body>

</html>