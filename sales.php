<?php 
include 'connect.php'; 

// Xử lý Thanh Toán (Giảm tồn kho và Ghi doanh thu)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    if ($cart && is_array($cart)) {
        $tong_tien = 0;
        $tong_giam_gia = 0;
        $thanh_tien = 0;
        
        // Tính tổng
        foreach ($cart as $item) {
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            $original_price = isset($item['original_price']) ? (float)$item['original_price'] : $price;
            
            $thanh_tien += ($qty * $price);
            $tong_tien += ($qty * $original_price);
            $tong_giam_gia += ($qty * ($original_price - $price));
        }
        
        // Tạo bảng hoa_don nếu chưa có
        $conn->query("CREATE TABLE IF NOT EXISTS `hoa_don` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `ma_hoa_don` varchar(20) NOT NULL,
          `ngay_tao` datetime DEFAULT current_timestamp(),
          `tong_tien` decimal(15,2) NOT NULL DEFAULT 0.00,
          `tong_giam_gia` decimal(15,2) DEFAULT 0.00,
          `thanh_tien` decimal(15,2) NOT NULL DEFAULT 0.00,
          `id_nhan_vien` int(11) DEFAULT NULL,
          `ghi_chu` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `ma_hoa_don` (`ma_hoa_don`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci");
        
        // Lưu vào bảng hóa đơn
        $ma_hd = 'HD' . time();
        $id_nv = $_SESSION['user_id'] ?? null;
        $conn->query("INSERT INTO hoa_don (ma_hoa_don, tong_tien, tong_giam_gia, thanh_tien, id_nhan_vien) 
                      VALUES ('$ma_hd', $tong_tien, $tong_giam_gia, $thanh_tien, $id_nv)");
        
        // Giảm tồn kho
        foreach ($cart as $item) {
            $id_sp = (int)$item['id'];
            $qty = (int)$item['qty'];
            $conn->query("UPDATE ton_kho SET so_luong = GREATEST(so_luong - $qty, 0) WHERE id_san_pham = $id_sp");
        }
        
        $msg = "Thanh toán thành công. Mã HĐ: $ma_hd - Tổng: " . number_format($thanh_tien, 0, ',', '.') . "đ";
        if ($tong_giam_gia > 0) {
            $msg .= " (Tiết kiệm: " . number_format($tong_giam_gia, 0, ',', '.') . "đ)";
        }
    }
}

// Lấy danh sách sản phẩm với khuyến mãi đang áp dụng
$now = date('Y-m-d H:i:s');
$sql = "SELECT sp.id, sp.ten_san_pham, sp.gia_ban, sp.ma_vach, sp.ma_sku, sp.hinh_anh, sp.don_vi_tinh,
        km.muc_giam, km.ten_chuong_trinh, km.id as km_id
        FROM san_pham sp
        LEFT JOIN chi_tiet_khuyen_mai ctkm ON sp.id = ctkm.id_san_pham
        LEFT JOIN khuyen_mai km ON ctkm.id_khuyen_mai = km.id 
            AND '$now' BETWEEN km.ngay_bat_dau AND km.ngay_ket_thuc
        ORDER BY sp.id DESC";
$result = $conn->query($sql);
$products = [];
while ($row = $result->fetch_assoc()) {
    // Tính giá sau khuyến mãi nếu có
    if ($row['muc_giam'] > 0) {
        $row['gia_goc'] = $row['gia_ban'];
        $row['gia_ban'] = $row['gia_ban'] * (1 - $row['muc_giam'] / 100);
    }
    $products[] = $row;
}
$products_json = json_encode($products);

include 'includes/header.php'; 
?>

<div class="row">
    <!-- Cột trái: Danh sách Sản phẩm -->
    <div class="col-lg-7 col-xl-8 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Hệ thống phân phối (POS)</h4>
            <?php if(isset($msg)): ?>
                <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> <?= $msg ?></span>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="posSearch" class="form-control border-start-0" placeholder="Tìm kiếm sản phẩm theo tên hoặc mã vạch..." onkeyup="filterProducts()">
            </div>
        </div>

        <!-- Grid Sản phẩm -->
        <div class="row g-3" id="posProductList">
            <?php foreach($products as $p): ?>
            <div class="col-md-6 col-lg-4 product-item" data-name="<?= strtolower($p['ten_san_pham']) ?>" data-sku="<?= strtolower($p['ma_sku']) ?>" data-barcode="<?= strtolower($p['ma_vach']) ?>">
                <div class="card h-100 product-card shadow-sm border-0 <?= !empty($p['muc_giam']) ? 'border-danger' : '' ?>" onclick='addToCart(<?= json_encode($p) ?>)' style="cursor: pointer; transition: transform 0.2s; <?= !empty($p['muc_giam']) ? 'border-width: 2px !important;' : '' ?>">
                    <?php if (!empty($p['muc_giam'])): ?>
                        <div class="position-absolute top-0 end-0 m-2">
                            <span class="badge bg-danger fs-6">-<?= $p['muc_giam'] ?>%</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <?php if (!empty($p['hinh_anh'])): ?>
                            <img src="<?= htmlspecialchars($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_san_pham']) ?>" class="img-fluid mb-3 mx-auto" style="max-height: 100px; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-box fa-3x text-custom mb-3 opacity-50"></i>
                        <?php endif; ?>
                        <h6 class="fw-bold text-truncate" title="<?= $p['ten_san_pham'] ?>"><?= $p['ten_san_pham'] ?></h6>
                        <small class="text-muted mb-2 d-block"><?= $p['ma_sku'] ?> | <?= $p['ma_vach'] ?></small>
                        
                        <?php if (!empty($p['gia_goc'])): ?>
                            <div>
                                <small class="text-muted text-decoration-line-through d-block"><?= number_format($p['gia_goc'], 0, ',', '.') ?> đ</small>
                                <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ / <?= htmlspecialchars($p['don_vi_tinh']) ?></h5>
                            </div>
                        <?php else: ?>
                            <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ / <?= htmlspecialchars($p['don_vi_tinh']) ?></h5>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($products)): ?>
            <div class="col-12"><p class="text-muted text-center py-5">Chưa có sản phẩm nào. Hãy thêm ở Quản lý Sản phẩm.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cột phải: Giỏ hàng -->
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm border-0 h-100 d-flex flex-column">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-shopping-cart text-custom me-2"></i> Giỏ hàng (Đơn hàng)</h5>
            </div>
            
            <div class="card-body p-0 flex-grow-1" style="overflow-y: auto; max-height: 50vh;" id="cartContainer">
                <!-- Items giỏ hàng sẽ render tại đây -->
                <div class="text-center text-muted py-5" id="emptyCartMsg">
                    <i class="fas fa-cart-arrow-down fa-3x mb-3 opacity-25"></i>
                    <p>Giỏ hàng trống</p>
                </div>
            </div>

            <div class="card-footer bg-light border-top p-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tổng phụ:</span>
                    <span class="fw-bold" id="cartSubtotal">0 đ</span>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                    <span class="text-muted">Chiết khấu/Giảm giá:</span>
                    <span class="fw-bold text-success">- 0 đ</span>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <h5 class="fw-bold mb-0">THÀNH TIỀN:</h5>
                    <h4 class="fw-bold text-danger mb-0" id="cartTotal">0 đ</h4>
                </div>
                
                <form method="POST" id="checkoutForm" onsubmit="return handleCheckout()">
                    <input type="hidden" name="checkout" value="1">
                    <input type="hidden" name="cart_data" id="cartDataInput">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-custom btn-lg w-100 fs-5 fw-bold" id="btnCheckout" disabled>
                            <i class="fas fa-money-bill-wave me-2"></i> THANH TOÁN
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="fas fa-trash me-2"></i> Làm mới giỏ hàng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.product-card:hover { transform: translateY(-5px); border-color: #6C5CE7 !important; }
.cart-item { transition: all 0.3s; }
</style>

<script>
let cart = [];

function formatMoney(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " đ";
}

function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.ten_san_pham,
            price: parseFloat(product.gia_ban),
            qty: 1,
            unit: product.don_vi_tinh || 'SP',
            original_price: product.gia_goc ? parseFloat(product.gia_goc) : null,
            discount: product.muc_giam ? parseFloat(product.muc_giam) : 0,
            promo_name: product.ten_chuong_trinh || null
        });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
        renderCart();
    }
}

