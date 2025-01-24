<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();
include 'header.php';

// جلب التذكيرات القادمة
$upcomingReminders = $pdo->prepare("
  SELECT r.*, d.title as document_title 
  FROM reminders r
  LEFT JOIN documents d ON r.document_id = d.id
  WHERE r.user_id = ? AND r.reminder_date >= CURDATE()
  ORDER BY r.reminder_date ASC
");
$upcomingReminders->execute([$_SESSION['user_id']]);

// جلب التذكيرات السابقة
$pastReminders = $pdo->prepare("
  SELECT r.*, d.title as document_title 
  FROM reminders r
  LEFT JOIN documents d ON r.document_id = d.id
  WHERE r.user_id = ? AND r.reminder_date < CURDATE()
  ORDER BY r.reminder_date DESC
  LIMIT 10
");
$pastReminders->execute([$_SESSION['user_id']]);
?>

<div class="container mt-4">
  <h2>التذكيرات والمتابعة</h2>

  <div class="row">
    <!-- إضافة تذكير جديد -->
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header">
          إضافة تذكير جديد
        </div>
        <div class="card-body">
          <form method="POST" action="process_reminder.php">
            <div class="mb-3">
              <label class="form-label">العنوان</label>
              <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">الوصف</label>
              <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">تاريخ التذكير</label>
              <input type="date" name="reminder_date" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">الكتاب المرتبط (اختياري)</label>
              <select name="document_id" class="form-control">
                <option value="">بدون كتاب</option>
                <?php
                $docs = $pdo->query("SELECT id, title FROM documents WHERE status != 'processed' ORDER BY created_at DESC")->fetchAll();
                foreach ($docs as $doc) {
                  echo "<option value='{$doc['id']}'>{$doc['title']}</option>";
                }
                ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">إضافة التذكير</button>
          </form>
        </div>
      </div>
    </div>

    <!-- التذكيرات القادمة -->
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header">
          التذكيرات القادمة
        </div>
        <div class="card-body">
          <div class="list-group">
            <?php while ($reminder = $upcomingReminders->fetch()): ?>
              <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                  <h5 class="mb-1"><?php echo $reminder['title']; ?></h5>
                  <small class="text-muted"><?php echo $reminder['reminder_date']; ?></small>
                </div>
                <p class="mb-1"><?php echo $reminder['description']; ?></p>
                <?php if ($reminder['document_id']): ?>
                  <small class="text-muted">مرتبط بالكتاب: <?php echo $reminder['document_title']; ?></small>
                <?php endif; ?>
                <div class="mt-2">
                  <a href="delete_reminder.php?id=<?php echo $reminder['id']; ?>" 
                     class="btn btn-sm btn-danger" 
                     onclick="return confirm('هل أنت متأكد من حذف هذا التذكير؟')">حذف</a>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </div>

      <!-- التذكيرات السابقة -->
      <div class="card">
        <div class="card-header">
          التذكيرات السابقة
        </div>
        <div class="card-body">
          <div class="list-group">
            <?php while ($reminder = $pastReminders->fetch()): ?>
              <div class="list-group-item list-group-item-secondary">
                <div class="d-flex w-100 justify-content-between">
                  <h5 class="mb-1"><?php echo $reminder['title']; ?></h5>
                  <small class="text-muted"><?php echo $reminder['reminder_date']; ?></small>
                </div>
                <p class="mb-1"><?php echo $reminder['description']; ?></p>
                <?php if ($reminder['document_id']): ?>
                  <small class="text-muted">مرتبط بالكتاب: <?php echo $reminder['document_title']; ?></small>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
