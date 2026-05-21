<?php
/**
 * Triangle Mart - PayPal return handler (success)
 *
 * Validates PayPal return and marks order as paid; redirects to confirmation.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Match order from PayPal return parameters ---
$user = current_user();
$orderId = $_GET['order_id'] ?? ($_GET['invoice'] ?? '');
$paymentId = $_GET['payment_id'] ?? ($_GET['cm'] ?? ($_GET['custom'] ?? ''));
$ref = $_GET['tx'] ?? ('PAYPAL-SANDBOX-' . time());
$order = fetch_one("SELECT o.*,p.status payment_status,p.amount payment_amount,p.payment_method FROM orders o JOIN payment p ON o.payment_id=p.payment_id WHERE o.order_id=:oid AND o.payment_id=:pid AND o.user_id=:uid", ['oid' => $orderId, 'pid' => $paymentId, 'uid' => $user['user_id']]);
if (!$order) die('Payment/order record not found.');
if ($order['status'] === 'PAID' || $order['payment_status'] === 'SUCCESS') {
    redirect_to('customer/order-confirmation.php?id=' . urlencode($order['order_id']));
    exit;
}
$slot = is_allowed_collection_slot($order['collection_slot_id']);
if (!$slot) {
    execute_sql("UPDATE payment SET status='FAILED',transaction_ref=:ref WHERE payment_id=:pid", ['ref' => $ref, 'pid' => $order['payment_id']]);
    execute_sql("UPDATE orders SET status='CANCELLED' WHERE order_id=:oid", ['oid' => $order['order_id']]);
    die('The selected collection slot is now full or unavailable.');
}
$items = fetch_all("SELECT oi.product_id,oi.quantity,p.stock_available FROM order_item oi JOIN product p ON oi.product_id=p.product_id WHERE oi.order_id=:oid", ['oid' => $order['order_id']]);
foreach ($items as $it) {
    if ((int)$it['quantity'] > (int)$it['stock_available']) {
        execute_sql("UPDATE payment SET status='FAILED',transaction_ref=:ref WHERE payment_id=:pid", ['ref' => $ref, 'pid' => $order['payment_id']]);
        execute_sql("UPDATE orders SET status='CANCELLED' WHERE order_id=:oid", ['oid' => $order['order_id']]);
        die('One or more products are no longer in stock.');
    }
}
$ops = [];
foreach ($items as $it) {
    $ops[] = ["UPDATE product SET stock_available=stock_available-:qty WHERE product_id=:prod", ['qty' => $it['quantity'], 'prod' => $it['product_id']]];
}
$ops[] = ["UPDATE payment SET status='SUCCESS',transaction_ref=:ref WHERE payment_id=:pid", ['ref' => $ref, 'pid' => $order['payment_id']]];
$ops[] = ["UPDATE orders SET status='PAID' WHERE order_id=:oid", ['oid' => $order['order_id']]];
$ops[] = ["DELETE FROM basket_item WHERE basket_id=:bid", ['bid' => $order['basket_id']]];
$ops[] = ["UPDATE basket SET basket_status='ACTIVE' WHERE basket_id=:bid", ['bid' => $order['basket_id']]];
$ops[] = ["UPDATE collection_slot SET current_orders=current_orders+1 WHERE collection_slot_id=:sid", ['sid' => $order['collection_slot_id']]];
$ops[] = ["INSERT INTO order_status_history(history_id,order_id,old_status,new_status,changed_by) VALUES(:hid,:oid,'PENDING','PAID',:uid)", ['hid' => next_id('order_status_history', 'history_id', 'H'), 'oid' => $order['order_id'], 'uid' => $user['user_id']]];
$exists = fetch_one('SELECT invoice_id FROM invoice WHERE order_id=:oid', ['oid' => $order['order_id']]);
if (!$exists) {
    $ops[] = ["INSERT INTO invoice(invoice_id,order_id,total_amount) VALUES(:iid,:oid,:total)", ['iid' => next_id('invoice', 'invoice_id', 'I'), 'oid' => $order['order_id'], 'total' => $order['total_amount']]];
}
execute_transaction($ops);
redirect_to('customer/order-confirmation.php?id=' . urlencode($order['order_id']));
exit;
