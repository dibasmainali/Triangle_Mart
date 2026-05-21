<?php
/**
 * Triangle Mart - Logout (action)
 *
 * Clears the session and redirects to the customer home page.
 */
require_once dirname(__DIR__) . '/includes/config.php';

session_destroy();
redirect_to('customer/home.php');
