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
if (!hasPermission('view_document')) {
    die('ليس لديك صلاحية لعرض الكتب');
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
        END as receiver_name,
        u.full_name as creator_name
    FROM documents d
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.id = ?
");

$stmt->execute([$id]);
$document = $stmt->fetch();

if (!$document) {
    die('الكتاب غير موجود');
}

// جلب تاريخ الكتاب
$stmt = $pdo->prepare("
    SELECT h.*, u.full_name as user_name
    FROM document_history h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.document_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

// جلب التعليقات
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as user_name
    FROM document_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.document_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

include 'header.php';
?>

<style>
.document-container {
    max-width: 1000px;
    margin: 0 auto;
}

.document-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.document-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.status-badge {
    border-radius: 15px;
    padding: 5px 15px;
    font-size: 0.9rem;
}

.action-button {
    transition: all 0.2s ease;
    margin: 0 5px;
    border-radius: 20px;
    padding: 5px 15px;
}

.action-button:hover {
    transform: scale(1.05);
}

.history-timeline {
    position: relative;
    padding: 20px 0;
}

.history-item {
    position: relative;
    padding-left: 30px;
    margin-bottom: 20px;
}

.history-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #007bff;
}

.history-item::after {
    content: '';
    position: absolute;
    left: -4px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #007bff;
}

.comment-card {
    border-left: 4px solid #007bff;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.comment-card:hover {
    transform: translateX(-5px);
}

.file-preview {
    max-width: 100%;
    height: auto;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease;
}
</style>

<div class="container mt-4">
    <div class="document-container">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success animate-fade-in" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            تمت العملية بنجاح
        </div>
        <?php endif; ?>

        <div class="card document-card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    <?php echo htmlspecialchars($document['title']); ?>
                </h3>
                <span class="badge bg-light text-dark">
                    <?php echo getStatusLabel($document['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-muted mb-3">معلومات الكتاب</h5>
                        <p><strong>من:</strong> <?php echo htmlspecialchars($document['sender_name']); ?></p>
                        <p><strong>إلى:</strong> <?php echo htmlspecialchars($document['receiver_name']); ?></p>
                        <p><strong>تاريخ الإنشاء:</strong> <?php echo formatDate($document['created_at']); ?></p>
                        <p><strong>منشئ الكتاب:</strong> <?php echo htmlspecialchars($document['creator_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <?php if ($document['file_path']): ?>
                        <h5 class="text-muted mb-3">الملف المرفق</h5>
                        <?php
                        $fileExt = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
                        if (in_array($fileExt, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="<?php echo htmlspecialchars($document['file_path']); ?>" class="file-preview mb-2">
                        <?php endif; ?>
                        <div>
                            <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-download me-1"></i>
                                تحميل الملف
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="text-muted mb-3">محتوى الكتاب</h5>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($document['content'])); ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-muted mb-3">تاريخ الكتاب</h5>
                        <div class="history-timeline">
                            <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <div class="mb-1">
                                    <strong><?php echo htmlspecialchars($item['user_name']); ?></strong>
                                    <small class="text-muted">
                                        (<?php echo timeAgo($item['created_at']); ?>)
                                    </small>
                                </div>
                                <div><?php echo htmlspecialchars($item['notes']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="text-muted mb-3">التعليقات</h5>
                        <?php if (hasPermission('add_comments')): ?>
                        <form id="commentForm" class="mb-3">
                            <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                            <div class="mb-2">
                                <textarea name="content" class="form-control" rows="2" placeholder="أضف تعليقاً..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-comment me-1"></i>
                                إضافة تعليق
                            </button>
                        </form>
                        <?php endif; ?>

                        <div id="commentsContainer">
                            <?php foreach ($comments as $comment): ?>
                            <div class="card comment-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                        <small class="text-muted">
                                            <?php echo timeAgo($comment['created_at']); ?>
                                        </small>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="documents.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        عودة للقائمة
                    </a>
                    <div>
                        <?php if (hasPermission('edit_document')): ?>
                        <a href="edit_document.php?id=<?php echo $document['id']; ?>" class="btn btn-primary action-button">
                            <i class="fas fa-edit me-1"></i>
                            تعديل
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('process_document')): ?>
                        <button class="btn btn-success action-button" onclick="processDocument(<?php echo $document['id']; ?>)">
                            <i class="fas fa-check me-1"></i>
                            معالجة
                        </button>
                        <?php endif; ?>

                        <?php if (hasPermission('archive_document')): ?>
                        <button class="btn btn-secondary action-button" onclick="archiveDocument(<?php echo $document['id']; ?>)">
                            <i class="fas fa-archive me-1"></i>
                            أرشفة
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// إضافة تعليق
document.getElementById('commentForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        const formData = new FormData(this);
        const response = await fetch('add_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(Object.fromEntries(formData))
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('error', error.message || 'حدث خطأ أثناء إضافة التعليق');
    }
});

// معالجة الكتاب
async function processDocument(id) {
    if (!confirm('هل أنت متأكد من معالجة هذا الكتاب؟')) return;

    try {
        const response = await fetch('process_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('error', error.message || 'حدث خطأ أثناء معالجة الكتاب');
    }
}

// أرشفة الكتاب
async function archiveDocument(id) {
    if (!confirm('هل أنت متأكد من أرشفة هذا الكتاب؟')) return;

    try {
        const response = await fetch('archive_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('error', error.message || 'حدث خطأ أثناء أرشفة الكتاب');
    }
}

// عرض الإشعارات
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    document.body.appendChild(toast);
    new bootstrap.Toast(toast, { delay: 3000 }).show();
}
</script>

<?php include 'footer.php'; ?> 