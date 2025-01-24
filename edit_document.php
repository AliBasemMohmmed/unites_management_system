<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من وجود معرف الكتاب
$id = $_GET['id'] ?? null;
if (!$id) {
    die('معرف الكتاب غير صحيح');
}

// التحقق من الصلاحيات
if (!hasPermission('edit_document')) {
    die('ليس لديك صلاحية لتعديل الكتب');
}

// جلب بيانات الكتاب
$stmt = $pdo->prepare("
    SELECT d.*, 
        CASE 
            WHEN d.sender_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.sender_id)
            WHEN d.sender_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.sender_id)
            WHEN d.sender_type = 'unit' THEN (SELECT name FROM units WHERE id = d.sender_id)
        END as sender_name,
        CASE 
            WHEN d.receiver_type = 'ministry' THEN (SELECT name FROM ministry_departments WHERE id = d.receiver_id)
            WHEN d.receiver_type = 'division' THEN (SELECT name FROM university_divisions WHERE id = d.receiver_id)
            WHEN d.receiver_type = 'unit' THEN (SELECT name FROM units WHERE id = d.receiver_id)
        END as receiver_name
    FROM documents d
    WHERE d.id = ?
");

$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    die('الكتاب غير موجود');
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من البيانات المطلوبة
        if (empty($_POST['title']) || empty($_POST['content'])) {
            throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
        }

        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $senderType = $_POST['sender_type'];
        $senderId = $_POST['sender_id'];
        $receiverType = $_POST['receiver_type'];
        $receiverId = $_POST['receiver_id'];

        // معالجة الملف المرفق
        $filePath = $document['file_path'];
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = getAllowedFileTypes();
            $fileType = $_FILES['document_file']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('نوع الملف غير مسموح به');
            }

            if ($_FILES['document_file']['size'] > getMaxFileSize()) {
                throw new Exception('حجم الملف كبير جداً');
            }

            $fileName = sanitizeFileName($_FILES['document_file']['name']);
            $uploadDir = UPLOAD_DIR;
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // حذف الملف القديم إذا وجد
            if ($document['file_path'] && file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }

            $filePath = $uploadDir . uniqid() . '_' . $fileName;
            
            if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
                throw new Exception('فشل في رفع الملف');
            }
        }

        // تحديث بيانات الكتاب
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE documents 
            SET title = ?, content = ?, file_path = ?,
                sender_type = ?, sender_id = ?,
                receiver_type = ?, receiver_id = ?,
                document_id = COALESCE(document_id, ?),
                updated_at = NOW()
            WHERE id = ?
        ");

        // إنشاء معرف فريد للكتاب إذا لم يكن موجوداً
        $documentId = $document['document_id'] ?? generateUniqueDocumentId();

        $stmt->execute([
            $title, $content, $filePath,
            $senderType, $senderId,
            $receiverType, $receiverId,
            $documentId,
            $id
        ]);

        // إضافة سجل في التاريخ
        $stmt = $pdo->prepare("
            INSERT INTO document_history (document_id, user_id, action, notes)
            VALUES (?, ?, 'edit', 'تم تعديل الكتاب')
        ");
        $stmt->execute([$id, $_SESSION['user_id']]);

        // جلب معلومات المرسل والمستلم الجديد
        $senderName = '';
        $receiverName = '';

        // جلب اسم المرسل
        switch ($senderType) {
            case 'ministry':
                $stmt = $pdo->prepare("SELECT name FROM ministry_departments WHERE id = ?");
                break;
            case 'division':
                $stmt = $pdo->prepare("SELECT name FROM university_divisions WHERE id = ?");
                break;
            case 'unit':
                $stmt = $pdo->prepare("SELECT name FROM units WHERE id = ?");
                break;
        }
        if ($stmt) {
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();
            $senderName = $sender['name'] ?? 'غير معروف';
        }

        // جلب اسم المستلم
        switch ($receiverType) {
            case 'ministry':
                $stmt = $pdo->prepare("SELECT name FROM ministry_departments WHERE id = ?");
                break;
            case 'division':
                $stmt = $pdo->prepare("SELECT name FROM university_divisions WHERE id = ?");
                break;
            case 'unit':
                $stmt = $pdo->prepare("SELECT name FROM units WHERE id = ?");
                break;
        }
        if ($stmt) {
            $stmt->execute([$receiverId]);
            $receiver = $stmt->fetch();
            $receiverName = $receiver['name'] ?? 'غير معروف';
        }

        // إرسال إشعار للجهة المستلمة الجديدة
        if ($receiverType != $document['receiver_type'] || $receiverId != $document['receiver_id']) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id 
                FROM users u 
                JOIN user_entities ue ON u.id = ue.user_id 
                WHERE ue.entity_type = ? AND ue.entity_id = ?
            ");
            $stmt->execute([$receiverType, $receiverId]);
            $users = $stmt->fetchAll();

            foreach ($users as $user) {
                addNotification(
                    $user['id'],
                    'تم تحويل كتاب إليك',
                    "تم تحويل الكتاب: $title من $senderName",
                    [
                        'type' => 'document',
                        'document_id' => $id,
                        'action' => 'transfer'
                    ]
                );
            }
        }

        $pdo->commit();

        // إعادة التوجيه إلى صفحة عرض الكتاب
        header("Location: view_document.php?id=$id&success=1");
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

