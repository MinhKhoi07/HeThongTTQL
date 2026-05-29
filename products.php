<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Quản lý sản phẩm</h3>
        <p class="text-muted mb-0">Quản lý thông tin, giá cả và mã vạch sản phẩm</p>
    </div>
    <div>
        <button class="btn btn-custom"><i class="fas fa-plus me-2"></i> Thêm sản phẩm mới</button>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="input-group" style="width: 300px;">
            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0 bg-light" placeholder="Tìm kiếm theo tên hoặc mã vạch...">
        </div>
        <div class="d-flex gap-2">
            <select class="form-select w-auto">
                <option>Tất cả</option>
                <option>Đồ uống</option>
                <option>Thực phẩm</option>
            </select>
            <button class="btn btn-outline-secondary"><i class="fas fa-filter me-2"></i> Lọc thêm</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Hình ảnh</th>
                    <th>Mã / Mã vạch</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><img src="https://via.placeholder.com/40" class="rounded" alt="Sp"></td>
                    <td>
                        <div class="fw-bold">P001</div>
                        <small class="text-muted">8934567890123</small>
                    </td>
                    <td class="fw-bold">Nước khoáng Lavie 500ml</td>
                    <td><span class="text-muted">Đồ uống</span></td>
                    <td class="fw-bold">6.000 <span class="text-decoration-underline">đ</span></td>
                    <td><span class="fw-bold text-primary">150</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-edit"></i></button>
                        <button class="action-btn"><i class="far fa-trash-alt"></i></button>
                    </td>
                </tr>
                <tr>
                    <td><img src="https://via.placeholder.com/40" class="rounded" alt="Sp"></td>
                    <td>
                        <div class="fw-bold">P002</div>
                        <small class="text-muted">8934567890124</small>
                    </td>
                    <td class="fw-bold">Bánh mì sandwich</td>
                    <td><span class="text-muted">Thực phẩm</span></td>
                    <td class="fw-bold">15.000 <span class="text-decoration-underline">đ</span></td>
                    <td><span class="fw-bold text-warning">25</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-edit"></i></button>
                        <button class="action-btn"><i class="far fa-trash-alt"></i></button>
                    </td>
                </tr>
                <tr>
                    <td><img src="https://via.placeholder.com/40" class="rounded" alt="Sp"></td>
                    <td>
                        <div class="fw-bold">P003</div>
                        <small class="text-muted">8934567890125</small>
                    </td>
                    <td class="fw-bold">Mì tôm Hảo Hảo chua cay</td>
                    <td><span class="text-muted">Thực phẩm</span></td>
                    <td class="fw-bold">4.000 <span class="text-decoration-underline">đ</span></td>
                    <td><span class="fw-bold text-success">300</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-edit"></i></button>
                        <button class="action-btn"><i class="far fa-trash-alt"></i></button>
                    </td>
                </tr>
                <tr>
                    <td><img src="https://via.placeholder.com/40" class="rounded" alt="Sp"></td>
                    <td>
                        <div class="fw-bold">P004</div>
                        <small class="text-muted">8934567890126</small>
                    </td>
                    <td class="fw-bold">Sữa chua Vinamilk</td>
                    <td><span class="text-muted">Sữa</span></td>
                    <td class="fw-bold">7.000 <span class="text-decoration-underline">đ</span></td>
                    <td><span class="fw-bold text-primary">80</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-edit"></i></button>
                        <button class="action-btn"><i class="far fa-trash-alt"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>