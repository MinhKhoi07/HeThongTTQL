<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Nhập hàng hóa</h3>
        <p class="text-muted mb-0">Quản lý phiếu nhập hàng từ nhà cung cấp</p>
    </div>
    <div>
        <button class="btn btn-custom"><i class="fas fa-plus me-2"></i> Tạo phiếu nhập</button>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold m-0">Lịch sử nhập hàng</h5>
        <div class="input-group" style="width: 250px;">
            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0 bg-light" placeholder="Tìm mã phiếu hoặc nhà cc...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã phiếu</th>
                    <th>Ngày nhập</th>
                    <th>Nhà cung cấp</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-bold text-success">IMP1001</td>
                    <td class="text-muted">27/05/2026</td>
                    <td class="fw-medium">NPP Đại Phát</td>
                    <td class="fw-bold">4.500.000 <span class="text-decoration-underline text-muted">đ</span></td>
                    <td><span class="badge-active bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Hoàn thành</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-file-alt"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold text-success">IMP1002</td>
                    <td class="text-muted">25/05/2026</td>
                    <td class="fw-medium">Công ty Vinamilk</td>
                    <td class="fw-bold">2.100.000 <span class="text-decoration-underline text-muted">đ</span></td>
                    <td><span class="badge-active bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Hoàn thành</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-file-alt"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold text-success">IMP1003</td>
                    <td class="text-muted">20/05/2026</td>
                    <td class="fw-medium">NPP Tuấn Tú</td>
                    <td class="fw-bold">8.500.000 <span class="text-decoration-underline text-muted">đ</span></td>
                    <td><span class="badge-active bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Hoàn thành</span></td>
                    <td>
                        <button class="action-btn"><i class="far fa-file-alt"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>