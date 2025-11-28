<?php
include __DIR__ . "/../../config/config.php";

// Lấy ID ưu đãi
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID ưu đãi không hợp lệ");
}
$offer_id = intval($_GET['id']);

// Lấy dữ liệu ưu đãi
$sql = "SELECT * FROM banners WHERE banner_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL lỗi: " . $conn->error);
$stmt->bind_param("i", $offer_id);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();
if (!$offer) die("Không tìm thấy ưu đãi");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($offer['title']) ?> – Ưu đãi VinCine</title>
  <link rel="stylesheet" href="public/assets/css/style.css">
  <style>
    body {
      background: #0e0e11;
      color: #eaeaea;
      font-family: system-ui, sans-serif;
      margin: 0;
      padding: 0;
    }
    .offer-header img {
      width: 100%;
      height: auto;
      display: block;
      object-fit: cover;
    }
    .offer-info {
      max-width: 900px;
      margin: 50px auto;
      background: rgba(255,255,255,0.05);
      padding: 40px;
      border-radius: 14px;
      box-shadow: 0 0 25px rgba(0,0,0,0.5);
      line-height: 1.7;
    }
    .offer-info h1 {
      color: #fff;
      margin-bottom: 10px;
      text-align: center;
    }
    .offer-date {
      text-align: center;
      color: #aaa;
      font-size: 0.95rem;
      margin-bottom: 25px;
    }
    .desc {
      font-size: 1.1rem;
      color: #ddd;
      white-space: pre-line;
    }
    .offer-expired {
      text-align: center;
      color: #ff5c5c;
      font-weight: bold;
      margin-top: 20px;
    }
    iframe.offer-frame {
      width: 100%;
      height: 90vh;
      border: none;
      margin-top: 40px;
      border-radius: 10px;
      box-shadow: 0 0 30px rgba(0,0,0,0.6);
    }
    .back-link {
      text-align: center;
      margin: 60px 0 100px;
    }
    .back-link a {
      color: #bbb;
      text-decoration: none;
      font-size: 1.05rem;
      transition: 0.2s;
    }
    .back-link a:hover {
      color: #fff;
    }
  </style>
</head>
<body>

  <!-- Ảnh banner -->
  <div class="offer-header">
    <img src="app/views/banners/<?= htmlspecialchars($offer['image_url']) ?>" 
         alt="<?= htmlspecialchars($offer['title']) ?>">
  </div>

  <!-- Nội dung ưu đãi -->
  <div class="offer-info">
    <h1><?= htmlspecialchars($offer['title']) ?></h1>
    <div class="offer-date">
      Ngày đăng: <?= date('d/m/Y', strtotime($offer['created_at'])) ?>
      <?= $offer['is_active'] ? '• Đang áp dụng' : '• Đã hết hạn' ?>
    </div>

    <?php if (!empty($offer['description'])): ?>
      <div class="desc"><?= nl2br(htmlspecialchars($offer['description'])) ?></div>
    <?php else: ?>
      <div class="desc">Hiện ưu đãi này chưa có nội dung mô tả chi tiết.</div>
    <?php endif; ?>

    <?php 
    // Chỉ nhúng link nếu hợp lệ (không phải "#" hoặc rỗng)
    if (!empty($offer['link_url']) && $offer['link_url'] !== "#"): ?>
      <iframe class="offer-frame" src="<?= htmlspecialchars($offer['link_url']) ?>"></iframe>
    <?php endif; ?>

    <?php if (isset($offer['is_active']) && !$offer['is_active']): ?>
      <div class="offer-expired">Ưu đãi này đã hết hạn.</div>
    <?php endif; ?>
  </div>

  <div class="back-link">
    <a href="index.php?p=ao">← Quay lại danh sách ưu đãi</a>
  </div>

</body>
</html>
