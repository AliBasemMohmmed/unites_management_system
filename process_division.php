<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hasPermission('add_division')) {
    die('غير مصرح لك بإضافة شعبة');
  }
  
  $name = $_POST['name'];
  $universityId = $_POST['university_id'];
  
  try {
    $stmt = $pdo->prepare("INSERT INTO university_divisions (name, university_id) VALUES (?, ?)");
    $stmt->execute([$name, $universityId]);
    header('Location: divisions.php?success=1');
    exit;
  } catch(PDOException $e) {
    die('خطأ في إضافة الشعبة: ' . $e->getMessage());
  }
}
?>
