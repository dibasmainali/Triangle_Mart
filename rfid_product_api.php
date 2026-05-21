<?php
header("Content-Type: application/json");

$conn = oci_connect("TRIANGLE_MART", "Trianglemart!", "localhost/FREEPDB1");

if (!$conn) {
    $e = oci_error();
    echo json_encode(["status" => "error", "message" => $e['message']]);
    exit;
}

$product_id  = $_REQUEST['product_id'] ?? '';
$name        = $_REQUEST['name'] ?? '';
$price       = $_REQUEST['price'] ?? 0;
$stock       = $_REQUEST['stock'] ?? 0;
$shop_id     = $_REQUEST['shop_id'] ?? '';
$category_id = $_REQUEST['category_id'] ?? '';

if ($product_id == '' || $name == '' || $shop_id == '' || $category_id == '') {
    echo json_encode(["status" => "error", "message" => "Missing required data"]);
    exit;
}

$check_sql = "SELECT COUNT(*) AS TOTAL FROM Product WHERE Product_Id = :product_id";

$stmt = oci_parse($conn, $check_sql);

oci_bind_by_name($stmt, ":product_id", $product_id);

oci_execute($stmt);

$row = oci_fetch_assoc($stmt);

if ($row['TOTAL'] > 0) {

    $sql = "UPDATE Product
            SET Stock_Available = Stock_Available + :stock,
                Updated_Date = SYSDATE
            WHERE Product_Id = :product_id";

} else {

    $sql = "INSERT INTO Product
            (Product_Id, Shop_Id, Category_Id, Name, Description, Price,
             Quantity_Per_Item, Stock_Available, Min_Order, Max_Order,
             Allergy_Info, Product_Status, Created_Date, Updated_Date)
            VALUES
            (:product_id, :shop_id, :category_id, :name,
             'RFID Added Product',
             :price,
             1,
             :stock,
             1,
             10,
             NULL,
             'ACTIVE',
             SYSDATE,
             SYSDATE)";
}

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ":product_id", $product_id);
oci_bind_by_name($stmt, ":stock", $stock);

if ($row['TOTAL'] == 0) {

    oci_bind_by_name($stmt, ":shop_id", $shop_id);
    oci_bind_by_name($stmt, ":category_id", $category_id);
    oci_bind_by_name($stmt, ":name", $name);
    oci_bind_by_name($stmt, ":price", $price);
}

if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {

    echo json_encode([
        "status" => "success",
        "message" => "Product inserted or stock updated"
    ]);

} else {

    $e = oci_error($stmt);

    echo json_encode([
        "status" => "error",
        "message" => $e['message']
    ]);
}

oci_close($conn);
?>