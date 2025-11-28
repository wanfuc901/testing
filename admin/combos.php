<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/include/check_log.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';

$rs = $conn->query("SELECT * FROM combos ORDER BY combo_id DESC");
?>
<link rel="stylesheet" href="public/assets/boxicon/css/boxicons.min.css">
<link rel="stylesheet" href="public/assets/css/admin.css">

<div class="admin-wrap">
  <div class="admin-container">
    <div class="admin-title">
      <h1><i class='bx bxs-drink'></i> Quản lý Combo bắp nước</h1>
      <div class="admin-actions">
        <a class="btn primary" href="index.php?p=admin_combos&create=1"><i class='bx bx-plus'></i> Thêm combo</a>
      </div>
    </div>

    <?php if(isset($_GET['create']) || isset($_GET['edit'])):
      $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
      $edit = ['name'=>'','description'=>'','price'=>'','image'=>'','active'=>1];
      if ($editId) $edit = $conn->query("SELECT * FROM combos WHERE combo_id=$editId")->fetch_assoc();
    ?>
    <form method="post" enctype="multipart/form-data" class="admin-form" action="app/controllers/admin/combos_controller.php">
      <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
      <input type="hidden" name="combo_id" value="<?=$editId?>">
      <input type="hidden" name="image_uploaded" id="image_uploaded" value="<?=htmlspecialchars($edit['image'])?>">

      <h3><i class='bx bx-edit'></i> <?= $editId ? 'Chỉnh sửa Combo' : 'Thêm Combo mới' ?></h3>

      <label><i class='bx bx-font'></i> Tên combo</label>
      <input type="text" name="name" class="input" required value="<?=htmlspecialchars($edit['name'])?>">

      <label><i class='bx bx-detail'></i> Mô tả</label>
      <textarea name="description" class="input"><?=htmlspecialchars($edit['description'])?></textarea>

      <label><i class='bx bx-purchase-tag'></i> Giá (VNĐ)</label>
      <input type="number" name="price" step="1000" class="input" required value="<?=$edit['price']?>">

      <label><i class='bx bx-image'></i> Hình ảnh combo</label>
      <div class="upload-box">
        <img id="preview" src="<?= $edit['image'] ? 'app/views/combos/'.htmlspecialchars($edit['image']) : '' ?>" 
             style="<?= $edit['image'] ? '' : 'display:none;' ?>width:120px;border-radius:10px;margin-bottom:10px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
        <div class="upload-actions">
          <input type="file" id="fileInput" accept="image/*" class="input-file">
          <button type="button" id="uploadBtn" class="btn ghost">
            <i class='bx bx-upload'></i> Tải ảnh lên
          </button>
        </div>
        <p class="help">Ảnh được lưu tự động vào <code>app/views/combos/</code></p>
      </div>

      <label><input type="checkbox" name="active" <?=$edit['active']?'checked':''?>> Hiển thị</label>

      <div class="form-btns">
        <button type="submit" class="btn primary"><i class='bx bx-save'></i> Lưu</button>
        <a href="index.php?p=admin_combos" class="btn ghost"><i class='bx bx-arrow-back'></i> Quay lại</a>
      </div>
    </form>

    <script>
    const fileInput = document.getElementById('fileInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const preview   = document.getElementById('preview');
    const hiddenInp = document.getElementById('image_uploaded');

    uploadBtn.addEventListener('click', async () => {
      const file = fileInput.files[0];
      if (!file) return alert("Vui lòng chọn ảnh trước.");

      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', 'combo');

      uploadBtn.disabled = true;
      uploadBtn.innerHTML = "<i class='bx bx-loader bx-spin'></i> Đang tải...";

      try {
        const res = await fetch('app/upload.php', { method: 'POST', body: formData });
        const text = await res.text();

        // Phân tích kết quả: trích tên file từ HTML phản hồi
        const match = text.match(/app\/views\/combos\/(img_[a-z0-9]+\.\w+)/i);
        if (match) {
          const filename = match[1].split('/').pop();
          hiddenInp.value = filename;
          preview.src = 'app/views/combos/' + filename;
          preview.style.display = 'block';
          alert('Upload thành công: ' + filename);
        } else {
          alert('Không thể xác định tên file, phản hồi:\n' + text);
        }
      } catch (err) {
        alert('Lỗi upload: ' + err.message);
      }

      uploadBtn.disabled = false;
      uploadBtn.innerHTML = "<i class='bx bx-upload'></i> Tải ảnh lên";
    });
    </script>

    <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Ảnh</th>
          <th>Tên Combo</th>
          <th>Giá</th>
          <th>Trạng thái</th>
          <th class="td-actions">Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php while($r=$rs->fetch_assoc()): ?>
        <tr>
          <td><?=$r['combo_id']?></td>
          <td><img src="app/views/combos/<?=htmlspecialchars($r['image']?:'default.png')?>" width="60" style="border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,.15)"></td>
          <td><?=htmlspecialchars($r['name'])?></td>
          <td><?=number_format($r['price'],0,',','.')?> ₫</td>
          <td><?=$r['active']?'<span class="badge ok">Hiển thị</span>':'<span class="badge err">Ẩn</span>'?></td>
          <td class="td-actions">
            <a href="index.php?p=admin_combos&edit=<?=$r['combo_id']?>" class="btn ghost" title="Sửa"><i class='bx bx-edit'></i></a>
            <a href="app/controllers/admin/combos_controller.php?action=toggle&combo_id=<?=$r['combo_id']?>" class="btn ghost" title="Ẩn/Hiện"><i class='bx bx-low-vision'></i></a>
            <a href="app/controllers/admin/combos_controller.php?action=delete&combo_id=<?=$r['combo_id']?>" class="btn danger" title="Xóa" onclick="return confirm('Xóa combo này?')"><i class='bx bx-trash'></i></a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
