<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>VinCine</title>

  <?php if ($role !== 'admin'): ?>
  <link rel="stylesheet" href="public/loading/loading.css">
  <?php endif; ?>
  <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>

<?php if ($role !== 'admin'): ?>
  <div id="vcLoader" class="vc-loader">
    <div class="vc-wrap">
      <div class="vc-spinner"></div>
      <div class="vc-text">Loading…</div>
    </div>
  </div>
<?php endif; ?>

<?php
if ($role === 'admin') {
    main();
} else {
    include "app/views/layouts/menu.php";
    main();
    include "app/views/layouts/footer.php";
}
?>

<?php if ($role !== 'admin'): ?>
  <script src="public/loading/loading.js"></script>
<?php endif; ?>
</body>
</html>
