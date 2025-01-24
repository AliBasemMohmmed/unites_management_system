<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

// التحقق من الصلاحيات
if (!hasPermission('view_periodic_reports')) {
  die('غير مصرح لك بعرض التقارير الدورية');
}

$period = $_GET['period'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
?>

<div class="container mt-4">
  <h2>التقارير الدورية</h2>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">نوع التقرير</label>
          <select name="period" class="form-control">
            <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>شهري</option>
            <option value="quarterly" <?php echo $period == 'quarterly' ? 'selected' : ''; ?>>ربع سنوي</option>
            <option value="yearly" <?php echo $period == 'yearly' ? 'selected' : ''; ?>>سنوي</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">السنة</label>
          <select name="year" class="form-control">
            <?php
            $currentYear = date('Y');
            for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
              $selected = $y == $year ? 'selected' : '';
              echo "<option value='$y' $selected>$y</option>";
            }
            ?>
          </select>
        </div>
        <?php if ($period == 'monthly'): ?>
        <div class="col-md-4">
          <label class="form-label">الشهر</label>
          <select name="month" class="form-control">
            <?php
            for ($m = 1; $m <= 12; $m++) {
              $selected = $m == $month ? 'selected' : '';
              echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
            }
            ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">عرض التقرير</button>
          <a href="export_periodic_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">تصدير التقرير</a>
        </div>
      </form>
    </div>
  </div>

  <?php
  // بناء الاستعلام حسب نوع التقرير
  $params = [];
  $dateCondition = "";
  
  if ($period == 'monthly') {
    $dateCondition = "YEAR(created_at) = ? AND MONTH(created_at) = ?";
    $params = [$year, $month];
  } elseif ($period == 'quarterly') {
    $quarter = ceil($month / 3);
    $dateCondition = "YEAR(created_at) = ? AND QUARTER(created_at) = ?";
    $params = [$year, $quarter];
  } else {
    $dateCondition = "YEAR(created_at) = ?";
    $params = [$year];
  }

  // إحصائيات الكتب
  $docStats = $pdo->prepare("
    SELECT 
      COUNT(*) as total_docs,
      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_docs,
      SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_docs
    FROM documents
    WHERE $dateCondition
  ");
  $docStats->execute($params);
  $docSummary = $docStats->fetch();

  // إحصائيات التقارير
  $reportStats = $pdo->prepare("
    SELECT COUNT(*) as total_reports
    FROM reports
    WHERE $dateCondition
  ");
  $reportStats->execute($params);
  $reportSummary = $reportStats->fetch();
  ?>

  <!-- عرض ملخص التقرير -->
  <div class="card mb-4">
    <div class="card-header">
      ملخص التقرير
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body">
              <h5>إجمالي الكتب</h5>
              <h3><?php echo $docSummary['total_docs']; ?></h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-warning text-white">
            <div class="card-body">
              <h5>كتب قيد الانتظار</h5>
              <h3><?php echo $docSummary['pending_docs']; ?></h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-success text-white">
            <div class="card-body">
              <h5>كتب تمت معالجتها</h5>
              <h3><?php echo $docSummary['processed_docs']; ?></h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-info text-white">
            <div class="card-body">
              <h5>إجمالي التقارير</h5>
              <h3><?php echo $reportSummary['total_reports']; ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- تفاصيل الكتب -->
  <div class="card mb-4">
    <div class="card-header">
      تفاصيل الكتب
    </div>
    <div class="card-body">
      <?php
      $detailedDocs = $pdo->prepare("
        SELECT d.*, 
          CASE 
            WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
            WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
            WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
          END as sender_name
        FROM documents d
        WHERE $dateCondition
        ORDER BY d.created_at DESC
      ");
      $detailedDocs->execute($params);
      ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>العنوان</th>
            <th>المرسل</th>
            <th>الحالة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($doc = $detailedDocs->fetch()): ?>
            <tr>
              <td><?php echo $doc['id']; ?></td>
              <td><?php echo $doc['title']; ?></td>
              <td><?php echo $doc['sender_name']; ?></td>
              <td><?php echo $doc['status']; ?></td>
              <td><?php echo $doc['created_at']; ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- تفاصيل التقارير -->
  <div class="card">
    <div class="card-header">
      تفاصيل التقارير
    </div>
    <div class="card-body">
      <?php
      $detailedReports = $pdo->prepare("
        SELECT r.*, u.name as unit_name
        FROM reports r
        JOIN units u ON r.unit_id = u.id
        WHERE $dateCondition
        ORDER BY r.created_at DESC
      ");
      $detailedReports->execute($params);
      ?>
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>العنوان</th>
            <th>الوحدة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($report = $detailedReports->fetch()): ?>
            <tr>
              <td><?php echo $report['id']; ?></td>
              <td><?php echo $report['title']; ?></td>
              <td><?php echo $report['unit_name']; ?></td>
              <td><?php echo $report['created_at']; ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