include 'header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.form-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.form-control {
    border-radius: 10px;
    padding: 10px 15px;
    border: 2px solid #ddd;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.custom-file-input {
    cursor: pointer;
}

.submit-btn {
    border-radius: 20px;
    padding: 10px 30px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.alert {
    border-radius: 10px;
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<div class="container mt-4">
    <div class="form-container">
        <div class="card form-card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    تعديل الكتاب
                </h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">عنوان الكتاب</label>
                        <input type="text" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($document['title']); ?>">
                        <div class="invalid-feedback">يرجى إدخال عنوان الكتاب</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">محتوى الكتاب</label>
                        <textarea name="content" class="form-control" rows="5" required><?php 
                            echo htmlspecialchars($document['content']); 
                        ?></textarea>
                        <div class="invalid-feedback">يرجى إدخال محتوى الكتاب</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع المرسل</label>
                            <select name="sender_type" class="form-select" required>
                                <option value="">اختر نوع المرسل</option>
                                <option value="ministry" <?php echo $document['sender_type'] == 'ministry' ? 'selected' : ''; ?>>قسم الوزارة</option>
                                <option value="division" <?php echo $document['sender_type'] == 'division' ? 'selected' : ''; ?>>شعبة</option>
                                <option value="unit" <?php echo $document['sender_type'] == 'unit' ? 'selected' : ''; ?>>وحدة</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المرسل</label>
                            <select name="sender_id" class="form-select" required>
                                <option value="">اختر المرسل</option>
                                <!-- سيتم ملء هذه القائمة عبر JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع المستلم</label>
                            <select name="receiver_type" class="form-select" required>
                                <option value="">اختر نوع المستلم</option>
                                <option value="ministry" <?php echo $document['receiver_type'] == 'ministry' ? 'selected' : ''; ?>>قسم الوزارة</option>
                                <option value="division" <?php echo $document['receiver_type'] == 'division' ? 'selected' : ''; ?>>شعبة</option>
                                <option value="unit" <?php echo $document['receiver_type'] == 'unit' ? 'selected' : ''; ?>>وحدة</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المستلم</label>
                            <select name="receiver_id" class="form-select" required>
                                <option value="">اختر المستلم</option>
                                <!-- سيتم ملء هذه القائمة عبر JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">الملف المرفق</label>
                        <?php if ($document['file_path']): ?>
                        <div class="mb-2">
                            <small class="text-muted">الملف الحالي:</small>
                            <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">
                                عرض الملف الحالي
                            </a>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="document_file" class="form-control" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <small class="text-muted">
                            الملفات المسموح بها: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG
                            (الحد الأقصى: <?php echo formatFileSize(getMaxFileSize()); ?>)
                        </small>
                    </div>

                    <div class="text-end">
                        <a href="view_document.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">إلغاء</a>
                        <button type="submit" class="btn btn-primary submit-btn">
                            <i class="fas fa-save me-2"></i>
                            حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// التحقق من صحة النموذج
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// تحديث قوائم المرسل والمستلم
async function updateEntityList(type, targetSelect, selectedId = null) {
    try {
        const response = await fetch(`get_entities.php?type=${type}`);
        const data = await response.json();
        
        if (data.success) {
            targetSelect.innerHTML = '<option value="">اختر...</option>';
            data.entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.id;
                option.textContent = entity.name;
                if (selectedId && entity.id == selectedId) {
                    option.selected = true;
                }
                targetSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('خطأ في جلب البيانات:', error);
    }
}

// تحديث القوائم عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    const senderType = document.querySelector('[name="sender_type"]');
    const senderId = document.querySelector('[name="sender_id"]');
    const receiverType = document.querySelector('[name="receiver_type"]');
    const receiverId = document.querySelector('[name="receiver_id"]');

    if (senderType.value) {
        updateEntityList(senderType.value, senderId, '<?php echo $document['sender_id']; ?>');
    }
    if (receiverType.value) {
        updateEntityList(receiverType.value, receiverId, '<?php echo $document['receiver_id']; ?>');
    }

    // ربط الأحداث بتغيير نوع المرسل والمستلم
    senderType.addEventListener('change', function() {
        updateEntityList(this.value, senderId);
    });

    receiverType.addEventListener('change', function() {
        updateEntityList(this.value, receiverId);
    });
});
</script>

<?php include 'footer.php'; ?> 