<?php
require_once __DIR__ . '/../app/include/require_admin.php';
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';

$q     = trim($_GET['q'] ?? '');
$role  = $_GET['role'] ?? 'all';      // all | admin | staff | customer

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

/* ===== WHERE BUILD ===== */
$whereUser = [];
$whereCus  = [];

if ($q !== '') {
  $qEsc = $conn->real_escape_string($q);
  $whereUser[] = "(u.name LIKE '%$qEsc%' OR u.email LIKE '%$qEsc%')";
  $whereCus[]  = "(c.fullname LIKE '%$qEsc%' OR c.email LIKE '%$qEsc%')";
}

if ($role !== 'all') {

  if ($role === 'customer') {
    // chỉ lấy customer
    $whereUser[] = "1=0";

  } else {
    // chỉ lấy user (admin / staff)
    $roleEsc = $conn->real_escape_string($role);
    $whereUser[] = "u.role='$roleEsc'";
    $whereCus[]  = "1=0";
  }
}


$whereUserSql = $whereUser ? 'WHERE '.implode(' AND ', $whereUser) : '';
$whereCusSql  = $whereCus  ? 'WHERE '.implode(' AND ', $whereCus)  : '';

/* ===== COUNT ===== */
$totalSql = "
SELECT COUNT(*) c FROM (
  SELECT u.user_id FROM users u $whereUserSql
  UNION ALL
  SELECT c.customer_id FROM customers c $whereCusSql
) x
";
$total = (int)($conn->query($totalSql)->fetch_assoc()['c'] ?? 0);
$totalPages = ceil($total / $perPage);

/* ===== DATA ===== */
$sql = "
SELECT * FROM (
  SELECT 
    u.user_id     AS id,
    u.name        AS name,
    u.email       AS email,
    u.role        AS role,
    'user'        AS type,
    u.created_at  AS created_at
  FROM users u
  $whereUserSql

  UNION ALL

  SELECT
    c.customer_id AS id,
    c.fullname    AS name,
    c.email       AS email,
    'customer'    AS role,
    'customer'    AS type,
    c.created_at  AS created_at
  FROM customers c
  $whereCusSql
) t
ORDER BY created_at DESC
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
      $editType = $_GET['type'] ?? 'user';
      $user = ['name'=>'','email'=>'','role'=>'staff'];

      if ($editId > 0) {
        if ($editType === 'customer') {
          $u = $conn->query(
            "SELECT customer_id AS id, fullname AS name, email
            FROM customers
            WHERE customer_id = $editId"
          )->fetch_assoc();

          if ($u) {
            $user = [
              'name'  => $u['name'],
              'email' => $u['email'],
              'role'  => 'customer'
            ];
          }
        } else {
          $u = $conn->query(
            "SELECT * FROM users WHERE user_id = $editId"
          )->fetch_assoc();

          if ($u) $user = $u;
        }
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
          <input type="hidden" name="type" value="<?= htmlspecialchars($editType) ?>">
          <label>Vai trò</label>
          <select class="input" name="role">
            <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
            <option value="staff" <?= $user['role']==='staff'?'selected':'' ?>>Staff</option>
            <?php if ($editType === 'customer'): ?>
              <option value="customer" selected>Customer</option>
            <?php endif; ?>
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

            <input class="input" name="q" placeholder="Tên hoặc email"
                  value="<?= htmlspecialchars($q) ?>">
           <select class="input" name="role">
            <option value="all">Tất cả</option>
            <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
            <option value="customer" <?= $role==='customer'?'selected':'' ?>>Customer</option>
          </select>
            <button class="btn">Lọc</button>
          </form>
      <table class="admin-table">
        <thead><tr><th>ID</th><th>Tên</th><th>Email</th><th>Vai trò</th><th>Tạo lúc</th><th></th></tr></thead>
        <tbody>
          <?php while($row=$rs->fetch_assoc()): ?>
            <tr>
              <td><?= (int)$row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td>
               <?php
                  $badgeClass =
                  $row['role']==='admin' ? 'ok' :
                  ($row['role']==='staff' ? 'warn' : 'ghost');
                ?>
                <span class="badge <?= $badgeClass ?>">
                  <?= htmlspecialchars($row['role']) ?>
                </span>

                </td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="td-actions">
                  <a class="btn ghost"
                    href="index.php?p=admin_users&edit=<?= (int)$row['id'] ?>&type=<?= urlencode($row['type']) ?>">
                    Sửa
                  </a>
                  <form action="app/controllers/admin/users_controller.php"
                        method="post"
                        onsubmit="return confirm('Xóa bản ghi này?');"
                        style="display:inline-block">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($row['type']) ?>">
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
        if ($q !== '')     $url .= "&q=".urlencode($q);
        if ($role !== 'all') $url .= "&role=".urlencode($role);
    ?>
    <a href="<?= $url ?>" class="btn <?= $i==$page?'primary':'ghost' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
    <?php endif; ?>
  </div>
</div>
