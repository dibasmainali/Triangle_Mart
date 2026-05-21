<?php
/**
 * Triangle Mart - PayPal IPN listener
 *
 * Server-to-server payment notification from PayPal (background, no HTML).
 */
// Optional PayPal IPN endpoint. For localhost testing, PayPal cannot reach this unless you use a public tunnel.
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/paypal-config.php';
$rawPostData = file_get_contents('php://input');
$req = 'cmd=_notify-validate&' . $rawPostData;
$ch = curl_init(PAYPAL_URL);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
$res = curl_exec($ch);
curl_close($ch);
if ($res !== 'VERIFIED') {
    http_response_code(400);
    exit('IPN not verified');
}
$paymentStatus = $_POST['payment_status'] ?? '';
$orderId = $_POST['invoice'] ?? ($_POST['item_number'] ?? '');
$paymentId = $_POST['custom'] ?? '';
$txnId = $_POST['txn_id'] ?? '';
$receiverEmail = $_POST['receiver_email'] ?? '';
$gross = isset($_POST['mc_gross']) ? (float)$_POST['mc_gross'] : 0;
$currency = $_POST['mc_currency'] ?? '';
$order = fetch_one("SELECT o.*,p.amount,p.status payment_status FROM orders o JOIN payment p ON o.payment_id=p.payment_id WHERE o.order_id=:oid AND o.payment_id=:pid", ['oid' => $orderId, 'pid' => $paymentId]);
if (!$order || $order['status'] === 'PAID') exit('Nothing to update');
if ($paymentStatus === 'Completed' && strtolower($receiverEmail) === strtolower(PAYPAL_BUSINESS_EMAIL) && $currency === PAYPAL_CURRENCY && abs($gross - (float)$order['amount']) < 0.01) {
    execute_sql("UPDATE payment SET status='SUCCESS',transaction_ref=:ref WHERE payment_id=:pid", ['ref' => $txnId, 'pid' => $paymentId]);
    execute_sql("UPDATE orders SET status='PAID' WHERE order_id=:oid", ['oid' => $orderId]);
}
