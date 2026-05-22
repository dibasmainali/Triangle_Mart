<?php
/**
 * Triangle Mart - Place order (action)
 *
 * Creates order, order items, payment record, and redirects to PayPal.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

// --- Create order from basket and start PayPal payment ---

$user = current_user();

$slot = $_POST['slot_id'] ?? '';
$method = $_POST['payment_method'] ?? 'PAYPAL';

if (!$slot) {
    die('Please select a collection slot.');
}

if ($method !== 'PAYPAL') {
    die('Only PayPal is implemented for this prototype.');
}

$basket = fetch_one("
    SELECT *
    FROM basket
    WHERE user_id = :uid
    AND basket_status = 'ACTIVE'
", [
    'uid' => $user['user_id']
]);

if (!$basket) {
    die('Basket is empty.');
}

$items = fetch_all("
    SELECT
        bi.product_id,
        bi.quantity,
        p.price AS original_price,
        p.shop_id,
        p.stock_available,
        p.min_order,
        p.max_order,
        p.product_status,
        NVL(d.discount_rate, 0) AS discount_rate,
        ROUND(
            p.price - (p.price * NVL(d.discount_rate, 0) / 100),
            2
        ) AS final_price
    FROM basket_item bi
    JOIN product p ON bi.product_id = p.product_id
    LEFT JOIN (
        SELECT
            product_id,
            MAX(discount_rate) AS discount_rate
        FROM discount
        WHERE discount_status = 'ACTIVE'
        AND start_date <= SYSDATE
        AND end_date >= TRUNC(SYSDATE)
        GROUP BY product_id
    ) d ON p.product_id = d.product_id
    WHERE bi.basket_id = :bid
", [
    'bid' => $basket['basket_id']
]);

if (!$items) {
    die('Basket is empty.');
}

$slotRow = is_allowed_collection_slot($slot, true);

if (!$slotRow) {
    die('Selected collection slot is no longer available.');
}

$total = 0;

foreach ($items as $it) {
    $quantity = (int)$it['quantity'];

    if ($it['product_status'] !== 'ACTIVE') {
        die('A product in your basket is no longer available.');
    }

    if ($quantity < (int)$it['min_order'] || $quantity > (int)$it['max_order']) {
        die('A product quantity does not meet order limits.');
    }

    if ($quantity > (int)$it['stock_available']) {
        die('Insufficient stock for product ' . e($it['product_id']));
    }

    $total += $quantity * (float)$it['final_price'];
}

$payid = next_id('payment', 'payment_id', 'P');
$oid = next_id('orders', 'order_id', 'O');
$hid = next_id('order_status_history', 'history_id', 'H');

$ops = [];

$ops[] = [
    "
    INSERT INTO payment
    (
        payment_id,
        user_id,
        payment_method,
        amount,
        status,
        transaction_ref
    )
    VALUES
    (
        :pid,
        :uid,
        :method,
        :amount,
        'PENDING',
        NULL
    )
    ",
    [
        'pid' => $payid,
        'uid' => $user['user_id'],
        'method' => $method,
        'amount' => $total
    ]
];

$ops[] = [
    "
    INSERT INTO orders
    (
        order_id,
        user_id,
        basket_id,
        payment_id,
        collection_slot_id,
        total_amount,
        status
    )
    VALUES
    (
        :oid,
        :uid,
        :bid,
        :pid,
        :sid,
        :total,
        'PENDING'
    )
    ",
    [
        'oid' => $oid,
        'uid' => $user['user_id'],
        'bid' => $basket['basket_id'],
        'pid' => $payid,
        'sid' => $slot,
        'total' => $total
    ]
];

foreach ($items as $it) {
    $quantity = (int)$it['quantity'];
    $priceAtPurchase = (float)$it['final_price'];
    $lineTotal = $quantity * $priceAtPurchase;

    $ops[] = [
        "
        INSERT INTO order_item
        (
            order_id,
            product_id,
            shop_id,
            quantity,
            price_at_purchase,
            line_total
        )
        VALUES
        (
            :oid,
            :prod,
            :shop,
            :qty,
            :price,
            :line
        )
        ",
        [
            'oid' => $oid,
            'prod' => $it['product_id'],
            'shop' => $it['shop_id'],
            'qty' => $quantity,
            'price' => $priceAtPurchase,
            'line' => $lineTotal
        ]
    ];
}

$ops[] = [
    "
    INSERT INTO order_status_history
    (
        history_id,
        order_id,
        old_status,
        new_status,
        changed_by
    )
    VALUES
    (
        :hid,
        :oid,
        NULL,
        'PENDING',
        :uid
    )
    ",
    [
        'hid' => $hid,
        'oid' => $oid,
        'uid' => $user['user_id']
    ]
];

execute_transaction($ops);

redirect_to('customer/paypal-payment.php?order_id=' . urlencode($oid));
