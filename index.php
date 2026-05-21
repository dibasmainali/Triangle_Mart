<?php
/**
 * Triangle Mart - Site entry point
 *
 * Redirects visitors to the customer home page.
 */
require_once __DIR__ . '/includes/config.php';
redirect_to('customer/home.php');
