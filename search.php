<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

$searchType = $_GET['type'] ?? 'documents';
$keyword = $_GET['keyword'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
?>

<div class="container mt-4">
  <h2>البحث المتقدم</h2>
  
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
          <label class="form-label">نوع البحث</label>
          <select name="type" class="form-control">
            <option value="documents" <?php echo $searchType == 'documents' ? 'selected' : ''; ?>>الكتب</option>
            <option value="reports" <?php echo $searchType == 'reports' ? 'selected' : ''; ?>>التقارير</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">كلمة البحث</label>
          <input type="text" name="keyword" class="form-control" value="<?php echo htmlspecialchars($keyword); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">من تاريخ</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">إلى تاريخ</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">الحالة</label>
          <select name="status" class="form-control">
            <option value="">الكل</option>
            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
            <option value="received" <?php echo $status == 'received' ? 'selected' : ''; ?>>تم الاستلام</option>
            <option value="processed" <?php echo $status == 'processed' ? 'selected' : ''; ?>>تمت المعالجة</option>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">بحث</button>
          <?php if ($searchType == 'documents'): ?>
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">تصدير النتائج</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if ($keyword || $dateFrom || $dateTo || $status): ?>
    <div class="card">
      <div class="card-header">
        نتائج البحث
      </div>
      <div class="card-body">
        <?php
        $params = [];
        $sql = "";
        
        if ($searchType == 'documents') {
          $sql = "SELECT d.*, 
                  CASE 
                    WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
                    WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
                    WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
                  END as sender_name
                  FROM documents d WHERE 1=1";
          
          if ($keyword) {
            $sql .= " AND (d.title LIKE ? OR d.content LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
          }
          if ($dateFrom) {
            $sql .= " AND DATE(d.created_at) >= ?";
            $params[] = $dateFrom;
          }
          if ($dateTo) {
            $sql .= " AND DATE(d.created_at) <= ?";
            $params[] = $dateTo;
          }
          if ($status) {
            $sql .= " AND d.status = ?";
            $params[] = $status;
          }
          
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
          $results = $stmt->fetchAll();
          
          if (count($results) > 0): ?>
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>العنوان</th>
                  <th>المرسل</th>
                  <th>الحالة</th>
                  <th>التاريخ</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($results as $row): ?>
                  <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['title']; ?></td>
                    <td><?php echo $row['sender_name']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                      <a href="view_document.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">عرض</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-center">لا توجد نتائج</p>
          <?php endif;
        } else {
          // بحث في التقارير
          $sql = "SELECT r.*, u.name as unit_name 
                  FROM reports r 
                  JOIN units u ON r.unit_id = u.id 
                  WHERE 1=1";
          
          if ($keyword) {
            $sql .= " AND (r.title LIKE ? OR r.content LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
          }
          if ($dateFrom) {
            $sql .= " AND DATE(r.created_at) >= ?";
            $params[] = $dateFrom;
          }
          if ($dateTo) {
            $sql .= " AND DATE(r.created_at) <= ?";
            $params[] = $dateTo;
          }
          
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
          $results = $stmt->fetchAll();
          
          if (count($results) > 0): ?>
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>العنوان</th>
                  <th>الوحدة</th>
                  <th>التاريخ</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($results as $row): ?>
                  <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['title']; ?></td>
                    <td><?php echo $row['unit_name']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                      <a href="view_report.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">عرض</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-center">لا توجد نتائج</p>
          <?php endif;
        }
        ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
