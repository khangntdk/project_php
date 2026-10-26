<?php
declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <section>
            <a class="brand footer-brand" href="<?= e(BASE_URL) ?>/index.php">
                <span class="brand-mark">T</span><span>Tech<span class="brand-accent">Zone</span></span>
            </a>
            <p>Thiết bị công nghệ chính hãng, dịch vụ tận tâm và thông tin minh bạch.</p>
        </section>
        <section>
            <h2>Liên hệ</h2>
            <p>Hotline: <a href="tel:19001234">1900 1234</a><br>Email: <a href="mailto:support@techzone.local">support@techzone.local</a></p>
        </section>
        <section>
            <h2>Hỗ trợ khách hàng</h2>
            <p><a href="<?= e(BASE_URL) ?>/orders-history.php">Tra cứu đơn hàng</a><br>
                <a href="<?= e(BASE_URL) ?>/products.php">Sản phẩm</a></p>
        </section>
        <section>
            <h2>Chính sách</h2>
            <p>Bảo hành chính hãng<br>Đổi trả theo điều kiện sản phẩm<br>Thanh toán COD hoặc chuyển khoản thủ công</p>
        </section>
    </div>
    <div class="footer-bottom"><div class="container">© <?= date('Y') ?> TechZone. Thông tin và giá bán được xác nhận khi đặt hàng.</div></div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/main.js" defer></script>
</body>
</html>
