<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

// التحقق من معرف الكتاب
$documentId = $_GET['id'] ?? null;
if (!$documentId) {
  die('معرف الكتاب غير صحيح');
}

// جلب معلومات الكتاب مع كافة التفاصيل المرتبطة
$document = $pdo->prepare("
  SELECT 
    d.*,
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
    u.full_name as processor_name,
    u.email as processor_email,
    d.created_at as document_date,
    d.updated_at as last_update,
    d.priority
  FROM documents d
  LEFT JOIN users u ON d.processor_id = u.id
  WHERE d.id = ?
");

$document->execute([$documentId]);
$doc = $document->fetch();

if (!$doc) {
  die('الكتاب غير موجود');
}

// جلب المرفقات
$attachments = $pdo->prepare("
  SELECT * FROM document_attachments 
  WHERE document_id = ? 
  ORDER BY created_at DESC
");
$attachments->execute([$documentId]);

// جلب التعليقات والملاحظات
$comments = $pdo->prepare("
  SELECT c.*, u.full_name, u.role
  FROM document_comments c
  JOIN users u ON c.user_id = u.id
  WHERE c.document_id = ?
  ORDER BY c.created_at DESC
");
$comments->execute([$documentId]);

// جلب سجل التغييرات
$history = $pdo->prepare("
  SELECT h.*, u.full_name, u.role
  FROM document_history h
  JOIN users u ON h.user_id = u.id
  WHERE h.document_id = ?
  ORDER BY h.created_at DESC
");
$history->execute([$documentId]);

include 'header.php';
?>

<div class="container-fluid mt-4">
  <!-- شريط الحالة العلوي -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="status-bar p-3 bg-light rounded">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="mb-0">
            <?php echo $doc['title']; ?>
            <span class="badge bg-<?php echo getStatusColor($doc['status']); ?> ms-2">
              <?php echo getStatusText($doc['status']); ?>
            </span>
          </h2>
          <div class="d-flex gap-2">
            <?php if (hasPermission('edit_document')): ?>
              <button class="btn btn-primary" onclick="editDocument(<?php echo $doc['id']; ?>)">
                تعديل الكتاب
              </button>
            <?php endif; ?>
            <?php if (hasPermission('process_document')): ?>
              <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#processModal">
                معالجة الكتاب
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- تفاصيل الكتاب -->
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header">
          <h5>تفاصيل الكتاب</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <strong>رقم الكتاب:</strong> <?php echo $doc['document_id']; ?>
            </div>
            <div class="col-md-6">
              <strong>تاريخ الإنشاء:</strong> <?php echo formatDate($doc['document_date']); ?>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <strong>المرسل:</strong> <?php echo $doc['sender_name']; ?>
            </div>
            <div class="col-md-6">
              <strong>المستلم:</strong> <?php echo $doc['receiver_name']; ?>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <strong>الأولوية:</strong>
              <span class="badge bg-<?php echo getPriorityColor($doc['priority']); ?>">
                <?php echo getPriorityText($doc['priority']); ?>
              </span>
            </div>
            <div class="col-md-6">
              <strong>تاريخ الإرسال:</strong>
              <?php echo $doc['send_date'] ? formatDate($doc['send_date']) : 'لم يتم الإرسال بعد'; ?>
            </div>
          </div>
          <div class="mb-3">
            <strong>المحتوى:</strong>
            <div class="document-content mt-2">
              <?php echo nl2br($doc['content']); ?>
            </div>
          </div>
          <?php if ($doc['send_notes']): ?>
            <div class="mb-3">
              <strong>ملاحظات الإرسال:</strong>
              <div class="mt-2">
                <?php echo nl2br($doc['send_notes']); ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($doc['tags']): ?>
            <div class="mb-3">
              <strong>الوسوم:</strong>
              <?php foreach (explode(',', $doc['tags']) as $tag): ?>
                <span class="badge bg-secondary me-1"><?php echo trim($tag); ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- المرفقات -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5>المرفقات</h5>
          <?php if (hasPermission('add_attachment')): ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#attachmentModal">
              إضافة مرفق
            </button>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($attachments->rowCount() > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>اسم الملف</th>
                    <th>النوع</th>
                    <th>الحجم</th>
                    <th>تاريخ الإضافة</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($attachment = $attachments->fetch()): ?>
                    <tr>
                      <td><?php echo $attachment['filename']; ?></td>
                      <td><?php echo $attachment['mime_type']; ?></td>
                      <td><?php echo formatFileSize($attachment['file_size']); ?></td>
                      <td><?php echo formatDate($attachment['created_at']); ?></td>
                      <td>
                        <a href="download_attachment.php?id=<?php echo $attachment['id']; ?>" 
                           class="btn btn-sm btn-info">تحميل</a>
                        <?php if (hasPermission('delete_attachment')): ?>
                          <button class="btn btn-sm btn-danger" 
                                  onclick="deleteAttachment(<?php echo $attachment['id']; ?>)">
                            حذف
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-center mb-0">لا توجد مرفقات</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- التعليقات -->
      <div class="card mb-4">
        <div class="card-header">
          <h5>التعليقات والملاحظات</h5>
        </div>
        <div class="card-body">
          <?php if (hasPermission('add_comment')): ?>
            <form id="commentForm" class="mb-4">
              <div class="mb-3">
                <textarea class="form-control" rows="3" placeholder="أضف تعليقاً..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary">إضافة تعليق</button>
            </form>
          <?php endif; ?>

          <div class="comments-list">
            <?php while ($comment = $comments->fetch()): ?>
              <div class="comment-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <strong><?php echo $comment['full_name']; ?></strong>
                    <small class="text-muted">(<?php echo $comment['role']; ?>)</small>
                  </div>
                  <small><?php echo formatDate($comment['created_at']); ?></small>
                </div>
                <p class="mb-0 mt-2"><?php echo $comment['content']; ?></p>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- الجانب الأيمن -->
    <div class="col-md-4">
      <!-- سجل المتابعة -->
      <div class="card mb-4">
        <div class="card-header">
          <h5>سجل المتابعة</h5>
        </div>
        <div class="card-body">
          <div class="timeline">
            <?php while ($entry = $history->fetch()): ?>
              <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                  <div class="d-flex justify-content-between">
                    <h6><?php echo getActionText($entry['action']); ?></h6>
                    <small><?php echo formatDate($entry['created_at']); ?></small>
                  </div>
                  <p class="mb-1"><?php echo $entry['notes']; ?></p>
                  <small>
                    بواسطة: <?php echo $entry['full_name']; ?> 
                    (<?php echo $entry['role']; ?>)
                  </small>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </div>

      <!-- الكتب المرتبطة -->
      <div class="card">
        <div class="card-header">
          <h5>الكتب المرتبطة</h5>
        </div>
        <div class="card-body">
          <?php
          $relatedDocs = $pdo->prepare("
            SELECT * FROM documents 
            WHERE (
              (sender_id = ? AND sender_type = ?) OR 
              (receiver_id = ? AND receiver_type = ?)
            )
            AND id != ?
            ORDER BY created_at DESC
            LIMIT 5
          ");
          $relatedDocs->execute([
            $doc['sender_id'],
            $doc['sender_type'],
            $doc['receiver_id'],
            $doc['receiver_type'],
            $doc['id']
          ]);
          
          if ($relatedDocs->rowCount() > 0):
            while ($related = $relatedDocs->fetch()):
          ?>
            <div class="related-doc-item">
              <a href="document_workflow.php?id=<?php echo $related['id']; ?>">
                <?php echo $related['title']; ?>
              </a>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="badge bg-<?php echo getStatusColor($related['status']); ?>">
                  <?php echo getStatusText($related['status']); ?>
                </span>
                <small class="text-muted">
                  <?php echo formatDate($related['created_at']); ?>
                </small>
              </div>
            </div>
          <?php 
            endwhile;
          else:
          ?>
            <p class="text-center mb-0">لا توجد كتب مرتبطة</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal معالجة الكتاب -->
<div class="modal fade" id="processModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">معالجة الكتاب</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="processForm">
          <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
          <div class="mb-3">
            <label class="form-label">الإجراء</label>
            <select name="action" class="form-control" required>
              <option value="receive">استلام</option>
              <option value="process">معالجة</option>
              <option value="forward">إعادة توجيه</option>
              <option value="reject">رفض</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary">تنفيذ الإجراء</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal إضافة مرفق -->
<div class="modal fade" id="attachmentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">إضافة مرفق</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="attachmentForm" enctype="multipart/form-data">
          <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
          <div class="mb-3">
            <label class="form-label">الملف</label>
            <input type="file" name="file" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">الوصف</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">رفع الملف</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.timeline {
  position: relative;
  padding: 20px 0;
}

.timeline-item {
  position: relative;
  padding-left: 30px;
  margin-bottom: 20px;
}

.timeline-marker {
  position: absolute;
  left: 0;
  top: 0;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background-color: #007bff;
  border: 2px solid #fff;
  box-shadow: 0 0 0 2px #007bff;
}

.timeline-item:before {
  content: '';
  position: absolute;
  left: 5px;
  top: 12px;
  bottom: -20px;
  width: 2px;
  background-color: #007bff;
}

.timeline-item:last-child:before {
  display: none;
}

.comment-item {
  padding: 15px;
  border-bottom: 1px solid #eee;
}

.comment-item:last-child {
  border-bottom: none;
}

.document-content {
  padding: 15px;
  background-color: #f8f9fa;
  border-radius: 4px;
  white-space: pre-wrap;
}
</style>

<script>
// معالجة نموذج التعليقات
document.getElementById('commentForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const content = this.querySelector('textarea').value;
  
  try {
    const response = await fetch('add_comment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        document_id: <?php echo $doc['id']; ?>,
        content: content
      })
    });
    
    if (response.ok) {
      location.reload();
    } else {
      throw new Error('فشل إضافة التعليق');
    }
  } catch (error) {
    alert(error.message);
  }
});

// معالجة نموذج المرفقات
document.getElementById('attachmentForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  try {
    const response = await fetch('add_attachment.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      location.reload();
    } else {
      throw new Error('فشل رفع المرفق');
    }
  } catch (error) {
    alert(error.message);
  }
});

// معالجة نموذج معالجة الكتاب
document.getElementById('processForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  try {
    const response = await fetch('process_document.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      location.reload();
    } else {
      throw new Error('فشل معالجة الكتاب');
    }
  } catch (error) {
    alert(error.message);
  }
});

// دالة حذف المرفق
async function deleteAttachment(id) {
  if (!confirm('هل أنت متأكد من حذف هذا المرفق؟')) return;
  
  try {
    const response = await fetch('delete_attachment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ id: id })
    });
    
    if (response.ok) {
      location.reload();
    } else {
      throw new Error('فشل حذف المرفق');
    }
  } catch (error) {
    alert(error.message);
  }
}

// دالة تعديل الكتاب
function editDocument(id) {
  window.location.href = `edit_document.php?id=${id}`;
}
</script>

<?php include 'footer.php'; ?>
