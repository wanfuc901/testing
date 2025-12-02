<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/include/check_log.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';
/* =========================================
   XỬ LÝ THÊM / SỬA / XÓA
========================================= */
$err = "";
$ok = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? "");
    $id   = intval($_POST['genre_id'] ?? 0);

    if ($name === "") {
        $err = "Tên thể loại không được để trống.";
    } else {
        if ($id === 0) {
            /* ===== THÊM ===== */
            $stmt = $conn->prepare("INSERT INTO genres (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) $ok = "Đã thêm thể loại mới.";
            else $err = "Lỗi thêm dữ liệu.";
        } else {
            /* ===== SỬA ===== */
            $stmt = $conn->prepare("UPDATE genres SET name=? WHERE genre_id=?");
            $stmt->bind_param("si", $name, $id);
            if ($stmt->execute()) $ok = "Đã cập nhật thể loại.";
            else $err = "Lỗi cập nhật.";
        }
    }
}

/* ===== XÓA ===== */
if (isset($_GET['delete'])) {
    $gid = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM genres WHERE genre_id=?");
    $stmt->bind_param("i", $gid);
    if ($stmt->execute()) $ok = "Đã xóa thể loại.";
    else $err = "Không thể xóa. Có thể đang được dùng bởi phim.";
}

/* LẤY DANH SÁCH */
$rs = $conn->query("SELECT * FROM genres ORDER BY genre_id DESC");
?>

<div class="admin-wrap">
    <div class="admin-container">

        <div class="admin-title">
            <h1><i class="bi bi-tags"></i> Quản lý loại phim</h1>
            <button class="btn primary" onclick="openForm(0,'')">
                <i class="bi bi-plus-circle"></i> Thêm thể loại
            </button>
        </div>

        <?php if ($err): ?>
            <div class="alert" style="padding:10px;border-left:4px solid red;background:#ffe6e6;margin-bottom:12px;">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <?php if ($ok): ?>
            <div class="alert" style="padding:10px;border-left:4px solid green;background:#e6ffec;margin-bottom:12px;">
                <?= htmlspecialchars($ok) ?>
            </div>
        <?php endif; ?>


        <table class="admin-table">
            <thead>
            <tr>
                <th style="width:80px">ID</th>
                <th>Tên thể loại</th>
                <th style="width:160px">Hành động</th>
            </tr>
            </thead>

            <tbody>
            <?php while ($row = $rs->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['genre_id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td class="td-actions">

                        <!-- SỬA -->
                        <button class="btn ghost"
                                onclick="openForm(<?= $row['genre_id'] ?>,'<?= htmlspecialchars($row['name']) ?>')">
                            <i class="bi bi-pencil-square"></i> Sửa
                        </button>

                        <!-- XÓA -->
                        <a class="btn"
                           style="border-color:#dc3545;color:#dc3545"
                           onclick="return confirm('Xóa thể loại này?')"
                           href="?delete=<?= $row['genre_id'] ?>">
                            <i class="bi bi-trash"></i> Xóa
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <!-- FORM POPUP -->
        <div id="genreForm" style="
            display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.45);backdrop-filter:blur(2px);
            align-items:center;justify-content:center;z-index:2000;
        ">
            <div class="admin-form" style="width:420px;">
                <h3><i class="bi bi-pencil"></i> Thông tin thể loại</h3>

                <form method="post">
                    <input type="hidden" id="genre_id" name="genre_id">

                    <label><i class="bi bi-type"></i> Tên thể loại</label>
                    <input class="input" type="text" name="name" id="genre_name" required>

                    <div class="form-btns">
                        <button class="btn primary" type="submit">
                            <i class="bi bi-check2-circle"></i> Lưu
                        </button>
                        <button class="btn ghost" type="button" onclick="closeForm()">
                            <i class="bi bi-x-lg"></i> Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function openForm(id, name) {
    document.getElementById("genre_id").value = id;
    document.getElementById("genre_name").value = name;
    document.getElementById("genreForm").style.display = "flex";
}
function closeForm() {
    document.getElementById("genreForm").style.display = "none";
}
</script>
