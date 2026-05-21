<?php
/**
 * Triangle Mart - Product listing
 *
 * Browse and search products with filters for category, shop, and keyword.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/product-card.php';

// --- Build product list query (search / category / shop filters) ---

$q = trim($_GET['q'] ?? '');
$shop = $_GET['shop'] ?? '';
$cat = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'name';

$sql = "
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
    WHERE p.product_status = 'ACTIVE'
";

$binds = [];

if ($q !== '') {

    $sql .= "
        AND (
            LOWER(p.name) LIKE LOWER(:q)
            OR LOWER(p.description) LIKE LOWER(:q)
        )
    ";

    $binds['q'] = "%$q%";
}

if ($shop !== '') {

    $sql .= " AND p.shop_id = :shop";
    $binds['shop'] = $shop;
}

if ($cat !== '') {

    $sql .= " AND p.category_id = :cat";
    $binds['cat'] = $cat;
}

if ($sort === 'price_low') {

    $sql .= " ORDER BY final_price ASC";
} elseif ($sort === 'price_high') {

    $sql .= " ORDER BY final_price DESC";
} elseif ($sort === 'rating') {

    $sql .= " ORDER BY avg_rating DESC, p.name";
} else {

    $sql .= " ORDER BY p.name";
}

$products = fetch_all($sql, $binds);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">

        <h1 class="page-title">Products</h1>

        <form method="get" class="filters" style="margin-bottom:20px">

            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="shop" value="<?= e($shop) ?>">
            <input type="hidden" name="category" value="<?= e($cat) ?>">

            <select name="sort" onchange="this.form.submit()">

                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>
                    Name
                </option>

                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>
                    Price low to high
                </option>

                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>
                    Price high to low
                </option>

                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>
                    Rating
                </option>

            </select>

        </form>

        <div class="product-grid">

            <?php foreach ($products as $p): ?>
                <?php product_card($p); ?>
            <?php endforeach; ?>

            <?php if (!$products): ?>
                <p>No products found.</p>
            <?php endif; ?>

        </div>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

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