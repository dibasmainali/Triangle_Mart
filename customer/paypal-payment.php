<?php
/**
 * Triangle Mart - PayPal checkout page
 *
 * Displays order summary and submits payment to PayPal sandbox/live.
 * Access: CUSTOMER only.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/paypal-config.php';

require_login(['CUSTOMER']);

$user = current_user();

$orderId = $_GET['order_id'] ?? '';

$order = fetch_one("
    SELECT
        o.*,
        p.payment_method,
        p.status payment_status
    FROM orders o
    JOIN payment p
        ON o.payment_id = p.payment_id
    WHERE o.order_id = :oid
    AND o.user_id = :uid
", [
    'oid' => $orderId,
    'uid' => $user['user_id']
]);

if (!$order) {
    die('Order not found.');
}

if ($order['status'] === 'PAID') {

    redirect_to('customer/order-confirmation.php?id=' . urlencode($order['order_id']));
}

$amount = number_format(
    (float)$order['total_amount'],
    2,
    '.',
    ''
);

$returnUrl = site_url(
    'actions/payment-success.php?order_id=' .
        urlencode($order['order_id']) .
        '&payment_id=' .
        urlencode($order['payment_id'])
);

$cancelUrl = site_url(
    'customer/payment-cancel.php?order_id=' .
        urlencode($order['order_id']) .
        '&payment_id=' .
        urlencode($order['payment_id'])
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Pay with PayPal</title>

    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">

</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>

    <main class="page-content">

        <h1 class="page-title">
            Pay with PayPal
        </h1>

        <div class="summary">

            <p>
                Order ID:
                <strong>
                    <?= e($order['order_id']) ?>
                </strong>
            </p>

            <p>
                Total:
                <strong>
                    £<?= number_format((float)$order['total_amount'], 2) ?>
                </strong>
            </p>

            <form
                action="<?= e(PAYPAL_URL) ?>"
                method="post">

                <input
                    type="hidden"
                    name="business"
                    value="<?= e(PAYPAL_BUSINESS_EMAIL) ?>">

                <input
                    type="hidden"
                    name="cmd"
                    value="_xclick">

                <input
                    type="hidden"
                    name="item_name"
                    value="Triangle Mart Order <?= e($order['order_id']) ?>">

                <input
                    type="hidden"
                    name="item_number"
                    value="<?= e($order['order_id']) ?>">

                <input
                    type="hidden"
                    name="amount"
                    value="<?= e($amount) ?>">

                <input
                    type="hidden"
                    name="currency_code"
                    value="<?= e(PAYPAL_CURRENCY) ?>">

                <input
                    type="hidden"
                    name="quantity"
                    value="1">

                <input
                    type="hidden"
                    name="custom"
                    value="<?= e($order['payment_id']) ?>">

                <input
                    type="hidden"
                    name="invoice"
                    value="<?= e($order['order_id']) ?>">

                <input
                    type="hidden"
                    name="return"
                    value="<?= e($returnUrl) ?>">

                <input
                    type="hidden"
                    name="cancel_return"
                    value="<?= e($cancelUrl) ?>">

                <input
                    type="hidden"
                    name="no_shipping"
                    value="1">

                <button
                    class="pay-btn"
                    type="submit">
                    Continue to PayPal
                </button>

            </form>

        </div>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

</body>

</html>