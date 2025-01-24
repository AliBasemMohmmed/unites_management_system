<?php
require_once 'functions.php';
include 'header.php';
?>

<div class="container mt-4">
  <h2>إدارة الأقسام</h2>
  
  <!-- نموذج إضافة قسم جديد -->
  <div class="card mb-4">
    <div class="card-header">
      إضافة قسم جديد
    </div>
    <div class="card-body">
      <form method="POST" action="process_department.php">
        <div class="mb-3">
          <label class="form-label">اسم القسم</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">الوصف</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">إضافة القسم</button>
      </form>
    </div>
  </div>

  <!-- عرض الأقسام الحالية -->
  <div class="card">
    <div class="card-header">
      الأقسام الحالية
    </div>
    <div class="card-body">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>اسم القسم</th>
            <th>الوصف</th>
            <th>تاريخ الإنشاء</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $pdo->query("SELECT * FROM ministry_departments ORDER BY id DESC");
          while ($row = $stmt->fetch()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['created_at']}</td>
                    <td>
                      <a href='edit_department.php?id={$row['id']}' class='btn btn-sm btn-primary'>تعديل</a>
                      <a href='delete_department.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a>
                    </td>
                  </tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
