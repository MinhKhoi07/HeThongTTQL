<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Kiểm tra tồn kho</h3>
        <p class="text-muted mb-0">Quản lý và theo dõi số lượng hàng hóa trong kho</p>
    </div>
    <div>
        <button class="btn btn-outline-secondary me-2"><i class="fas fa-file-export me-2"></i> Xuất báo cáo</button>
        <button class="btn btn-custom"><i class="fas fa-plus me-2"></i> Thêm sản phẩm mới</button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border border-primary border-opacity-25">
            <div>
                <p class="text-muted mb-1">Tổng sản phẩm</p>
                <h3 class="fw-bold mb-0">8</h3>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-filter"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border border-danger border-opacity-25">
            <div>
                <p class="text-muted mb-1">Sắp hết hàng</p>
                <h3 class="fw-bold mb-0 text-danger">0</h3>
            </div>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 d-flex flex-row align-items-center justify-content-between border border-success border-opacity-25 shadow-sm">
            <div>
                <p class="text-muted mb-1">Tình trạng kho</p>
                <h3 class="fw-bold mb-0 text-success">Tốt</h3>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="input-group" style="width: 400px;">
            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0 bg-light" placeholder="Tìm theo tên hoặc mã vạch...">
        </div>
        <div>
           <select class="form-select w-auto d-inline-block">
               <option>Tất cả</option>
           </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã SP / Barcode</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="fw-bold">P001</div>
                        <small class="text-muted">8934567890123</small>
                    </td>
                    <td>
                        <img src="https://via.placeholder.com/30" class="mr-2 rounded" alt=""> 
                        <span class="fw-bold">Nước khoáng Lavie 500ml</span>
                    </td>
                    <td><span class="badge bg-light text-secondary border">Đồ uống</span></td>
                    <td class="fw-bold">6.000 <span class="text-decoration-underline">đ</span></td>
                    <td class="fw-bold fs-5">150</td>
                    <td><span class="badge-active">Bình thường</span></td>
                </tr>
                <tr>
                    <td>
                        <div class="fw-bold">P002</div>
                        <small class="text-muted">8934567890124</small>
                    </td>
                    <td>
                        <img src="https://via.placeholder.com/30" class="mr-2 rounded" alt=""> 
                        <span class="fw-bold">Bánh mì sandwich</span>
                    </td>
                    <td><span class="badge bg-light text-secondary border">Thực phẩm</span></td>
                    <td class="fw-bold">15.000 <span class="text-decoration-underline">đ</span></td>
                    <td class="fw-bold fs-5">25</td>
                    <td><span class="badge bg-warning bg-opacity-25 text-warning px-3 py-1 rounded-pill">Cảnh báo</span></td>
                </tr>
                <tr>
                    <td>
                        <div class="fw-bold">P003</div>
                        <small class="text-muted">8934567890125</small>
                    </td>
                    <td>
                        <img src="https://via.placeholder.com/30" class="mr-2 rounded" alt=""> 
                        <span class="fw-bold">Mì tôm Hảo Hảo chua cay</span>
                    </td>
                    <td><span class="badge bg-light text-secondary border">Thực phẩm</span></td>
                    <td class="fw-bold">4.000 <span class="text-decoration-underline">đ</span></td>
                    <td class="fw-bold fs-5">300</td>
                    <td><span class="badge-active">Bình thường</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>