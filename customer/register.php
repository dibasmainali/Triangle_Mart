<?php
/**
 * Triangle Mart - Registration page
 *
 * Creates customer or trader accounts and sends email verification link.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

// --- Registration form handling ---
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $type = $_POST['user_type'] ?? 'CUSTOMER';
    $business = trim($_POST['business_name'] ?? '');
    $desc = trim($_POST['business_description'] ?? '');
    if (!in_array($type, ['CUSTOMER', 'TRADER'], true)) $type = 'CUSTOMER';
    if ($first === '' || $last === '' || $email === '' || $password === '') $error = 'Please fill in all required fields.';
    elseif ($type === 'TRADER' && $business === '') $error = 'Trader registration requires a business name.';
    else {
        $existing = fetch_one('SELECT user_id FROM users WHERE email=:email', ['email' => $email]);
        if ($existing) $error = 'This email is already registered.';
        else {
            $prefix = $type === 'TRADER' ? 'T' : 'C';
            $uid = next_id('users', 'user_id', $prefix);
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 day'));
            execute_sql("INSERT INTO users(user_id,user_type,first_name,last_name,email,password,phone,address,business_name,business_description,email_verified,approval_status,verification_token,verification_expires) VALUES(:uid,:ut,:fn,:ln,:em,:pw,:ph,:ad,:bn,:bd,'N','PENDING',:tok,TO_DATE(:exp,'YYYY-MM-DD HH24:MI:SS'))", ['uid' => $uid, 'ut' => $type, 'fn' => $first, 'ln' => $last, 'em' => $email, 'pw' => $password, 'ph' => $phone, 'ad' => $address, 'bn' => $business, 'bd' => $desc, 'tok' => $token, 'exp' => $expiry]);
            if ($type === 'TRADER') {
                $sid = next_id('shop', 'shop_id', 'S');
                execute_sql("INSERT INTO shop(shop_id,user_id,shop_name,shop_address,shop_status) VALUES(:sid,:uid,:sn,:sa,'ACTIVE')", ['sid' => $sid, 'uid' => $uid, 'sn' => $business, 'sa' => $address]);
            }
            $link = site_url('actions/verify-email.php?token=' . urlencode($token));
            $mail = send_verification_email($email, $first, $link);
            $success = $mail === true ? 'Registration successful. Please check verification-links.log or your email to verify your account.' : 'Account created, but verification email could not be sent: ' . $mail;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <div class="page-wrap">
        <div class="form-card">
            <div class="logo"><img src="<?= app_url('assets/images/logo3comp.png') ?>" alt="logo"></div>
            <div class="subtitle">Create your Triangle Mart account</div><?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?><form method="POST">
                <div class="form-group"><label>Account Type</label><select name="user_type" id="user_type">
                        <option value="CUSTOMER">Customer</option>
                        <option value="TRADER">Trader</option>
                    </select></div>
                <div class="form-group"><label>First Name</label><input name="first_name" required></div>
                <div class="form-group"><label>Last Name</label><input name="last_name" required></div>
                <div class="form-group"><label>Email Address</label><input name="email" type="email" required></div>
                <div class="form-group"><label>Phone Number</label><input name="phone"></div>
                <div class="form-group"><label>Address</label><input name="address"></div>
                <div class="form-group trader-only"><label>Business Name</label><input name="business_name"></div>
                <div class="form-group trader-only"><label>Business Description</label><textarea name="business_description"></textarea></div>
                <div class="form-group"><label>Password</label><input name="password" type="password" required></div><button class="btn btn-primary" type="submit">Register</button>
            </form>
            <div class="muted-link">Already have an account? <a href="<?= app_url('customer/login.php') ?>">Login</a></div>
        </div>
    </div>
    <script>
        const s = document.getElementById('user_type'),
            t = document.querySelectorAll('.trader-only');

        function f() {
            t.forEach(x => x.style.display = s.value === 'TRADER' ? 'block' : 'none')
        }
        s.addEventListener('change', f);
        f();
    </script>
</body>

</html>