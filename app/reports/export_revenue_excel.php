<?php
require_once __DIR__ . '/../include/require_admin.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Chuẩn hóa tham số ngày về đúng định dạng Y-m-d.
 * Chặn mọi chuỗi lạ trước khi đưa vào truy vấn.
 */
function normalize_report_date(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    $date  = DateTime::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

$from = normalize_report_date($_GET['from'] ?? null, date('Y-m-01'));
$to   = normalize_report_date($_GET['to'] ?? null, date('Y-m-d'));

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

/* === Lấy dữ liệu === */
$sql = "
  SELECT DATE_FORMAT(COALESCE(p.paid_at, t.booked_at), '%Y-%m') AS Thang,
         SUM(COALESCE(p.amount, t.price)) AS DoanhThu
  FROM tickets t
  LEFT JOIN payments p
         ON p.payment_id = t.payment_id
        AND p.status = 'success'
  WHERE (t.status = 'confirmed' OR t.paid = 1)
    AND DATE(COALESCE(p.paid_at, t.booked_at)) BETWEEN ? AND ?
  GROUP BY DATE_FORMAT(COALESCE(p.paid_at, t.booked_at), '%Y-%m')
  ORDER BY Thang ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('[vincine] export_revenue prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Không tạo được báo cáo. Vui lòng thử lại sau.');
}

$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$data = $stmt->get_result();

if ($data === false) {
    error_log('[vincine] export_revenue query failed: ' . $conn->error);
    http_response_code(500);
    exit('Không tạo được báo cáo. Vui lòng thử lại sau.');
}



/* === Tạo Excel === */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Báo cáo doanh thu');

/* === Tiêu đề === */
$sheet->mergeCells('A1:C1');
$sheet->setCellValue('A1', 'BÁO CÁO DOANH THU TỪ ' . $from . ' ĐẾN ' . $to);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* === Header === */
$sheet->setCellValue('A3', 'STT');
$sheet->setCellValue('B3', 'Tháng');
$sheet->setCellValue('C3', 'Doanh thu (VNĐ)');

$headerStyle = [
  'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
  'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
  'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4CAF50']],
];
$sheet->getStyle('A3:C3')->applyFromArray($headerStyle);

/* === Dữ liệu === */
$row = 4;
$stt = 1;
$total = 0;
while ($r = $data->fetch_assoc()) {
  $sheet->setCellValue("A$row", $stt++);
  $sheet->setCellValue("B$row", $r['Thang']);
  $sheet->setCellValue("C$row", $r['DoanhThu']);
  $total += $r['DoanhThu'];
  $row++;
}

/* === Tổng cộng === */
$sheet->setCellValue("B$row", 'TỔNG CỘNG');
$sheet->setCellValue("C$row", $total);
$sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
$sheet->getStyle("B$row:C$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF59D');

/* === Định dạng số và viền === */
$sheet->getStyle("C4:C$row")->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("A3:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

/* === Căn chỉnh và auto width === */
foreach (range('A', 'C') as $col)
  $sheet->getColumnDimension($col)->setAutoSize(true);

$sheet->getStyle("A4:A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("B4:B$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

/* === Xuất file === */
$filename = "BaoCaoDoanhThu_{$from}_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
