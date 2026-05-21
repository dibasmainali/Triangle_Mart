<?php 
require_once dirname(__DIR__) . '/includes/config.php';

// --- Load all categories from database ---

$cats = fetch_all("
    SELECT 
        c.category_id,
        c.category_name,
        c.parent_category_id,
        c.category_image_name,
        c.category_image_mime,
        COUNT(p.product_id) product_count 
    FROM category c 
    LEFT JOIN product p ON c.category_id = p.category_id 
    GROUP BY c.category_id, c.category_name, c.parent_category_id, 
             c.category_image_name, c.category_image_mime
    ORDER BY c.category_name
"); 
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Triangle Mart</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/styles.css') ?>">
    <style>
        /* Additional styles specific to categories page */
        .category-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
            background: #f5f5f5;
            display: block;
        }
        
        .image-placeholder {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-size: 64px;
            border-radius: 8px 8px 0 0;
            color: white;
        }
        
        /* Category-specific gradient backgrounds */
        .image-placeholder.cat-CAT001 { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .image-placeholder.cat-CAT002 { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .image-placeholder.cat-CAT003 { background: linear-gradient(135deg, #3498db, #2980b9); }
        .image-placeholder.cat-CAT004 { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .image-placeholder.cat-CAT005 { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .card-info {
            padding: 16px;
            text-align: center;
        }
        
        .card-info h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #333;
        }
        
        .card-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .category-image, .image-placeholder {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__) . '/includes/search.php'; ?>
    
    <main class="page-content">
        <h1 class="page-title">Browse by Category</h1>
        <div class="product-grid">
            <?php foreach ($cats as $c): ?>
                <div class="card">
                    <a href="<?= app_url('customer/products.php?category=' . urlencode($c['category_id'])) ?>">
                        <?php if (!empty($c['category_image_name'])): ?>
                            <img 
                                src="<?= app_url('actions/display_category_image.php?id=' . urlencode($c['category_id'])) ?>" 
                                alt="<?= e($c['category_name']) ?>"
                                class="category-image"
                                loading="lazy"
                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder cat-<?= e($c['category_id']) ?>\'><?= getCategoryIcon($c['category_id']) ?></div>';"
                            >
                        <?php else: ?>
                            <div class="image-placeholder cat-<?= e($c['category_id']) ?>">
                                <?= getCategoryIcon($c['category_id']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-info">
                            <h3><?= e($c['category_name']) ?></h3>
                            <p><?= (int)$c['product_count'] ?> products</p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    
    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>

<?php 
// Helper function to get category icon based on ID
function getCategoryIcon($categoryId) {
    $icons = [
        'CAT001' => '🥩',  // Meat
        'CAT002' => '🥬',  // Fruit and Vegetables
        'CAT003' => '🐟',  // Fish and Seafood
        'CAT004' => '🥖',  // Bakery
        'CAT005' => '🥪',  // Deli
    ];
    return $icons[$categoryId] ?? '📦';
}
?>