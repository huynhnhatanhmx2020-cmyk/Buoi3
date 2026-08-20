SIMPLE COLOR CHANGER - HƯỚNG DẪN CÀI ĐẶT
==========================================

1. Nén thư mục "simple-color-changer" thành file .zip
   (đảm bảo file simple-color-changer.php nằm trong thư mục simple-color-changer)

2. Vào WordPress Dashboard > Plugins > Add New > Upload Plugin

3. Chọn file .zip vừa tạo, bấm "Install Now", sau đó bấm "Activate"

4. Sau khi kích hoạt, ở menu bên trái Dashboard sẽ xuất hiện mục
   "Đổi màu Website" (icon cây cọ). Bấm vào đó.

5. Chọn màu cho các thành phần: nền trang, chữ, link, header, footer, nút bấm...
   rồi bấm "Lưu thay đổi".

6. Ra ngoài trang chủ website (front-end) để xem kết quả.

GHI CHÚ:
- Plugin dùng !important trong CSS để đảm bảo màu được áp dụng dù theme có
  CSS mạnh hơn. Nếu theme của bạn dùng class/id đặc thù mà chưa được cover,
  bạn có thể mở file simple-color-changer.php và thêm selector vào mảng
  $this->fields (phần "selector") tương ứng.
- Có thể thêm nhiều mục màu khác bằng cách copy 1 khối trong mảng $fields
  và đổi label/selector/property/default theo ý muốn.
