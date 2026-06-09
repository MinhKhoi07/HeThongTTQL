<?php 
include 'connect.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nhan_vien_id = $_SESSION['user_id'] ?? 1;

// Xử lý Thanh Toán bằng Prepared Statements (Chống SQL Injection)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $tong_tien = $_POST['tong_tien'] ?? 0;
    $tien_khach_dua = $_POST['tien_khach_dua'] ?? $tong_tien;
    $tien_thoi = $tien_khach_dua - $tong_tien;
    $id_khach_hang = !empty($_POST['id_khach_hang']) ? $_POST['id_khach_hang'] : null;

    if ($cart && is_array($cart)) {
        $conn->begin_transaction();
        try {
            // 1. Tạo Hóa đơn
            $ma_hd = 'HD' . time();
            $stmt_hd = $conn->prepare("INSERT INTO hoa_don (ma_hoa_don, id_nhan_vien, id_khach_hang, tong_tien, tien_khach_dua, tien_thoi) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_hd->bind_param("siiddd", $ma_hd, $nhan_vien_id, $id_khach_hang, $tong_tien, $tien_khach_dua, $tien_thoi);
            $stmt_hd->execute();
            $id_hoa_don = $stmt_hd->insert_id;

            // 2. Thêm chi tiết hóa đơn & Giảm tồn kho
            $stmt_ct = $conn->prepare("INSERT INTO chi_tiet_hoa_don (id_hoa_don, id_san_pham, so_luong, gia_ban, muc_giam_gia, thanh_tien) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_tk = $conn->prepare("UPDATE ton_kho SET so_luong = GREATEST(so_luong - ?, 0) WHERE id_san_pham = ?");

            foreach ($cart as $item) {
                $id_sp = (int)$item['id'];
                $qty = (int)$item['qty'];
                $gia = (float)$item['price'];
                $giam = (float)($item['discount'] ?? 0);
                $thanh_tien = ($gia - ($gia * $giam / 100)) * $qty;

                // Thêm detail HD
                $stmt_ct->bind_param("iiiddd", $id_hoa_don, $id_sp, $qty, $gia, $giam, $thanh_tien);
                $stmt_ct->execute();

                // Trừ tồn kho
                $stmt_tk->bind_param("ii", $qty, $id_sp);
                $stmt_tk->execute();
            }

            $conn->commit();
            $msg = "Thanh toán thành công! Mã HĐ: $ma_hd";
        } catch (Exception $e) {
            $conn->rollback();
            $msg_err = "Lỗi thanh toán: " . $e->getMessage();
        }
    }
}

// Lấy danh sách sản phẩm và kiểm tra Khuyến mãi (Đang diễn ra)
$sql = "SELECT sp.id, sp.ten_san_pham, sp.gia_ban, sp.ma_vach, sp.ma_sku, sp.hinh_anh, sp.id_danh_muc,
        COALESCE(SUM(tk.so_luong), 0) as ton_kho
        FROM san_pham sp
        LEFT JOIN ton_kho tk ON sp.id = tk.id_san_pham
        GROUP BY sp.id
        ORDER BY sp.id DESC";
$result = $conn->query($sql);

// Lấy khuyến mãi khả dụng
$now = date('Y-m-d H:i:s');
$sql_km = "SELECT * FROM khuyen_mai WHERE ngay_bat_dau <= '$now' AND ngay_ket_thuc >= '$now'";
$km_result = $conn->query($sql_km);
$promotions = [];
while ($km = $km_result->fetch_assoc()) {
    $promotions[] = $km;
}

// Lấy DSSP khuyến mãi chọn lọc
$sp_km = [];
$ctkm = $conn->query("SELECT id_san_pham, id_khuyen_mai FROM chi_tiet_khuyen_mai");
while($ct = $ctkm->fetch_assoc()) {
    $sp_km[$ct['id_san_pham']][] = $ct['id_khuyen_mai'];
}

$products = [];
while ($row = $result->fetch_assoc()) {
    // Áp dụng khuyến mãi cao nhất
    $max_discount = 0;
    foreach ($promotions as $km) {
        $km_id = $km['id'];
        $val = (float)$km['muc_giam'];
        
        $is_apply = false;
        if ($km['loai_ap_dung'] == 'tat_ca') {
            $is_apply = true;
        } elseif ($km['loai_ap_dung'] == 'danh_muc' && $km['gia_tri_ap_dung'] == $row['id_danh_muc']) {
            $is_apply = true;
        } elseif ($km['loai_ap_dung'] == 'san_pham' && isset($sp_km[$row['id']]) && in_array($km_id, $sp_km[$row['id']])) {
            $is_apply = true;
        }

        if ($is_apply && $val > $max_discount) {
            $max_discount = $val;
        }
    }
    
    $row['discount'] = $max_discount;
    $products[] = $row;
}
$products_json = json_encode($products);

// Tạo bảng khách hàng nếu chưa có
$conn->query("CREATE TABLE IF NOT EXISTS `khach_hang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_khach_hang` varchar(20) DEFAULT NULL,
  `ten_khach_hang` varchar(100) NOT NULL,
  `so_dien_thoai` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL,
  `diem_tich_luy` int(11) DEFAULT 0,
  `ngay_tao` datetime DEFAULT current_timestamp(),
  `ghi_chu` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_khach_hang` (`ma_khach_hang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci");

// Thêm khách hàng mặc định nếu chưa có
$check_kh = $conn->query("SELECT COUNT(*) as c FROM khach_hang")->fetch_assoc();
if ($check_kh['c'] == 0) {
    $conn->query("INSERT INTO khach_hang (ma_khach_hang, ten_khach_hang, diem_tich_luy) VALUES ('KH001', 'Khách lẻ', 0)");
}

// Lấy danh sách khách hàng
$customers = $conn->query("SELECT id, ten_khach_hang, so_dien_thoai FROM khach_hang")->fetch_all(MYSQLI_ASSOC);

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
            <?php if(isset($msg_err)): ?>
                <span class="text-danger fw-bold"><i class="fas fa-times-circle"></i> <?= $msg_err ?></span>
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
                <?php $is_out_of_stock = $p['ton_kho'] <= 0; ?>
                <div class="card h-100 product-card shadow-sm border-0 <?= $is_out_of_stock ? 'opacity-50' : '' ?>" 
                     data-product='<?= json_encode($p) ?>'
                     <?= !$is_out_of_stock ? "onclick=\"addToCartFromElement(this)\"" : "" ?> 
                     style="cursor: <?= $is_out_of_stock ? 'not-allowed' : 'pointer' ?>; transition: transform 0.2s;">
                    
                    <?php if ($p['discount'] > 0): ?>
                        <div class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 m-2 rounded shadow-sm fw-bold" style="font-size: 0.8rem; z-index: 2;">
                            -<?= $p['discount'] ?>%
                        </div>
                    <?php endif; ?>

                    <div class="card-body text-center d-flex flex-column justify-content-center position-relative">
                        <?php if (!empty($p['hinh_anh'])): ?>
                            <img src="<?= htmlspecialchars($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_san_pham']) ?>" class="img-fluid mb-3 mx-auto" style="max-height: 100px; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-box fa-3x text-custom mb-3 opacity-50"></i>
                        <?php endif; ?>
                        
                        <h6 class="fw-bold text-truncate" title="<?= $p['ten_san_pham'] ?>"><?= $p['ten_san_pham'] ?></h6>
                        <small class="text-muted d-block"><?= $p['ma_sku'] ?> | Tồn: <span class="<?= $is_out_of_stock ? 'text-danger fw-bold' : '' ?>"><?= $p['ton_kho'] ?></span></small>
                        
                        <div class="mt-2">
                            <?php if ($p['discount'] > 0): ?>
                                <small class="text-decoration-line-through text-muted"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ</small>
                                <h5 class="text-danger fw-bold mb-0">
                                    <?= number_format($p['gia_ban'] - ($p['gia_ban'] * $p['discount'] / 100), 0, ',', '.') ?> đ
                                </h5>
                            <?php else: ?>
                                <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ</h5>
                            <?php endif; ?>
                        </div>
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
                <form method="POST" id="checkoutForm" onsubmit="return handleCheckout()">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Khách hàng</label>
                        <select name="id_khach_hang" class="form-select form-select-sm">
                            <option value="">Khách Vãng Lai / Mua Lẻ</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['ten_khach_hang'] ?> (<?= $c['so_dien_thoai'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tổng phụ:</span>
                        <span class="fw-bold" id="cartSubtotal">0 đ</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                        <span class="text-muted">Chiết khấu/Giảm giá:</span>
                        <span class="fw-bold text-success" id="cartDiscount">- 0 đ</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 align-items-center">
                        <h5 class="fw-bold mb-0">THÀNH TIỀN:</h5>
                        <h4 class="fw-bold text-danger mb-0" id="cartTotal">0 đ</h4>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Khách đưa (đ)</label>
                            <input type="number" name="tien_khach_dua" id="tienKhachDua" class="form-control form-control-sm text-end fw-bold" placeholder="0" min="0" onkeyup="caculateChange()" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1">Tiền thối lại</label>
                            <input type="text" id="tienThoiClient" class="form-control form-control-sm text-end fw-bold text-primary bg-white" placeholder="0 đ" readonly>
                        </div>
                    </div>
                    
                    <input type="hidden" name="checkout" value="1">
                    <input type="hidden" name="cart_data" id="cartDataInput">
                    <input type="hidden" name="tong_tien" id="tongTienInput" value="0">
                    
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

function addToCartFromElement(element) {
    const product = JSON.parse(element.getAttribute('data-product'));
    addToCart(product);
}

function addToCart(product) {
    if (product.ton_kho <= 0) return;

    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        if (existing.qty < product.ton_kho) {
            existing.qty += 1;
        } else {
            alert('Số lượng trong kho không đủ!');
        }
    } else {
        cart.push({
            id: product.id,
            name: product.ten_san_pham,
            price: parseFloat(product.gia_ban),
            discount: parseFloat(product.discount || 0),
            max_qty: parseInt(product.ton_kho),
            qty: 1
        });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (item) {
        if (item.qty + delta > item.max_qty) {
            alert('Số lượng trong kho không đủ!');
            return;
        }
        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
        renderCart();
    }
}

function renderCart() {
    const container = document.getElementById('cartContainer');
    const subtotalEl = document.getElementById('cartSubtotal');
    const discountEl = document.getElementById('cartDiscount');
    const totalEl = document.getElementById('cartTotal');
    const btnCheckout = document.getElementById('btnCheckout');
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5" id="emptyCartMsg">
                <i class="fas fa-cart-arrow-down fa-3x mb-3 opacity-25"></i>
                <p>Giỏ hàng trống</p>
            </div>`;
        subtotalEl.innerText = "0 đ";
        discountEl.innerText = "- 0 đ";
        totalEl.innerText = "0 đ";
        document.getElementById('tongTienInput').value = 0;
        btnCheckout.disabled = true;
        caculateChange();
        return;
    }

    let html = '<ul class="list-group list-group-flush">';
    let subtotal = 0;
    let totalDiscount = 0;

    cart.forEach(item => {
        let itemTotal = item.price * item.qty;
        let itemDiscount = itemTotal * item.discount / 100;
        
        subtotal += itemTotal;
        totalDiscount += itemDiscount;

        html += `
        <li class="list-group-item py-3 cart-item">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold pe-2" style="font-size: 0.95rem;">${item.name}</div>
                <div class="fw-bold text-danger text-end">
                    ${formatMoney(itemTotal - itemDiscount)}
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    ${formatMoney(item.price)}
                    ${item.discount > 0 ? `<sup class="text-danger">(-${item.discount}%)</sup>` : ''} 
                </div>
                <div class="input-group input-group-sm w-auto">
                    <button class="btn btn-outline-secondary" type="button" onclick="updateQty('${item.id}', -1)"><i class="fas fa-minus"></i></button>
                    <input type="text" class="form-control text-center fw-bold px-0" value="${item.qty}" style="width: 40px;" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="updateQty('${item.id}', 1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </li>`;
    });
    html += '</ul>';
    
    container.innerHTML = html;
    
    let endTotal = subtotal - totalDiscount;

    subtotalEl.innerText = formatMoney(subtotal);
    discountEl.innerText = "- " + formatMoney(totalDiscount);
    totalEl.innerText = formatMoney(endTotal);
    
    document.getElementById('tongTienInput').value = endTotal;
    
    // Suggest khách đưa
    document.getElementById('tienKhachDua').value = endTotal;

    btnCheckout.disabled = false;
    caculateChange();
}

function caculateChange() {
    let tong = parseFloat(document.getElementById('tongTienInput').value) || 0;
    let khachDua = parseFloat(document.getElementById('tienKhachDua').value) || 0;
    let btnCheckout = document.getElementById('btnCheckout');
    
    if (khachDua < tong) {
        document.getElementById('tienThoiClient').value = "Thiếu tiền";
        document.getElementById('tienThoiClient').classList.replace("text-primary", "text-danger");
        btnCheckout.disabled = true;
    } else {
        document.getElementById('tienThoiClient').value = formatMoney(khachDua - tong);
        document.getElementById('tienThoiClient').classList.replace("text-danger", "text-primary");
        btnCheckout.disabled = cart.length === 0;
    }
}

function clearCart() {
    if(confirm('Chắc chắn làm mới giỏ hàng?')) {
        cart = [];
        renderCart();
    }
}

function handleCheckout() {
    document.getElementById('cartDataInput').value = JSON.stringify(cart);
    return true;
}

function filterProducts() {
    const term = document.getElementById('posSearch').value.toLowerCase();
    const items = document.querySelectorAll('.product-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name');
        const sku = el.getAttribute('data-sku');
        const barcode = el.getAttribute('data-barcode');
        if (name.includes(term) || sku.includes(term) || barcode.includes(term)) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}        renderCart();
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
    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        
        const div = document.createElement('div');
        div.className = 'cart-item p-3 border-bottom';
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">${item.name}</div>
                <div class="fw-bold text-danger">${formatMoney(itemTotal)}</div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">${formatMoney(item.price)} / SP</small>
                <div class="input-group input-group-sm" style="width: 100px;">
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, -1)">-</button>
                    <input type="text" class="form-control text-center px-1" value="${item.qty}" readonly>
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });

    subtotalEl.innerText = formatMoney(total);
    totalEl.innerText = formatMoney(total); // Có thể thêm chiết khấu
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