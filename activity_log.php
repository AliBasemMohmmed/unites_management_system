<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
if (!hasPermission('view_activity_log')) {
  die('غير مصرح لك بعرض سجل النشاطات');
}

include 'header.php';

$page = $_GET['page'] ?? 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// جلب إجمالي عدد السجلات
$totalLogs = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// جلب السجلات مع معلومات المستخدم
$logs = $pdo->prepare("
  SELECT l.*, u.username, u.full_name 
  FROM activity_log l
  LEFT JOIN users u ON l.user_id = u.id
  ORDER BY l.created_at DESC
  LIMIT ? OFFSET ?
");
$logs->execute([$perPage, $offset]);
?>

<div class="container mt-4">
  <h2>سجل النشاطات</h2>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>المستخدم</th>
              <th>النشاط</th>
              <th>النوع</th>
              <th>التفاصيل</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($log = $logs->fetch()): ?>
              <tr>
                <td><?php echo $log['created_at']; ?></td>
                <td><?php echo $log['full_name'] ?? 'غير معروف'; ?></td>
                <td><?php echo $log['action']; ?></td>
                <td><?php echo $log['entity_type']; ?></td>
                <td>
                  <button type="button" class="btn btn-sm btn-info" 
                          data-bs-toggle="modal" 
                          data-bs-target="#detailsModal<?php echo $log['id']; ?>">
                    التفاصيل
                  </button>

                  <!-- Modal -->
                  <div class="modal fade" id="detailsModal<?php echo $log['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">تفاصيل النشاط</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <pre><?php echo json_encode(json_decode($log['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
