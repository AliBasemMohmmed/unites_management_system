<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$type = $_GET['type'] ?? 'documents';
?>

<div class="container mt-4">
  <h2>الأرشيف</h2>

  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-3">
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
        <div class="col-md-3">
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
        <div class="col-md-3">
          <label class="form-label">النوع</label>
          <select name="type" class="form-control">
            <option value="documents" <?php echo $type == 'documents' ? 'selected' : ''; ?>>الكتب</option>
            <option value="reports" <?php echo $type == 'reports' ? 'selected' : ''; ?>>التقارير</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <button type="submit" class="btn btn-primary w-100">عرض</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      نتائج الأرشيف
    </div>
    <div class="card-body">
      <?php
      if ($type == 'documents') {
        $sql = "SELECT d.*, 
                CASE 
                  WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
                  WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
                  WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
                END as sender_name
                FROM documents d 
                WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
                ORDER BY created_at DESC";
      } else {
        $sql = "SELECT r.*, u.name as unit_name 
                FROM reports r 
                JOIN units u ON r.unit_id = u.id 
                WHERE YEAR(r.created_at) = ? AND MONTH(r.created_at) = ?
                ORDER BY r.created_at DESC";
      }

      $stmt = $pdo->prepare($sql);
      $stmt->execute([$year, $month]);
      $results = $stmt->fetchAll();

      if (count($results) > 0):
        if ($type == 'documents'): ?>
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
                    <?php if ($row['file_path']): ?>
                      <a href="<?php echo $row['file_path']; ?>" class="btn btn-sm btn-secondary" target="_blank">تحميل</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
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
                    <?php if ($row['file_path']): ?>
                      <a href="<?php echo $row['file_path']; ?>" class="btn btn-sm btn-secondary" target="_blank">تحميل</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-center">لا توجد نتائج للفترة المحددة</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
