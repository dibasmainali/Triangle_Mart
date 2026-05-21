<?php
/**
 * Triangle Mart - PayPal payment constants
 *
 * Sandbox mode, business email, currency, and PayPal form action URL.
 */
define('PAYPAL_SANDBOX', true);
define('PAYPAL_BUSINESS_EMAIL', 'sb-nfrer50703044@business.example.com');
define('PAYPAL_CURRENCY', 'GBP');
define('PAYPAL_URL', PAYPAL_SANDBOX ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr');
