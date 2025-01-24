<?php
require_once 'functions.php';
require_once 'auth.php';
requireLogin();

$documentId = $_GET['id'] ?? null;
if (!$documentId) {
    die('معرف الكتاب غير صحيح');
}

// جلب معلومات الكتاب
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document) {
    die('الكتاب غير موجود');
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>إرسال الكتاب: <?php echo htmlspecialchars($document['title']); ?></h3>
            <span class="badge <?php echo getStatusClass($document['status']); ?>">
                <?php echo getStatusLabel($document['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="process_send_document.php">
                <input type="hidden" name="document_id" value="<?php echo $documentId; ?>">
                
                <div class="mb-3">
                    <label class="form-label">نوع الإرسال</label>
                    <select name="send_type" class="form-select" required>
                        <option value="single">إرسال لجهة واحدة</option>
                        <option value="multiple">إرسال لعدة جهات</option>
                        <option value="broadcast">تعميم</option>
                    </select>
                </div>

                <div id="single-receiver">
                    <div class="mb-3">
                        <label class="form-label">نوع المستلم</label>
                        <select name="receiver_type" class="form-select">
                            <option value="">اختر نوع المستلم</option>
                            <option value="ministry">قسم الوزارة</option>
                            <option value="division">شعبة</option>
                            <option value="unit">وحدة</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">المستلم</label>
                        <select name="receiver_id" class="form-select">
                            <option value="">اختر المستلم</option>
                        </select>
                    </div>
                </div>

                <div id="multiple-receivers" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label">اختر المستلمين</label>
                        <div id="receivers-list" class="border p-3">
                            <!-- سيتم ملؤها بواسطة JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ملاحظات الإرسال</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> إرسال الكتاب
                    </button>
                    <a href="documents.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('select[name="send_type"]').addEventListener('change', function() {
    const singleReceiver = document.getElementById('single-receiver');
    const multipleReceivers = document.getElementById('multiple-receivers');
    
    if (this.value === 'single') {
        singleReceiver.style.display = 'block';
        multipleReceivers.style.display = 'none';
    } else {
        singleReceiver.style.display = 'none';
        multipleReceivers.style.display = 'block';
        if (this.value === 'broadcast') {
            loadAllReceivers();
        }
    }
});

// تحديث قائمة المستلمين
document.querySelector('select[name="receiver_type"]').addEventListener('change', function() {
    const receiverType = this.value;
    if (!receiverType) return;

    fetch(`get_receivers.php?type=${receiverType}`)
        .then(response => response.json())
        .then(data => {
            const select = document.querySelector('select[name="receiver_id"]');
            select.innerHTML = '<option value="">اختر المستلم</option>';
            data.forEach(receiver => {
                select.innerHTML += `<option value="${receiver.id}">${receiver.name}</option>`;
            });
        });
});

function loadAllReceivers() {
    fetch('get_all_receivers.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('receivers-list');
            container.innerHTML = '';
            Object.entries(data).forEach(([type, receivers]) => {
                const section = document.createElement('div');
                section.className = 'mb-3';
                section.innerHTML = `
                    <h6>${getReceiverTypeLabel(type)}</h6>
                    ${receivers.map(r => `
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="receivers[]" 
                                   value="${type}:${r.id}" id="recv_${type}_${r.id}">
                            <label class="form-check-label" for="recv_${type}_${r.id}">
                                ${r.name}
                            </label>
                        </div>
                    `).join('')}
                `;
                container.appendChild(section);
            });
        });
}

function getReceiverTypeLabel(type) {
    const labels = {
        ministry: 'أقسام الوزارة',
        division: 'الشعب',
        unit: 'الوحدات'
    };
    return labels[type] || type;
}
</script>

<?php include 'footer.php'; ?> 