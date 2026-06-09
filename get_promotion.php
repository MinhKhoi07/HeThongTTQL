<?php
include 'connect.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Lấy thông tin khuyến mãi
    $result = $conn->query("SELECT * FROM khuyen_mai WHERE id = $id");
    $promo = $result->fetch_assoc();
    
    // Lấy danh sách sản phẩm áp dụng
    $sp_result = $conn->query("SELECT id_san_pham FROM chi_tiet_khuyen_mai WHERE id_khuyen_mai = $id");
    $san_phams = [];
    while ($row = $sp_result->fetch_assoc()) {
        $san_phams[] = $row['id_san_pham'];
    }
    
    $promo['san_phams'] = $san_phams;
    
    header('Content-Type: application/json');
    echo json_encode($promo);
}
?>
