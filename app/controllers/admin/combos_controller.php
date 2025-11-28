<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/config.php';

/*
 * ===== COMBOS CONTROLLER =====
 * Ảnh combo được upload qua app/upload.php (type=combo)
 */

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$id     = (int)($_POST['combo_id'] ?? $_GET['combo_id'] ?? 0);

switch ($action) {
  case 'create':
  case 'update':
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $price  = (float)($_POST['price'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    // === NHẬN TÊN ẢNH TỪ INPUT ẨN ===
    $img = trim($_POST['image_uploaded'] ?? '');

    // === Lưu vào DB ===
    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO combos (name, description, price, image, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsi", $name, $desc, $price, $img, $active);
    } else {
        if ($img) {
            $stmt = $conn->prepare("UPDATE combos SET name=?, description=?, price=?, image=?, active=? WHERE combo_id=?");
            $stmt->bind_param("ssdsii", $name, $desc, $price, $img, $active, $id);
        } else {
            $stmt = $conn->prepare("UPDATE combos SET name=?, description=?, price=?, active=? WHERE combo_id=?");
            $stmt->bind_param("ssdii", $name, $desc, $price, $active, $id);
        }
    }
    $stmt->execute();
    header("Location: ../../../index.php?p=admin_combos");
    exit;

  case 'delete':
    $conn->query("DELETE FROM combos WHERE combo_id=$id");
    header("Location: ../../../index.php?p=admin_combos");
    exit;

  case 'toggle':
    $conn->query("UPDATE combos SET active = 1 - active WHERE combo_id=$id");
    header("Location: ../../../index.php?p=admin_combos");
    exit;

  default:
    http_response_code(400);
    echo "Yêu cầu không hợp lệ.";
}
?>
