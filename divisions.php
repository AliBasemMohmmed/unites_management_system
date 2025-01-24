<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';
?>

<div class="container mt-4">
  <h2>إدارة الشعب</h2>
  
  <?php if (hasPermission('add_division')): ?>
  <div class="card mb-4">
    <div class="card-header">
      إضافة شعبة جديدة
    </div>
    <div class="card-body">
      <form method="POST" action="process_division.php">
        <div class="mb-3">
          <label class="form-label">اسم الشعبة</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">الجامعة</label>
          <select name="university_id" id="university_select" class="form-control" required>
            <option value="">اختر الجامعة</option>
            <?php
            // جلب الجامعات التي لم يتم تعيين رئيس شعبة لها
            $universities = $pdo->query("
                SELECT u.* 
                FROM universities u
                WHERE u.id NOT IN (
                    SELECT DISTINCT university_id 
                    FROM university_divisions 
                    WHERE division_manager_id IS NOT NULL
                )
                ORDER BY u.name
            ")->fetchAll();

            if (empty($universities)) {
                echo "<option value='' disabled>لا توجد جامعات متاحة</option>";
            } else {
                foreach ($universities as $univ) {
                    echo "<option value='{$univ['id']}'>{$univ['name']}</option>";
                }
            }
            ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">مدير الشعبة</label>
          <select name="division_manager_id" id="division_manager_select" class="form-control" required>
            <option value="">اختر مدير الشعبة</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">إضافة الشعبة</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      الشعب الحالية
    </div>
    <div class="card-body">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>اسم الشعبة</th>
            <th>الجامعة</th>
            <th>مدير الشعبة</th>
            <th>تاريخ الإنشاء</th>
            <th>الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $pdo->query("
            SELECT d.*, u.name as university_name,
                   m.full_name as manager_name
            FROM university_divisions d 
            JOIN universities u ON d.university_id = u.id 
            LEFT JOIN users m ON d.division_manager_id = m.id
            ORDER BY d.id DESC
          ");
          while ($row = $stmt->fetch()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['university_name']}</td>
                    <td>" . ($row['manager_name'] ?? 'غير معين') . "</td>
                    <td>{$row['created_at']}</td>
                    <td>";
            if (hasPermission('edit_division')) {
              echo "<a href='edit_division.php?id={$row['id']}' class='btn btn-sm btn-primary'>تعديل</a> ";
            }
            if (hasPermission('delete_division')) {
              echo "<a href='delete_division.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a>";
            }
            echo "</td></tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.getElementById('university_select').addEventListener('change', function() {
    const universityId = this.value;
    const managerSelect = document.getElementById('division_manager_select');
    
    // تفريغ القائمة
    managerSelect.innerHTML = '<option value="">اختر مدير الشعبة</option>';
    
    if (universityId) {
        // إظهار رسالة تحميل
        managerSelect.disabled = true;
        managerSelect.innerHTML = '<option value="">جاري التحميل...</option>';
        
        // جلب المستخدمين حسب الجامعة المختارة
        fetch(`get_division_users.php?university_id=${universityId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('حدث خطأ في جلب البيانات');
                }
                return response.json();
            })
            .then(users => {
                managerSelect.innerHTML = '<option value="">اختر مدير الشعبة</option>';
                if (users.length === 0) {
                    managerSelect.innerHTML += '<option value="" disabled>لا يوجد مستخدمين متاحين</option>';
                } else {
                    users.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.full_name;
                        managerSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                managerSelect.innerHTML = '<option value="">حدث خطأ في جلب البيانات</option>';
            })
            .finally(() => {
                managerSelect.disabled = false;
            });
    }
});
</script>

<?php include 'footer.php'; ?>
