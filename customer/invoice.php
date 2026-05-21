<?php
/**
 * Triangle Mart - Order invoice
 *
 * Printable receipt for a single order.
 * Access: CUSTOMER only (own orders).
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_login(['CUSTOMER']);
$user = current_user();
$oid = $_GET['id'] ?? '';
$order = fetch_one("SELECT o.*, i.invoice_id, i.invoice_date, cs.collection_date, cs.time_range FROM orders o JOIN invoice i ON o.order_id=i.order_id JOIN collection_slot cs ON o.collection_slot_id=cs.collection_slot_id WHERE o.order_id=:oid AND o.user_id=:uid", ['oid' => $oid, 'uid' => $user['user_id']]);
if (!$order) die('Invoice not found.');
$items = fetch_all("SELECT oi.*,p.name,s.shop_name FROM order_item oi JOIN product p ON oi.product_id=p.product_id JOIN shop s ON oi.shop_id=s.shop_id WHERE oi.order_id=:oid", ['oid' => $oid]);

// Calculate totals from fetched data (same as before but organized for display)
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)$it['line_total'];
}
$tax = $subtotal * 0.10;
$total = $subtotal + $tax;
$paid = (float)$order['total_amount'];
$amount_due = $paid - $total;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
</head>

<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>

    <main class="page-content">
        <div class="invoice-wrapper">
            <!-- Company Header -->
            <div class="invoice-header-section">
                <div class="company-details">
                    <img src="<?= app_url('assets/images/logo3comp.png') ?>" style="height:64px; margin-bottom: 8px;" alt="Triangle Mart logo">
                </div>
                <div class="company-details">
                    <p>Cleckhuddersfax, Yorkshire, UK - 122 122</p>
                    <p>Business Number: 00XXXXX1234XX0XX</p>
                </div>
            </div>

            <!-- Billing & Invoice Info -->
            <div class="info-grid">
                <div class="billing-info">
                    <h3>Billed to</h3>
                    <p><strong>Triangle Mart</strong></p>
                    <p>Cleckhuddersfax, Yorkshire</p>
                    <p>UK - 122 122</p>
                    <p>+0 (000) 123-4567</p>
                </div>
                <div class="invoice-info-box">
                    <div class="info-line">
                        <span class="info-label">Invoice number</span>
                        <span class="info-value"><strong>#<?= e($order['invoice_id']) ?></strong></span>
                    </div>
                    <div class="info-line">
                        <span class="info-label">Reference</span>
                        <span class="info-value">INV-<?= substr($order['order_id'], -5) ?></span>
                    </div>
                    <div class="info-line">
                        <span class="info-label">Invoice of (GBP)</span>
                        <span class="info-value">£<?= number_format($subtotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Subject & Dates -->
            <div class="subject-dates-row">
                <div class="subject-box">
                    <span class="label-text">Subject</span>
                    <span class="value-text">Order #<?= e($order['order_id']) ?></span>
                </div>
                <div class="dates-box">
                    <div class="date-item">
                        <span class="label-text">Invoice date</span>
                        <span class="value-text"><?= date('d F, Y', strtotime($order['invoice_date'])) ?></span>
                    </div>
                    <div class="date-item">
                        <span class="label-text">Due date</span>
                        <span class="value-text"><?= date('d F, Y', strtotime($order['invoice_date'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="text-align: left; ">ITEM DETAIL</th>
                        <th style="text-align: center;">QTY</th>
                        <th style="text-align: center;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td style="text-align: left; ">
                                <div class="item-title"><?= e($it['name']) ?></div>
                                <div class="item-desc-line">Item description</div>
                                <div class="item-shop-name"><?= e($it['shop_name']) ?></div>
                            </td>
                            <td style="text-align: center;"><?= (int)$it['quantity'] ?></td>
                            <td style="text-align: center;">£<?= number_format((float)$it['line_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals-panel">
                <div class="totals-card">
                    <div class="total-line">
                        <span>Total</span>
                        <span>£<?= number_format($subtotal, 2) ?></span>
                    </div>

                </div>
            </div>

            <!-- Collection Info (preserved from original) -->
            <div class="collection-row">
                <p><strong>Collection:</strong> <?= e($order['collection_date']) ?> · <?= e($order['time_range']) ?></p>
            </div>

            <!-- Thank You Message -->
            <div class="thankyou-row">
                <p>Thanks you for choosing us !</p>
            </div>
        </div>
    </main>

    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>

</html>