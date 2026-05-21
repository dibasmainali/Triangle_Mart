<?php
/**
 * Triangle Mart - Seed trader account (dev utility)
 *
 * Creates a sample trader for local testing. Not for production use.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$email = 'john@bakery.com';
$password_plain = 'password';
$existing = fetch_one('SELECT user_id FROM users WHERE email=:email', ['email' => $email]);
if ($existing) {
    echo 'Trader already exists. User ID: ' . e($existing['user_id']);
    exit;
}
$user_id = next_id('users', 'user_id', 'T');
$shop_id = next_id('shop', 'shop_id', 'S');
execute_sql("INSERT INTO users(user_id,user_type,first_name,last_name,email,password,phone,address,business_name,business_description,email_verified,approval_status) VALUES(:uid,'TRADER','John','Baker',:email,:password,'07123456789','Cleckhuddersfax','John Bakery','Local bakery trader','Y','APPROVED')", ['uid' => $user_id, 'email' => $email, 'password' => $password_plain]);
execute_sql("INSERT INTO shop(shop_id,user_id,shop_name,shop_address,shop_status) VALUES(:sid,:uid,'John Bakery','Cleckhuddersfax High Street','ACTIVE')", ['sid' => $shop_id, 'uid' => $user_id]);
echo '<h2>Trader created successfully</h2><p>Email: john@bakery.com</p><p>Password: password</p><p><a href="' . e(app_url('customer/login.php')) . '">Go to login</a></p>';
