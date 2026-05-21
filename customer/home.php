<?php
/**
 * Triangle Mart - Customer home page
 *
 * Landing page with banner and featured products. Supports AJAX add-to-cart and wishlist.
 * Access: public (cart/wishlist actions require login).
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/product-card.php';

// --- Load featured products (active, with optional discount) ---
$products = fetch_all("
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
        ROUND(p.price - (p.price * NVL(d.discount_rate, 0) / 100), 2) AS final_price
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
    WHERE p.product_status = 'ACTIVE'
    ORDER BY p.created_date DESC
    FETCH FIRST 10 ROWS ONLY
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triangle Mart - Home</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">

        <section class="hero">
            <img src="<?= app_url('assets/images/banner.png') ?>" alt="Triangle Mart banner">
        </section>

        <section class="products">

            <div class="products-header">
                <h2>Featured Products</h2>
                <a href="<?= app_url('customer/products.php') ?>">View all →</a>
            </div>

            <div class="product-grid">

                <?php foreach ($products as $p): ?>
                    <?php product_card($p); ?>
                <?php endforeach; ?>

                <?php if (!$products): ?>
                    <p>No featured products available.</p>
                <?php endif; ?>

            </div>

        </section>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

    <!-- AJAX: add to cart and toggle wishlist without full page reload -->
    <script>
        const wishlistIconFilled = '<?= app_url("assets/images/icons8-heart-48.png") ?>';
        const wishlistIconEmpty = '<?= app_url("assets/images/icons8-favourite-48-nofill.png") ?>';

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

                            alert(
                                'You can only add up to 20 different products to your cart.'
                            );

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
                }, 500);
            });
        });
    </script>

</body>

</html>