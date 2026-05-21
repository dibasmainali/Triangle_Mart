<?php
/**
 * Triangle Mart - Manage products
 *
 * Add, edit, remove, and upload images for shop products.
 * Access: TRADER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['TRADER']);

// --- Product CRUD for trader's shop ---

$u = current_user();

$shop = fetch_one("
    SELECT shop_id, shop_name, shop_address, shop_status
    FROM shop
    WHERE user_id = :uid
", [
    'uid' => $u['user_id']
]);

if (!$shop) {
    die("No shop assigned to this trader.");
}

$cats = fetch_all("
    SELECT category_id, category_name
    FROM category
    ORDER BY category_name
");

$editProduct = null;
$message = '';

function save_product_image($productId, $shopId, $file)
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Image upload failed.');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($file['type'], $allowedTypes, true)) {
        die('Only JPG, PNG and WEBP images are allowed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        die('Image must be under 2MB.');
    }

    $imageData = file_get_contents($file['tmp_name']);

    $conn = db_connect();

    $sql = "
        UPDATE product
        SET
            product_image = EMPTY_BLOB(),
            product_image_name = :img_name,
            product_image_mime = :img_mime,
            product_image_date = SYSDATE,
            updated_date = SYSDATE
        WHERE product_id = :pid
        AND shop_id = :sid
        RETURNING product_image INTO :img_blob
    ";

    $stmt = oci_parse($conn, $sql);

    if (!$stmt) {
        $e = oci_error($conn);
        die('Image SQL parse error: ' . htmlentities($e['message']));
    }

    $blob = oci_new_descriptor($conn, OCI_D_LOB);

    oci_bind_by_name($stmt, ':img_name', $file['name']);
    oci_bind_by_name($stmt, ':img_mime', $file['type']);
    oci_bind_by_name($stmt, ':pid', $productId);
    oci_bind_by_name($stmt, ':sid', $shopId);
    oci_bind_by_name($stmt, ':img_blob', $blob, -1, OCI_B_BLOB);

    $ok = oci_execute($stmt, OCI_NO_AUTO_COMMIT);

    if (!$ok) {
        $e = oci_error($stmt);
        oci_rollback($conn);
        die('Image SQL error: ' . htmlentities($e['message']));
    }

    if (!$blob->save($imageData)) {
        oci_rollback($conn);
        die('Could not save product image.');
    }

    oci_commit($conn);

    $blob->free();
    oci_free_statement($stmt);
    oci_close($conn);
}

if (isset($_GET['edit'])) {
    $editProduct = fetch_one("
        SELECT 
            product_id,
            category_id,
            name,
            description,
            price,
            quantity_per_item,
            stock_available,
            min_order,
            max_order,
            allergy_info,
            product_status,
            product_image_name
        FROM product
        WHERE product_id = :pid
        AND shop_id = :sid
    ", [
        'pid' => $_GET['edit'],
        'sid' => $shop['shop_id']
    ]);
}

if (isset($_GET['remove'])) {
    execute_sql("
        UPDATE product
        SET product_status = 'REMOVED',
            updated_date = SYSDATE
        WHERE product_id = :pid
        AND shop_id = :sid
    ", [
        'pid' => $_GET['remove'],
        'sid' => $shop['shop_id']
    ]);

    redirect_to('trader/products.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = trim($_POST['product_id'] ?? '');

    if ($pid === '') {
        $pid = next_id('product', 'product_id', 'PR');
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $quantityPerItem = (int)($_POST['quantity_per_item'] ?? 1);
    $stock = (int)($_POST['stock_available'] ?? 0);
    $minOrder = (int)($_POST['min_order'] ?? 1);
    $maxOrder = (int)($_POST['max_order'] ?? 10);
    $allergyInfo = trim($_POST['allergy_info'] ?? '');
    $status = $_POST['product_status'] ?? 'ACTIVE';

    if ($name === '' || $description === '' || $categoryId === '') {
        $message = 'Please fill in all required product fields.';
    } elseif ($price < 0) {
        $message = 'Price cannot be negative.';
    } elseif ($quantityPerItem <= 0) {
        $message = 'Quantity per item must be greater than zero.';
    } elseif ($stock < 0) {
        $message = 'Stock cannot be negative.';
    } elseif ($minOrder < 1) {
        $message = 'Minimum order must be at least 1.';
    } elseif ($maxOrder < $minOrder) {
        $message = 'Maximum order cannot be lower than minimum order.';
    } elseif (!in_array($status, ['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK'], true)) {
        $message = 'Invalid product status.';
    } else {
        $existing = fetch_one("
            SELECT product_id
            FROM product
            WHERE product_id = :pid
            AND shop_id = :sid
        ", [
            'pid' => $pid,
            'sid' => $shop['shop_id']
        ]);

        if ($existing) {
            execute_sql("
                UPDATE product
                SET category_id = :cat,
                    name = :name,
                    description = :descr,
                    price = :price,
                    quantity_per_item = :qpi,
                    stock_available = :stock,
                    min_order = :min_order,
                    max_order = :max_order,
                    allergy_info = :allergy,
                    product_status = :status,
                    updated_date = SYSDATE
                WHERE product_id = :pid
                AND shop_id = :sid
            ", [
                'cat' => $categoryId,
                'name' => $name,
                'descr' => $description,
                'price' => $price,
                'qpi' => $quantityPerItem,
                'stock' => $stock,
                'min_order' => $minOrder,
                'max_order' => $maxOrder,
                'allergy' => $allergyInfo,
                'status' => $status,
                'pid' => $pid,
                'sid' => $shop['shop_id']
            ]);
        } else {
            execute_sql("
                INSERT INTO product
                (
                    product_id,
                    shop_id,
                    category_id,
                    name,
                    description,
                    price,
                    quantity_per_item,
                    stock_available,
                    min_order,
                    max_order,
                    allergy_info,
                    product_status
                )
                VALUES
                (
                    :pid,
                    :sid,
                    :cat,
                    :name,
                    :descr,
                    :price,
                    :qpi,
                    :stock,
                    :min_order,
                    :max_order,
                    :allergy,
                    :status
                )
            ", [
                'pid' => $pid,
                'sid' => $shop['shop_id'],
                'cat' => $categoryId,
                'name' => $name,
                'descr' => $description,
                'price' => $price,
                'qpi' => $quantityPerItem,
                'stock' => $stock,
                'min_order' => $minOrder,
                'max_order' => $maxOrder,
                'allergy' => $allergyInfo,
                'status' => $status
            ]);
        }

        save_product_image(
            $pid,
            $shop['shop_id'],
            $_FILES['product_image'] ?? null
        );

        redirect_to('trader/products.php');
        exit;
    }
}

$products = fetch_all("
    SELECT 
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.stock_available,
        p.min_order,
        p.max_order,
        p.product_status,
        p.product_image_name,
        c.category_name
    FROM product p
    JOIN category c ON p.category_id = c.category_id
    WHERE p.shop_id = :sid
    AND p.product_status <> 'REMOVED'
    ORDER BY p.name
", [
    'sid' => $shop['shop_id']
]);

$totalProducts = count($products);
$activeProducts = 0;
$lowStockProducts = 0;

foreach ($products as $p) {
    if ($p['product_status'] === 'ACTIVE') {
        $activeProducts++;
    }

    if ((int)$p['stock_available'] <= 5) {
        $lowStockProducts++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Products - Triangle Mart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/trader-header.php'; ?>

    <main class="page-content">

        <div class="products-header">
            <div>
                <h1 class="page-title">Manage Products</h1>
                <p><?= e($shop['shop_name']) ?> · <?= e($shop['shop_status']) ?></p>
            </div>

            <?php if ($editProduct): ?>
                <a class="checkout" style="max-width:180px;" href="<?= app_url('trader/products.php') ?>">
                    + New Product
                </a>
            <?php endif; ?>
        </div>

        <div class="trader-summary-grid">
            <div class="trader-summary-card">
                <h2><?= (int)$totalProducts ?></h2>
                <p>Total Products</p>
            </div>

            <div class="trader-summary-card">
                <h2><?= (int)$activeProducts ?></h2>
                <p>Active Products</p>
            </div>

            <div class="trader-summary-card">
                <h2><?= (int)$lowStockProducts ?></h2>
                <p>Low Stock Items</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="error"><?= e($message) ?></div>
        <?php endif; ?>

        <section class="trader-panel-box">
            <h2><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h2>

            <form method="post" class="trader-form" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= e($editProduct['product_id'] ?? '') ?>">

                <div class="trader-form-grid">
                    <div>
                        <label>Product Name</label>
                        <input
                            name="name"
                            required
                            value="<?= e($editProduct['name'] ?? '') ?>">
                    </div>

                    <div>
                        <label>Category</label>
                        <select name="category_id" required>
                            <option value="">Select category</option>

                            <?php foreach ($cats as $c): ?>
                                <option
                                    value="<?= e($c['category_id']) ?>"
                                    <?= (($editProduct['category_id'] ?? '') === $c['category_id']) ? 'selected' : '' ?>>
                                    <?= e($c['category_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>
                </div>

                <label>Description</label>
                <textarea name="description" required><?= e($editProduct['description'] ?? '') ?></textarea>

                <div class="trader-form-grid">

                    <div>
                        <label>Price (£)</label>
                        <input
                            name="price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            value="<?= e($editProduct['price'] ?? '') ?>">
                    </div>

                    <div>
                        <label>Quantity Per Item</label>
                        <input
                            name="quantity_per_item"
                            type="number"
                            min="1"
                            value="<?= e($editProduct['quantity_per_item'] ?? 1) ?>">
                    </div>

                    <div>
                        <label>Stock Available</label>
                        <input
                            name="stock_available"
                            type="number"
                            min="0"
                            value="<?= e($editProduct['stock_available'] ?? 0) ?>">
                    </div>

                    <div>
                        <label>Product Status</label>
                        <select name="product_status">
                            <?php foreach (['ACTIVE', 'INACTIVE', 'OUT_OF_STOCK'] as $status): ?>
                                <option
                                    value="<?= e($status) ?>"
                                    <?= (($editProduct['product_status'] ?? 'ACTIVE') === $status) ? 'selected' : '' ?>>
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Minimum Order</label>
                        <input
                            name="min_order"
                            type="number"
                            min="1"
                            value="<?= e($editProduct['min_order'] ?? 1) ?>">
                    </div>

                    <div>
                        <label>Maximum Order</label>
                        <input
                            name="max_order"
                            type="number"
                            min="1"
                            value="<?= e($editProduct['max_order'] ?? 10) ?>">
                    </div>

                </div>

                <label>Allergy Information</label>
                <input
                    name="allergy_info"
                    value="<?= e($editProduct['allergy_info'] ?? '') ?>"
                    placeholder="Example: Gluten, nuts, dairy">

                <label>Product Image</label>
                <input
                    type="file"
                    name="product_image"
                    accept="image/jpeg,image/png,image/webp">

                <?php if (!empty($editProduct['product_image_name'])): ?>
                    <p style="margin-top:8px;">
                        Current image:
                        <strong><?= e($editProduct['product_image_name']) ?></strong>
                    </p>

                    <div style="margin-top:12px; max-width:220px;">
                        <img
                            src="<?= app_url('actions/product-image.php?id=' . urlencode($editProduct['product_id'])) ?>"
                            alt="<?= e($editProduct['name']) ?>"
                            style="
                            width:100%;
                            height:220px;
                            object-fit:cover;
                            border-radius:16px;
                            border:2px solid #e7e7ee;
                            background:#ddd;
                        ">
                    </div>
                <?php endif; ?>

                <button class="checkout" style="max-width:220px;" type="submit">
                    <?= $editProduct ? 'Update Product' : 'Create Product' ?>
                </button>

            </form>
        </section>

        <section class="trader-panel-box">

            <div class="products-header">
                <h2>My Products</h2>
                <small><?= (int)$totalProducts ?> products in your shop</small>
            </div>

            <table class="data-table">
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="width:80px;">
                            <img
                                src="<?= app_url('actions/product-image.php?id=' . urlencode($p['product_id'])) ?>"
                                alt="<?= e($p['name']) ?>"
                                style="
                                width:60px;
                                height:60px;
                                object-fit:cover;
                                border-radius:10px;
                                background:#ddd;
                            "
                                onerror="this.style.display='none';">
                        </td>

                        <td>
                            <strong><?= e($p['name']) ?></strong><br>
                            <small><?= e(substr($p['description'], 0, 65)) ?>...</small>
                        </td>

                        <td><?= e($p['category_name']) ?></td>
                        <td><?= (int)$p['stock_available'] ?></td>
                        <td>£<?= number_format((float)$p['price'], 2) ?></td>
                        <td><?= (int)$p['min_order'] ?></td>
                        <td><?= (int)$p['max_order'] ?></td>
                        <td><?= e($p['product_status']) ?></td>

                        <td>
                            <a
                                class="trader-action-link"
                                href="<?= app_url('trader/products.php?edit=' . urlencode($p['product_id'])) ?>">
                                Edit
                            </a>
                            |
                            <a
                                class="trader-action-link"
                                href="<?= app_url('trader/products.php?remove=' . urlencode($p['product_id'])) ?>"
                                onclick="return confirm('Remove this product?')">
                                Remove
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$products): ?>
                    <tr>
                        <td colspan="9">No products added yet.</td>
                    </tr>
                <?php endif; ?>

            </table>

        </section>

    </main>

    <?php include dirname(__DIR__) . '/includes/trader-footer.php'; ?>

</body>

</html>
