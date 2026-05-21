<?php
/**
 * Triangle Mart - Application configuration and shared helpers
 *
 * Database connection, session, URL helpers, auth guards, and basket/wishlist utilities.
 * Included by every page and action handler.
 */
session_start();

define('APP_ROOT', dirname(__DIR__));

// --- Database credentials (Oracle via OCI8) ---
define('DB_USERNAME', 'TRIANGLE_MART');
define('DB_PASSWORD', 'Trianglemart!');
define('DB_CONNECTION', 'localhost:1521/FREEPDB1');

define('ALLOW_EMAIL_VERIFICATION_BYPASS', true); // coursework/local testing helper

/** Open a new Oracle database connection. */
function db_connect()
{
    $conn = oci_connect(DB_USERNAME, DB_PASSWORD, DB_CONNECTION, 'AL32UTF8');
    if (!$conn) {
        $e = oci_error();
        die('Oracle connection failed: ' . htmlentities($e['message']));
    }
    return $conn;
}

/** Rename bind placeholders to safe Oracle names (p_*). */
function normalise_binds($sql, $binds)
{
    $new_binds = [];
    foreach ($binds as $key => $value) {
        $old_key = ltrim($key, ':');
        $new_key = 'p_' . preg_replace('/[^a-zA-Z0-9_]/', '', $old_key);
        $sql = str_replace(':' . $old_key, ':' . $new_key, $sql);
        $new_binds[$new_key] = $value;
    }
    return [$sql, $new_binds];
}

/** Execute SELECT; returns [connection, statement] for manual fetch. */
function run_query($sql, $binds = [])
{
    [$sql, $binds] = normalise_binds($sql, $binds);
    $conn = db_connect();
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        die('SQL parse error: ' . htmlentities($e['message']));
    }
    foreach ($binds as $key => $value) {
        oci_bind_by_name($stmt, ':' . $key, $binds[$key]);
    }
    $ok = oci_execute($stmt);
    if (!$ok) {
        $e = oci_error($stmt);
        die('SQL error: ' . htmlentities($e['message']));
    }
    return [$conn, $stmt];
}

/** Run query and return all rows as lowercase associative arrays. */
function fetch_all($sql, $binds = [])
{
    [$conn, $stmt] = run_query($sql, $binds);
    $rows = [];
    while (($row = oci_fetch_assoc($stmt)) !== false) {
        $rows[] = array_change_key_case($row, CASE_LOWER);
    }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

/** Run query and return the first row or null. */
function fetch_one($sql, $binds = [])
{
    $rows = fetch_all($sql, $binds);
    return $rows[0] ?? null;
}

/** Run INSERT/UPDATE/DELETE with auto-commit. */
function execute_sql($sql, $binds = [])
{
    [$sql, $binds] = normalise_binds($sql, $binds);
    $conn = db_connect();
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        die('SQL parse error: ' . htmlentities($e['message']));
    }
    foreach ($binds as $key => $value) {
        oci_bind_by_name($stmt, ':' . $key, $binds[$key]);
    }
    $ok = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    if (!$ok) {
        $e = oci_error($stmt);
        oci_rollback($conn);
        die('SQL error: ' . htmlentities($e['message']));
    }
    oci_free_statement($stmt);
    oci_close($conn);
}

/** Run multiple statements in one commit; rolls back on failure. */
function execute_transaction($operations)
{
    $conn = db_connect();
    try {
        foreach ($operations as $operation) {
            [$sql, $binds] = normalise_binds($operation[0], $operation[1] ?? []);
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                $e = oci_error($conn);
                throw new Exception($e['message']);
            }
            foreach ($binds as $key => $value) {
                oci_bind_by_name($stmt, ':' . $key, $binds[$key]);
            }
            $ok = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
            if (!$ok) {
                $e = oci_error($stmt);
                throw new Exception($e['message']);
            }
            oci_free_statement($stmt);
        }
        oci_commit($conn);
    } catch (Exception $ex) {
        oci_rollback($conn);
        oci_close($conn);
        die('Transaction error: ' . htmlentities($ex->getMessage()));
    }
    oci_close($conn);
}

/** Generate next prefixed ID (e.g. P0001, B0002). */
function next_id($table, $column, $prefix)
{
    $row = fetch_one("SELECT NVL(MAX(TO_NUMBER(REGEXP_REPLACE($column, '[^0-9]', ''))), 0) + 1 AS next_num FROM $table");
    return $prefix . str_pad($row['next_num'], 4, '0', STR_PAD_LEFT);
}

