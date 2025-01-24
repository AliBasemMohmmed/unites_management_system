<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من صلاحيات النسخ الاحتياطي
if (!hasPermission('manage_backup')) {
  die('غير مصرح لك بإدارة النسخ الاحتياطي');
}

function createBackup() {
  global $pdo;
  
  $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
  $backup = [];
  
  foreach ($tables as $table) {
    $structure = $pdo->query("SHOW CREATE TABLE $table")->fetch();
    $backup[$table]['structure'] = $structure['Create Table'];
    
    $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    $backup[$table]['data'] = $rows;
  }
  
  $backupDir = 'backups';
  if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
  }
  
  $filename = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.json';
  file_put_contents($filename, json_encode($backup, JSON_PRETTY_PRINT));
  
  return $filename;
}

// معالجة طلب النسخ الاحتياطي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
  try {
    $backupFile = createBackup();
    $success = "تم إنشاء النسخة الاحتياطية بنجاح: " . basename($backupFile);
  } catch (Exception $e) {
    $error = "حدث خطأ أثناء إنشاء النسخة الاحتياطية: " . $e->getMessage();
  }
}

include 'header.php';
?>

<div class="container mt-4">
  <h2>إدارة النسخ الاحتياطي</h2>

  <?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
  <?php endif; ?>

  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header">
          إنشاء نسخة احتياطية جديدة
        </div>
        <div class="card-body">
          <form method="POST">
            <button type="submit" name="create_backup" class="btn btn-primary">
              إنشاء نسخة احتياطية
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          النسخ الاحتياطية السابقة
        </div>
        <div class="card-body">
          <?php
          $backupDir = 'backups';
          if (file_exists($backupDir)) {
            $backups = glob($backupDir . '/backup_*.json');
            if (count($backups) > 0):
          ?>
            <div class="list-group">
              <?php foreach ($backups as $backup): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <?php echo basename($backup); ?>
                  <div>
                    <a href="<?php echo $backup; ?>" class="btn btn-sm btn-info" download>تحميل</a>
                    <a href="delete_backup.php?file=<?php echo urlencode(basename($backup)); ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('هل أنت متأكد من حذف هذه النسخة الاحتياطية؟')">حذف</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php 
            else:
              echo '<p class="text-center">لا توجد نسخ احتياطية</p>';
            endif;
          } else {
            echo '<p class="text-center">لا توجد نسخ احتياطية</p>';
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
