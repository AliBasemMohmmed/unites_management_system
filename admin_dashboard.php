<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من صلاحيات المدير
if (!hasPermission('access_admin_dashboard')) {
  die('غير مصرح لك بالوصول إلى لوحة تحكم المدير');
}

include 'header.php';

// إحصائيات النظام
$stats = [
  'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
  'documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
  'pending_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn(),
  'reports' => $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn(),
  'units' => $pdo->query("SELECT COUNT(*) FROM units")->fetchColumn()
];

// النشاطات الأخيرة
$recentActivities = $pdo->query("
  SELECT l.*, u.username, u.full_name 
  FROM activity_log l
  LEFT JOIN users u ON l.user_id = u.id
  ORDER BY l.created_at DESC
  LIMIT 10
")->fetchAll();

// المستخدمين النشطين
$activeUsers = $pdo->query("
  SELECT u.*, COUNT(l.id) as activity_count 
  FROM users u
  LEFT JOIN activity_log l ON u.id = l.user_id
  WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  GROUP BY u.id
  ORDER BY activity_count DESC
  LIMIT 10
")->fetchAll();
?>

<div class="container-fluid mt-4">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-2">
      <div class="list-group">
        <a href="#overview" class="list-group-item list-group-item-action active" data-bs-toggle="list">نظرة عامة</a>
        <a href="#users" class="list-group-item list-group-item-action" data-bs-toggle="list">المستخدمون</a>
        <a href="#documents" class="list-group-item list-group-item-action" data-bs-toggle="list">الكتب</a>
        <a href="#reports" class="list-group-item list-group-item-action" data-bs-toggle="list">التقارير</a>
        <a href="#system" class="list-group-item list-group-item-action" data-bs-toggle="list">إعدادات النظام</a>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-10">
      <div class="tab-content">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview">
          <h2>نظرة عامة على النظام</h2>
          
          <!-- Stats Cards -->
          <div class="row mb-4">
            <div class="col-md-3">
              <div class="card bg-primary text-white">
                <div class="card-body">
                  <h5>المستخدمون</h5>
                  <div class="d-flex justify-content-between">
                    <h2><?php echo $stats['users']; ?></h2>
                    <i class="fas fa-users fa-2x"></i>
                  </div>
                  <small>نشط: <?php echo $stats['active_users']; ?></small>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-success text-white">
                <div class="card-body">
                  <h5>الكتب</h5>
                  <div class="d-flex justify-content-between">
                    <h2><?php echo $stats['documents']; ?></h2>
                    <i class="fas fa-file-alt fa-2x"></i>
                  </div>
                  <small>قيد الانتظار: <?php echo $stats['pending_documents']; ?></small>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-info text-white">
                <div class="card-body">
                  <h5>التقارير</h5>
                  <div class="d-flex justify-content-between">
                    <h2><?php echo $stats['reports']; ?></h2>
                    <i class="fas fa-chart-bar fa-2x"></i>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-warning text-white">
                <div class="card-body">
                  <h5>الوحدات</h5>
                  <div class="d-flex justify-content-between">
                    <h2><?php echo $stats['units']; ?></h2>
                    <i class="fas fa-building fa-2x"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Activities -->
          <div class="card mb-4">
            <div class="card-header">
              <h5>النشاطات الأخيرة</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>المستخدم</th>
                      <th>النشاط</th>
                      <th>النوع</th>
                      <th>التاريخ</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                      <tr>
                        <td><?php echo $activity['full_name']; ?></td>
                        <td><?php echo $activity['action']; ?></td>
                        <td><?php echo $activity['entity_type']; ?></td>
                        <td><?php echo $activity['created_at']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Active Users -->
          <div class="card">
            <div class="card-header">
              <h5>المستخدمون النشطون</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>المستخدم</th>
                      <th>الدور</th>
                      <th>عدد النشاطات</th>
                      <th>آخر نشاط</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($activeUsers as $user): ?>
                      <tr>
                        <td><?php echo $user['full_name']; ?></td>
                        <td><?php echo $user['role']; ?></td>
                        <td><?php echo $user['activity_count']; ?></td>
                        <td><?php echo $user['last_login']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- System Settings Tab -->
        <div class="tab-pane fade" id="system">
          <h2>إعدادات النظام</h2>
          
          <div class="card">
            <div class="card-body">
              <form method="POST" action="update_settings.php">
                <div class="mb-3">
                  <label class="form-label">اسم النظام</label>
                  <input type="text" name="system_name" class="form-control" value="<?php echo getSystemSetting('system_name'); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">البريد الإلكتروني للنظام</label>
                  <input type="email" name="system_email" class="form-control" value="<?php echo getSystemSetting('system_email'); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">مدة جلسة المستخدم (بالدقائق)</label>
                  <input type="number" name="session_lifetime" class="form-control" value="<?php echo getSystemSetting('session_lifetime'); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">عدد محاولات تسجيل الدخول المسموح بها</label>
                  <input type="number" name="max_login_attempts" class="form-control" value="<?php echo getSystemSetting('max_login_attempts'); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">مدة حفظ السجلات (بالأيام)</label>
                  <input type="number" name="log_retention_days" class="form-control" value="<?php echo getSystemSetting('log_retention_days'); ?>">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">تفعيل النسخ الاحتياطي التلقائي</label>
                  <div class="form-check">
                    <input type="checkbox" name="auto_backup_enabled" class="form-check-input" 
                           <?php echo getSystemSetting('auto_backup_enabled') ? 'checked' : ''; ?>>
                    <label class="form-check-label">تفعيل</label>
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">توقيت النسخ الاحتياطي التلقائي</label>
                  <input type="time" name="auto_backup_time" class="form-control" value="<?php echo getSystemSetting('auto_backup_time'); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