/** Escape output for safe HTML display. */
function e($value)
{
    return htmlentities((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Return logged-in user from session or null. */
function current_user()
{
    return $_SESSION['user'] ?? null;
}

/** Reload user row from database into session. */
function refresh_current_user()
{
    if (!isset($_SESSION['user']['user_id'])) return null;
    $_SESSION['user'] = fetch_one('SELECT * FROM users WHERE user_id = :uid', ['uid' => $_SESSION['user']['user_id']]);
    return $_SESSION['user'];
}

/** Web path prefix for this app (e.g. /TriangleMart/). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(dirname($script), '/');
    foreach (['/actions', '/customer', '/trader', '/tools'] as $segment) {
        $pos = strpos($dir, $segment);
        if ($pos !== false) {
            $dir = substr($dir, 0, $pos);
            break;
        }
    }
    $base = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    return $base;
}

/** Build a path relative to the site root. */
function app_url(string $path = ''): string
{
    return base_path() . ltrim($path, '/');
}

/** Build a full absolute URL (for PayPal return/cancel links). */
function site_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . app_url($path);
}

/** HTTP redirect and stop script execution. */
function redirect_to(string $path): void
{
    if (preg_match('#^(https?://|/)#', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . app_url($path));
    }
    exit;
}

/** Require session user; optional restrict to user_type (CUSTOMER, TRADER). */
function require_login($types = [])
{
    if (!isset($_SESSION['user'])) {
        redirect_to('customer/login.php');
    }
    if ($types && !in_array($_SESSION['user']['user_type'], $types, true)) {
        redirect_to('customer/home.php');
    }
}

/** Get or create ACTIVE basket for customer; returns basket_id. */
function ensure_customer_basket($userId)
{
    $basket = fetch_one('SELECT basket_id, basket_status FROM basket WHERE user_id = :uid', ['uid' => $userId]);
    if (!$basket) {
        $bid = next_id('basket', 'basket_id', 'B');
        execute_sql('INSERT INTO basket(basket_id, user_id, basket_status) VALUES(:bid, :uid, \'ACTIVE\')', ['bid' => $bid, 'uid' => $userId]);
        return $bid;
    }
    if ($basket['basket_status'] !== 'ACTIVE') {
        execute_sql('DELETE FROM basket_item WHERE basket_id = :bid', ['bid' => $basket['basket_id']]);
        execute_sql('UPDATE basket SET basket_status = \'ACTIVE\' WHERE basket_id = :bid', ['bid' => $basket['basket_id']]);
    }
    return $basket['basket_id'];
}

/** Get or create wishlist for customer; returns wishlist_id. */
function ensure_customer_wishlist($userId)
{
    $wishlist = fetch_one('SELECT wishlist_id FROM wishlist WHERE user_id = :uid', ['uid' => $userId]);
    if ($wishlist) return $wishlist['wishlist_id'];
    $wid = next_id('wishlist', 'wishlist_id', 'W');
    execute_sql('INSERT INTO wishlist(wishlist_id, user_id) VALUES(:wid, :uid)', ['wid' => $wid, 'uid' => $userId]);
    return $wid;
}

/** Validate collection slot (Wed–Fri, capacity); returns slot row or false. */
function is_allowed_collection_slot($slotId)
{
    $slot = fetch_one("SELECT collection_slot_id, TO_CHAR(collection_date,'DY','NLS_DATE_LANGUAGE=ENGLISH') day_code, time_range, current_orders, max_orders FROM collection_slot WHERE collection_slot_id = :sid AND collection_date >= TRUNC(SYSDATE + 1)", ['sid' => $slotId]);
    if (!$slot) return false;
    $day = trim($slot['day_code']);
    if (!in_array($day, ['WED', 'THU', 'FRI'], true)) return false;
    if (!in_array($slot['time_range'], ['10-13', '13-16', '16-19'], true)) return false;
    if ((int)$slot['current_orders'] >= (int)$slot['max_orders']) return false;
    return $slot;
}

/** Check whether a product is on the customer's wishlist. */
function in_wishlist($userId, $productId)
{
    $result = fetch_one("
        SELECT 1 
        FROM wishlist w 
        JOIN wishlist_item wi ON w.wishlist_id = wi.wishlist_id 
        WHERE w.user_id = :uid AND wi.product_id = :pid
    ", [
        'uid' => $userId,
        'pid' => $productId
    ]);

    return $result !== null;
}

/** @deprecated Use app_url() instead */
define('ROOT_PREFIX', base_path());

