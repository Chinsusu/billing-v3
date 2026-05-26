# Codebase Guidelines and Development Rules for AI Agents

Tài liệu này cung cấp hướng dẫn và nguyên tắc cốt lõi giúp các Agent AI cộng tác hiệu quả trên codebase **Billing v3**.

---

## 🏗️ 1. Tổng Quan Kiến Trúc & Stack Công Nghệ

Dự án sử dụng kiến trúc Hybrid Monorepo:
- **Control-plane (`apps/backend-laravel`):** Laravel 12+, quản lý giao dịch, auth (session-based), ví, phân quyền (RBAC), UI/API và quản trị.
- **Data-plane (`apps/worker-go`):** Go daemon worker, kết nối và thực hiện các tác vụ cấp phát VPS/Proxy thực tế dựa trên hàng đợi trong cơ sở dữ liệu.
- **Database & Services:** PostgreSQL 16 (chung cho cả backend và worker), Redis 7, RabbitMQ 4, Mailpit, Caddy làm reverse proxy hỗ trợ HTTPS cục bộ.

---

## 🌿 2. Quy Trình Phân Nhánh & Git Workflow

- **Branching Model:** 
  - `main`: Nhánh sản xuất (Production). Chỉ gộp từ `develop` khi phát hành phiên bản ổn định.
  - `develop`: Nhánh phát triển chính. Mọi tính năng mới đều phải được gộp vào đây trước.
  - `feature/BILL-[ID]-[mota]`: Nhánh tính năng cá nhân tạo từ `develop`.
- **Commit Messages:** Tuân thủ Conventional Commits (ví dụ: `feat(wallet): add topup validation`, `fix(worker): handle connection timeout`).
- **PR Requirement:** Mọi PR trước khi gộp vào `develop` bắt buộc phải vượt qua tất cả các checks tại CI (GitHub Actions).

---

## 🏁 3. Cổng Kiểm Soát Chất Lượng (Quality Gates)

Trước khi thực hiện commit mã nguồn hoặc kết thúc task, các Agent phải đảm bảo chạy thành công các lệnh kiểm tra tự động sau:

1. **Kiểm tra cục bộ toàn bộ hệ thống:**
   ```bash
   make ci
   ```
2. **Kiểm tra phần Backend (PHP Laravel):**
   ```bash
   make backend-test
   ```
   *Yêu cầu:* Toàn bộ unit/feature tests phải Pass. Mã nguồn nên được format chuẩn bằng Laravel Pint.
3. **Kiểm tra phần Worker (Go):**
   ```bash
   make worker-test
   ```
   *Yêu cầu:* Mã nguồn được định dạng bằng `gofmt -w .`, kiểm tra lỗi tĩnh bằng `go vet ./...` và chạy thành công unit test `go test ./...`.
4. **Smoke Test tích hợp hệ thống (E2E):**
   ```bash
   docker compose -f infra/docker-compose.dev.yml exec backend php artisan runtime:smoke-provisioning
   ```

---

## 🎨 4. Nguyên Tắc Thiết Kế UI/UX Hệ Thống (Tham Chiếu Vuexy Template)

Để đảm bảo giao diện thống nhất, hiện đại và cao cấp giống như **Vuexy HTML Admin Template**, mọi thay đổi UI trong `resources/views` cần tuân thủ các quy tắc sau:

### A. Hệ Thống Màu Sắc (Color Palette Variables)
Tránh sử dụng màu trơn cơ bản (như đỏ `#ff0000`, xanh `#00ff00`). Hãy sử dụng hệ màu được chọn lọc dưới dạng biến CSS HSL:
- **Primary (Chủ đạo - Violet-Indigo):** `#7367F0` (HSL `245, 82%, 67%`)
- **Secondary (Phụ):** `#808390` (HSL `232, 7%, 53%`)
- **Success (Thành công):** `#28C76F` (HSL `147, 66%, 47%`)
- **Info (Thông tin):** `#00CFE8` (HSL `186, 100%, 45%`)
- **Warning (Cảnh báo):** `#FF9F43` (HSL `30, 100%, 63%`)
- **Danger (Nguy hiểm):** `#EA5455` (HSL `359, 82%, 63%`)

### B. Typography & Font Chữ
- Sử dụng font chữ hiện đại, chuyên nghiệp thay vì mặc định trình duyệt: Ưu tiên font **Inter** hoặc **Public Sans** lấy từ Google Fonts.
- Định hình phân cấp tiêu đề rõ ràng, sử dụng các lớp font-weight phù hợp (như Semibold `600` cho tiêu đề, Regular `400` cho nội dung thường).

### C. Bố Cục Trang (Layout Architecture)
Bố cục giao diện sau khi được đánh bóng (polished) cần mô phỏng cấu trúc của Vuexy Vertical Menu:
- **Left Sidebar:** Cố định bên trái, nền tối hoặc kính mờ (glassmorphism) chứa logo Billing v3 và danh sách các menu có icon SVG.
- **Top Navbar:** Nằm ngang trên cùng, chứa công cụ tìm kiếm, nút chuyển đổi Light/Dark mode, icon thông báo, và avatar người dùng điều hướng tài khoản/impersonation.
- **Main Content:** Căn giữa, các phần tử chính được bọc trong các thẻ `.card` có viền mỏng (`1px solid #e5e7eb`), bo góc mềm mại (`border-radius: 8px`), đổ bóng nhẹ (`box-shadow`) và nền trắng (Light mode) hoặc tối nhẹ (Dark mode).

### D. Hiệu Ứng Động (Transitions & Micro-animations)
- Áp dụng các hiệu ứng chuyển đổi mượt mà khi di chuột qua các thành phần tương tác (buttons, links, menu items):
  ```css
  transition: all 0.15s ease-in-out;
  ```
- Hiệu ứng di chuột lên nút: Nâng nhẹ bóng đổ, chuyển sắc độ màu nhẹ hoặc phóng to nhẹ (`scale(1.02)`).

---

## 🔒 5. Bảo Mật & Bảo Mật Dữ Liệu

- **Redaction:** Tuyệt đối không lưu các thông tin nhạy cảm (mật khẩu dạng rõ, API tokens, webhook secrets) vào tệp log hay cơ sở dữ liệu dưới dạng thô.
- **Audit Logs:** Ghi lại vết toàn bộ các hành động mang tính chất thay đổi hệ thống cấu hình hay tiền tệ từ phía Admin.
- **Credentials:** Mã hóa tất cả thông tin kết nối cổng thanh toán hay nhà cung cấp dịch vụ ở trạng thái tĩnh (encrypted at rest).
