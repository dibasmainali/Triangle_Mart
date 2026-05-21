<?php
/**
 * Triangle Mart - Category image stream
 *
 * Outputs category image from database BLOB or default placeholder.
 * Access: public.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$category_id = $_GET['id'] ?? '';

if (empty($category_id)) {
    header('HTTP/1.0 404 Not Found');
    echo "No category ID provided";
    exit;
}

// Get direct connection for BLOB retrieval
$conn = db_connect();

$sql = "SELECT Category_Image, Category_Image_Mime, Category_Image_Name, Category_Name 
        FROM Category 
        WHERE Category_Id = :id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':id', $category_id);
oci_execute($stmt);

if ($row = oci_fetch_assoc($stmt)) {
    // Check if image exists in database
    if (isset($row['CATEGORY_IMAGE']) && !is_null($row['CATEGORY_IMAGE'])) {
        // Get the LOB descriptor
        $lob = $row['CATEGORY_IMAGE'];
        
        // Check if LOB has data
        $size = $lob->size();
        if ($size > 0) {
            $image_data = $lob->load();
            $mime_type = $row['CATEGORY_IMAGE_MIME'] ?? 'image/jpeg';
            
            // Clear any output buffers
            if (ob_get_level()) ob_end_clean();
            
            // Set headers
            header("Content-Type: $mime_type");
            header("Content-Length: " . $size);
            header("Cache-Control: public, max-age=86400");
            header("Content-Disposition: inline; filename=\"" . $row['CATEGORY_IMAGE_NAME'] . "\"");
            
            // Output image data
            echo $image_data;
            
            oci_free_statement($stmt);
            oci_close($conn);
            exit;
        } else {
            // LOB is empty
            error_log("Image LOB is empty for category: $category_id");
        }
    } else {
        error_log("No image data found for category: $category_id");
    }
} else {
    error_log("Category not found: $category_id");
}

oci_free_statement($stmt);
oci_close($conn);

// If no image found, return a colored placeholder with category name
$category_name = $row['CATEGORY_NAME'] ?? 'Category';
$colors = [
    'CAT001' => ['r' => 231, 'g' => 76, 'b' => 60],   // Meat - Red
    'CAT002' => ['r' => 46, 'g' => 204, 'b' => 113],  // Vegetables - Green
    'CAT003' => ['r' => 52, 'g' => 152, 'b' => 219],  // Seafood - Blue
    'CAT004' => ['r' => 241, 'g' => 196, 'b' => 15],  // Bakery - Yellow
    'CAT005' => ['r' => 155, 'g' => 89, 'b' => 182],  // Deli - Purple
];

$color = $colors[$category_id] ?? ['r' => 149, 'g' => 165, 'b' => 166];

// Create a simple PNG placeholder
$width = 400;
$height = 300;
$image = imagecreatetruecolor($width, $height);

// Allocate colors
$bg_color = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);
$text_color = imagecolorallocate($image, 255, 255, 255);
$dark_color = imagecolorallocate($image, 
    max(0, $color['r'] - 50), 
    max(0, $color['g'] - 50), 
    max(0, $color['b'] - 50)
);

// Fill background
imagefill($image, 0, 0, $bg_color);

// Add a pattern of dots for texture
for ($i = 0; $i < 500; $i++) {
    $x = rand(0, $width);
    $y = rand(0, $height);
    imagesetpixel($image, $x, $y, $dark_color);
}

// Add emoji as text (using built-in font since TTF might not be available)
$icon = '📦';
if ($category_id == 'CAT001') $icon = '🥩';
elseif ($category_id == 'CAT002') $icon = '🥬';
elseif ($category_id == 'CAT003') $icon = '🐟';
elseif ($category_id == 'CAT004') $icon = '🥖';
elseif ($category_id == 'CAT005') $icon = '🥪';

// Draw a circle behind the icon
$circle_color = imagecolorallocate($image, 255, 255, 255);
imagefilledellipse($image, $width/2, $height/2 - 30, 80, 80, $circle_color);

// Output PNG
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
exit;
?>