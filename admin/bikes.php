<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Quản lý tin đăng</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm tin đăng, người đăng, trạng thái">
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
                            <li><a class="menu-link active" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
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
                    <div class="page-breadcrumb">Admin / Quản lý tin đăng</div>
                    <div class="page-kicker">Quản lý tin đăng</div>
                    <h1 class="section-title mb-2">Quản lý tin đăng xe đạp</h1>
                    <p class="section-subtitle mb-4">Kiểm tra, duyệt và quản lý toàn bộ tin đăng xe đạp trên hệ thống.</p>

                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-card-list"></i></span>
                                <div><small>Tổng tin đăng</small><strong>428</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Chờ duyệt</small><strong>36</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                                <div><small>Đã duyệt</small><strong>312</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                                <div><small>Đã bán</small><strong>80</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc kiểm duyệt</h2>
                                    <p class="text-muted mb-0">Lọc nhanh tin đăng theo trạng thái, danh mục và giá để xử lý thuận tiện hơn.</p>
                                </div>
                                <a href="#" class="btn btn-outline-success"><i class="bi bi-download me-2"></i>Xuất danh sách</a>
                            </div>
                            <form>
                                <div class="row g-3">
                                    <div class="col-xl-4 col-md-6">
                                        <input type="text" class="form-control" placeholder="Tìm theo tên xe hoặc người đăng">
                                    </div>
                                    <div class="col-xl-2 col-md-6">
                                        <select class="form-select">
                                            <option>Tất cả</option>
                                            <option>Chờ duyệt</option>
                                            <option>Đã duyệt</option>
                                            <option>Từ chối</option>
                                            <option>Đã bán</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <select class="form-select">
                                            <option>Danh mục xe</option>
                                            <option>Road Bike</option>
                                            <option>Mountain Bike</option>
                                            <option>Touring</option>
                                            <option>Fixed Gear</option>
                                            <option>City Bike</option>
                                            <option>E-Bike</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4">
                                        <select class="form-select">
                                            <option>Sắp xếp giá</option>
                                            <option>Giá tăng dần</option>
                                            <option>Giá giảm dần</option>
                                            <option>Mới nhất</option>
                                            <option>Cũ nhất</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-4 d-grid">
                                        <button type="button" class="btn btn-success"><i class="bi bi-funnel me-2"></i>Lọc</button>
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
                                        <h2 class="section-heading mb-1">Danh sách tin đăng</h2>
                                        <p class="text-muted mb-0">Theo dõi 10 tin đăng gần nhất cần kiểm tra hoặc cập nhật trạng thái.</p>
                                    </div>
                                    <div class="text-muted small">Hiển thị 1-10 trong 428 tin đăng</div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ảnh</th>
                                                <th>Tên xe</th>
                                                <th>Người đăng</th>
                                                <th>Danh mục</th>
                                                <th>Giá</th>
                                                <th>Khu vực</th>
                                                <th>Ngày đăng</th>
                                                <th>Trạng thái</th>
                                                <th>Lượt xem</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=120&q=80" alt="Trek Domane SL 6" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Trek Domane SL 6</td>
                                                <td>Nguyễn Minh Khôi</td>
                                                <td>Road Bike</td>
                                                <td>68.000.000đ</td>
                                                <td>TP. Hồ Chí Minh</td>
                                                <td>08/04/2026</td>
                                                <td><span class="status-badge status-pending">Chờ duyệt</span></td>
                                                <td>145</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1511994298241-608e28f14fde?auto=format&fit=crop&w=120&q=80" alt="Giant Talon 1" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Giant Talon 1</td>
                                                <td>Trần Quốc Bảo</td>
                                                <td>Mountain Bike</td>
                                                <td>21.500.000đ</td>
                                                <td>Đà Nẵng</td>
                                                <td>08/04/2026</td>
                                                <td><span class="status-badge status-approved">Đã duyệt</span></td>
                                                <td>221</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=120&q=80" alt="Specialized Stumpjumper" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Specialized Stumpjumper</td>
                                                <td>Lê Thanh Hưng</td>
                                                <td>Mountain Bike</td>
                                                <td>47.900.000đ</td>
                                                <td>Hà Nội</td>
                                                <td>07/04/2026</td>
                                                <td><span class="status-badge status-pending">Chờ duyệt</span></td>
                                                <td>98</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?auto=format&fit=crop&w=120&q=80" alt="Cannondale Quick 4" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Cannondale Quick 4</td>
                                                <td>Phạm Thùy Linh</td>
                                                <td>City Bike</td>
                                                <td>18.900.000đ</td>
                                                <td>Cần Thơ</td>
                                                <td>07/04/2026</td>
                                                <td><span class="status-badge status-sold">Đã bán</span></td>
                                                <td>334</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1507035895480-2b3156c31fc8?auto=format&fit=crop&w=120&q=80" alt="Brompton C Line" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Brompton C Line</td>
                                                <td>Hoàng Việt Anh</td>
                                                <td>City Bike</td>
                                                <td>33.500.000đ</td>
                                                <td>Hải Phòng</td>
                                                <td>07/04/2026</td>
                                                <td><span class="status-badge status-rejected">Từ chối</span></td>
                                                <td>63</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?auto=format&fit=crop&w=120&q=80" alt="Scott Addict 30" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Scott Addict 30</td>
                                                <td>Ngô Quốc Hào</td>
                                                <td>Road Bike</td>
                                                <td>74.000.000đ</td>
                                                <td>TP. Hồ Chí Minh</td>
                                                <td>06/04/2026</td>
                                                <td><span class="status-badge status-approved">Đã duyệt</span></td>
                                                <td>286</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1547447134-cd3f5c716030?auto=format&fit=crop&w=120&q=80" alt="Marin Fairfax 2" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Marin Fairfax 2</td>
                                                <td>Võ Gia Phúc</td>
                                                <td>City Bike</td>
                                                <td>14.800.000đ</td>
                                                <td>Nha Trang</td>
                                                <td>06/04/2026</td>
                                                <td><span class="status-badge status-approved">Đã duyệt</span></td>
                                                <td>172</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=120&q=80" alt="Polygon Siskiu T7" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Polygon Siskiu T7</td>
                                                <td>Đặng Minh Quân</td>
                                                <td>Mountain Bike</td>
                                                <td>39.600.000đ</td>
                                                <td>Bình Dương</td>
                                                <td>05/04/2026</td>
                                                <td><span class="status-badge status-pending">Chờ duyệt</span></td>
                                                <td>87</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=120&q=80" alt="Liv Alight 3" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Liv Alight 3</td>
                                                <td>Trương Bích Ngọc</td>
                                                <td>City Bike</td>
                                                <td>12.900.000đ</td>
                                                <td>Huế</td>
                                                <td>05/04/2026</td>
                                                <td><span class="status-badge status-approved">Đã duyệt</span></td>
                                                <td>119</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                            <tr>
                                                <td><img src="https://images.unsplash.com/photo-1511994298241-608e28f14fde?auto=format&fit=crop&w=120&q=80" alt="Merida Scultura 5000" width="72" height="52" class="rounded-3 object-fit-cover"></td>
                                                <td>Merida Scultura 5000</td>
                                                <td>Phan Đức Duy</td>
                                                <td>Road Bike</td>
                                                <td>52.000.000đ</td>
                                                <td>Đồng Nai</td>
                                                <td>04/04/2026</td>
                                                <td><span class="status-badge status-rejected">Từ chối</span></td>
                                                <td>54</td>
                                                <td><div class="d-flex flex-wrap gap-2"><a href="#" class="btn btn-sm btn-outline-dark">Xem</a><a href="#" class="btn btn-sm btn-success">Duyệt</a><a href="#" class="btn btn-sm btn-outline-dark">Từ chối</a><a href="#" class="btn btn-sm btn-outline-success">Sửa</a><a href="#" class="btn btn-sm btn-outline-danger">Xóa</a></div></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card mb-4">
                                <h2 class="section-heading">Hướng dẫn kiểm duyệt</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>Kiểm tra ảnh và mô tả rõ ràng</strong><div class="text-muted mt-1">Ảnh phải đúng sản phẩm, mô tả cần đủ tình trạng, cấu hình và lịch sử sử dụng.</div></div>
                                    <div class="mini-status-item"><strong>Xác minh giá bán hợp lý</strong><div class="text-muted mt-1">So sánh với thị trường để hạn chế tin đăng có giá bất thường hoặc gây hiểu nhầm.</div></div>
                                    <div class="mini-status-item"><strong>Loại bỏ tin sai thông tin</strong><div class="text-muted mt-1">Từ chối tin dùng ảnh không liên quan, mô tả thiếu trung thực hoặc sai danh mục xe.</div></div>
                                    <div class="mini-status-item"><strong>Ưu tiên tin đầy đủ thông số</strong><div class="text-muted mt-1">Tin có đủ khung, bánh, phanh, truyền động sẽ giúp người mua tin tưởng hơn.</div></div>
                                </div>
                            </div>

                            <div class="content-card">
                                <h2 class="section-heading">Rà soát nhanh hôm nay</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>9 tin cần duyệt gấp</strong><div class="text-muted mt-1">Các tin đăng mới trong 6 giờ gần nhất đang chờ xử lý để hiển thị công khai.</div></div>
                                    <div class="mini-status-item"><strong>3 tin bị báo cáo</strong><div class="text-muted mt-1">Cần kiểm tra lại chất lượng ảnh và mô tả trước khi quyết định giữ hoặc gỡ tin.</div></div>
                                    <div class="mini-status-item"><strong>2 tin bị từ chối hôm nay</strong><div class="text-muted mt-1">Lý do phổ biến là thiếu ảnh thật và thông tin giá chưa rõ ràng.</div></div>
                                    <div class="mini-status-item"><strong>Nhắc nhở</strong><div class="text-muted mt-1">Ưu tiên xử lý nhóm chờ duyệt trước 24 giờ để đảm bảo trải nghiệm cho người bán.</div></div>
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

