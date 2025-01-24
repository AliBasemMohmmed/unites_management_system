<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';
?>

<div class="container mt-4">
  <h2>إدارة التقارير</h2>
  
  <?php if (hasPermission('add_report')): ?>
  <div class="card mb-4">
    <div class="card-header">
      إضافة تقرير جديد
    </div>
    <div class="card-body">
      <form method="POST" action="process_report.php" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">عنوان التقرير</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">محتوى التقرير</label>
          <textarea name="content" class="form-control" rows="5" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">الملف المرفق</label>
          <input type="file" name="report_file" class="form-control">
        </div>
        <?php if (getUserRole() === 'unit'): ?>
          <input type="hidden" name="unit_id" value="<?php echo $_SESSION['entity_id']; ?>">
        <?php else: ?>
          <div class="mb-3">
            <label class="form-label">الوحدة</label>
            <select name="unit_id" class="form-control" required>
              <?php
              $units = $pdo->query("SELECT * FROM units")->fetchAll();
              foreach ($units as $unit) {
                echo "<option value='{$unit['id']}'>{$unit['name']}</option>";
              }
              ?>
            </select>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">إضافة التقرير</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      التقارير الحالية
    </div>
    <div class="card-body">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>العنوان</th>
            <th>الوحدة</th>
            <th>تاريخ الإنشاء</th>
            <th>الملف المرفق</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sql = "SELECT r.*, u.name as unit_name 
                  FROM reports r 
                  JOIN units u ON r.unit_id = u.id";
          
          if (getUserRole() === 'unit') {
            $sql .= " WHERE r.unit_id = " . $_SESSION['entity_id'];
          }
          
          $sql .= " ORDER BY r.created_at DESC";
          
          $stmt = $pdo->query($sql);
          while ($row = $stmt->fetch()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['title']}</td>
                    <td>{$row['unit_name']}</td>
                    <td>{$row['created_at']}</td>
                    <td>";
            if ($row['file_path']) {
              echo "<a href='{$row['file_path']}' target='_blank' class='btn btn-sm btn-info'>عرض الملف</a>";
            } else {
              echo "لا يوجد ملف مرفق";
            }
            echo "</td><td>";
            if (hasPermission('edit_report')) {
              echo "<a href='edit_report.php?id={$row['id']}' class='btn btn-sm btn-primary'>تعديل</a> ";
            }
            if (hasPermission('delete_report')) {
              echo "<a href='delete_report.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a>";
            }
            echo "</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
