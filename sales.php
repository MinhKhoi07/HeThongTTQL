<?php include 'includes/header.php'; ?>

<!-- No general container padding to allow full width for POS -->
<div class="pos-layout">
    <!-- Products Section -->
    <div class="pos-products">
        <div class="d-flex justify-content-between mb-4">
            <div class="input-group" style="width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 bg-light" placeholder="Tìm kiếm sản phẩm...">
            </div>
            <div>
                <button class="btn btn-custom ms-2"><i class="fas fa-expand"></i></button>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success">Tất cả</button>
                <button type="button" class="btn btn-outline-secondary bg-light text-dark">Đồ uống</button>
                <button type="button" class="btn btn-outline-secondary bg-light text-dark">Thực phẩm</button>
                <button type="button" class="btn btn-outline-secondary bg-light text-dark">Sữa</button>
                <button type="button" class="btn btn-outline-secondary bg-light text-dark">Ăn vặt</button>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
            <!-- Product Item -->
            <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/e0f2fe/1f2937?text=Water" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Nước khoáng Lavie...</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">6.000 đ</span>
                        <small class="text-muted">Kho: 150</small>
                    </div>
                </div>
            </div>
            <!-- Product Item -->
            <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/fef3c7/1f2937?text=Bread" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Bánh mì sandwich</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">15.000 đ</span>
                        <small class="text-muted">Kho: 25</small>
                    </div>
                </div>
            </div>
            <!-- Product Item -->
            <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/fee2e2/1f2937?text=Noodle" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Mì tôm Hảo Hảo...</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">4.000 đ</span>
                        <small class="text-muted">Kho: 300</small>
                    </div>
                </div>
            </div>
            <!-- Product Item -->
            <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/e0e7ff/1f2937?text=Milk" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Sữa chua Vinamilk</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">7.000 đ</span>
                        <small class="text-muted">Kho: 80</small>
                    </div>
                </div>
            </div>
            <!-- Product Item -->
            <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/f3f4f6/1f2937?text=Snack" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Snack khoai tây...</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">12.000 đ</span>
                        <small class="text-muted">Kho: 45</small>
                    </div>
                </div>
            </div>
             <!-- Product Item -->
             <div class="col">
                <div class="product-card">
                    <img src="https://via.placeholder.com/150/fee2e2/1f2937?text=Cola" alt="Sản phẩm" class="img-fluid rounded">
                    <h6 class="mt-2 mb-1 text-truncate">Nước ngọt Coca...</h6>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-success">10.000 đ</span>
                        <small class="text-muted">Kho: 120</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Section -->
    <div class="pos-cart">
        <div class="p-3 border-bottom bg-light rounded-top d-flex align-items-center text-success fw-bold">
            <i class="fas fa-file-invoice me-2"></i> Đơn hàng hiện tại
        </div>
        <div class="cart-items d-flex flex-column align-items-center justify-content-center text-center text-muted">
            <i class="fas fa-shopping-cart fa-3x mb-3" style="color: #e5e7eb;"></i>
            <p>Chưa có sản phẩm nào</p>
        </div>
        <div class="cart-summary">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Tạm tính (0 sản phẩm)</span>
                <span>0 đ</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Chiết khấu</span>
                <span>0 đ</span>
            </div>
            <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                <span class="text-muted">Thuế VAT (8%)</span>
                <span>0 đ</span>
            </div>
            <div class="d-flex justify-content-between mb-3 align-items-center">
                <span class="fw-bold fs-5">Tổng cộng</span>
                <span class="fw-bold fs-4 text-success">0 <span class="text-decoration-underline">đ</span></span>
            </div>
            <button class="btn btn-secondary w-100 py-3 fw-bold disabled" style="background-color: #e2e8f0; color: #6b7280; border: none;">
                <i class="fas fa-credit-card me-2"></i> THANH TOÁN
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>