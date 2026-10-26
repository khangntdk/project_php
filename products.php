<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Sản phẩm';
$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$categorySlug = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
$brandId = filter_var($_GET['brand'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$minPrice = isset($_GET['min_price']) && is_string($_GET['min_price']) && $_GET['min_price'] !== ''
    ? filter_var($_GET['min_price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
    : null;
$maxPrice = isset($_GET['max_price']) && is_string($_GET['max_price']) && $_GET['max_price'] !== ''
    ? filter_var($_GET['max_price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
    : null;
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'price_asc' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) ASC, p.created_at DESC',
    'price_desc' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) DESC, p.created_at DESC',
];
$sort = isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']])
    ? $_GET['sort']
    : 'newest';
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = $requestedPage === false ? 1 : $requestedPage;
$perPage = 12;
$products = [];
$categories = [];
$brands = [];
$totalProducts = 0;
$totalPages = 1;
$filterError = '';
$databaseError = false;

if ($minPrice === false || $maxPrice === false) {
    $filterError = 'Vui lòng nhập khoảng giá là số tiền hợp lệ, không âm.';
    $minPrice = $minPrice === false ? null : $minPrice;
    $maxPrice = $maxPrice === false ? null : $maxPrice;
}
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    $filterError = 'Giá thấp nhất không thể lớn hơn giá cao nhất.';
}

try {
    $pdo = db();
    $categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
    $brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll();

    $where = ['p.status = :status'];
    $parameters = [':status' => 'active'];

    if ($query !== '') {
        $where[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku OR b.name LIKE :search_brand)';
        $search = '%' . $query . '%';
        $parameters[':search_name'] = $search;
        $parameters[':search_sku'] = $search;
        $parameters[':search_brand'] = $search;
    }
    if ($categorySlug !== '') {
        $where[] = 'c.slug = :category_slug';
        $parameters[':category_slug'] = $categorySlug;
    }
    if ($brandId !== false && $brandId !== null) {
        $where[] = 'p.brand_id = :brand_id';
        $parameters[':brand_id'] = $brandId;
    }
    if ($minPrice !== null) {
        $where[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) >= :min_price';
        $parameters[':min_price'] = $minPrice;
    }
    if ($maxPrice !== null) {
        $where[] = 'COALESCE(NULLIF(p.sale_price, 0), p.price) <= :max_price';
        $parameters[':max_price'] = $maxPrice;
    }

    $from = ' FROM products p
              INNER JOIN categories c ON c.id = p.category_id
              INNER JOIN brands b ON b.id = p.brand_id
              WHERE ' . implode(' AND ', $where);
    if ($filterError === '') {
        $countStatement = $pdo->prepare('SELECT COUNT(*)' . $from);
        $countStatement->execute($parameters);
        $totalProducts = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        $productStatement = $pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.image, p.stock,
                    c.name AS category_name, c.slug AS category_slug, b.name AS brand_name'
            . $from
            . ' ORDER BY ' . $sortOptions[$sort]
            . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $productStatement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $productStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $productStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $productStatement->execute();
        $products = $productStatement->fetchAll();
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('TechZone product listing query failed: ' . $exception->getMessage());
    $databaseError = true;
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <h1>Khám phá sản phẩm</h1>
                <p>Tìm thiết bị công nghệ phù hợp từ danh mục sản phẩm của TechZone.</p>
            </div>
        </div>
        <?php if ($databaseError): ?>
            <div class="alert alert-danger" role="alert">Không thể tải danh sách sản phẩm. Vui lòng kiểm tra MySQL và cấu hình cơ sở dữ liệu rồi thử tải lại.</div>
        <?php else: ?>
            <?php if ($filterError !== ''): ?>
                <div class="alert alert-warning" role="alert"><?= e($filterError) ?></div>
            <?php endif; ?>
            <div class="catalog-layout">
                <aside class="filter-panel">
                    <h2>Bộ lọc sản phẩm</h2>
                    <form method="get" action="<?= e(BASE_URL) ?>/products.php" class="filter-form">
                        <div>
                            <label for="filter-query" class="form-label">Từ khóa</label>
                            <input id="filter-query" class="form-control" type="search" name="q" maxlength="120" value="<?= e($query) ?>" placeholder="Tên hoặc mã sản phẩm">
                        </div>
                        <div>
                            <label for="filter-category" class="form-label">Danh mục</label>
                            <select id="filter-category" class="form-select" name="category">
                                <option value="">Tất cả danh mục</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filter-brand" class="form-label">Thương hiệu</label>
                            <select id="filter-brand" class="form-select" name="brand">
                                <option value="">Tất cả thương hiệu</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= (int) $brand['id'] ?>" <?= $brandId !== false && $brandId !== null && $brandId === (int) $brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Khoảng giá (₫)</label>
                            <div class="price-filter-row">
                                <input class="form-control" type="number" name="min_price" min="0" value="<?= e($minPrice ?? '') ?>" placeholder="Từ" aria-label="Giá thấp nhất">
                                <input class="form-control" type="number" name="max_price" min="0" value="<?= e($maxPrice ?? '') ?>" placeholder="Đến" aria-label="Giá cao nhất">
                            </div>
                        </div>
                        <div>
                            <label for="filter-sort" class="form-label">Sắp xếp</label>
                            <select id="filter-sort" class="form-select" name="sort">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                            </select>
                        </div>
                        <button class="btn btn-techzone w-100" type="submit">Áp dụng bộ lọc</button>
                        <a class="btn btn-outline-secondary w-100" href="<?= e(BASE_URL) ?>/products.php">Xóa bộ lọc</a>
                    </form>
                </aside>
                <div class="catalog-results">
                    <div class="catalog-summary">
                        <span>Tìm thấy <strong><?= $totalProducts ?></strong> sản phẩm</span>
                        <?php if ($query !== ''): ?><span>Từ khóa: “<?= e($query) ?>”</span><?php endif; ?>
                    </div>
                    <?php if ($products === []): ?>
                        <div class="empty-state">
                            <h2 class="h5">Không tìm thấy sản phẩm</h2>
                            <p class="mb-0">Thử đổi từ khóa hoặc điều chỉnh bộ lọc để xem thêm kết quả.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 g-lg-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col-6 col-xl-4">
                                    <?php require __DIR__ . '/includes/product-card.php'; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <?php
                            $paginationQuery = $_GET;
                            unset($paginationQuery['page']);
                            ?>
                            <nav class="mt-4" aria-label="Phân trang sản phẩm">
                                <ul class="pagination justify-content-center flex-wrap">
                                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                        <?php $paginationQuery['page'] = $page; ?>
                                        <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= e(BASE_URL) ?>/products.php?<?= e(http_build_query($paginationQuery)) ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
