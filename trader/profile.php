<?php
/**
 * Triangle Mart - Trader profile and shop settings
 *
 * Update personal details and shop name/address.
 * Access: TRADER only.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['TRADER']);

// --- Update trader account and shop details ---

$u = current_user();

$shop = fetch_one("
    SELECT shop_id, shop_name, shop_address, shop_status
    FROM shop
    WHERE user_id = :uid
", [
    'uid' => $u['user_id']
]);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $businessDescription = trim($_POST['business_description'] ?? '');
    $shopName = trim($_POST['shop_name'] ?? '');
    $shopAddress = trim($_POST['shop_address'] ?? '');
    $shopStatus = $_POST['shop_status'] ?? 'ACTIVE';

    if ($firstName === '' || $lastName === '') {
        $message = 'First name and last name are required.';
    } elseif ($shopName === '') {
        $message = 'Shop name is required.';
    } elseif (!in_array($shopStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
        $message = 'Invalid shop status.';
    } else {
        execute_sql("
            UPDATE users
            SET first_name = :fn,
                last_name = :ln,
                phone = :phone,
                business_name = :bname,
                business_description = :bdesc
            WHERE user_id = :uid
            AND user_type = 'TRADER'
        ", [
            'fn' => $firstName,
            'ln' => $lastName,
            'phone' => $phone,
            'bname' => $businessName,
            'bdesc' => $businessDescription,
            'uid' => $u['user_id']
        ]);

        if ($shop) {
            execute_sql("
                UPDATE shop
                SET shop_name = :sname,
                    shop_address = :saddr,
                    shop_status = :status
                WHERE shop_id = :sid
                AND user_id = :uid
            ", [
                'sname' => $shopName,
                'saddr' => $shopAddress,
                'status' => $shopStatus,
                'sid' => $shop['shop_id'],
                'uid' => $u['user_id']
            ]);
        }

        $_SESSION['user'] = fetch_one("
            SELECT 
                user_id,
                user_type,
                first_name,
                last_name,
                email,
                phone,
                address,
                business_name,
                business_description,
                email_verified,
                approval_status
            FROM users
            WHERE user_id = :uid
        ", [
            'uid' => $u['user_id']
        ]);

        redirect_to('trader/profile.php');
        exit;
    }
}

$u = current_user();

$shop = fetch_one("
    SELECT shop_id, shop_name, shop_address, shop_status
    FROM shop
    WHERE user_id = :uid
", [
    'uid' => $u['user_id']
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trader Profile - Triangle Mart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>

<?php include dirname(__DIR__) . '/includes/trader-header.php'; ?>

<main class="page-content">

    <div class="products-header">
        <div>
            <h1 class="page-title">Trader Profile Settings</h1>
            <p>Manage your account, business and shop information.</p>
        </div>

        <a class="checkout" style="max-width:190px;" href="<?= app_url('trader/dashboard.php') ?>">
            Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="error"><?= e($message) ?></div>
    <?php endif; ?>

    <section class="trader-panel-box">
        <form method="post" class="trader-form">

            <h2>Account Information</h2>

            <div class="trader-form-grid">
                <div>
                    <label>First Name</label>
                    <input name="first_name" required value="<?= e($u['first_name']) ?>">
                </div>

                <div>
                    <label>Last Name</label>
                    <input name="last_name" required value="<?= e($u['last_name']) ?>">
                </div>

                <div>
                    <label>Email</label>
                    <input value="<?= e($u['email']) ?>" disabled>
                </div>

                <div>
                    <label>Phone</label>
                    <input name="phone" value="<?= e($u['phone']) ?>">
                </div>
            </div>

            <h2>Business Information</h2>

            <label>Business Name</label>
            <input name="business_name" value="<?= e($u['business_name']) ?>">

            <label>Business Description</label>
            <textarea name="business_description"><?= e($u['business_description']) ?></textarea>

            <h2>Shop Information</h2>

            <div class="trader-form-grid">
                <div>
                    <label>Shop Name</label>
                    <input name="shop_name" required value="<?= e($shop['shop_name'] ?? '') ?>">
                </div>

                <div>
                    <label>Shop Status</label>
                    <select name="shop_status">
                        <?php foreach (['ACTIVE','SUSPENDED','CLOSED'] as $status): ?>
                            <option value="<?= e($status) ?>"
                                <?= (($shop['shop_status'] ?? 'ACTIVE') === $status) ? 'selected' : '' ?>>
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Shop Address</label>
            <input name="shop_address" value="<?= e($shop['shop_address'] ?? '') ?>">

            <button class="checkout" style="max-width:220px;" type="submit">
                Save Changes
            </button>

        </form>
    </section>

</main>

<?php include dirname(__DIR__) . '/includes/trader-footer.php'; ?>

</body>
</html>