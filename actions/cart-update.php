<?php require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);
$user = current_user();
$pid = $_POST['product_id'] ?? '';
$qty = (int)($_POST['quantity'] ?? 0);
$basket = fetch_one("SELECT * FROM basket WHERE user_id=:uid AND basket_status='ACTIVE'", ['uid' => $user['user_id']]);
if (!$basket) {
    redirect_to('customer/cart.php');
    exit;
}
if ($qty <= 0) {
    execute_sql('DELETE FROM basket_item WHERE basket_id=:bid AND product_id=:pid', ['bid' => $basket['basket_id'], 'pid' => $pid]);
    redirect_to('customer/cart.php');
    exit;
}
$product = fetch_one("SELECT stock_available,min_order,max_order,product_status FROM product WHERE product_id=:pid", ['pid' => $pid]);
if (!$product || $product['product_status'] !== 'ACTIVE') die('Product unavailable.');
$min = (int)$product['min_order'];
$max = (int)$product['max_order'];
$stock = (int)$product['stock_available'];
if ($qty < $min) $qty = $min;
if ($qty > $max) $qty = $max;
if ($qty > $stock) die('Quantity exceeds available stock.');
execute_sql('UPDATE basket_item SET quantity=:q WHERE basket_id=:bid AND product_id=:pid', ['q' => $qty, 'bid' => $basket['basket_id'], 'pid' => $pid]);
redirect_to('customer/cart.php');
exit;