function clearCart() {
    if(confirm('Bạn muốn xoá trắng giỏ hàng không?')) {
        cart = [];
        renderCart();
    }
}

function renderCart() {
    const container = document.getElementById('cartContainer');
    const emptyMsg = document.getElementById('emptyCartMsg');
    const subtotalEl = document.getElementById('cartSubtotal');
    const totalEl = document.getElementById('cartTotal');
    const btnCheckout = document.getElementById('btnCheckout');
    const cartDataInput = document.getElementById('cartDataInput');

    if (cart.length === 0) {
        emptyMsg.style.display = 'block';
        Array.from(container.getElementsByClassName('cart-item')).forEach(el => el.remove());
        subtotalEl.innerText = '0 đ';
        totalEl.innerText = '0 đ';
        btnCheckout.disabled = true;
        cartDataInput.value = '';
        return;
    }

    emptyMsg.style.display = 'none';
    
    // Xoá các items cũ html
    Array.from(container.getElementsByClassName('cart-item')).forEach(el => el.remove());

    let total = 0;
    let totalDiscount = 0;
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        
        if (item.original_price) {
            subtotal += item.original_price * item.qty;
            totalDiscount += (item.original_price - item.price) * item.qty;
        } else {
            subtotal += itemTotal;
        }
        
        const div = document.createElement('div');
        div.className = 'cart-item p-3 border-bottom';
        
        let priceHTML = '';
        if (item.original_price) {
            const savedAmount = (item.original_price - item.price) * item.qty;
            priceHTML = `
                <div>
                    <small class="text-muted text-decoration-line-through">${formatMoney(item.original_price)} / ${item.unit}</small>
                    <div class="text-success fw-bold">${formatMoney(item.price)} / ${item.unit}</div>
                </div>
            `;
        } else {
            priceHTML = `<small class="text-muted">${formatMoney(item.price)} / ${item.unit}</small>`;
        }
        
        let promoLabel = '';
        if (item.discount > 0) {
            promoLabel = `<span class="badge bg-danger ms-2" style="font-size: 0.65rem;">-${item.discount}%</span>`;
        }
        
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">${item.name}${promoLabel}</div>
                <div class="fw-bold text-danger">${formatMoney(itemTotal)}</div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                ${priceHTML}
                <div class="input-group input-group-sm" style="width: 100px;">
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, -1)">-</button>
                    <input type="text" class="form-control text-center px-1" value="${item.qty}" readonly>
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });

    subtotalEl.innerText = formatMoney(subtotal);
    document.querySelector('.card-footer .text-success').innerText = '- ' + formatMoney(totalDiscount);
    totalEl.innerText = formatMoney(total);
    btnCheckout.disabled = false;
    cartDataInput.value = JSON.stringify(cart);
}

function handleCheckout() {
    if (cart.length === 0) return false;
    return confirm('Xác nhận thanh toán đơn hàng này?');
}

function filterProducts() {
    const input = document.getElementById('posSearch').value.toLowerCase();
    const items = document.getElementsByClassName('product-item');
    for (let i = 0; i < items.length; i++) {
        const name = items[i].getAttribute('data-name');
        const sku = items[i].getAttribute('data-sku');
        const barcode = items[i].getAttribute('data-barcode');
        
        if (name.includes(input) || sku.includes(input) || barcode.includes(input)) {
            items[i].style.display = 'block';
        } else {
            items[i].style.display = 'none';
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>