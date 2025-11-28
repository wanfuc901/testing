<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/* === Lấy dữ liệu === */
$sql = "
  SELECT DATE_FORMAT(COALESCE(p.paid_at, t.booked_at), '%Y-%m') AS Thang,
         SUM(COALESCE(p.amount, t.price)) AS DoanhThu
  FROM tickets t
  LEFT JOIN payments p ON p.ticket_id=t.ticket_id AND p.status='success'
  WHERE (t.status='confirmed' OR t.paid=1)
    AND DATE(COALESCE(p.paid_at, t.booked_at)) BETWEEN '$from' AND '$to'
  GROUP BY DATE_FORMAT(COALESCE(p.paid_at, t.booked_at), '%Y-%m')
  ORDER BY Thang ASC
";
$data = $conn->query($sql);

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
