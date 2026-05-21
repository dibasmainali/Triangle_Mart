<?php
/**
 * Triangle Mart - Shop listing
 *
 * Browse all trader shops and filter products by shop.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';

// --- Active shops with product counts ---
$shops = fetch_all("
    SELECT
        s.shop_id,
        s.user_id,
        s.shop_name,
        s.shop_address,
        s.shop_status,
        u.first_name,
        u.last_name,
        COUNT(p.product_id) product_count
    FROM shop s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.shop_id = p.shop_id AND p.product_status = 'ACTIVE'
    WHERE s.shop_status = 'ACTIVE'
    GROUP BY s.shop_id, s.user_id, s.shop_name, s.shop_address, s.shop_status, u.first_name, u.last_name
    ORDER BY s.shop_name
");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shops</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">
        <h1 class="page-title">Browse by Shop</h1>
        <div class="product-grid">
            <?php foreach ($shops as $s): ?>
                <div class="card">
                    <a href="<?= app_url('customer/products.php?shop=' . urlencode($s['shop_id'])) ?>">
                        <div class="card-info">
                            <h3><?= e($s['shop_name']) ?></h3>
                            <p><?= e($s['shop_address']) ?></p>
                            <small><?= (int)$s['product_count'] ?> products</small>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>

</html>
