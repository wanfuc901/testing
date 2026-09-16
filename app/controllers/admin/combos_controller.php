<?php
require_once __DIR__ . '/../../include/require_admin.php';
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
  case 'toggle':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
        http_response_code(400);
        exit('Yêu cầu không hợp lệ.');
    }

    $sql = ($action === 'delete')
        ? 'DELETE FROM combos WHERE combo_id = ?'
        : 'UPDATE combos SET active = 1 - active WHERE combo_id = ?';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[vincine] combos_controller prepare failed: ' . $conn->error);
        http_response_code(500);
        exit('Không thực hiện được thao tác.');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    header('Location: ../../../index.php?p=admin_combos');
    exit;

  default:
    http_response_code(400);
    echo "Yêu cầu không hợp lệ.";
}
?>
