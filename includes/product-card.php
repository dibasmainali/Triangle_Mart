<?php
/**
 * Triangle Mart - Product card component
 *
 * Renders a single product card with price, discount badge, cart and wishlist actions.
 * Function: product_card()
 */
function product_card($p)
{
    // Pricing: original vs discounted
    $originalPrice = isset($p['original_price']) ? (float)$p['original_price'] : (float)$p['price'];
    $finalPrice = isset($p['final_price']) ? (float)$p['final_price'] : $originalPrice;
    $discountRate = isset($p['discount_rate']) ? (float)$p['discount_rate'] : 0;
    $stock = isset($p['stock_available']) ? (int)$p['stock_available'] : 0;

    // Wishlist state for logged-in customers only
    $u = current_user();
    $inWishlist = false;

    if ($u && $u['user_type'] === 'CUSTOMER') {
        $check = fetch_one("
            SELECT 1 FROM wishlist w 
            JOIN wishlist_item wi ON w.wishlist_id = wi.wishlist_id 
            WHERE w.user_id = :uid AND wi.product_id = :pid
        ", [
            'uid' => $u['user_id'],
            'pid' => $p['product_id']
        ]);
        $inWishlist = $check !== null;
    }
?>
    <div class="card">

        <?php if ($discountRate > 0): ?>
            <div class="offer-badge">
                <?= number_format($discountRate, 0) ?>% OFF
            </div>
        <?php endif; ?>

        <a href="<?= app_url('customer/product_detail.php?id=' . urlencode($p['product_id'])) ?>">
            <div class="image">
                <img
                    src="<?= app_url('actions/product-image.php?id=' . urlencode($p['product_id'])) ?>"
                    alt="<?= e($p['name']) ?>"
                    class="product-img"
                    onerror="this.style.display='none'; this.parentElement.classList.add('product-placeholder'); this.parentElement.innerHTML='<?= strtoupper(substr(e($p['name']), 0, 1)) ?>';">
            </div>

            <div class="card-info">
                <div class="title-price">
                    <h3><?= e($p['name']) ?></h3>
                </div>

                <p><?= e(substr($p['description'], 0, 90)) ?>...</p>
                <small><?= e($p['shop_name'] ?? '') ?></small>

                <div class="offer-prices">
                    <?php if ($discountRate > 0): ?>
                        <span class="old-price">£<?= number_format($originalPrice, 2) ?></span>
                        <span class="new-price">£<?= number_format($finalPrice, 2) ?></span>
                    <?php else: ?>
                        <span class="normal-price">£<?= number_format($originalPrice, 2) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>

        <?php // Cart and wishlist buttons (customers only) ?>
        <?php if ($u && $u['user_type'] === 'CUSTOMER'): ?>
            <div class="product-card-actions">

                <?php if ($stock > 0 && ($p['product_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                    <form class="ajax-cart-form">
                        <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button class="product-cart-btn" type="submit">
                            Add To Cart
                        </button>
                    </form>
                <?php else: ?>
                    <button class="product-cart-btn" disabled style="opacity:0.5; cursor:not-allowed;">
                        Out of Stock
                    </button>
                <?php endif; ?>

                <form class="ajax-wishlist-form" action="<?= app_url('actions/wishlist-add.php') ?>" method="post">
                    <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                    <button class="product-wishlist-btn" type="submit">
                        <img src="<?= app_url($inWishlist ? 'assets/images/icons8-heart-48.png' : 'assets/images/icons8-favourite-48-nofill.png') ?>" alt="Wishlist">
                    </button>
                </form>

            </div>
        <?php endif; ?>

    </div>
<?php } ?>
