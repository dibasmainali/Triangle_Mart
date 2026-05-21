<?php
/**
 * Triangle Mart - Wishlist
 *
 * Saved products for later; add to cart or remove.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Load wishlist products for current customer ---

$user = current_user();

$wishlist = fetch_one("
    SELECT wishlist_id
    FROM wishlist
    WHERE user_id = :uid
", ['uid' => $user['user_id']]);

if (!$wishlist) {

    $wid = next_id('wishlist', 'wishlist_id', 'W');

    execute_sql("
        INSERT INTO wishlist(wishlist_id, user_id)
        VALUES(:wid, :uid)
    ", [
        'wid' => $wid,
        'uid' => $user['user_id']
    ]);

} else {

    $wid = $wishlist['wishlist_id'];
}

$products = fetch_all("
    SELECT
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.stock_available,
        p.product_status,
        p.product_image_name,
        s.shop_name
    FROM wishlist_item wi
    JOIN product p ON wi.product_id = p.product_id
    JOIN shop s ON p.shop_id = s.shop_id
    WHERE wi.wishlist_id = :wid
    ORDER BY wi.date_added DESC
", ['wid' => $wid]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Wishlist</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

<?php include dirname(__DIR__) . '/includes/header.php'; ?>
<?php include dirname(__DIR__) . '/includes/search.php'; ?>

<main class="page-content">

    <h1 class="page-title">My Wishlist</h1>

    <?php if (!$products): ?>

        <div class="summary">
            <p>Your wishlist is empty.</p>

            <a class="checkout" href="<?= app_url('customer/products.php') ?>">
                Browse Products
            </a>
        </div>

    <?php else: ?>

        <div class="product-grid">

            <?php foreach ($products as $p): ?>

                <div class="card">

                    <a href="<?= app_url('customer/product_detail.php?id=' . urlencode($p['product_id'])) ?>">

                        <div class="image">

                            <img
                                src="<?= app_url('actions/product-image.php?id=' . urlencode($p['product_id'])) ?>"
                                alt="<?= e($p['name']) ?>"
                                class="product-img"
                                onerror="
                                    this.style.display='none';
                                    this.parentElement.classList.add('product-placeholder');
                                    this.parentElement.innerHTML='<?= strtoupper(substr(e($p['name']),0,1)) ?>';
                                "
                            >

                        </div>

                        <div class="card-info">

                            <div class="title-price">
                                <h3><?= e($p['name']) ?></h3>
                            </div>

                            <p>
                                <?= e(substr($p['description'], 0, 90)) ?>
                            </p>

                            <small>
                                <?= e($p['shop_name']) ?>
                            </small>

                            <div class="offer-prices">

                                <span class="normal-price">
                                    £<?= number_format((float)$p['price'], 2) ?>
                                </span>

                            </div>

                        </div>

                    </a>

                    <div class="product-card-actions">

                        <?php if (
                            (int)$p['stock_available'] > 0 &&
                            $p['product_status'] === 'ACTIVE'
                        ): ?>

                            <form action="<?= app_url('actions/cart-add.php') ?>" method="post">

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= e($p['product_id']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="quantity"
                                    value="1"
                                >

                                <button
                                    class="product-cart-btn"
                                    type="submit"
                                >
                                    Add to Cart
                                </button>

                            </form>

                        <?php endif; ?>

                        <form action="<?= app_url('actions/wishlist-remove.php') ?>" method="post">

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= e($p['product_id']) ?>"
                            >

                            <button
                                class="product-wishlist-page-btn"
                                type="submit"
                            >
                                Remove
                            </button>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>
