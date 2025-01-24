<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// جلب معلومات المستخدم الكاملة
$userInfo = getUserFullInfo($_SESSION['user_id']);

// التحقق من وجود معلومات المستخدم
if (!$userInfo) {
    die('لم يتم العثور على معلومات المستخدم. الرجاء التواصل مع مدير النظام.');
}

$welcomeMessage = getWelcomeMessage($userInfo);

include 'header.php';

// التأكد من وجود الدور
if (!isset($userInfo['role'])) {
    $userInfo['role'] = 'user';
}

// إحصائيات حسب نوع المستخدم
$stats = [];
switch($userInfo['role']) {
  case 'admin':
    $stats = [
      'documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
      'reports' => $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn(),
      'units' => $pdo->query("SELECT COUNT(*) FROM units WHERE is_active = 1")->fetchColumn(),
      'divisions' => $pdo->query("SELECT COUNT(*) FROM university_divisions")->fetchColumn()
    ];
    break;
  
  case 'unit':
    if (isset($userInfo['entity_id'])) {
      $stats = [
        'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'unit' AND sender_id = {$userInfo['entity_id']}")->fetchColumn(),
        'pending_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'unit' AND sender_id = {$userInfo['entity_id']} AND status = 'pending'")->fetchColumn(),
        'reports' => $pdo->query("SELECT COUNT(*) FROM reports WHERE unit_id = {$userInfo['entity_id']}")->fetchColumn(),
        'staff' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'unit' AND entity_id = {$userInfo['entity_id']}")->fetchColumn()
      ];
    }
    break;
    
  case 'division':
    if (isset($userInfo['entity_id'])) {
      $stats = [
        'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'division' AND sender_id = {$userInfo['entity_id']}")->fetchColumn(),
        'units' => $pdo->query("SELECT COUNT(*) FROM units WHERE division_id = {$userInfo['entity_id']} AND is_active = 1")->fetchColumn(),
        'reports' => $pdo->query("
          SELECT COUNT(*) 
          FROM reports r 
          JOIN units u ON r.unit_id = u.id 
          WHERE u.division_id = {$userInfo['entity_id']}
          AND u.is_active = 1
        ")->fetchColumn()
      ];
    }
    break;
    
  case 'ministry':
    if (isset($userInfo['entity_id'])) {
      $stats = [
        'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'ministry' AND sender_id = {$userInfo['entity_id']}")->fetchColumn(),
        'divisions' => $pdo->query("
          SELECT COUNT(*) 
          FROM university_divisions ud 
          JOIN universities u ON ud.university_id = u.id 
          WHERE u.ministry_department_id = {$userInfo['entity_id']}
        ")->fetchColumn(),
        'universities' => $pdo->query("SELECT COUNT(*) FROM universities WHERE ministry_department_id = {$userInfo['entity_id']}")->fetchColumn()
      ];
    }
    break;
}

// جلب معلومات الوحدة إذا كان المستخدم من نوع وحدة
$unitInfo = null;
if ($userInfo['role'] == 'unit') {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               c.name as college_name,
               d.name as division_name,
               un.name as university_name,
               creator.full_name as created_by_name,
               updater.full_name as updated_by_name
        FROM units u
        LEFT JOIN colleges c ON u.college_id = c.id
        LEFT JOIN university_divisions d ON u.division_id = d.id
        LEFT JOIN universities un ON u.university_id = un.id
        LEFT JOIN users creator ON u.created_by = creator.id
        LEFT JOIN users updater ON u.updated_by = updater.id
        WHERE u.user_id = ? 
        AND u.is_active = 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unitInfo = $stmt->fetch();

    // إذا لم يتم العثور على معلومات الوحدة
    if (!$unitInfo) {
        error_log("لم يتم العثور على معلومات الوحدة للمستخدم: " . $_SESSION['user_id']);
    } else {
        // تحديث الإحصائيات للوحدة
        $stats = [
            'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'unit' AND sender_id = {$unitInfo['id']}")->fetchColumn(),
            'pending_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'unit' AND sender_id = {$unitInfo['id']} AND status = 'pending'")->fetchColumn(),
            'reports' => $pdo->query("SELECT COUNT(*) FROM reports WHERE unit_id = {$unitInfo['id']}")->fetchColumn(),
            'staff' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'unit' AND id IN (SELECT user_id FROM units WHERE id = {$unitInfo['id']})")->fetchColumn()
        ];

        // تحديث آخر الكتب
        $latestDocs = $pdo->query("
            SELECT d.*, 
                   CASE 
                     WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
                     WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
                     WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
                   END as sender_name
            FROM documents d 
            WHERE (sender_type = 'unit' AND sender_id = {$unitInfo['id']})
               OR (receiver_type = 'unit' AND receiver_id = {$unitInfo['id']})
            ORDER BY created_at DESC 
            LIMIT 5
        ")->fetchAll();

        // تحديث آخر التقارير
        $latestReports = $pdo->query("
            SELECT r.*, u.name as unit_name 
            FROM reports r 
            JOIN units u ON r.unit_id = u.id 
            WHERE r.unit_id = {$unitInfo['id']}
            ORDER BY r.created_at DESC 
            LIMIT 5
        ")->fetchAll();
    }
}

// تحديث استعلامات الشعبة
if ($userInfo['role'] == 'division' && isset($userInfo['entity_id'])) {
    $stats = [
        'documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE sender_type = 'division' AND sender_id = {$userInfo['entity_id']}")->fetchColumn(),
        'units' => $pdo->query("SELECT COUNT(*) FROM units WHERE division_id = {$userInfo['entity_id']} AND is_active = 1")->fetchColumn(),
        'reports' => $pdo->query("
            SELECT COUNT(*) 
            FROM reports r 
            JOIN units u ON r.unit_id = u.id 
            WHERE u.division_id = {$userInfo['entity_id']}
            AND u.is_active = 1
        ")->fetchColumn()
    ];
}

// آخر الكتب حسب نوع المستخدم
$latestDocs = [];
switch($userInfo['role']) {
  case 'admin':
    $latestDocs = $pdo->query("
      SELECT d.*, 
             CASE 
               WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
               WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
               WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
             END as sender_name
      FROM documents d 
      ORDER BY created_at DESC 
      LIMIT 5
    ")->fetchAll();
    break;
    
  default:
    if (isset($userInfo['role']) && isset($userInfo['entity_id'])) {
      $entityType = $userInfo['role'];
      $entityId = $userInfo['entity_id'];
      $latestDocs = $pdo->query("
        SELECT d.*, 
               CASE 
                 WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
                 WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
                 WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
               END as sender_name
        FROM documents d 
        WHERE (sender_type = '$entityType' AND sender_id = $entityId)
           OR (receiver_type = '$entityType' AND receiver_id = $entityId)
        ORDER BY created_at DESC 
        LIMIT 5
      ")->fetchAll();
    }
}

// آخر التقارير حسب نوع المستخدم
$latestReports = [];
switch($userInfo['role']) {
  case 'admin':
    $latestReports = $pdo->query("
      SELECT r.*, u.name as unit_name 
      FROM reports r 
      JOIN units u ON r.unit_id = u.id 
      ORDER BY r.created_at DESC 
      LIMIT 5
    ")->fetchAll();
    break;
    
  case 'unit':
    if (isset($userInfo['entity_id'])) {
      $latestReports = $pdo->query("
        SELECT r.*, u.name as unit_name 
        FROM reports r 
        JOIN units u ON r.unit_id = u.id 
        WHERE r.unit_id = {$userInfo['entity_id']}
        ORDER BY r.created_at DESC 
        LIMIT 5
      ")->fetchAll();
    }
    break;
    
  case 'division':
    if (isset($userInfo['entity_id'])) {
      $latestReports = $pdo->query("
        SELECT r.*, u.name as unit_name 
        FROM reports r 
        JOIN units u ON r.unit_id = u.id 
        JOIN users usr ON u.id = usr.entity_id 
        WHERE usr.role = 'unit' 
        AND usr.university_id = {$userInfo['university_id']}
        ORDER BY r.created_at DESC 
        LIMIT 5
      ")->fetchAll();
    }
    break;
}
?>

<!-- إضافة CSS للتأثيرات الحركية في بداية الملف -->
<style>
/* تأثيرات عامة */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

.slide-in {
    animation: slideIn 0.5s ease-out;
}

.bounce {
    animation: bounce 0.5s ease-in-out;
}

/* تأثيرات البطاقات */
.card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stat-card {
    overflow: hidden;
    position: relative;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,0.2),
        transparent
    );
    transition: 0.5s;
}

.stat-card:hover::before {
    left: 100%;
}

.stat-icon {
    font-size: 4rem;
    opacity: 0.2;
    position: absolute;
    right: -10px;
    bottom: -10px;
    transform: rotate(-15deg);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: rotate(0deg) scale(1.1);
    opacity: 0.3;
}

/* تأثيرات القوائم */
.list-group-item {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.list-group-item:hover {
    transform: translateX(5px);
    border-left: 3px solid var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}

/* تأثيرات الشارات */
.badge {
    transition: all 0.3s ease;
}

.badge:hover {
    transform: scale(1.1);
}

/* تأثيرات الأيقونات */
.fa, .fas {
    transition: all 0.3s ease;
}

.card:hover .fa,
.card:hover .fas {
    transform: scale(1.2);
}

/* التأثيرات الحركية */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes bounce {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); opacity: 0.8; }
    70% { transform: scale(0.9); opacity: 0.9; }
    100% { transform: scale(1); opacity: 1; }
}

/* تخصيص الألوان */
:root {
    --gradient-primary: linear-gradient(45deg, #007bff, #00bcd4);
    --gradient-success: linear-gradient(45deg, #28a745, #84c687);
    --gradient-warning: linear-gradient(45deg, #ffc107, #ffdb4a);
    --gradient-danger: linear-gradient(45deg, #dc3545, #ff6b6b);
}

.bg-primary {
    background: var(--gradient-primary) !important;
}

.bg-success {
    background: var(--gradient-success) !important;
}

.bg-warning {
    background: var(--gradient-warning) !important;
}

.bg-danger {
    background: var(--gradient-danger) !important;
}

/* تحسينات إضافية */
.alert-info {
    background: linear-gradient(45deg, #17a2b8, #89d8e2);
    border: none;
    color: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-header {
    border-bottom: none;
    background: transparent;
    padding: 1.5rem 1.5rem 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

.text-muted {
    color: #6c757d !important;
}

/* تأثيرات التحميل */
.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,0.2),
        transparent
    );
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* تصميم معلومات الوحدة */
.unit-info-card {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border: none;
}

.unit-info-card .card-header {
    background: var(--gradient-primary);
    color: white;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.unit-info-card .card-header::before {
    content: '';
    position: absolute;
    width: 200%;
    height: 200%;
    background: rgba(255,255,255,0.1);
    transform: rotate(45deg);
    top: -50%;
    left: -50%;
}

.unit-info-card .card-header h5 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    position: relative;
    z-index: 1;
}

.unit-info-card .card-body {
    padding: 2rem;
}

.unit-info-item {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.unit-info-item:hover {
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.unit-info-item strong {
    color: #2c3e50;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 0.5rem;
}

.unit-info-item p {
    color: #34495e;
    font-size: 1.1rem;
    margin: 0;
}

.unit-info-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #e9ecef, transparent);
    margin: 1.5rem 0;
}

.unit-description {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.unit-description strong {
    color: #2c3e50;
    font-size: 1.1rem;
    display: block;
    margin-bottom: 1rem;
}

.unit-description p {
    color: #34495e;
    line-height: 1.6;
    margin: 0;
}

.unit-meta {
    font-size: 0.9rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    margin-top: 0.5rem;
}

.unit-meta i {
    margin-right: 0.5rem;
}

/* تصميم رسالة الترحيب */
.welcome-card {
    background: linear-gradient(120deg, #2980b9, #3498db);
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(41, 128, 185, 0.2);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.welcome-card::before {
    content: '';
    position: absolute;
    width: 150%;
    height: 100%;
    background: linear-gradient(120deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
    transform: rotate(-45deg);
    animation: shine 3s infinite;
}

.welcome-message {
    padding: 2rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.welcome-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-avatar i {
    font-size: 2.5rem;
    color: white;
}

.welcome-text {
    flex: 1;
}

.welcome-text h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.welcome-text p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.welcome-info {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.welcome-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.welcome-info-item i {
    font-size: 1.2rem;
    opacity: 0.8;
}

@keyframes shine {
    0% { left: -150%; }
    50% { left: -60%; }
    100% { left: 150%; }
}
</style>

<!-- إضافة JavaScript للتأثيرات في نهاية الملف -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تأثيرات ظهور البطاقات
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        setTimeout(() => {
            card.classList.add('fade-in');
            card.style.opacity = '1';
        }, index * 100);
    });

    // تأثيرات ظهور الإحصائيات
    const stats = document.querySelectorAll('.stat-card');
    stats.forEach((stat, index) => {
        stat.classList.add('bounce');
        stat.style.animationDelay = `${index * 0.1}s`;
    });

    // تأثيرات القوائم
    const listItems = document.querySelectorAll('.list-group-item');
    listItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            item.classList.add('slide-in');
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, 300 + index * 100);
    });

    // تأثير تحميل البيانات
    const contentAreas = document.querySelectorAll('.card-body');
    contentAreas.forEach(area => {
        area.classList.add('loading');
        setTimeout(() => {
            area.classList.remove('loading');
        }, 1000);
    });
});
</script>

<div class="container mt-4">
  <!-- رسالة الترحيب -->
  <div class="welcome-card fade-in">
    <div class="welcome-message">
        <div class="welcome-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="welcome-text">
            <h4>مرحباً بك، <?php echo htmlspecialchars($userInfo['full_name']); ?></h4>
            <p>
                <?php 
                $roleTitle = '';
                $entityName = '';
                
                switch($userInfo['role']) {
                    case 'unit':
                        $roleTitle = 'رئيس وحدة في';
                        $entityName = $unitInfo['college_name'] . ' - ' . $unitInfo['university_name'];
                        break;
                    case 'division':
                        $roleTitle = 'رئيس شعبة في';
                        $entityName = $userInfo['division_name'] . ' - ' . $userInfo['university_name'];
                        break;
                    case 'ministry':
                        $roleTitle = 'مسؤول في';
                        $entityName = $userInfo['department_name'];
                        break;
                    case 'admin':
                        $roleTitle = 'مدير النظام';
                        break;
                }
                
                echo $roleTitle;
                if (!empty($entityName)) {
                    echo ' ' . $entityName;
                }
                ?>
            </p>
            <div class="welcome-info">
                <div class="welcome-info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo date('Y/m/d'); ?></span>
                </div>
                <div class="welcome-info-item">
                    <i class="fas fa-clock"></i>
                    <span><?php echo date('h:i A'); ?></span>
                </div>
                <div class="welcome-info-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>آخر دخول: <?php echo $userInfo['last_login'] ? date('Y/m/d h:i A', strtotime($userInfo['last_login'])) : 'أول دخول'; ?></span>
                </div>
            </div>
        </div>
    </div>
  </div>

  <!-- معلومات المستخدم -->
  <?php if ($userInfo['role'] == 'unit' && $unitInfo): ?>
  <div class="card unit-info-card mb-4 fade-in">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-building me-2"></i>
            معلومات الوحدة
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>اسم الوحدة</strong>
                    <p><?php echo $unitInfo['name']; ?></p>
                </div>
                <div class="unit-info-item">
                    <strong>الكلية</strong>
                    <p><?php echo $unitInfo['college_name'] ?? 'غير محدد'; ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>الشعبة</strong>
                    <p><?php echo $unitInfo['division_name'] ?? 'غير محدد'; ?></p>
                </div>
                <div class="unit-info-item">
                    <strong>الجامعة</strong>
                    <p><?php echo $unitInfo['university_name'] ?? 'غير محدد'; ?></p>
                </div>
            </div>
        </div>

        <div class="unit-info-divider"></div>

        <div class="row">
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>تاريخ الإنشاء</strong>
                    <p><?php echo date('Y-m-d H:i', strtotime($unitInfo['created_at'])); ?></p>
                    <div class="unit-meta">
                        <i class="fas fa-user"></i>
                        بواسطة: <?php echo $unitInfo['created_by_name'] ?? 'غير محدد'; ?>
                    </div>
                </div>
            </div>
            <?php if ($unitInfo['updated_at']): ?>
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>آخر تحديث</strong>
                    <p><?php echo date('Y-m-d H:i', strtotime($unitInfo['updated_at'])); ?></p>
                    <div class="unit-meta">
                        <i class="fas fa-user-edit"></i>
                        بواسطة: <?php echo $unitInfo['updated_by_name'] ?? 'غير محدد'; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($unitInfo['description']): ?>
        <div class="unit-description">
            <strong>
                <i class="fas fa-info-circle me-2"></i>
                الوصف
            </strong>
            <p><?php echo nl2br(htmlspecialchars($unitInfo['description'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
  </div>
  <?php elseif ($userInfo['role'] == 'division'): ?>
  <div class="card unit-info-card mb-4 fade-in">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-layer-group me-2"></i>
            معلومات الشعبة
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>اسم الشعبة</strong>
                    <p><?php echo $userInfo['division_name']; ?></p>
                </div>
                <div class="unit-info-item">
                    <strong>الجامعة</strong>
                    <p><?php echo $userInfo['university_name'] ?? 'غير محدد'; ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="unit-info-item">
                    <strong>تاريخ الإنشاء</strong>
                    <p><?php echo date('Y-m-d H:i', strtotime($userInfo['created_at'])); ?></p>
                    <div class="unit-meta">
                        <i class="fas fa-user"></i>
                        رئيس الشعبة: <?php echo $userInfo['full_name']; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="unit-info-divider"></div>

        <div class="row">
            <div class="col-md-12">
                <div class="unit-info-item">
                    <strong>إحصائيات الشعبة</strong>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-building fa-2x mb-2 text-primary"></i>
                                <h4><?php echo $stats['units']; ?></h4>
                                <p class="text-muted">الوحدات</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-file-alt fa-2x mb-2 text-success"></i>
                                <h4><?php echo $stats['documents']; ?></h4>
                                <p class="text-muted">الكتب</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-chart-bar fa-2x mb-2 text-warning"></i>
                                <h4><?php echo $stats['reports']; ?></h4>
                                <p class="text-muted">التقارير</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($userInfo['role'] == 'ministry'): ?>
  <div class="card mb-4">
    <div class="card-header bg-light">
      <h5 class="mb-0">
        <i class="fas fa-info-circle me-2"></i>
        معلومات <?php echo getEntityTypeLabel($userInfo['role']); ?>
      </h5>
    </div>
    <div class="card-body">
      <div class="row">
        <?php if ($userInfo['role'] == 'division' && isset($userInfo['division_name'])): ?>
          <div class="col-md-6">
            <p><strong>الشعبة:</strong> <?php echo $userInfo['division_name']; ?></p>
            <p><strong>الجامعة:</strong> <?php echo $userInfo['university_name'] ?? 'غير محدد'; ?></p>
          </div>
        <?php elseif ($userInfo['role'] == 'ministry' && isset($userInfo['department_name'])): ?>
          <div class="col-md-6">
            <p><strong>القسم:</strong> <?php echo $userInfo['department_name']; ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <h2>لوحة التحكم</h2>
  
  <!-- إحصائيات النظام -->
  <div class="row mb-4">
    <?php foreach ($stats as $key => $value): ?>
      <div class="col-md-3">
        <div class="card stat-card bg-<?php echo getStatCardColor($key); ?> text-white">
          <div class="card-body">
            <h5 class="card-title"><?php echo getStatLabel($key); ?></h5>
            <div class="d-flex justify-content-between align-items-center">
              <h2 class="mb-0 display-4"><?php echo $value; ?></h2>
              <i class="<?php echo getStatIcon($key); ?> stat-icon"></i>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  
  <div class="row">
    <!-- آخر الكتب -->
    <?php if (!empty($latestDocs)): ?>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <h5 class="mb-0">
            <i class="fas fa-file-alt me-2"></i>
            آخر الكتب
          </h5>
        </div>
        <div class="card-body">
          <div class="list-group">
            <?php foreach ($latestDocs as $doc): ?>
              <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                  <h6 class="mb-1"><?php echo $doc['title']; ?></h6>
                  <small class="text-muted"><?php echo timeAgo($doc['created_at']); ?></small>
                </div>
                <p class="mb-1 text-muted">
                  <i class="fas fa-user me-1"></i>
                  من: <?php echo $doc['sender_name'] ?? 'غير محدد'; ?>
                </p>
                <span class="badge <?php echo getStatusClass($doc['status']); ?>">
                  <?php echo getStatusLabel($doc['status']); ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- آخر التقارير -->
    <?php if (!empty($latestReports)): ?>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-light">
          <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>
            آخر التقارير
          </h5>
        </div>
        <div class="card-body">
          <div class="list-group">
            <?php foreach ($latestReports as $report): ?>
              <a href="view_report.php?id=<?php echo $report['id']; ?>" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                  <h6 class="mb-1"><?php echo $report['title']; ?></h6>
                  <small class="text-muted"><?php echo timeAgo($report['created_at']); ?></small>
                </div>
                <p class="mb-1 text-muted">
                  <i class="fas fa-building me-1"></i>
                  الوحدة: <?php echo $report['unit_name'] ?? 'غير محدد'; ?>
                </p>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php 
/**
 * دالة للحصول على لون البطاقة الإحصائية
 */
function getStatCardColor($key) {
    $colors = [
        'documents' => 'primary',
        'pending_documents' => 'warning',
        'reports' => 'success',
        'units' => 'info',
        'divisions' => 'secondary',
        'staff' => 'danger',
        'universities' => 'dark'
    ];
    return $colors[$key] ?? 'primary';
}

/**
 * دالة للحصول على عنوان الإحصائية
 */
function getStatLabel($key) {
    $labels = [
        'documents' => 'الكتب',
        'pending_documents' => 'كتب قيد الانتظار',
        'reports' => 'التقارير',
        'units' => 'الوحدات',
        'divisions' => 'الشعب',
        'staff' => 'الموظفين',
        'universities' => 'الجامعات'
    ];
    return $labels[$key] ?? $key;
}

/**
 * دالة للحصول على أيقونة الإحصائية
 */
function getStatIcon($key) {
    $icons = [
        'documents' => 'fas fa-file-alt',
        'pending_documents' => 'fas fa-clock',
        'reports' => 'fas fa-chart-bar',
        'units' => 'fas fa-building',
        'divisions' => 'fas fa-layer-group',
        'staff' => 'fas fa-users',
        'universities' => 'fas fa-university'
    ];
    return $icons[$key] ?? 'fas fa-chart-line';
}
?>

<?php include 'footer.php'; ?>
