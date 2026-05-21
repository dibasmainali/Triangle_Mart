<?php
/**
 * Triangle Mart - Product detail page
 *
 * Single product view with reviews, add to cart, and wishlist.
 * Access: public (purchase actions require CUSTOMER login).
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/product-card.php';

// --- Load single product, shop, reviews, wishlist state ---

$id = $_GET['id'] ?? '';

$p = fetch_one("
    SELECT
        p.product_id,
        p.shop_id,
        p.category_id,
        p.name,
        p.description,
        p.price AS original_price,
        p.price,
        p.quantity_per_item,
        p.stock_available,
        p.min_order,
        p.max_order,
        p.allergy_info,
        p.product_status,
        p.product_image_name,
        s.shop_name,
        c.category_name,
        NVL(d.discount_rate, 0) AS discount_rate,
        ROUND(p.price - (p.price * NVL(d.discount_rate, 0) / 100), 2) AS final_price,
        NVL(rv.avg_rating, 0) AS avg_rating,
        NVL(rv.review_count, 0) AS review_count
    FROM product p
    JOIN shop s ON p.shop_id = s.shop_id
    JOIN category c ON p.category_id = c.category_id
    LEFT JOIN (
        SELECT product_id, MAX(discount_rate) AS discount_rate
        FROM discount
        WHERE discount_status = 'ACTIVE'
        AND start_date <= SYSDATE
        AND end_date >= TRUNC(SYSDATE)
        GROUP BY product_id
    ) d ON p.product_id = d.product_id
    LEFT JOIN (
        SELECT product_id, AVG(rating) AS avg_rating, COUNT(review_id) AS review_count
        FROM review
        WHERE review_status = 'VISIBLE'
        GROUP BY product_id
    ) rv ON p.product_id = rv.product_id
    WHERE p.product_id = :id
", [
    'id' => $id
]);

if (!$p) {
    die('Product not found.');
}

$related = fetch_all("
    SELECT
        p.product_id,
        p.shop_id,
        p.category_id,
        p.name,
        p.description,
        p.price AS original_price,
        p.price,
        p.quantity_per_item,
        p.stock_available,
        p.min_order,
        p.max_order,
        p.allergy_info,
        p.product_status,
        p.product_image_name,
        s.shop_name,
        NVL(d.discount_rate, 0) AS discount_rate,
        ROUND(p.price - (p.price * NVL(d.discount_rate, 0) / 100), 2) AS final_price,
        NVL(rv.avg_rating, 0) AS avg_rating,
        NVL(rv.review_count, 0) AS review_count
    FROM product p
    JOIN shop s ON p.shop_id = s.shop_id
    LEFT JOIN (
        SELECT product_id, MAX(discount_rate) AS discount_rate
        FROM discount
        WHERE discount_status = 'ACTIVE'
        AND start_date <= SYSDATE
        AND end_date >= TRUNC(SYSDATE)
        GROUP BY product_id
    ) d ON p.product_id = d.product_id
    LEFT JOIN (
        SELECT product_id, AVG(rating) AS avg_rating, COUNT(review_id) AS review_count
        FROM review
        WHERE review_status = 'VISIBLE'
        GROUP BY product_id
    ) rv ON p.product_id = rv.product_id
    WHERE p.category_id = :cat
    AND p.product_id <> :id
    AND p.product_status = 'ACTIVE'
    ORDER BY p.name
    FETCH FIRST 4 ROWS ONLY
", [
    'cat' => $p['category_id'],
    'id' => $id
]);

$reviews = fetch_all("
    SELECT
        r.review_id,
        r.rating,
        r.product_comment,
        r.review_date,
        u.first_name,
        u.last_name
    FROM review r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.product_id = :pid
    AND r.review_status = 'VISIBLE'
    ORDER BY r.review_date DESC
", [
    'pid' => $id
]);

$canReview = false;
$reviewOrderId = '';
$u = current_user();

if ($u && $u['user_type'] === 'CUSTOMER') {
    $ordered = fetch_one("
        SELECT o.order_id
        FROM orders o
        JOIN order_item oi ON o.order_id = oi.order_id
        WHERE o.user_id = :uid
        AND oi.product_id = :pid
        AND o.status IN ('PAID','PROCESSING','READY','COLLECTED')
        FETCH FIRST 1 ROWS ONLY
    ", [
        'uid' => $u['user_id'],
        'pid' => $id
    ]);

    if ($ordered) {
        $canReview = true;
        $reviewOrderId = $ordered['order_id'];
    }
}

$originalPrice = (float)$p['original_price'];
$finalPrice = (float)$p['final_price'];
$discountRate = (float)$p['discount_rate'];

// Check if product is in wishlist
$inWishlist = false;
if ($u && $u['user_type'] === 'CUSTOMER') {
    $check = fetch_one("
        SELECT 1 FROM wishlist w 
        JOIN wishlist_item wi ON w.wishlist_id = wi.wishlist_id 
        WHERE w.user_id = :uid AND wi.product_id = :pid
    ", [
        'uid' => $u['user_id'],
        'pid' => $id
    ]);
    $inWishlist = $check !== null;
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($p['name']) ?></title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">

        <section class="product-section">

            <div class="product-image">
                <img
                    src="<?= app_url('actions/product-image.php?id=' . urlencode($p['product_id'])) ?>"
                    alt="<?= e($p['name']) ?>"
                    class="product-detail-img"
                    onerror="this.style.display='none'; this.parentElement.classList.add('product-placeholder'); this.parentElement.innerHTML='<?= strtoupper(substr(e($p['name']), 0, 1)) ?>';">
            </div>

            <div class="product-details">

                <h1><?= e($p['name']) ?></h1>

                <p class="shop">
                    <?= e($p['shop_name']) ?> · <?= e($p['category_name']) ?>
                </p>

                <?php if ($discountRate > 0): ?>
                    <div class="detail-offer-box">
                        <span class="offer-badge" style="position: relative; display: inline-block;">
                            <?= number_format($discountRate, 0) ?>% OFF
                        </span>
                        <div class="offer-prices">
                            <span class="old-price">£<?= number_format($originalPrice, 2) ?></span>
                            <span class="new-price">£<?= number_format($finalPrice, 2) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="price">£<?= number_format($originalPrice, 2) ?></p>
                <?php endif; ?>

                <p>
                    Rating: <?= number_format((float)$p['avg_rating'], 1) ?>/5
                    (<?= (int)$p['review_count'] ?>)
                </p>

                <p class="description">
                    <?= e($p['description']) ?>
                    <br>
                    Stock available: <?= (int)$p['stock_available'] ?>
                </p>

                <!-- SIDE BY SIDE BUTTONS -->
                <!-- SIDE BY SIDE BUTTONS -->
                <div class="product-actions-group">
                    <?php if ($u && $u['user_type'] === 'CUSTOMER'): ?>
                        <!-- Logged in customer -->
                        <?php if ($p['product_status'] === 'ACTIVE' && (int)$p['stock_available'] > 0): ?>
                            <form class="cart-form ajax-cart-detail">
                                <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                                <div class="form-group">
                                    <label>Quantity</label>
                                    <input type="number" name="quantity"
                                        min="<?= (int)$p['min_order'] ?>"
                                        max="<?= min((int)$p['max_order'], (int)$p['stock_available']) ?>"
                                        value="<?= (int)$p['min_order'] ?>">
                                </div>
                                <button class="add-btn" type="submit">
                                    Add to Cart
                                </button>
                                <div class="cart-message" style="display:none;"></div>
                            </form>
                        <?php else: ?>
                            <div class="error" style="flex: 1; text-align: center; margin: 0; display: flex; align-items: center; justify-content: center;">
                                This product is currently unavailable.
                            </div>
                        <?php endif; ?>

                        <form action="<?= app_url('actions/wishlist-add.php') ?>" method="post" class="wishlist-form ajax-wishlist-detail">
                            <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                            <input type="hidden" name="return_url" value="<?= e(app_url('customer/product_detail.php?id=' . $p['product_id'])) ?>">
                            <button class="wishlist-heart-btn" type="submit">
                                <img src="<?= app_url($inWishlist ? 'assets/images/icons8-heart-48.png' : 'assets/images/icons8-favourite-48-nofill.png') ?>" alt="Wishlist">
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- Not logged in - show login prompt -->
                        <?php if ($p['product_status'] === 'ACTIVE' && (int)$p['stock_available'] > 0): ?>
                            <a href="<?= app_url('customer/login.php') ?>" class="add-btn" style="text-decoration: none; text-align: center;">
                                Login to Add to Cart
                            </a>
                        <?php else: ?>
                            <div class="error" style="flex: 1; text-align: center; margin: 0; display: flex; align-items: center; justify-content: center;">
                                This product is currently unavailable.
                            </div>
                        <?php endif; ?>

                        <a href="<?= app_url('customer/login.php') ?>" class="wishlist-heart-btn" style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                            <img src="<?= app_url('assets/images/icons8-favourite-48-nofill.png') ?>" alt="Login to Wishlist">
                        </a>
                    <?php endif; ?>
                </div>

                <p class="allergens">
                    Allergen Information: <?= e($p['allergy_info'] ?: 'None stated') ?>
                </p>

            </div>

        </section>

        <section class="related">
            <h2>Reviews</h2>

            <?php if ($canReview): ?>
                <form action="<?= app_url('actions/submit-review.php') ?>" method="post" class="form-card wide">
                    <input type="hidden" name="product_id" value="<?= e($p['product_id']) ?>">
                    <input type="hidden" name="order_id" value="<?= e($reviewOrderId) ?>">

                    <label>Rating</label>
                    <select name="rating" required>
                        <option value="5">5</option>
                        <option value="4">4</option>
                        <option value="3">3</option>
                        <option value="2">2</option>
                        <option value="1">1</option>
                    </select>

                    <label>Comment</label>
                    <textarea name="comment"></textarea>

                    <button class="btn btn-primary" type="submit">Submit Review</button>
                </form>
            <?php endif; ?>

            <?php foreach ($reviews as $r): ?>
                <div class="summary">
                    <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                    · <?= number_format((float)$r['rating'], 1) ?>/5
                    <p><?= e($r['product_comment']) ?></p>
                </div>
            <?php endforeach; ?>

            <?php if (!$reviews): ?>
                <p>No reviews yet.</p>
            <?php endif; ?>

        </section>

        <section class="related">
            <h2>Related products</h2>
            <div class="product-grid">
                <?php foreach ($related as $r): ?>
                    <?php product_card($r); ?>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

    <script>
        const wishlistIconFilled = '<?= app_url("assets/images/icons8-heart-48.png") ?>';
        const wishlistIconEmpty = '<?= app_url("assets/images/icons8-favourite-48-nofill.png") ?>';

        // AJAX handler for the main add to cart button on product detail page
        document.querySelectorAll('.ajax-cart-detail').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const button = form.querySelector('button');
                const messageDiv = form.querySelector('.cart-message');
                const originalText = button.innerHTML;
                const formData = new FormData(form);

                // Disable button and show loading state
                button.disabled = true;
                button.innerHTML = 'Adding...';

                // Hide previous message
                if (messageDiv) {
                    messageDiv.style.display = 'none';
                    messageDiv.innerHTML = '';
                }

                try {
                    const response = await fetch('<?= app_url("actions/cart-add.php") ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        // Success - update button temporarily
                        button.innerHTML = 'Added ✓';

                        // Show success message
                        if (messageDiv) {
                            messageDiv.style.display = 'block';
                            messageDiv.style.color = 'green';
                            messageDiv.innerHTML = '✓ Product added to your basket!';

                            // Fade out after 3 seconds
                            setTimeout(() => {
                                messageDiv.style.opacity = '0';
                                setTimeout(() => {
                                    messageDiv.style.display = 'none';
                                    messageDiv.style.opacity = '1';
                                }, 500);
                            }, 3000);
                        }
                    } else {
                        const message = await response.text();

                        if (message === 'Cart limit reached') {
                            button.innerHTML = 'Cart Full';
                            if (messageDiv) {
                                messageDiv.style.display = 'block';
                                messageDiv.style.color = 'red';
                                messageDiv.innerHTML = '⚠️ You can only add up to 20 different products to your cart.';
                            }
                            alert('You can only add up to 20 different products to your cart.');
                        } else {
                            button.innerHTML = 'Error';
                            if (messageDiv) {
                                messageDiv.style.display = 'block';
                                messageDiv.style.color = 'red';
                                messageDiv.innerHTML = '❌ ' + message;
                            }
                        }
                    }
                } catch (error) {
                    button.innerHTML = 'Error';
                    if (messageDiv) {
                        messageDiv.style.display = 'block';
                        messageDiv.style.color = 'red';
                        messageDiv.innerHTML = '❌ Network error. Please try again.';
                    }
                }

                // Reset button after 2 seconds
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }, 2000);
            });
        });

        // AJAX handler for wishlist button on product detail page
        document.querySelectorAll('.ajax-wishlist-detail').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const button = form.querySelector('button');
                const img = button.querySelector('img');
                const formData = new FormData(form);
                const productId = formData.get('product_id');

                button.disabled = true;

                try {
                    const response = await fetch('<?= app_url("actions/wishlist-add.php") ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();

                        if (data.inWishlist) {
                            button.classList.add('in-wishlist');
                            img.src = wishlistIconFilled;
                        } else {
                            button.classList.remove('in-wishlist');
                            img.src = wishlistIconEmpty;
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                }

                setTimeout(() => {
                    button.disabled = false;
                }, 500);
            });
        });

        // AJAX handler for cart buttons on related product cards
        document.querySelectorAll('.ajax-cart-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const button = form.querySelector('button');
                const originalText = button.innerText;
                const formData = new FormData(form);

                button.disabled = true;
                button.innerText = 'Adding...';

                try {
                    const response = await fetch('<?= app_url("actions/cart-add.php") ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        button.innerText = 'Added ✓';
                    } else {
                        const message = await response.text();

                        if (message === 'Cart limit reached') {
                            button.innerText = 'Cart Full';
                            alert('You can only add up to 20 different products to your cart.');
                        } else {
                            button.innerText = 'Error';
                        }
                    }
                } catch (error) {
                    button.innerText = 'Error';
                }

                setTimeout(() => {
                    button.disabled = false;
                    button.innerText = originalText;
                }, 1500);
            });
        });

        // AJAX handler for wishlist buttons on related product cards
        document.querySelectorAll('.ajax-wishlist-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const button = this.querySelector('button');
                const img = button.querySelector('img');
                const formData = new FormData(this);
                const productId = formData.get('product_id');

                button.disabled = true;

                try {
                    const response = await fetch('<?= app_url("actions/wishlist-add.php") ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();

                        if (data.inWishlist) {
                            button.classList.add('in-wishlist');
                            img.src = wishlistIconFilled;
                        } else {
                            button.classList.remove('in-wishlist');
                            img.src = wishlistIconEmpty;
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                }

                setTimeout(() => {
                    button.disabled = false;
                }, 1200);
            });
        });
    </script>
</body>

</html>
</body>

</html>
</body>

</html>
