<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// إضافة ملفات CSS و JavaScript في header.php
$additionalCSS = '<link rel="stylesheet" href="assets/css/documents.css">';
$additionalJS = '<script src="assets/js/documents.js" defer></script>';

include 'header.php';

// جلب الكتب مع معلومات إضافية
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
    ORDER BY d.created_at DESC
");
$stmt->execute();
$documents = $stmt->fetchAll();
?>

<style>
.document-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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

.action-button.view {
    background-color: #007bff;
    color: white;
}

.action-button.edit {
    background-color: #28a745;
    color: white;
}

.action-button.delete {
    background-color: #dc3545;
    color: white;
}

.action-button.archive {
    background-color: #6c757d;
    color: white;
}

.status-badge {
    border-radius: 15px;
    padding: 5px 15px;
    font-size: 0.9rem;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.search-box {
    border-radius: 25px;
    padding: 10px 20px;
    border: 2px solid #ddd;
    transition: all 0.3s ease;
}

.search-box:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.add-document-btn {
    border-radius: 25px;
    padding: 10px 25px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.add-document-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
</style>

<div class="container mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>
                إدارة الكتب
            </h2>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if (hasPermission('add_document')): ?>
            <a href="add_document.php" class="btn btn-primary add-document-btn">
                <i class="fas fa-plus me-2"></i>
                إضافة كتاب جديد
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <input type="text" id="searchTable" class="form-control search-box" placeholder="بحث في الكتب...">
                </div>
                <div class="col-md-4">
                    <select id="statusFilter" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="pending">قيد الانتظار</option>
                        <option value="received">تم الاستلام</option>
                        <option value="processed">تمت المعالجة</option>
                        <option value="archived">مؤرشف</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row" id="documentsContainer">
        <?php foreach ($documents as $doc): ?>
        <div class="col-md-6 mb-4 document-item">
            <div class="card document-card">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <?php echo htmlspecialchars($doc['title']); ?>
                        <span class="badge status-badge <?php echo getStatusClass($doc['status']); ?> float-end">
                            <?php echo getStatusLabel($doc['status']); ?>
                        </span>
                    </h5>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo timeAgo($doc['created_at']); ?>
                        </small>
                    </div>
                    <p class="card-text">
                        <strong>من:</strong> <?php echo htmlspecialchars($doc['sender_name'] ?? 'غير محدد'); ?><br>
                        <strong>إلى:</strong> <?php echo htmlspecialchars($doc['receiver_name'] ?? 'غير محدد'); ?>
                    </p>
                    <div class="text-end mt-3">
                        <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn action-button view">
                            <i class="fas fa-eye"></i>
                            عرض
                        </a>
                        <a href="document_workflow.php?id=<?php echo $doc['id']; ?>" class="btn action-button workflow">
                            <i class="fas fa-project-diagram"></i>
                            مسار العمل
                        </a>
                        <?php if (hasPermission('edit_document')): ?>
                        <a href="edit_document.php?id=<?php echo $doc['id']; ?>" class="btn action-button edit">
                            <i class="fas fa-edit"></i>
                            تعديل
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('archive_document')): ?>
                        <button onclick="archiveDocument(<?php echo $doc['id']; ?>)" class="btn action-button archive">
                            <i class="fas fa-archive"></i>
                            أرشفة
                        </button>
                        <?php endif; ?>
                        <?php if (hasPermission('delete_document')): ?>
                        <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" class="btn action-button delete">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // وظيفة البحث المباشر
    const searchInput = document.getElementById('searchTable');
    const statusFilter = document.getElementById('statusFilter');
    const documents = document.querySelectorAll('.document-item');

    function filterDocuments() {
        const searchText = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value.toLowerCase();

        documents.forEach(doc => {
            const text = doc.textContent.toLowerCase();
            const status = doc.querySelector('.status-badge').textContent.toLowerCase();
            
            const matchesSearch = text.includes(searchText);
            const matchesStatus = !statusValue || status.includes(statusValue);
            
            if (matchesSearch && matchesStatus) {
                doc.style.display = '';
                // إضافة تأثير حركي عند الظهور
                doc.style.animation = 'fadeIn 0.5s ease';
            } else {
                doc.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('keyup', filterDocuments);
    statusFilter.addEventListener('change', filterDocuments);
});

// دالة حذف الكتاب
async function deleteDocument(docId) {
    if (!confirm('هل أنت متأكد من حذف هذا الكتاب؟')) return;

    try {
        const response = await fetch('delete_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId })
        });

        const data = await response.json();
        
        if (data.success) {
            showToast('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('error', error.message || 'حدث خطأ أثناء الحذف');
    }
}

// دالة أرشفة الكتاب
async function archiveDocument(docId) {
    if (!confirm('هل أنت متأكد من أرشفة هذا الكتاب؟')) return;

    try {
        const response = await fetch('archive_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId })
        });

        const data = await response.json();
        
        if (data.success) {
            showToast('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        showToast('error', error.message || 'حدث خطأ أثناء الأرشفة');
    }
}

// دالة عرض الإشعارات
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
