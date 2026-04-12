<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Quản lý người dùng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>
<body class="admin-dashboard-page">
    <header class="admin-topbar">
        <div class="container-fluid px-3 px-lg-4 py-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                    <div class="brand-title">Bike Marketplace Admin</div>
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 w-100 justify-content-lg-end">
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm người dùng, email, vai trò">
                    <div class="d-flex align-items-center gap-2">
                        <button class="admin-icon-btn" type="button"><i class="bi bi-bell"></i></button>
                        <button class="admin-icon-btn" type="button"><i class="bi bi-chat-dots"></i></button>
                        <div class="d-flex align-items-center gap-2">
                            <span class="admin-avatar">AD</span>
                            <div class="small">
                                <div class="fw-bold">Quản trị viên</div>
                                <div class="text-muted">Admin hệ thống</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-shell">
        <div class="container-fluid px-3 px-lg-4">
            <div class="row g-4">
                <aside class="col-xl-2 col-lg-3">
                    <div class="sidebar-card admin-sidebar">
                                                <ul class="menu-list">
                            <li><a class="menu-link" href="index.php"><i class="bi bi-grid"></i> Tổng quan</a></li>
                            <li><a class="menu-link" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link active" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
                            <li><a class="menu-link" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
                            <li><a class="menu-link" href="categories.php"><i class="bi bi-tags"></i> Danh mục xe</a></li>
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../login.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Quản lý người dùng</div>
                    <div class="page-kicker">Quản lý tài khoản</div>
                    <h1 class="section-title mb-2">Quản lý người dùng</h1>
                    <p class="section-subtitle mb-4">Theo dõi, tìm kiếm và quản lý tài khoản người dùng trên hệ thống.</p>

                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-people"></i></span>
                                <div><small>Tổng số tài khoản</small><strong>1.284</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-bag"></i></span>
                                <div><small>Người mua</small><strong>812</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-shop"></i></span>
                                <div><small>Người bán</small><strong>438</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-person-lock"></i></span>
                                <div><small>Tài khoản bị khóa</small><strong>34</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc người dùng</h2>
                                    <p class="text-muted mb-0">Tìm nhanh theo họ tên, email, vai trò và trạng thái tài khoản.</p>
                                </div>
                                <a href="#" class="btn btn-success"><i class="bi bi-person-plus me-2"></i>Thêm tài khoản</a>
                            </div>
                            <form>
                                <div class="row g-3">
                                    <div class="col-xl-4 col-md-6">
                                        <input type="text" class="form-control" placeholder="Tìm theo họ tên hoặc email">
                                    </div>
                                    <div class="col-xl-2 col-md-6">
                                        <select class="form-select">
                                            <option>Tất cả vai trò</option>
                                            <option>Người mua</option>
                                            <option>Người bán</option>
                                            <option>Quản trị viên</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <select class="form-select">
                                            <option>Tất cả trạng thái</option>
                                            <option>Hoạt động</option>
                                            <option>Bị khóa</option>
                                            <option>Chờ xác minh</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <select class="form-select">
                                            <option>Mới nhất</option>
                                            <option>Cũ nhất</option>
                                            <option>Tên A-Z</option>
                                            <option>Tên Z-A</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4 d-grid">
                                        <button type="button" class="btn btn-outline-success"><i class="bi bi-funnel me-2"></i>Lọc</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-8">
                            <div class="content-card">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                                    <div>
                                        <h2 class="section-heading mb-1">Danh sách tài khoản</h2>
                                        <p class="text-muted mb-0">Hiển thị 10 tài khoản gần đây để quản trị và xử lý nhanh.</p>
                                    </div>
                                    <div class="text-muted small">Hiển thị 1-10 trong 1.284 tài khoản</div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ảnh đại diện</th>
                                                <th>Họ tên</th>
                                                <th>Email</th>
                                                <th>Số điện thoại</th>
                                                <th>Vai trò</th>
                                                <th>Ngày tham gia</th>
                                                <th>Trạng thái</th>
                                                <th>Số tin đăng</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=96&q=80" alt="Nguyễn Minh Khoa" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Nguyễn Minh Khoa</td>
                                                <td>khoa.nguyen@gmail.com</td>
                                                <td>0901 234 567</td>
                                                <td><span class="status-badge status-approved">Người bán</span></td>
                                                <td>08/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>12</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&q=80" alt="Trần Bích Ngọc" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Trần Bích Ngọc</td>
                                                <td>ngoc.tran@mail.com</td>
                                                <td>0912 345 876</td>
                                                <td><span class="status-badge status-pending">Người mua</span></td>
                                                <td>08/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>0</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=96&q=80" alt="Lê Thanh Tùng" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Lê Thanh Tùng</td>
                                                <td>tung.le@bike.vn</td>
                                                <td>0935 667 321</td>
                                                <td><span class="status-badge status-approved">Người bán</span></td>
                                                <td>07/04/2026</td>
                                                <td><span class="status-badge status-pending">Chờ xác minh</span></td>
                                                <td>4</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=96&q=80" alt="Phạm Thùy Linh" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Phạm Thùy Linh</td>
                                                <td>linh.pham@gmail.com</td>
                                                <td>0987 221 145</td>
                                                <td><span class="status-badge status-approved">Người bán</span></td>
                                                <td>07/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>8</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=96&q=80" alt="Võ Gia Phúc" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Võ Gia Phúc</td>
                                                <td>phuc.vo@mail.com</td>
                                                <td>0903 789 210</td>
                                                <td><span class="status-badge status-pending">Người mua</span></td>
                                                <td>06/04/2026</td>
                                                <td><span class="status-badge status-rejected">Bị khóa</span></td>
                                                <td>0</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=96&q=80" alt="Trương Mai Anh" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Trương Mai Anh</td>
                                                <td>maianh.truong@gmail.com</td>
                                                <td>0918 003 764</td>
                                                <td><span class="status-badge status-pending">Người mua</span></td>
                                                <td>06/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>1</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=96&q=80" alt="Đặng Minh Quân" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Đặng Minh Quân</td>
                                                <td>quan.dang@marketplace.vn</td>
                                                <td>0977 885 400</td>
                                                <td><span class="status-badge status-approved">Người bán</span></td>
                                                <td>05/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>15</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&q=80" alt="Ngô Khánh Vy" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Ngô Khánh Vy</td>
                                                <td>vy.ngo@bike.vn</td>
                                                <td>0944 119 008</td>
                                                <td><span class="status-badge status-pending">Người mua</span></td>
                                                <td>05/04/2026</td>
                                                <td><span class="status-badge status-pending">Chờ xác minh</span></td>
                                                <td>0</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=96&q=80" alt="Phan Đức Duy" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Phan Đức Duy</td>
                                                <td>duy.phan@admin.vn</td>
                                                <td>0909 110 225</td>
                                                <td><span class="status-badge status-sold">Quản trị viên</span></td>
                                                <td>04/04/2026</td>
                                                <td><span class="status-badge status-approved">Hoạt động</span></td>
                                                <td>0</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=96&q=80" alt="Hoàng Việt Anh" width="52" height="52" class="rounded-circle object-fit-cover"></td>
                                                <td>Hoàng Việt Anh</td>
                                                <td>vietnam.bike@gmail.com</td>
                                                <td>0922 561 778</td>
                                                <td><span class="status-badge status-approved">Người bán</span></td>
                                                <td>04/04/2026</td>
                                                <td><span class="status-badge status-rejected">Bị khóa</span></td>
                                                <td>6</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-dark">Khóa</a><a href="#" class="btn btn-sm btn-success">Mở khóa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card mb-4">
                                <h2 class="section-heading">Lưu ý quản lý tài khoản</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>Kiểm tra email và vai trò người dùng</strong><div class="text-muted mt-1">Đảm bảo tài khoản được phân đúng nhóm để tránh nhầm quyền truy cập.</div></div>
                                    <div class="mini-status-item"><strong>Khóa tài khoản vi phạm quy định</strong><div class="text-muted mt-1">Áp dụng với các trường hợp đăng sai nội dung, spam hoặc có hành vi gây rủi ro.</div></div>
                                    <div class="mini-status-item"><strong>Theo dõi người bán có nhiều tin đăng</strong><div class="text-muted mt-1">Ưu tiên kiểm tra nhóm người bán hoạt động nhiều để duy trì chất lượng marketplace.</div></div>
                                    <div class="mini-status-item"><strong>Xử lý hỗ trợ nhanh chóng</strong><div class="text-muted mt-1">Phản hồi sớm các yêu cầu mở khóa hoặc cập nhật hồ sơ để giữ trải nghiệm ổn định.</div></div>
                                </div>
                            </div>

                            <div class="content-card">
                                <h2 class="section-heading">Tổng quan nhanh</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>16 người dùng mới hôm nay</strong><div class="text-muted mt-1">Tăng nhẹ so với hôm qua, chủ yếu đến từ nhóm người mua mới đăng ký.</div></div>
                                    <div class="mini-status-item"><strong>34 tài khoản bị khóa</strong><div class="text-muted mt-1">Cần theo dõi lịch sử xử lý để tránh bỏ sót các trường hợp cần mở lại.</div></div>
                                    <div class="mini-status-item"><strong>438 người bán đang hoạt động</strong><div class="text-muted mt-1">Đây là nhóm tạo phần lớn tin đăng mới và cần được giám sát liên tục.</div></div>
                                    <div class="mini-status-item"><strong>Nhắc quản trị</strong><div class="text-muted mt-1">Ưu tiên kiểm tra tài khoản chờ xác minh và phản hồi các yêu cầu hỗ trợ trong ngày.</div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <nav aria-label="Điều hướng trang" class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Sau</a></li>
                        </ul>
                    </nav>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

