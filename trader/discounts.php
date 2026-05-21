<?php
/**
 * Triangle Mart - Manage discounts
 * Create and cancel product discount offers.
 * Access: TRADER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['TRADER']);

// --- Discount management ---

$u = current_user();
$message = '';

$shop = fetch_one("
    SELECT shop_id, shop_name
    FROM shop
    WHERE user_id = :uid
", ['uid' => $u['user_id']]);

if (!$shop) {
    die("No shop assigned to this trader.");
}

$products = fetch_all("
    SELECT product_id, name, price
    FROM product
    WHERE shop_id = :sid
    AND product_status = 'ACTIVE'
    ORDER BY name
", ['sid' => $shop['shop_id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = $_POST['product_id'] ?? '';
    $rate = (float)($_POST['discount_rate'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';

    $validProduct = fetch_one("
        SELECT p.product_id
        FROM product p
        JOIN shop s ON p.shop_id = s.shop_id
        WHERE p.product_id = :pid
        AND s.user_id = :uid
    ", [
        'pid' => $productId,
        'uid' => $u['user_id']
    ]);

    if (!$validProduct) {
        $message = "Invalid product selected.";
    } elseif ($rate <= 0 || $rate > 100) {
        $message = "Discount rate must be between 1 and 100.";
    } elseif ($start === '' || $end === '') {
        $message = "Please select start and end dates.";
    } else {
        execute_sql("
            INSERT INTO discount
            (
                discount_id,
                product_id,
                created_by,
                discount_rate,
                start_date,
                end_date,
                discount_status
            )
            VALUES
            (
                :did,
                :pid,
                :uid,
                :rate,
                TO_DATE(:start_date, 'YYYY-MM-DD'),
                TO_DATE(:end_date, 'YYYY-MM-DD'),
                'ACTIVE'
            )
        ", [
            'did' => next_id('discount', 'discount_id', 'D'),
            'pid' => $productId,
            'uid' => $u['user_id'],
            'rate' => $rate,
            'start_date' => $start,
            'end_date' => $end
        ]);

        redirect_to('trader/discounts.php');
        exit;
    }
}

if (isset($_GET['cancel'])) {
    execute_sql("
        UPDATE discount d
        SET discount_status = 'CANCELLED'
        WHERE d.discount_id = :did
        AND d.created_by = :uid
    ", [
        'did' => $_GET['cancel'],
        'uid' => $u['user_id']
    ]);

    redirect_to('trader/discounts.php');
    exit;
}

$discounts = fetch_all("
    SELECT
        d.discount_id,
        d.discount_rate,
        TO_CHAR(d.start_date, 'YYYY-MM-DD') AS start_date,
        TO_CHAR(d.end_date, 'YYYY-MM-DD') AS end_date,
        d.discount_status,
        p.name,
        p.price
    FROM discount d
    JOIN product p ON d.product_id = p.product_id
    JOIN shop s ON p.shop_id = s.shop_id
    WHERE s.user_id = :uid
    ORDER BY d.start_date DESC
", ['uid' => $u['user_id']]);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Manage Discounts</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/trader-header.php'; ?>

    <main class="page-content">

        <h1 class="page-title">Manage Discounts</h1>
        <p><?= e($shop['shop_name']) ?></p>

        <?php if ($message): ?>
            <div class="error"><?= e($message) ?></div>
        <?php endif; ?>

        <section class="trader-panel-box">
            <h2>Create Discount</h2>

            <form method="post" class="trader-form">

                <label>Product</label>
                <select name="product_id" required>
                    <option value="">Select product</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= e($p['product_id']) ?>">
                            <?= e($p['name']) ?> — £<?= number_format((float)$p['price'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="trader-form-grid">
                    <div>
                        <label>Discount Rate (%)</label>
                        <input type="number" name="discount_rate" min="1" max="100" step="0.01" required>
                    </div>

                    <div>
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>

                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>

                <button class="checkout" style="max-width:220px;" type="submit">
                    Create Discount
                </button>
            </form>
        </section>

        <section class="trader-panel-box">
            <h2>My Discounts</h2>

            <table class="data-table">
                <tr>
                    <th>Product</th>
                    <th>Original Price</th>
                    <th>Discount</th>
                    <th>New Price</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($discounts as $d): ?>
                    <?php
                    $price = (float)$d['price'];
                    $rate = (float)$d['discount_rate'];
                    $newPrice = $price - ($price * $rate / 100);
                    ?>
                    <tr>
                        <td><?= e($d['name']) ?></td>
                        <td>£<?= number_format($price, 2) ?></td>
                        <td><?= number_format($rate, 2) ?>%</td>
                        <td>£<?= number_format($newPrice, 2) ?></td>
                        <td><?= e($d['start_date']) ?></td>
                        <td><?= e($d['end_date']) ?></td>
                        <td><?= e($d['discount_status']) ?></td>
                        <td>
                            <?php if ($d['discount_status'] === 'ACTIVE'): ?>
                                <a class="trader-action-link"
                                    href="<?= app_url('trader/discounts.php?cancel=' . urlencode($d['discount_id'])) ?>"
                                    onclick="return confirm('Cancel this discount?')">
                                    Cancel
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$discounts): ?>
                    <tr>
                        <td colspan="8">No discounts created yet.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </section>

    </main>

    <?php include dirname(__DIR__) . '/includes/trader-footer.php'; ?>

</body>

</html>