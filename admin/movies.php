<?php
require_once __DIR__ . '/../app/config/config.php';
include __DIR__ . '/../app/views/layouts/admin_menu.php';



$q = trim($_GET['q'] ?? '');
$sql = "
  SELECT m.*, g.name AS genre_name
  FROM movies m
  LEFT JOIN genres g ON m.genre_id = g.genre_id
";
if ($q !== '') {
  $qEsc = $conn->real_escape_string($q);
  $sql .= " WHERE m.title LIKE '%$qEsc%' OR g.name LIKE '%$qEsc%'";
}
$sql .= " ORDER BY m.movie_id DESC";
$rs = $conn->query($sql);
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = '';
if ($q !== '') {
  $qEsc = $conn->real_escape_string($q);
  $where = "WHERE m.title LIKE '%$qEsc%' OR g.name LIKE '%$qEsc%'";
}

$total = $conn->query("
  SELECT COUNT(*) AS c
  FROM movies m
  LEFT JOIN genres g ON m.genre_id=g.genre_id
  $where
")->fetch_assoc()['c'] ?? 0;

$sql = "
  SELECT m.*, g.name AS genre_name
  FROM movies m
  LEFT JOIN genres g ON m.genre_id=g.genre_id
  $where
  ORDER BY m.movie_id DESC
  LIMIT $perPage OFFSET $offset
";
$rs = $conn->query($sql);
$totalPages = ceil($total / $perPage);

?>

<div class="admin-wrap">
  <div class="admin-container">
    <div class="admin-title">
      <h1>Quản lý phim</h1>
      <div class="admin-actions">
        <a class="btn primary" href="index.php?p=admin_movies&create=1">Thêm phim</a>
      </div>
    </div>

    <?php if (isset($_GET['create']) || isset($_GET['edit'])):
      $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
      $movie = [
        'title'=>'','description'=>'','duration'=>'','genre_id'=>'',
        'start_date'=>'','end_date'=>'','status'=>'upcoming',
        'release_date'=>'','poster_url'=>'','ticket_price'=>80000.00
      ];

      if ($editId>0) {
        $m = $conn->query("SELECT * FROM movies WHERE movie_id=".$editId)->fetch_assoc();
        if ($m) $movie = $m;
      }

      $genres = $conn->query("SELECT genre_id, name FROM genres ORDER BY name ASC");
    ?>
    <link rel="stylesheet" href="public/assets/css/admin.css">
<link rel="stylesheet" href="public/assets/bootstrap-icons/bootstrap-icons.css">

    <form class="admin-container" action="app/controllers/admin/movies_controller.php" method="post" enctype="multipart/form-data">



      <input type="hidden" name="action" value="<?= $editId? 'update':'create' ?>">
      <?php if($editId): ?><input type="hidden" name="movie_id" value="<?= $editId ?>"><?php endif; ?>

      <div class="form-grid">
        <div>
          <label>Tiêu đề</label>
          <input class="input" name="title" required value="<?= htmlspecialchars($movie['title']) ?>">
        </div>

        <!-- ✅ COMBO BOX THỂ LOẠI -->
        <div>
          <label>🎭 Thể loại</label>
          <select name="genre_id" required>
            <option value="">-- Chọn thể loại --</option>
            <?php while ($g = $genres->fetch_assoc()): ?>
              <option value="<?= $g['genre_id'] ?>" <?= ($movie['genre_id'] == $g['genre_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($g['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div>
          <label>Thời lượng (phút)</label>
          <input class="input" type="number" name="duration" value="<?= (int)$movie['duration'] ?>">
        </div>

        <div>
          <label>Giá vé mặc định</label>
          <input class="input" type="number" name="ticket_price" step="5000" value="<?= (float)$movie['ticket_price'] ?>">
        </div>

        <div>
          <label>Ngày bắt đầu</label>
          <input class="input" type="date" name="start_date" value="<?= htmlspecialchars($movie['start_date']) ?>">
        </div>

        <div>
          <label>Ngày kết thúc</label>
          <input class="input" type="date" name="end_date" value="<?= htmlspecialchars($movie['end_date']) ?>">
        </div>

        <div>
          <label>Ngày phát hành</label>
          <input class="input" type="date" name="release_date" value="<?= htmlspecialchars($movie['release_date']) ?>">
        </div>

        <div>
          <label>Trạng thái</label>
          <select name="status" class="input">
            <?php foreach(['upcoming','active','expired'] as $st): ?>
              <option value="<?= $st ?>" <?= $movie['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

          <label>Poster phim</label>
          <?php if (!empty($movie['poster_url'])): ?>
            <img src="app/views/banners/<?= htmlspecialchars($movie['poster_url']) ?>" 
                 alt="" style="width:120px;border-radius:6px;margin-bottom:6px;">
          <?php endif; ?>
          <input class="input" type="file" name="poster_file" accept="image/*">
          <div class="help">Chọn ảnh mới để upload hoặc nhập tên file bên dưới.</div>
          <input class="input" name="poster_url" placeholder="hoặc nhập tên ảnh (vd: poster.png)" 
                 value="<?= htmlspecialchars(trim((string)$movie['poster_url'])) ?>">
        </div>

        <div class="full">
          <label>Mô tả</label>
          <textarea name="description"><?= htmlspecialchars((string)$movie['description']) ?></textarea>
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px">
        <button class="btn primary" type="submit"><?= $editId? 'Cập nhật':'Tạo mới' ?></button>
        <a class="btn ghost" href="index.php?p=admin_movies">Hủy</a>
      </div>
    </form>

    <?php else: ?>
      <!-- 🔎 Thanh tìm kiếm -->
      <form class="filter-bar" method="get">
        <input type="hidden" name="p" value="admin_movies">
        <input class="input" name="q" placeholder="Tìm theo tiêu đề hoặc thể loại..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn">Lọc</button>
      </form>

      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th><th>Tiêu đề</th><th>Thể loại</th><th>Poster</th>
            <th>Thời lượng</th><th>Trạng thái</th><th>Giá vé</th><th>BĐ/KTh</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $rs->fetch_assoc()): ?>
          <tr>
            <td><?= (int)$row['movie_id'] ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['genre_name'] ?? '—') ?></td>
            <td>
              <?php if($row['poster_url']): ?>
                <img src="app/views/movies/<?= htmlspecialchars($row['poster_url']) ?>" 
                     alt="" style="width:50px;height:70px;object-fit:cover;border-radius:4px;">
              <?php endif; ?>
            </td>
            <td><?= (int)$row['duration'] ?>'</td>
            <td><span class="badge <?= $row['status']==='active'?'ok':($row['status']==='upcoming'?'warn':'err') ?>"><?= $row['status'] ?></span></td>
            <td><?= number_format((float)$row['ticket_price'],0,'.','.') ?> đ</td>
            <td><?= htmlspecialchars($row['start_date']).' → '.htmlspecialchars($row['end_date']) ?></td>
            <td class="td-actions">
            <a class="btn ghost" href="index.php?p=admin_movies&edit=<?= (int)$row['movie_id'] ?>">Sửa</a>

           <form action="app/controllers/admin/movies_controller.php"
              method="post"
              style="margin:0; padding:0; display:inline-flex; align-items:center;"
              onsubmit="return confirm('Xóa phim này?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="movie_id" value="<?= (int)$row['movie_id'] ?>">
            <button class="btn" type="submit">Xóa</button>
              </form>

            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;margin-top:18px;gap:8px;">
  <?php for($i=1;$i<=$totalPages;$i++): ?>
    <?php
      $url = "index.php?p=admin_movies&page=$i";
      if ($q !== '') $url .= "&q=".urlencode($q);
    ?>
    <a href="<?= $url ?>" 
       class="btn <?= $i==$page?'primary':'ghost' ?>">
       <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

    <?php endif; ?>
  </div>
</div>
