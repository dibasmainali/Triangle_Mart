<?php
/**
 * Triangle Mart - Remove from wishlist (action)
 *
 * Removes a product from the wishlist and redirects back.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);

$user = current_user();
$productId = $_POST['product_id'] ?? '';

if (!$productId) {
    redirect_to('customer/wishlist.php');
    exit;
}

// Get wishlist ID
$wishlist = fetch_one("SELECT wishlist_id FROM wishlist WHERE user_id = :uid", ['uid' => $user['user_id']]);

if ($wishlist) {
    execute_sql('DELETE FROM wishlist_item WHERE wishlist_id = :wid AND product_id = :pid', [
        'wid' => $wishlist['wishlist_id'],
        'pid' => $productId
    ]);
}

redirect_to('customer/wishlist.php');
exit;
?>