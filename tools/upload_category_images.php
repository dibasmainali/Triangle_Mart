<?php
/**
 * Triangle Mart - Upload category images (admin tool)
 *
 * Bulk upload or update category images in the database.
 * Access: development/admin use.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$message = '';
$message_type = '';

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['category_image'])) {
    $category_id = $_POST['category_id'];
    $category_name = $_POST['category_name'];
    
    if ($_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
        $image_data = file_get_contents($_FILES['category_image']['tmp_name']);
        $image_name = $_FILES['category_image']['name'];
        $image_mime = $_FILES['category_image']['type'];
        
        // Validate image type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
        if (in_array($image_mime, $allowed_types)) {
            
            // Get direct connection for BLOB handling
            $conn = db_connect();
            
            // Update category with image
            $sql = "UPDATE Category SET 
                    Category_Image = EMPTY_BLOB(), 
                    Category_Image_Name = :name,
                    Category_Image_Mime = :mime,
                    Category_Image_Date = SYSDATE
                    WHERE Category_Id = :id
                    RETURNING Category_Image INTO :blob";
            
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':name', $image_name);
            oci_bind_by_name($stmt, ':mime', $image_mime);
            oci_bind_by_name($stmt, ':id', $category_id);
            
            $blob = oci_new_descriptor($conn, OCI_D_LOB);
            oci_bind_by_name($stmt, ':blob', $blob, -1, OCI_B_BLOB);
            
            $result = oci_execute($stmt, OCI_DEFAULT);
            if ($result) {
                $blob->save($image_data);
                oci_commit($conn);
                $message = "✓ Image uploaded successfully for $category_name!";
                $message_type = "success";
            } else {
                oci_rollback($conn);
                $error = oci_error($stmt);
                $message = "Failed to upload image for $category_name. Error: " . ($error['message'] ?? 'Unknown error');
                $message_type = "error";
            }
            $blob->free();
            oci_free_statement($stmt);
            oci_close($conn);
        } else {
            $message = "Only JPEG, PNG, GIF, and WEBP images are allowed.";
            $message_type = "error";
        }
    } else {
        $message = "Please select a valid image file.";
        $message_type = "error";
    }
}

// Handle image removal
if (isset($_POST['remove_image'])) {
    $category_id = $_POST['category_id'];
    $category_name = $_POST['category_name'];
    
    $conn = db_connect();
    
    $sql = "UPDATE Category SET 
            Category_Image = NULL, 
            Category_Image_Name = NULL,
            Category_Image_Mime = NULL,
            Category_Image_Date = NULL
            WHERE Category_Id = :id";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $category_id);
    
    if (oci_execute($stmt)) {
        oci_commit($conn);
        $message = "✓ Image removed for $category_name!";
        $message_type = "success";
    } else {
        $error = oci_error($stmt);
        $message = "Failed to remove image. Error: " . ($error['message'] ?? 'Unknown error');
        $message_type = "error";
    }
    oci_free_statement($stmt);
    oci_close($conn);
}

// Fetch all categories using your existing fetch_all function
$categories = fetch_all("
    SELECT 
        c.Category_Id,
        c.Category_Name,
        c.Category_Image_Name,
        c.Category_Image_Mime,
        c.Category_Image_Date,
        COUNT(p.Product_Id) as product_count
    FROM Category c 
    LEFT JOIN Product p ON c.Category_Id = p.Category_Id 
    GROUP BY c.Category_Id, c.Category_Name, c.Category_Image_Name, 
             c.Category_Image_Mime, c.Category_Image_Date
    ORDER BY c.Category_Name
");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Category Images</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .category-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .image-preview {
            width: 100%;
            height: 250px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border-bottom: 1px solid #eee;
        }
        
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image {
            text-align: center;
            color: #999;
            font-size: 48px;
        }
        
        .category-info {
            padding: 20px;
        }
        
        .category-info h3 {
            margin-bottom: 5px;
            color: #333;
            font-size: 20px;
        }
        
        .category-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .image-details {
            font-size: 12px;
            color: #888;
            margin: 10px 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .upload-form {
            margin-top: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-label {
            display: block;
            padding: 10px;
            background: #f0f0f0;
            border: 2px dashed #ccc;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .file-label:hover {
            background: #e8e8e8;
            border-color: #999;
        }
        
        button {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-upload {
            background: #28a745;
            color: white;
        }
        
        .btn-upload:hover {
            background: #218838;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            margin-top: 10px;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            body {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📷 Upload Category Images</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <div class="category-card">
                    <div class="image-preview">
                        <?php if (!empty($cat['category_image_name'])): ?>
                            <img src="display_category_image.php?id=<?= urlencode($cat['category_id']) ?>" 
                                 alt="<?= htmlspecialchars($cat['category_name']) ?>">
                        <?php else: ?>
                            <div class="no-image">
                                📷
                                <div style="font-size: 14px; margin-top: 10px;">No image uploaded</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="category-info">
                        <h3><?= htmlspecialchars($cat['category_name']) ?></h3>
                        <p><?= $cat['product_count'] ?> products</p>
                        
                        <?php if (!empty($cat['category_image_name'])): ?>
                            <div class="image-details">
                                <strong>📄 Image:</strong> <?= htmlspecialchars($cat['category_image_name']) ?><br>
                                <strong>📅 Uploaded:</strong> <?= date('F j, Y', strtotime($cat['category_image_date'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['category_id']) ?>">
                            <input type="hidden" name="category_name" value="<?= htmlspecialchars($cat['category_name']) ?>">
                            
                            <div class="file-input-wrapper">
                                <input type="file" name="category_image" id="file_<?= $cat['category_id'] ?>" 
                                       accept="image/jpeg,image/png,image/gif,image/webp" 
                                       onchange="this.form.submit()">
                                <label for="file_<?= $cat['category_id'] ?>" class="file-label">
                                    📁 Choose Image (JPG, PNG, GIF, WEBP)
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-upload">Upload Image</button>
                        </form>
                        
                        <?php if (!empty($cat['category_image_name'])): ?>
                            <form method="POST" onsubmit="return confirm('Remove image from <?= htmlspecialchars($cat['category_name']) ?>?')">
                                <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['category_id']) ?>">
                                <input type="hidden" name="category_name" value="<?= htmlspecialchars($cat['category_name']) ?>">
                                <button type="submit" name="remove_image" class="btn-remove">🗑 Remove Image</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px;">
            <hr>
            <p style="color: #666;">
                <strong>Tip:</strong> Images will automatically resize to fit the category cards.<br>
                Recommended size: 400x300 pixels for best results.
            </p>
            <p style="margin-top: 15px;">
                <a href="<?= app_url('customer/categories.php') ?>" style="color: #007bff; text-decoration: none;">← Back to Categories Page</a>
            </p>
        </div>
    </div>
</body>
</html>