<?php
/**
 * Triangle Mart - Add/remove wishlist item (action)
 *
 * Toggles a product on the customer wishlist. Supports AJAX JSON response.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

$user = current_user();
$productId = $_POST['product_id'] ?? '';
$returnUrl = $_POST['return_url'] ?? 'customer/wishlist.php';

// --- Toggle product on wishlist (add if missing, remove if present) ---
if (!$productId) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(400);
        echo 'Product ID required';
        exit;
    }
    redirect_to("$returnUrl");
    exit;
}

// Get or create wishlist
$wishlist = fetch_one("SELECT wishlist_id FROM wishlist WHERE user_id = :uid", ['uid' => $user['user_id']]);

if (!$wishlist) {
    $wid = next_id('wishlist', 'wishlist_id', 'W');
    execute_sql("INSERT INTO wishlist(wishlist_id, user_id) VALUES(:wid, :uid)", [
        'wid' => $wid,
        'uid' => $user['user_id']
    ]);
    $wishlistId = $wid;
} else {
    $wishlistId = $wishlist['wishlist_id'];
}

// Check if already in wishlist
$exists = fetch_one("SELECT 1 FROM wishlist_item WHERE wishlist_id = :wid AND product_id = :pid", [
    'wid' => $wishlistId,
    'pid' => $productId
]);

if (!$exists) {
    // Add to wishlist
    execute_sql("INSERT INTO wishlist_item(wishlist_id, product_id) VALUES(:wid, :pid)", [
        'wid' => $wishlistId,
        'pid' => $productId
    ]);
    $added = true;
} else {
    // Remove from wishlist (toggle)
    execute_sql("DELETE FROM wishlist_item WHERE wishlist_id = :wid AND product_id = :pid", [
        'wid' => $wishlistId,
        'pid' => $productId
    ]);
    $added = false;
}

// Return JSON for AJAX requests from product cards
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['added' => $added, 'inWishlist' => $added]);
    exit;
}

redirect_to("$returnUrl");
exit;
