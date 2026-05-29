<?php 
include 'connect.php'; 

// Xử lý Thanh Toán (Giảm tồn kho)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    if ($cart && is_array($cart)) {
        foreach ($cart as $item) {
            $id_sp = (int)$item['id'];
            $qty = (int)$item['qty'];
            // Giảm số lượng trong tồn kho
            $conn->query("UPDATE ton_kho SET so_luong = GREATEST(so_luong - $qty, 0) WHERE id_san_pham = $id_sp");
        }
        $msg = "Thanh toán thành công!";
    }
}

// Lấy danh sách sản phẩm để đưa vào POS
$sql = "SELECT id, ten_san_pham, gia_ban, ma_vach, ma_sku FROM san_pham ORDER BY id DESC";
$result = $conn->query($sql);
$products = [];
while ($row = $result->fetch_assoc()) {
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
                <div class="card h-100 product-card shadow-sm border-0" onclick='addToCart(<?= json_encode($p) ?>)' style="cursor: pointer; transition: transform 0.2s;">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <i class="fas fa-box fa-3x text-custom mb-3 opacity-50"></i>
                        <h6 class="fw-bold text-truncate" title="<?= $p['ten_san_pham'] ?>"><?= $p['ten_san_pham'] ?></h6>
                        <small class="text-muted mb-2 d-block"><?= $p['ma_sku'] ?> | <?= $p['ma_vach'] ?></small>
                        <h5 class="text-danger fw-bold mb-0"><?= number_format($p['gia_ban'], 0, ',', '.') ?> đ</h5>
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
            qty: 1
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