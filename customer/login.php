<?php
/**
 * Triangle Mart - Login page
 *
 * Authenticates customers and traders. Redirects to home or trader dashboard on success.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$error = '';
$success = $_SESSION['login_flash'] ?? '';
unset($_SESSION['login_flash']);

// --- Handle login form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = fetch_one('SELECT * FROM users WHERE email=:email', ['email' => $email]);
    if (!$user || $password !== $user['password']) $error = 'Invalid email or password.';
    elseif ($user['email_verified'] !== 'Y') $error = 'Please verify your email before logging in.';
    elseif ($user['approval_status'] !== 'APPROVED') $error = 'Your account is not approved yet.';
    else {
        $_SESSION['user'] = $user;
        redirect_to($user['user_type'] === 'TRADER' ? 'trader/dashboard.php' : 'customer/home.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<!-- Login form (no site header on this page) --><html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <div class="page-wrap">
        <div class="form-card">
            <div class="logo"><img src="<?= app_url('assets/images/logo3comp.png') ?>" alt="logo"></div>
            <div class="subtitle">Login to continue</div><?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?><form method="POST">
                <div class="form-group"><label>Email Address</label><input name="email" type="email" required></div>
                <div class="form-group"><label>Password</label><input name="password" type="password" required></div><button class="btn btn-primary" type="submit">Login</button>
            </form>
            <div class="muted-link"><a href="<?= app_url('customer/password-reset.php') ?>">Forgot password?</a></div>
            <div class="muted-link">Don't have an account? <a href="<?= app_url('customer/register.php') ?>">Register</a></div>
        </div>
    </div>
</body>

</html>
