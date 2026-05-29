<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "htttql";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
// echo "Kết nối CSDL '$dbname' thành công!";
?>