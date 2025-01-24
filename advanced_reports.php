<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

// التحقق من الصلاحيات
// if (!hasPermission('view_advanced_reports')) {
//   die('غير مصرح لك بعرض التقارير المتقدمة');
// }

// استلام المعايير
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'summary';
$groupBy = $_GET['group_by'] ?? 'daily';
?>

<div class="container-fluid mt-4">
  <h2>التقارير المتقدمة</h2>

  <!-- فلتر التقارير -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">من تاريخ</label>
          <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">إلى تاريخ</label>
          <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">نوع التقرير</label>
          <select name="report_type" class="form-control">
            <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>ملخص</option>
            <option value="detailed" <?php echo $reportType == 'detailed' ? 'selected' : ''; ?>>تفصيلي</option>
            <option value="performance" <?php echo $reportType == 'performance' ? 'selected' : ''; ?>>الأداء</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">تجميع حسب</label>
          <select name="group_by" class="form-control">
            <option value="daily" <?php echo $groupBy == 'daily' ? 'selected' : ''; ?>>يومي</option>
            <option value="weekly" <?php echo $groupBy == 'weekly' ? 'selected' : ''; ?>>أسبوعي</option>
            <option value="monthly" <?php echo $groupBy == 'monthly' ? 'selected' : ''; ?>>شهري</option>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">عرض التقرير</button>
          <button type="button" class="btn btn-success" onclick="exportReport()">تصدير Excel</button>
        </div>
      </form>
    </div>
  </div>

  <?php
  // بناء الاستعلام حسب نوع التقرير
  $params = [$startDate, $endDate];
  
  if ($reportType == 'summary') {
    // تقرير ملخص
    $groupByClause = "";
    switch ($groupBy) {
      case 'daily':
        $groupByClause = "DATE(created_at)";
        break;
      case 'weekly':
        $groupByClause = "YEARWEEK(created_at)";
        break;
      case 'monthly':
        $groupByClause = "DATE_FORMAT(created_at, '%Y-%m')";
        break;
    }

    $sql = "
      SELECT 
        $groupByClause as period,
        COUNT(*) as total_documents,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_documents,
        AVG(CASE 
          WHEN status = 'processed' AND updated_at IS NOT NULL 
          THEN TIMESTAMPDIFF(HOUR, created_at, updated_at)
          ELSE TIMESTAMPDIFF(HOUR, created_at, NOW())
        END) as avg_processing_time
      FROM documents
      WHERE created_at BETWEEN ? AND ?
      GROUP BY $groupByClause
      ORDER BY period DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    ?>

    <!-- عرض التقرير الملخص -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>تقرير ملخص</h5>
      </div>
      <div class="card-body">
        <!-- الرسم البياني -->
        <canvas id="summaryChart" class="mb-4"></canvas>

        <!-- جدول البيانات -->
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>الفترة</th>
                <th>إجمالي الكتب</th>
                <th>قيد الانتظار</th>
                <th>تمت المعالجة</th>
                <th>متوسط وقت المعالجة (ساعة)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo $row['period']; ?></td>
                  <td><?php echo $row['total_documents']; ?></td>
                  <td><?php echo $row['pending_documents']; ?></td>
                  <td><?php echo $row['processed_documents']; ?></td>
                  <td><?php echo round($row['avg_processing_time'], 1); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script>
      // إعداد الرسم البياني
      const ctx = document.getElementById('summaryChart').getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($results, 'period')); ?>,
          datasets: [
            {
              label: 'قيد الانتظار',
              data: <?php echo json_encode(array_column($results, 'pending_documents')); ?>,
              backgroundColor: 'rgba(255, 193, 7, 0.5)'
            },
            {
              label: 'تمت المعالجة',
              data: <?php echo json_encode(array_column($results, 'processed_documents')); ?>,
              backgroundColor: 'rgba(40, 167, 69, 0.5)'
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    </script>

  <?php } elseif ($reportType == 'performance') {
    // تقرير الأداء
    $sql = "
      SELECT 
        u.full_name,
        COUNT(d.id) as total_processed,
        AVG(CASE 
          WHEN d.status = 'processed' 
          THEN TIMESTAMPDIFF(HOUR, d.created_at, d.updated_at)
          ELSE NULL 
        END) as avg_processing_time,
        MIN(CASE 
          WHEN d.status = 'processed' 
          THEN TIMESTAMPDIFF(HOUR, d.created_at, d.updated_at)
          ELSE NULL 
        END) as min_processing_time,
        MAX(CASE 
          WHEN d.status = 'processed' 
          THEN TIMESTAMPDIFF(HOUR, d.created_at, d.updated_at)
          ELSE NULL 
        END) as max_processing_time
      FROM users u
      LEFT JOIN documents d ON u.id = d.processor_id AND d.created_at BETWEEN ? AND ?
      WHERE u.role IN ('admin', 'ministry', 'division')
      GROUP BY u.id, u.full_name
      HAVING total_processed > 0
      ORDER BY total_processed DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    ?>

    <!-- عرض تقرير الأداء -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>تقرير الأداء</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>الموظف</th>
                <th>عدد الكتب المعالجة</th>
                <th>متوسط وقت المعالجة (ساعة)</th>
                <th>أقل وقت معالجة</th>
                <th>أعلى وقت معالجة</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $row): ?>
                <tr>
                  <td><?php echo $row['full_name']; ?></td>
                  <td><?php echo $row['total_processed']; ?></td>
                  <td><?php echo round($row['avg_processing_time'], 1); ?></td>
                  <td><?php echo round($row['min_processing_time'], 1); ?></td>
                  <td><?php echo round($row['max_processing_time'], 1); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php } ?>
</div>

<script>
function exportReport() {
  const params = new URLSearchParams(window.location.search);
  params.append('export', 'excel');
  window.location.href = 'export_report.php?' + params.toString();
}
</script>

<?php include 'footer.php'; ?>
