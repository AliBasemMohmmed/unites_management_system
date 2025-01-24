<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من وجود مكتبة PHPSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// إنشاء ملف Excel جديد
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// تعيين اتجاه الورقة من اليمين إلى اليسار
$sheet->setRightToLeft(true);

// إضافة رأس الجدول
$headers = ['#', 'العنوان', 'المرسل', 'المستلم', 'الحالة', 'تاريخ الإنشاء', 'آخر تحديث'];
$sheet->fromArray($headers, NULL, 'A1');

// تنسيق رأس الجدول
$headerStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E9ECEF']
    ]
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// جلب البيانات من قاعدة البيانات (نفس استعلام صفحة documents.php)
$sql = "SELECT d.*, 
        CASE 
            WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
            WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
            WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
            WHEN d.sender_type = 'user' THEN (SELECT full_name FROM users WHERE id = d.sender_id)
        END as sender_name,
        // ... باقي الاستعلام ...";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$row = 2;

while ($document = $stmt->fetch()) {
    $sheet->setCellValue('A' . $row, $document['id']);
    $sheet->setCellValue('B' . $row, $document['title']);
    $sheet->setCellValue('C' . $row, $document['sender_name']);
    $sheet->setCellValue('D' . $row, $document['receiver_name']);
    $sheet->setCellValue('E' . $row, getStatusLabel($document['status']));
    $sheet->setCellValue('F' . $row, formatDate($document['created_at']));
    $sheet->setCellValue('G' . $row, formatDate($document['updated_at']));
    $row++;
}

// تنسيق عرض الأعمدة
foreach(range('A','G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// إعداد headers لتحميل الملف
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="documents_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// إنشاء ملف Excel وإرساله للتحميل
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 