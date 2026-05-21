<?php
/**
 * Triangle Mart - Add to cart (action)
 *
 * Adds or updates quantity for a product in the active basket.
 * Supports AJAX (JSON/text response) and form POST redirect.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Validate product and update basket ---

function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$user = current_user();

$pid = $_POST['product_id'] ?? '';
$qty = max(1, (int)($_POST['quantity'] ?? 1));

if ($pid === '') {

    if (is_ajax_request()) {

        http_response_code(400);
        echo 'Product was not provided.';
        exit;
    }

    die('Product was not provided.');
}

$product = fetch_one("
    SELECT
        product_id,
        stock_available,
        min_order,
        max_order,
        product_status
    FROM product
    WHERE product_id = :pid
", [
    'pid' => $pid
]);

if (!$product || $product['product_status'] !== 'ACTIVE') {

    if (is_ajax_request()) {

        http_response_code(400);
        echo 'This product is not available.';
        exit;
    }

    die('This product is not available.');
}

$min = (int)$product['min_order'];
$max = (int)$product['max_order'];
$stock = (int)$product['stock_available'];

if ($qty < $min) {
    $qty = $min;
}

if ($qty > $max) {
    $qty = $max;
}

if ($qty > $stock) {

    if (is_ajax_request()) {

        http_response_code(400);
        echo 'There is not enough stock available for this product.';
        exit;
    }

    die('There is not enough stock available for this product.');
}

$bid = ensure_customer_basket($user['user_id']);

$existing = fetch_one("
    SELECT quantity
    FROM basket_item
    WHERE basket_id = :bid
    AND product_id = :pid
", [
    'bid' => $bid,
    'pid' => $pid
]);

/*
|--------------------------------------------------------------------------
| LIMIT CART TO 20 DIFFERENT PRODUCTS
|--------------------------------------------------------------------------
*/

if (!$existing) {

    $cart_count = fetch_one("
        SELECT COUNT(*) AS total
        FROM basket_item
        WHERE basket_id = :bid
    ", [
        'bid' => $bid
    ]);

    if ((int)$cart_count['total'] >= 20) {

        if (is_ajax_request()) {

            http_response_code(400);
            echo 'Cart limit reached';
            exit;
        }

        die('Your cart can only contain 20 products.');
    }
}

if ($existing) {

    $new = (int)$existing['quantity'] + $qty;

    if ($new > $max) {
        $new = $max;
    }

    if ($new > $stock) {

        if (is_ajax_request()) {

            http_response_code(400);
            echo 'There is not enough stock available for this quantity.';
            exit;
        }

        die('There is not enough stock available for this quantity.');
    }

    execute_sql("
        UPDATE basket_item
        SET quantity = :q
        WHERE basket_id = :bid
        AND product_id = :pid
    ", [
        'q' => $new,
        'bid' => $bid,
        'pid' => $pid
    ]);

} else {

    execute_sql("
        INSERT INTO basket_item(
            basket_id,
            product_id,
            quantity
        )
        VALUES(
            :bid,
            :pid,
            :q
        )
    ", [
        'bid' => $bid,
        'pid' => $pid,
        'q' => $qty
    ]);
}

if (is_ajax_request()) {

    echo 'success';
    exit;
}

redirect_to('customer/cart.php');
exit;
?>