<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من الصلاحيات
$userRole = $_SESSION['user_role'];
$userEntityType = $_SESSION['entity_type'] ?? null;
$userEntityId = $_SESSION['entity_id'] ?? null;

// فقط الأدمن ومدراء الشعب يمكنهم الوصول
if ($userRole !== 'admin' && $userEntityType !== 'division') {
    die('غير مصرح لك بالوصول إلى هذه الصفحة');
}

// التحقق من وجود الجدول وتحديثه
try {
    // إضافة الأعمدة الجديدة
    $alterQueries = [
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS created_by INT AFTER created_at",
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        "ALTER TABLE colleges ADD COLUMN IF NOT EXISTS updated_by INT AFTER updated_at",
        "ALTER TABLE colleges ADD CONSTRAINT IF NOT EXISTS fk_colleges_created_by FOREIGN KEY (created_by) REFERENCES users(id)",
        "ALTER TABLE colleges ADD CONSTRAINT IF NOT EXISTS fk_colleges_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)"
    ];

    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            error_log("خطأ في تنفيذ استعلام التعديل: " . $query . " - " . $e->getMessage());
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("خطأ في تحديث جدول الكليات: " . $e->getMessage());
}

// التحقق من الانتماء الرئيسي للمستخدم
$userDivisionId = null;
if ($userEntityType === 'division') {
    $stmt = $pdo->prepare("
        SELECT ue.entity_id 
        FROM user_entities ue 
        WHERE ue.user_id = ? 
        AND ue.entity_type = 'division' 
        AND ue.is_primary = 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $userDivisionId = $result ? $result['entity_id'] : null;
}

include 'header.php';
?>

<div class="container mt-4">
    <h2>إدارة الكليات</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if ($userRole === 'admin' || ($userEntityType === 'division' && hasPermission('add_college'))): ?>
    <div class="card mb-4">
        <div class="card-header">
            إضافة كلية جديدة
        </div>
        <div class="card-body">
            <form method="POST" action="process_college.php">
                <div class="mb-3">
                    <label class="form-label">اسم الكلية</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">الجامعة</label>
                    <select name="university_id" class="form-control" required>
                        <option value="">اختر الجامعة</option>
                        <?php
                        // تعديل استعلام عرض الجامعات في نموذج الإضافة
                        if ($userRole === 'admin') {
                            $stmt = $pdo->query("SELECT * FROM universities ORDER BY name");
                        } else {
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT u.* 
                                FROM universities u
                                INNER JOIN university_divisions ud ON u.id = ud.university_id
                                INNER JOIN user_entities ue ON ud.id = ue.entity_id
                                WHERE ue.user_id = ? 
                                AND ue.entity_type = 'division'
                                AND ue.is_primary = 1
                                ORDER BY u.name
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                        }
                        
                        while ($univ = $stmt->fetch()) {
                            echo "<option value='{$univ['id']}'>{$univ['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">إضافة الكلية</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- عرض الكليات -->
    <div class="card">
        <div class="card-header">
            الكليات الحالية
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الكلية</th>
                            <th>الجامعة</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // تعديل استعلام عرض الكليات
                            if ($userRole === 'admin') {
                                $stmt = $pdo->query("
                                    SELECT c.*, u.name as university_name 
                                    FROM colleges c 
                                    LEFT JOIN universities u ON c.university_id = u.id 
                                    ORDER BY c.id DESC
                                ");
                            } else {
                                $stmt = $pdo->prepare("
                                    SELECT c.*, u.name as university_name 
                                    FROM colleges c 
                                    INNER JOIN universities u ON c.university_id = u.id 
                                    INNER JOIN university_divisions ud ON u.id = ud.university_id
                                    INNER JOIN user_entities ue ON ud.id = ue.entity_id
                                    WHERE ue.user_id = ? 
                                    AND ue.entity_type = 'division'
                                    AND ue.is_primary = 1
                                    ORDER BY c.id DESC
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                            }

                            while ($row = $stmt->fetch()) {
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['name']}</td>
                                        <td>{$row['university_name']}</td>
                                        <td>{$row['created_at']}</td>
                                        <td class='text-nowrap'>";
                                
                                // عرض أزرار التعديل والحذف فقط للأدمن أو لمدير الشعبة للكليات التابعة له
                                if ($userRole === 'admin' || 
                                    ($userEntityType === 'division' && hasPermission('edit_college'))) {
                                    echo "<a href='edit_college.php?id={$row['id']}' class='btn btn-sm btn-primary me-1'>
                                            <i class='fas fa-edit'></i> تعديل
                                          </a>";
                                    echo "<a href='delete_college.php?id={$row['id']}' 
                                          class='btn btn-sm btn-danger'
                                          onclick='return confirm(\"هل أنت متأكد من حذف هذه الكلية؟\")'>
                                            <i class='fas fa-trash'></i> حذف
                                          </a>";
                                }
                                echo "</td></tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='5' class='text-danger'>حدث خطأ في عرض البيانات</td></tr>";
                            error_log($e->getMessage());
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 