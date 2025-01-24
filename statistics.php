<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

// إحصائيات عامة
$stats = [
  'documents' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
  'pending_docs' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn(),
  'processed_docs' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'processed'")->fetchColumn(),
  'reports' => $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn()
];

// إحصائيات شهرية
$monthlyStats = $pdo->query("
  SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
  FROM documents
  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
  ORDER BY month DESC
  LIMIT 12
")->fetchAll();

// إحصائيات حسب النوع
$typeStats = $pdo->query("
  SELECT sender_type, COUNT(*) as count
  FROM documents
  GROUP BY sender_type
")->fetchAll();
?>

<div class="container mt-4">
  <h2>الإحصائيات والتقارير</h2>

  <!-- إحصائيات عامة -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <h5>إجمالي الكتب</h5>
          <h2><?php echo $stats['documents']; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-warning text-white">
        <div class="card-body">
          <h5>كتب قيد الانتظار</h5>
          <h2><?php echo $stats['pending_docs']; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body">
          <h5>كتب تمت معالجتها</h5>
          <h2><?php echo $stats['processed_docs']; ?></h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body">
          <h5>إجمالي التقارير</h5>
          <h2><?php echo $stats['reports']; ?></h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- الرسم البياني الشهري -->
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header">
          إحصائيات الكتب الشهرية
        </div>
        <div class="card-body">
          <canvas id="monthlyChart"></canvas>
        </div>
      </div>
    </div>

    <!-- إحصائيات حسب النوع -->
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header">
          توزيع الكتب حسب المصدر
        </div>
        <div class="card-body">
          <canvas id="typeChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- إضافة مكتبة Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// إعداد الرسم البياني الشهري
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode(array_column($monthlyStats, 'month')); ?>,
    datasets: [{
      label: 'عدد الكتب',
      data: <?php echo json_encode(array_column($monthlyStats, 'count')); ?>,
      borderColor: 'rgb(75, 192, 192)',
      tension: 0.1
    }]
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

// إعداد الرسم البياني الدائري
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
  type: 'pie',
  data: {
    labels: <?php echo json_encode(array_column($typeStats, 'sender_type')); ?>,
    datasets: [{
      data: <?php echo json_encode(array_column($typeStats, 'count')); ?>,
      backgroundColor: [
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(255, 205, 86)'
      ]
    }]
  }
});
</script>

<?php include 'footer.php'; ?>
