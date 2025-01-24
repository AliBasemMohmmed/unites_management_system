<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

if (!hasPermission('monitor_system')) {
  die('غير مصرح لك بمراقبة النظام');
}

include 'header.php';

// معلومات النظام
$systemInfo = [
  'php_version' => PHP_VERSION,
  'server_software' => $_SERVER['SERVER_SOFTWARE'],
  'database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
  'max_upload_size' => ini_get('upload_max_filesize'),
  'max_post_size' => ini_get('post_max_size'),
  'memory_limit' => ini_get('memory_limit')
];

// إحصائيات قاعدة البيانات
$dbStats = [
  'tables' => $pdo->query("SHOW TABLES")->rowCount(),
  'total_size' => $pdo->query("
    SELECT SUM(data_length + index_length) / 1024 / 1024 AS size 
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
  ")->fetchColumn()
];

// حالة الخدمات
$services = [
  'database' => true,
  'file_system' => is_writable('uploads/'),
  'backup_system' => is_writable('backups/'),
  'mail_system' => function_exists('mail')
];
?>

<div class="container mt-4">
  <h2>مراقبة النظام</h2>

  <div class="row">
    <!-- System Information -->
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header">
          <h5>معلومات النظام</h5>
        </div>
        <div class="card-body">
          <table class="table">
            <tr>
              <td>إصدار PHP</td>
              <td><?php echo $systemInfo['php_version']; ?></td>
            </tr>
            <tr>
              <td>برنامج الخادم</td>
              <td><?php echo $systemInfo['server_software']; ?></td>
            </tr>
            <tr>
              <td>إصدار قاعدة البيانات</td>
              <td><?php echo $systemInfo['database_version']; ?></td>
            </tr>
            <tr>
              <td>الحد الأقصى لحجم الملف</td>
              <td><?php echo $systemInfo['max_upload_size']; ?></td>
            </tr>
            <tr>
              <td>الحد الأقصى لحجم الطلب</td>
              <td><?php echo $systemInfo['max_post_size']; ?></td>
            </tr>
            <tr>
              <td>حد الذاكرة</td>
              <td><?php echo $systemInfo['memory_limit']; ?></td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <!-- Database Stats -->
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header">
          <h5>إحصائيات قاعدة البيانات</h5>
        </div>
        <div class="card-body">
          <table class="table">
            <tr>
              <td>عدد الجداول</td>
              <td><?php echo $dbStats['tables']; ?></td>
            </tr>
            <tr>
              <td>حجم قاعدة البيانات</td>
              <td><?php echo round($dbStats['total_size'], 2); ?> MB</td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Services Status -->
  <div class="card mb-4">
    <div class="card-header">
      <h5>حالة الخدمات</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <?php foreach ($services as $service => $status): ?>
          <div class="col-md-3 mb-3">
            <div class="card <?php echo $status ? 'bg-success' : 'bg-danger'; ?> text-white">
              <div class="card-body">
                <h5><?php echo ucfirst($service); ?></h5>
                <span><?php echo $status ? 'يعمل' : 'متوقف'; ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- System Logs -->
  <div class="card">
    <div class="card-header">
      <h5>سجلات النظام الأخيرة</h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>النوع</th>
              <th>الرسالة</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $systemLogs = $pdo->query("
              SELECT * FROM system_logs 
              ORDER BY created_at DESC 
              LIMIT 10
            ")->fetchAll();
            
            foreach ($systemLogs as $log):
            ?>
              <tr>
                <td><?php echo $log['created_at']; ?></td>
                <td>
                  <span class="badge bg-<?php echo $log['type'] == 'error' ? 'danger' : 'info'; ?>">
                    <?php echo $log['type']; ?>
                  </span>
                </td>
                <td><?php echo $log['message']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
