<?php
/**
 * Triangle Mart - Email verification (action)
 *
 * Validates token from registration email and approves account.
 * Access: public (via email link).
 */
require_once dirname(__DIR__) . '/includes/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid verification link.');
}

$user = fetch_one(
    "SELECT user_id, user_type, email_verified
     FROM users
     WHERE verification_token = :tok
     AND verification_expires > SYSDATE",
    ['tok' => $token]
);

if (!$user) {
    die('Invalid or expired verification token.');
}

$user_id = $user['user_id'] ?? $user['USER_ID'];
$user_type = $user['user_type'] ?? $user['USER_TYPE'];
$email_verified = $user['email_verified'] ?? $user['EMAIL_VERIFIED'];

if ($email_verified === 'Y') {
    die('This email address is already verified.');
}

$status = ($user_type === 'TRADER') ? 'PENDING' : 'APPROVED';

execute_sql(
    "UPDATE users
     SET email_verified = 'Y',
         approval_status = :st,
         verification_token = NULL,
         verification_expires = NULL
     WHERE user_id = :uid",
    [
        'st'  => $status,
        'uid' => $user_id
    ]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verified</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>
<body>
    <div class="page-wrap">
        <div class="form-card">
            <h2>Email verified successfully.</h2>

            <?php if ($user_type === 'TRADER'): ?>
                <p>Your trader account is waiting for admin approval in Oracle APEX.</p>
            <?php else: ?>
                <p>Your account has been approved. You can now log in.</p>
            <?php endif; ?>

            <a class="btn btn-primary" href="<?= app_url('customer/login.php') ?>">Login</a>
        </div>
    </div>
</body>
</html>
