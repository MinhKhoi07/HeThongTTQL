<?php
/**
 * get_order_detail.php
 * Trả về JSON danh sách sản phẩm trong một hóa đơn.
 */
header('Content-Type: application/json; charset=utf-8');
include 'connect.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$result = $conn->query("
    SELECT ct.so_luong, ct.gia_ban, ct.muc_giam_gia, ct.thanh_tien,
           sp.ten_san_pham, sp.ma_sku
    FROM chi_tiet_hoa_don ct
    JOIN san_pham sp ON ct.id_san_pham = sp.id
    WHERE ct.id_hoa_don = $id
    ORDER BY ct.id
");

$items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode($items);
