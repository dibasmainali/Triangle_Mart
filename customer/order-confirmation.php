<?php require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);
$user = current_user();
$id = $_GET['id'] ?? '';
$o = fetch_one("SELECT o.*, cs.collection_date, cs.time_range FROM orders o JOIN collection_slot cs ON o.collection_slot_id=cs.collection_slot_id WHERE o.order_id=:id AND o.user_id=:uid", ['id' => $id, 'uid' => $user['user_id']]);
if (!$o) die('Order not found.'); ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Order Confirmed</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body><?php include dirname(__DIR__) . '/includes/header.php'; ?><main class="page-content">
        <h1 class="page-title">Order Confirmed</h1>
        <div class="summary">
            <p>Order ID: <strong><?= e($o['order_id']) ?></strong></p>
            <p>Total: £<?= number_format((float)$o['total_amount'], 2) ?></p>
            <p>Collection: <?= e($o['collection_date']) ?>, <?= e($o['time_range']) ?></p><a class="checkout" href="<?= app_url('customer/invoice.php?id=' . urlencode($o['order_id'])) ?>">View Invoice</a><a class="checkout" href="<?= app_url('customer/customer-profile.php') ?>">View order history</a>
        </div>
    </main><?php include dirname(__DIR__) . '/includes/footer.php'; ?></body>

</html>