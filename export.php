<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// تحقق من الصلاحيات
if (!hasPermission('export_documents')) {
  die('غير مصرح لك بتصدير البيانات');
}

// استلام معايير البحث
$type = $_GET['type'] ?? 'documents';
$keyword = $_GET['keyword'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';

// بناء استعلام البحث
$params = [];
$sql = "";

if ($type == 'documents') {
  $sql = "SELECT d.id, d.title, d.content, d.status, d.created_at,
          CASE 
            WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
            WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
            WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
          END as sender_name
          FROM documents d WHERE 1=1";
}

if ($keyword) {
  $sql .= " AND (title LIKE ? OR content LIKE ?)";
  $params[] = "%$keyword%";
  $params[] = "%$keyword%";
}
if ($dateFrom) {
  $sql .= " AND DATE(created_at) >= ?";
  $params[] = $dateFrom;
}
if ($dateTo) {
  $sql .= " AND DATE(created_at) <= ?";
  $params[] = $dateTo;
}
if ($status) {
  $sql .= " AND status = ?";
  $params[] = $status;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// تحديد نوع الملف كـ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=export_' . date('Y-m-d') . '.csv');

// إنشاء مخرج CSV
$output = fopen('php://output', 'w');

// إضافة BOM للدعم العربي
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// كتابة رؤوس الأعمدة
fputcsv($output, ['الرقم', 'العنوان', 'المرسل', 'الحالة', 'التاريخ']);

// كتابة البيانات
foreach ($results as $row) {
  fputcsv($output, [
    $row['id'],
    $row['title'],
    $row['sender_name'],
    $row['status'],
    $row['created_at']
  ]);
}

fclose($output);
?>
