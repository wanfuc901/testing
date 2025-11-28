<?php
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = '';
if ($q !== '') {
  $qEsc = $conn->real_escape_string($q);
  $where = "WHERE name LIKE '%$qEsc%' OR email LIKE '%$qEsc%'";
}

$total = $conn->query("SELECT COUNT(*) AS c FROM users $where")->fetch_assoc()['c'] ?? 0;
$totalPages = ceil($total / $perPage);

$sql = "
  SELECT user_id,name,email,role,created_at
  FROM users
  $where
  ORDER BY user_id DESC
  LIMIT $perPage OFFSET $offset
";
$rs = $conn->query($sql);

?>
<div class="admin-wrap">
  <div class="admin-container">
    <div class="admin-title">
      <h1>Người dùng</h1>
      <div class="admin-actions">
        <a class="btn primary" href="index.php?p=admin_users&create=1">Thêm</a>
      </div>
    </div>

    <?php if(isset($_GET['create']) || isset($_GET['edit'])):
      $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
      $user = ['name'=>'','email'=>'','role'=>'customer'];
      if ($editId>0) {
        $u = $conn->query("SELECT * FROM users WHERE user_id=".$editId)->fetch_assoc();
        if ($u) $user=$u;
      }
    ?>
    <form class="admin-container" action="app/controllers/admin/users_controller.php" method="post">
      <input type="hidden" name="action" value="<?= $editId? 'update':'create_admin' ?>">
      <?php if($editId): ?><input type="hidden" name="user_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="form-grid">
        <div><label>Họ tên</label><input class="input" name="name" required value="<?= htmlspecialchars($user['name']) ?>"></div>
        <div><label>Email</label><input class="input" type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"></div>
        <?php if(!$editId): ?>
          <div class="full"><label>Mật khẩu</label><input class="input" type="password" name="password" required></div>
        <?php endif; ?>
        <div>
          <label>Vai trò</label>
          <select class="input" name="role">
            <option value="customer" <?= $user['role']==='customer'?'selected':'' ?>>customer</option>
            <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>admin</option>
          </select>
        </div>
      </div>
      <div style="margin-top:12px">
        <button class="btn primary" type="submit"><?= $editId? 'Cập nhật':'Tạo' ?></button>
        <a class="btn ghost" href="index.php?p=admin_users">Hủy</a>
      </div>
    </form>
    <?php else: ?>
      <form class="filter-bar" method="get">
        <input type="hidden" name="p" value="admin_users">
        <input class="input" name="q" placeholder="Tìm tên hoặc email..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn">Lọc</button>
      </form>
      <table class="admin-table">
        <thead><tr><th>ID</th><th>Tên</th><th>Email</th><th>Vai trò</th><th>Tạo lúc</th><th></th></tr></thead>
        <tbody>
          <?php while($row=$rs->fetch_assoc()): ?>
            <tr>
              <td><?= (int)$row['user_id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><span class="badge <?= $row['role']==='admin'?'ok':'warn' ?>"><?= $row['role'] ?></span></td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td class="td-actions">
                <a class="btn ghost" href="index.php?p=admin_users&edit=<?= (int)$row['user_id'] ?>">Sửa</a>
                <form action="app/controllers/admin/users_controller.php" method="post" onsubmit="return confirm('Xóa người dùng?');" style="display:inline-block">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                  <button class="btn" type="submit">Xóa</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;margin-top:16px;gap:6px;flex-wrap:wrap">
  <?php for($i=1;$i<=$totalPages;$i++): ?>
    <?php
      $url = "index.php?p=admin_users&page=$i";
      if ($q !== '') $url .= "&q=".urlencode($q);
    ?>
    <a href="<?= $url ?>" class="btn <?= $i==$page?'primary':'ghost' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
    <?php endif; ?>
  </div>
</div>
