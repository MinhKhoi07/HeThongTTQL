        </div> <!-- End Content Area -->
    </div> <!-- End Main Content -->

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Realtime Clock Script -->
    <script>
        function updateClock() {
            const now = new Date();
            const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            const dayName = days[now.getDay()];
            const date = now.getDate();
            const month = now.getMonth() + 1;
            const year = now.getFullYear();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const clockElement = document.getElementById('realtime-clock');
            if (clockElement) {
                clockElement.textContent = `${dayName}, ${date} tháng ${month}, ${year} - ${hours}:${minutes}:${seconds}`;
            }
        }
        
        updateClock(); // Khởi tạo ngay lập tức
        setInterval(updateClock, 1000); // Cập nhật mỗi giây
    </script>
</body>
</html>