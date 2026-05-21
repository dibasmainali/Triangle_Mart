<?php
/**
 * Triangle Mart - Customer profile and orders
 *
 * View account details, update profile/password, and order history.
 * Access: CUSTOMER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';

require_login(['CUSTOMER']);

$u = current_user();
$profileError = '';
$passwordError = '';
$flashSuccess = $_SESSION['profile_flash'] ?? '';
unset($_SESSION['profile_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'profile';

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $passwordError = 'Please fill in all password fields.';
        } elseif ($current !== $u['password']) {
            $passwordError = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $passwordError = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $passwordError = 'New password and confirmation do not match.';
        } elseif ($new === $current) {
            $passwordError = 'New password must be different from your current password.';
        } else {
            execute_sql(
                'UPDATE users SET password = :pw WHERE user_id = :uid AND user_type = \'CUSTOMER\'',
                ['pw' => $new, 'uid' => $u['user_id']]
            );
            refresh_current_user();
            $_SESSION['profile_flash'] = 'Password updated successfully.';
            redirect_to('customer/customer-profile.php');
            exit;
        }
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($firstName === '' || $lastName === '') {
            $profileError = 'First name and last name are required.';
        } else {
            execute_sql(
                'UPDATE users
                 SET first_name = :fn,
                     last_name = :ln,
                     phone = :ph,
                     address = :addr
                 WHERE user_id = :uid',
                [
                    'fn'   => $firstName,
                    'ln'   => $lastName,
                    'ph'   => $phone,
                    'addr' => $address,
                    'uid'  => $u['user_id']
                ]
            );
            refresh_current_user();
            $_SESSION['profile_flash'] = 'Profile updated successfully.';
            redirect_to('customer/customer-profile.php');
            exit;
        }
    }
}

$u = current_user();

$orders = fetch_all("
    SELECT
        o.*,
        COUNT(oi.product_id) item_count
    FROM orders o
    LEFT JOIN order_item oi
        ON o.order_id = oi.order_id
    WHERE o.user_id = :uid
    GROUP BY
        o.order_id,
        o.user_id,
        o.basket_id,
        o.payment_id,
        o.collection_slot_id,
        o.order_date,
        o.total_amount,
        o.status
    ORDER BY o.order_date DESC
", [
    'uid' => $u['user_id']
]);

$initials = strtoupper(
    substr($u['first_name'] ?? '', 0, 1) . substr($u['last_name'] ?? '', 0, 1)
);

function order_status_class(string $status): string
{
    return match ($status) {
        'COLLECTED', 'DELIVERED' => 'delivered',
        'CANCELLED' => 'cancelled',
        default => 'processing',
    };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>

    <main class="page-content account-page">

        <div class="account-hero">
            <div class="account-hero-left">
                <div class="account-hero-avatar" aria-hidden="true"><?= e($initials) ?></div>
                <div>
                    <h1 class="account-hero-title">My Account</h1>
                    <p class="account-hero-subtitle">Manage your personal details, password, and order history.</p>
                </div>
            </div>
            <div class="account-hero-actions">
                <a class="account-btn account-btn--ghost" href="<?= app_url('customer/products.php') ?>">Shop</a>
                <a class="account-btn account-btn--ghost" href="<?= app_url('customer/home.php') ?>">Home</a>
            </div>
        </div>

        <nav class="account-tabs" aria-label="Account sections">
            <a class="account-tab" href="#personal">Personal</a>
            <a class="account-tab" href="#security">Security</a>
            <a class="account-tab" href="#orders">Orders</a>
        </nav>

        <?php if ($flashSuccess): ?>
            <div class="success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <div class="account-layout">

            <div class="account-main">

                <section id="personal" class="account-card">
                    <div class="account-section-head">
                        <h2>Personal information</h2>
                        <p>Update your name, phone number, and delivery address.</p>
                    </div>

                    <?php if ($profileError): ?>
                        <div class="error"><?= e($profileError) ?></div>
                    <?php endif; ?>

                    <form method="post" class="account-form">
                        <input type="hidden" name="form_action" value="profile">

                        <div class="account-form-grid">
                            <div class="form-group">
                                <label for="first_name">First name</label>
                                <input id="first_name" name="first_name" required value="<?= e($u['first_name']) ?>">
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last name</label>
                                <input id="last_name" name="last_name" required value="<?= e($u['last_name']) ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email address</label>
                                <input id="email" type="email" value="<?= e($u['email']) ?>" disabled>
                                <span class="field-hint">Email cannot be changed here.</span>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone number</label>
                                <input id="phone" name="phone" type="tel" value="<?= e($u['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input id="address" name="address" value="<?= e($u['address'] ?? '') ?>">
                        </div>

                        <button class="account-save-btn" type="submit">Save changes</button>
                    </form>
                </section>

                <section id="security" class="account-card">
                    <div class="account-section-head">
                        <h2>Security</h2>
                        <p>Change your password. You will need your current password.</p>
                    </div>

                    <?php if ($passwordError): ?>
                        <div class="error"><?= e($passwordError) ?></div>
                    <?php endif; ?>

                    <form method="post" class="account-form">
                        <input type="hidden" name="form_action" value="password">

                        <div class="account-form-grid account-form-grid--narrow">
                            <div class="form-group">
                                <label for="current_password">Current password</label>
                                <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                            </div>

                            <div class="form-group">
                                <label for="new_password">New password</label>
                                <input id="new_password" name="new_password" type="password" required minlength="6" autocomplete="new-password">
                                <span class="field-hint">At least 6 characters.</span>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm new password</label>
                                <input id="confirm_password" name="confirm_password" type="password" required minlength="6" autocomplete="new-password">
                            </div>
                        </div>

                        <button class="account-save-btn" type="submit">Update password</button>
                    </form>
                </section>

                <section id="orders" class="account-card">
                    <div class="account-section-head">
                        <h2>Order history</h2>
                        <p>View past orders, download invoices, or cancel pending orders.</p>
                    </div>

                    <?php if (empty($orders)): ?>
                        <p class="account-empty">You have not placed any orders yet.</p>
                        <a class="account-save-btn" href="<?= app_url('customer/products.php') ?>">Browse products</a>
                    <?php else: ?>

                        <div class="order-list">
                            <?php foreach ($orders as $o): ?>
                                <article class="order-card">
                                    <div class="order-card-main">
                                        <div class="order-card-top">
                                            <strong class="order-id">Order #<?= e($o['order_id']) ?></strong>
                                            <span class="status <?= e(order_status_class($o['status'])) ?>">
                                                <?= e($o['status']) ?>
                                            </span>
                                        </div>
                                        <p class="order-meta">
                                            <?= e($o['order_date']) ?>
                                            &middot;
                                            <?= (int)$o['item_count'] ?> item<?= (int)$o['item_count'] === 1 ? '' : 's' ?>
                                        </p>
                                    </div>

                                    <div class="order-card-total">
                                        £<?= number_format((float)$o['total_amount'], 2) ?>
                                    </div>

                                    <div class="order-card-actions">
                                        <?php if (
                                            in_array(
                                                $o['status'],
                                                ['PAID', 'PROCESSING', 'READY', 'COLLECTED'],
                                                true
                                            )
                                        ): ?>
                                            <a
                                                class="checkout small"
                                                href="<?= app_url('customer/invoice.php?id=' . urlencode($o['order_id'])) ?>">
                                                Invoice
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($o['status'] === 'PENDING'): ?>
                                            <form action="<?= app_url('actions/cancel-order.php') ?>" method="post">
                                                <input type="hidden" name="order_id" value="<?= e($o['order_id']) ?>">
                                                <button class="account-cancel-btn" type="submit">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>
                </section>

            </div>
        </div>

    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>

</body>

</html>
