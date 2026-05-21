<?php
/**
 * Triangle Mart - Customer profile and orders
 *
 * View account details and order history; cancel pending orders.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';

require_login(['CUSTOMER']);

// --- Profile and order history ---

$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    execute_sql(
        'UPDATE users
         SET first_name = :fn,
             last_name = :ln,
             phone = :ph,
             address = :addr
         WHERE user_id = :uid',
        [
            'fn'   => $_POST['first_name'],
            'ln'   => $_POST['last_name'],
            'ph'   => $_POST['phone'],
            'addr' => $_POST['address'],
            'uid'  => $u['user_id']
        ]
    );

    refresh_current_user();

    redirect_to('customer/customer-profile.php');
    exit;
}

$orders = fetch_all("
    SELECT
        o.*,
        COUNT(oi.product_id) item_count
    FROM orders o
    LEFT JOIN order_item oi
        ON o.order_id = oi.order_id
    WHERE o.user_id = :uid
    GROUP BY
        o.order_id,
        o.user_id,
        o.basket_id,
        o.payment_id,
        o.collection_slot_id,
        o.order_date,
        o.total_amount,
        o.status
    ORDER BY o.order_date DESC
", [
    'uid' => $u['user_id']
]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>

    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content">

        <h1 class="page-title">My Profile</h1>

        <div class="profile-box">

            <div class="profile-top">
                <div class="avatar"></div>

                <div>
                    <h3><?= e($u['first_name'] . ' ' . $u['last_name']) ?></h3>
                    <small>Customer ID: <?= e($u['user_id']) ?></small>
                </div>
            </div>

            <form method="post" class="profile-form">

                <input
                    name="first_name"
                    value="<?= e($u['first_name']) ?>"
                    placeholder="First name">

                <input
                    name="last_name"
                    value="<?= e($u['last_name']) ?>"
                    placeholder="Last name">

                <input
                    name="phone"
                    value="<?= e($u['phone']) ?>"
                    placeholder="Phone">

                <input
                    name="address"
                    value="<?= e($u['address']) ?>"
                    placeholder="Address">

                <button class="btn btn-primary">
                    Save Profile
                </button>

            </form>

        </div>

        <div class="history">

            <h2>Order history</h2>

            <?php foreach ($orders as $o): ?>

                <div class="order">

                    <div>
                        <strong>
                            #<?= e($o['order_id']) ?>
                        </strong>

                        <br>

                        <small>
                            <?= e($o['order_date']) ?>
                            ·
                            <?= (int)$o['item_count'] ?> items
                        </small>

                        <br>

                        <span class="status processing">
                            <?= e($o['status']) ?>
                        </span>
                    </div>

                    <div>
                        £<?= number_format((float)$o['total_amount'], 2) ?>
                    </div>

                    <div class="order-actions">

                        <?php if (
                            in_array(
                                $o['status'],
                                ['PAID', 'PROCESSING', 'READY', 'COLLECTED'],
                                true
                            )
                        ): ?>

                            <a
                                class="checkout small"
                                href="<?= app_url('customer/invoice.php?id=' . urlencode($o['order_id'])) ?>">
                                Invoice
                            </a>

                        <?php endif; ?>

                        <?php if ($o['status'] === 'PENDING'): ?>

                            <form action="<?= app_url('actions/cancel-order.php') ?>" method="post">

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?= e($o['order_id']) ?>">

                                <button class="btn btn-danger">
                                    Cancel
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

</body>

</html>