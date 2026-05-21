<?php require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);
$user = current_user();
$oid = $_GET['order_id'] ?? '';
$pid = $_GET['payment_id'] ?? '';
$order = fetch_one("SELECT o.*,p.status payment_status FROM orders o JOIN payment p ON o.payment_id=p.payment_id WHERE o.order_id=:oid AND o.payment_id=:pid AND o.user_id=:uid", ['oid' => $oid, 'pid' => $pid, 'uid' => $user['user_id']]);
if ($order && $order['status'] === 'PENDING') {
    execute_transaction([["UPDATE payment SET status='CANCELLED' WHERE payment_id=:pid", ['pid' => $pid]], ["UPDATE orders SET status='CANCELLED' WHERE order_id=:oid", ['oid' => $oid]], ["INSERT INTO order_status_history(history_id,order_id,old_status,new_status,changed_by) VALUES(:hid,:oid,'PENDING','CANCELLED',:uid)", ['hid' => next_id('order_status_history', 'history_id', 'H'), 'oid' => $oid, 'uid' => $user['user_id']]]]);
} ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body><?php include dirname(__DIR__) . '/includes/header.php'; ?><main class="page-content">
        <h1 class="page-title">Payment Cancelled</h1>
        <div class="summary">
            <p>Your PayPal payment was cancelled.</p><a class="checkout" href="<?= app_url('customer/cart.php') ?>">Return to Basket</a>
        </div>
    </main><?php include dirname(__DIR__) . '/includes/footer.php'; ?></body>

</html>