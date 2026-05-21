<section class="search-section">
    <form action="<?= app_url('customer/products.php') ?>" method="get" class="search-form">
        <input type="text" name="q" placeholder="Search Product e.g. sourdough, salmon..." value="<?= e($_GET['q'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
    <div class="filters"><span>Browse By</span><a class="filter-btn" href="<?= app_url('customer/shops.php') ?>">Shop</a><a class="filter-btn" href="<?= app_url('customer/categories.php') ?>">Category</a></div>
</section>
