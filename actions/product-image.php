<?php
/**
 * Triangle Mart - Product image stream
 *
 * Outputs product image binary from database BLOB or fallback placeholder.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$id = $_GET['id'] ?? '';

// --- Stream image BLOB or return placeholder ---
if ($id === '') {
    http_response_code(404);
    exit;
}

$conn = db_connect();

$sql = "
    SELECT
        product_image,
        product_image_mime
    FROM product
    WHERE product_id = :id
    AND product_image IS NOT NULL
";

$stmt = oci_parse($conn, $sql);

if (!$stmt) {
    $e = oci_error($conn);
    http_response_code(500);
    exit('SQL parse error: ' . htmlentities($e['message']));
}

oci_bind_by_name($stmt, ':id', $id);

$ok = oci_execute($stmt);

if (!$ok) {
    $e = oci_error($stmt);
    http_response_code(500);
    exit('SQL error: ' . htmlentities($e['message']));
}

$row = oci_fetch_assoc($stmt);

if (!$row || empty($row['PRODUCT_IMAGE'])) {
    oci_free_statement($stmt);
    oci_close($conn);
    http_response_code(404);
    exit;
}

$contentType = $row['PRODUCT_IMAGE_MIME'] ?: 'image/jpeg';

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');

echo $row['PRODUCT_IMAGE']->load();

oci_free_statement($stmt);
oci_close($conn);

exit;
