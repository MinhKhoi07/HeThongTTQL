<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Thống kê kinh doanh</h3>
        <p class="text-muted mb-0">Tổng quan về doanh thu và hoạt động bán hàng</p>
    </div>
    <div>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-success active">Tuần này</button>
            <button type="button" class="btn btn-outline-secondary">Tháng này</button>
            <button type="button" class="btn btn-outline-secondary">Năm nay</button>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fas fa-arrow-up"></i> +12.5%</span>
            </div>
            <p class="text-muted mb-1">Tổng doanh thu</p>
            <h2 class="fw-bold mb-0">43.200.000 <span class="text-decoration-underline">đ</span></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fas fa-arrow-up"></i> +5.2%</span>
            </div>
            <p class="text-muted mb-1">Tổng đơn hàng</p>
            <h2 class="fw-bold mb-0">1,245</h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon" style="background-color: #f3e8ff; color: #7e22ce;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2"><i class="fas fa-arrow-down"></i> -2.1%</span>
            </div>
            <p class="text-muted mb-1">Doanh thu trung bình/ngày</p>
            <h2 class="fw-bold mb-0">6.171.429 <span class="text-decoration-underline">đ</span></h2>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Biểu đồ doanh thu</h5>
                <a href="#" class="text-success text-decoration-none fw-medium">Xem chi tiết <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <canvas id="revenueChart" style="max-height: 250px;"></canvas>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card p-4 h-100">
            <h5 class="fw-bold mb-4">Sản phẩm bán chạy</h5>
            
            <div class="d-flex align-items-center mb-4">
                <div class="bg-warning bg-opacity-25 text-warning rounded p-2 me-3 fw-bold" style="width: 35px; text-align: center;">#1</div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Mì tôm Hảo Hảo chua cay</h6>
                    <small class="text-muted">Thực phẩm</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">1250</div>
                    <small class="text-success">đã bán</small>
                </div>
            </div>
            
            <div class="d-flex align-items-center mb-4">
                <div class="bg-secondary bg-opacity-25 text-secondary rounded p-2 me-3 fw-bold" style="width: 35px; text-align: center;">#2</div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Nước khoáng Lavie 500ml</h6>
                    <small class="text-muted">Đồ uống</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">850</div>
                    <small class="text-success">đã bán</small>
                </div>
            </div>
            
            <div class="d-flex align-items-center mb-4">
                <div class="bg-danger bg-opacity-10 text-danger rounded p-2 me-3 fw-bold" style="width: 35px; text-align: center;">#3</div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Nước ngọt Coca Cola...</h6>
                    <small class="text-muted">Đồ uống</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">620</div>
                    <small class="text-success">đã bán</small>
                </div>
            </div>

            <div class="d-flex align-items-center">
                <div class="bg-light text-muted rounded p-2 me-3 fw-bold" style="width: 35px; text-align: center;">#4</div>
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Sữa chua Vinamilk</h6>
                    <small class="text-muted">Sữa</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">430</div>
                    <small class="text-success">đã bán</small>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('revenueChart').getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');   
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['0', '1M', '2.5M', '5M', '7.5M', '10M'],
            datasets: [{
                label: 'Doanh thu',
                data: [4500000, 5200000, 4000000, 6200000, 6000000, 8500000],
                borderColor: '#10b981',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { borderDash: [5, 5], color: '#e5e7eb' },
                    ticks: { callback: function(value) { return value / 1000000 + 'M'; } }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>