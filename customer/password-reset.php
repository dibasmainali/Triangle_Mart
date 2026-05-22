<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

$error = '';
$success = '';
$debugResetLink = '';

$token = trim($_GET['token'] ?? '');
$hasToken = $token !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($hasToken) {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = fetch_one(
            "SELECT user_id, first_name, email
             FROM users
             WHERE verification_token = :tok
             AND verification_expires > SYSDATE
             AND email_verified = 'Y'
             AND approval_status = 'APPROVED'",
            ['tok' => $token]
        );

        if (!$user) {
            $error = 'Invalid or expired reset link.';
        } elseif ($new === '' || $confirm === '') {
            $error = 'Please fill in all fields.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            execute_sql(
                "UPDATE users
                 SET password = :pw,
                     verification_token = NULL,
                     verification_expires = NULL
                 WHERE user_id = :uid",
                ['pw' => $new, 'uid' => $user['user_id']]
            );
            $_SESSION['login_flash'] = 'Password reset successfully. Please login.';
            redirect_to('customer/login.php');
        }
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Please enter your email address.';
        } else {
            $user = fetch_one(
                "SELECT user_id, first_name, email, email_verified, approval_status
                 FROM users
                 WHERE email = :em",
                ['em' => $email]
            );

            if ($user && $user['email_verified'] === 'Y' && $user['approval_status'] === 'APPROVED') {
                $newToken = bin2hex(random_bytes(32));

                execute_sql(
                    "UPDATE users
                     SET verification_token = :tok,
                         verification_expires = SYSDATE + (1/24)
                     WHERE user_id = :uid",
                    [
                        'tok' => $newToken,
                        'uid' => $user['user_id']
                    ]
                );

                $link = site_url('customer/password-reset.php?token=' . urlencode($newToken));
                $mail = send_password_reset_email($email, $user['first_name'] ?: 'Customer', $link);
                if ($mail !== true) {
                    if (defined('ALLOW_EMAIL_VERIFICATION_BYPASS') && ALLOW_EMAIL_VERIFICATION_BYPASS) {
                        $success = 'Email could not be sent. Use the reset link below.';
                        $debugResetLink = $link;
                    } else {
                        $error = 'Could not send reset email. Please try again later.';
                    }
                }
            }

            if ($error === '' && $success === '') {
                $success = 'If an account exists for that email, a password reset link has been sent.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $hasToken ? 'Reset Password' : 'Forgot Password' ?> - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <div class="page-wrap">
        <div class="form-card">
            <div class="logo"><img src="<?= app_url('assets/images/logo3comp.png') ?>" alt="logo"></div>
            <div class="subtitle"><?= $hasToken ? 'Set a new password' : 'Get a password reset link' ?></div>

            <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?= e($success) ?><?php if ($debugResetLink): ?> <a href="<?= e($debugResetLink) ?>"><?= e($debugResetLink) ?></a><?php endif; ?></div><?php endif; ?>

            <?php if ($hasToken): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New password</label>
                        <input name="new_password" type="password" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Confirm new password</label>
                        <input name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">Reset password</button>
                </form>
                <div class="muted-link"><a href="<?= app_url('customer/login.php') ?>">Back to login</a></div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input name="email" type="email" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Send reset link</button>
                </form>
                <div class="muted-link"><a href="<?= app_url('customer/login.php') ?>">Back to login</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
