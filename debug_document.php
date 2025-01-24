<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// طباعة معرف الكتاب المطلوب
echo "Document ID requested: " . ($_GET['id'] ?? 'none') . "<br>";

// التحقق من محتويات جدول documents
$stmt = $pdo->query("SELECT * FROM documents");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "جميع الكتب في النظام:\n";
print_r($documents);
echo "</pre>";
?> 