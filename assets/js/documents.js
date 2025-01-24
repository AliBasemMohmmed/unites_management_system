document.addEventListener('DOMContentLoaded', function() {
  // إضافة القائمة المنسدلة
  const contextMenu = document.createElement('div');
  contextMenu.className = 'context-menu';
  document.body.appendChild(contextMenu);

  // تعريف خيارات القائمة
  const menuItems = {
    archive: { icon: 'archive', text: 'أرشفة', permission: 'can_archive' },
    comment: { icon: 'comment', text: 'إضافة تعليق', permission: 'can_comment' },
    send: { icon: 'paper-plane', text: 'إرسال', permission: 'can_send' },
    sendMultiple: { icon: 'share', text: 'إرسال لعدة جهات', permission: 'can_send' },
    broadcast: { icon: 'broadcast-tower', text: 'تعميم', permission: 'can_broadcast' },
    edit: { icon: 'edit', text: 'تعديل', permission: 'can_edit' },
    delete: { icon: 'trash', text: 'حذف', permission: 'can_delete' }
  };

  // تحديث معالج النقر على عمود الإجراءات
  document.querySelectorAll('.actions-cell').forEach(actionCell => {
    actionCell.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const row = this.parentElement;
      const docId = row.querySelector('td:first-child').textContent;
      const docStatus = row.querySelector('.badge').textContent.trim();
      
      // إزالة التحديد من جميع الصفوف
      document.querySelectorAll('.table tbody tr').forEach(r => r.classList.remove('selected'));
      
      // إضافة تحديد للصف الحالي
      row.classList.add('selected');
      
      // إنشاء محتوى القائمة مع مراعاة حالة الوثيقة
      contextMenu.innerHTML = Object.entries(menuItems)
        .filter(([action, { permission }]) => {
          // التحقق من الصلاحيات وحالة الوثيقة
          if (docStatus === 'مؤرشف' && action !== 'comment') return false;
          if (docStatus === 'تم الإرسال' && ['edit', 'delete'].includes(action)) return false;
          return true;
        })
        .map(([action, { icon, text }]) => `
          <div class="context-menu-item ${getMenuItemClass(action, docStatus)}" 
               data-action="${action}" 
               data-id="${docId}">
            <i class="fas fa-${icon}"></i> ${text}
          </div>
        `).join('');
      
      // تحديد موقع القائمة
      const cellRect = this.getBoundingClientRect();
      const menuHeight = contextMenu.offsetHeight;
      const windowHeight = window.innerHeight;
      
      contextMenu.style.display = 'block';
      
      // تحديد ما إذا كان يجب عرض القائمة فوق أو تحت الخلية
      const spaceBelow = windowHeight - cellRect.bottom;
      const spaceAbove = cellRect.top;
      
      if (spaceBelow >= menuHeight || spaceBelow >= spaceAbove) {
        contextMenu.style.top = (window.scrollY + cellRect.bottom + 5) + 'px';
      } else {
        contextMenu.style.top = (window.scrollY + cellRect.top - menuHeight - 5) + 'px';
      }
      
      contextMenu.style.left = cellRect.left + 'px';
    });
  });

  // دالة مساعدة لتحديد صنف عنصر القائمة
  function getMenuItemClass(action, docStatus) {
    if (docStatus === 'مؤرشف' && action !== 'comment') return 'disabled';
    if (docStatus === 'تم الإرسال' && ['edit', 'delete'].includes(action)) return 'disabled';
    return '';
  }

  // إخفاء القائمة فقط عند النقر خارج القائمة والجدول
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.context-menu') && 
        !e.target.closest('.actions-cell')) {
      contextMenu.style.display = 'none';
      document.querySelectorAll('.table tbody tr').forEach(r => 
        r.classList.remove('selected')
      );
    }
  });

  // تحديث معالج النقر على عناصر القائمة
  contextMenu.addEventListener('click', async function(e) {
    const item = e.target.closest('.context-menu-item');
    if (!item || item.classList.contains('disabled')) return;

    const action = item.dataset.action;
    const docId = item.dataset.id;

    try {
      switch(action) {
        case 'archive':
          await archiveDocument(docId);
          break;
        case 'comment':
          await showCommentDialog(docId);
          break;
        case 'send':
          await showSendDialog(docId, 'single');
          break;
        case 'sendMultiple':
          await showSendDialog(docId, 'multiple');
          break;
        case 'broadcast':
          await showSendDialog(docId, 'broadcast');
          break;
        case 'edit':
          window.location.href = `edit_document.php?id=${docId}`;
          break;
        case 'delete':
          await deleteDocument(docId);
          break;
      }
      
      // إخفاء القائمة بعد تنفيذ الإجراء
      contextMenu.style.display = 'none';
      
    } catch (error) {
      showToast('error', error.message || 'حدث خطأ أثناء تنفيذ الإجراء');
    }
  });

  // تفعيل زر تصدير Excel
  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function() {
      exportToExcel();
    });
  }

  // تفعيل زر الطباعة
  const printBtn = document.getElementById('printBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function() {
      printDocuments();
    });
  }

  // تفعيل زر الفلتر
  const filterForm = document.getElementById('filterForm');
  if (filterForm) {
    filterForm.addEventListener('submit', function(e) {
      e.preventDefault();
      applyFilter(this);
    });

    // إضافة زر إعادة تعيين الفلتر
    const resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.className = 'btn btn-outline-secondary ms-2';
    resetBtn.innerHTML = '<i class="fas fa-undo"></i> إعادة تعيين';
    
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
      filterBtn.parentNode.insertBefore(resetBtn, filterBtn.nextSibling);
    }

    // معالج حدث إعادة التعيين
    resetBtn.addEventListener('click', function() {
      filterForm.reset();
      const tableRows = document.querySelectorAll('.table tbody tr');
      tableRows.forEach(row => row.style.display = '');
      
      // إعادة تعيين حقل البحث
      const searchInput = document.getElementById('searchTable');
      if (searchInput) {
        searchInput.value = '';
      }
    });
  }

  // إضافة وظيفة البحث المباشر
  const searchInput = document.getElementById('searchTable');
  if (searchInput) {
    searchInput.addEventListener('keyup', function() {
      const searchText = this.value.toLowerCase();
      const tableRows = document.querySelectorAll('.table tbody tr');

      tableRows.forEach(row => {
        let text = '';
        // تجميع النص من جميع الخلايا ما عدا خلية الإجراءات
        row.querySelectorAll('td:not(.actions-cell)').forEach(cell => {
          text += cell.textContent + ' ';
        });
        
        text = text.toLowerCase();
        
        // إظهار أو إخفاء الصف بناءً على نتيجة البحث
        if (text.includes(searchText)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }
});

// دالة الأرشفة
async function archiveDocument(docId) {
  if (!confirm('هل أنت متأكد من أرشفة هذا الكتاب؟')) return;

  try {
    const response = await fetch('archive_document.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ id: docId })
    });

    // التحقق من نوع المحتوى المستلم
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      throw new Error('خطأ في الاستجابة من الخادم');
    }

    let data;
    try {
      data = await response.json();
    } catch (e) {
      console.error('خطأ في تحليل البيانات:', e);
      throw new Error('تنسيق البيانات المستلمة غير صحيح');
    }
    
    if (response.ok && data.success) {
      showToast('success', data.message || 'تم أرشفة الكتاب بنجاح');
      setTimeout(() => location.reload(), 1000);
    } else {
      throw new Error(data.message || 'فشل في أرشفة الكتاب');
    }
  } catch (error) {
    console.error('خطأ في الأرشفة:', error);
    showToast('error', error.message || 'حدث خطأ أثناء الأرشفة');
  }
}

// دالة إضافة تعليق
async function showCommentDialog(docId) {
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.innerHTML = `
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">إضافة تعليق</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <textarea class="form-control" id="commentText" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="button" class="btn btn-primary" onclick="submitComment(${docId})">إضافة</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();
}

// دالة إرسال التعليق
async function submitComment(docId) {
  const commentText = document.getElementById('commentText').value;
  if (!commentText.trim()) {
    showToast('error', 'الرجاء إدخال نص التعليق');
    return;
  }

  try {
    const response = await fetch('add_comment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        document_id: docId,
        content: commentText
      })
    });

    const data = await response.json();
    
    if (response.ok && data.success) {
      showToast('success', data.message || 'تم إضافة التعليق بنجاح');
      bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
      setTimeout(() => location.reload(), 1000);
    } else {
      throw new Error(data.message || 'فشل في إضافة التعليق');
    }
  } catch (error) {
    console.error('خطأ في إضافة التعليق:', error);
    showToast('error', error.message || 'حدث خطأ أثناء إضافة التعليق');
  }
}

// دالة الإرسال
async function showSendDialog(docId, type) {
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.innerHTML = `
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            ${type === 'broadcast' ? 'تعميم الكتاب' : 
              type === 'multiple' ? 'إرسال لعدة جهات' : 'إرسال الكتاب'}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="sendForm">
            <div class="mb-3" id="receiversContainer">
              ${type === 'single' ? `
                <select class="form-select" name="receiver_id" required>
                  <option value="">اختر الجهة</option>
                </select>
              ` : `
                <div id="receiversList" class="border p-3">
                  <!-- سيتم تحميل الجهات هنا -->
                </div>
              `}
            </div>
            <div class="mb-3">
              <label class="form-label">ملاحظات</label>
              <textarea class="form-control" name="notes" rows="3"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="button" class="btn btn-primary" onclick="submitSend(${docId}, '${type}')">إرسال</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  const bsModal = new bootstrap.Modal(modal);
  
  // تحميل قائمة المستلمين
  await loadReceivers(type);
  
  bsModal.show();
}

// دالة تحميل المستلمين
async function loadReceivers(type) {
  try {
    const response = await fetch('get_receivers.php?type=' + type, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error('فشل في الاتصال بالخادم');
    }

    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.message || 'فشل في تحميل قائمة المستلمين');
    }

    const receivers = data.data;
    
    if (type === 'single') {
      const select = document.querySelector('select[name="receiver_id"]');
      select.innerHTML = '<option value="">اختر الجهة</option>';
      
      receivers.forEach(group => {
        if (group.items && group.items.length > 0) {
          const optgroup = document.createElement('optgroup');
          optgroup.label = group.group;
          
          group.items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            optgroup.appendChild(option);
          });
          
          select.appendChild(optgroup);
        }
      });
    } else {
      const container = document.getElementById('receiversList');
      container.innerHTML = '';
      
      receivers.forEach(group => {
        if (group.items && group.items.length > 0) {
          const groupDiv = document.createElement('div');
          groupDiv.className = 'receiver-group mb-3';
          
          groupDiv.innerHTML = `
            <h6 class="mb-2">${group.group}</h6>
            <div class="row">
              ${group.items.map(item => `
                <div class="col-md-6 mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" 
                           name="receivers[]" value="${item.id}" id="receiver_${item.id}">
                    <label class="form-check-label" for="receiver_${item.id}">
                      ${item.name}
                    </label>
                  </div>
                </div>
              `).join('')}
            </div>
          `;
          
          container.appendChild(groupDiv);
        }
      });

      // إضافة زر تحديد/إلغاء تحديد الكل
      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'mb-3 text-end';
      actionsDiv.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="toggleAllReceivers(true)">
          تحديد الكل
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllReceivers(false)">
          إلغاء تحديد الكل
        </button>
      `;
      container.insertBefore(actionsDiv, container.firstChild);
    }
  } catch (error) {
    console.error('خطأ في تحميل المستلمين:', error);
    showToast('error', error.message);
  }
}

// دالة تحديد/إلغاء تحديد كل المستلمين
function toggleAllReceivers(checked) {
  const checkboxes = document.querySelectorAll('input[name="receivers[]"]');
  checkboxes.forEach(checkbox => checkbox.checked = checked);
}

// دالة إرسال الكتاب
async function submitSend(docId, type) {
  const form = document.getElementById('sendForm');
  const formData = new FormData(form);
  
  // التحقق من اختيار مستلم على الأقل
  if (type === 'single' && !formData.get('receiver_id')) {
    showToast('error', 'الرجاء اختيار جهة الإرسال');
    return;
  }
  
  if (type !== 'single') {
    const selectedReceivers = formData.getAll('receivers[]');
    if (!selectedReceivers.length) {
      showToast('error', 'الرجاء اختيار جهة واحدة على الأقل');
      return;
    }
  }

  formData.append('document_id', docId);
  formData.append('send_type', type);

  try {
    const response = await fetch('send_document.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();
    
    if (response.ok && data.success) {
      showToast('success', data.message || 'تم إرسال الكتاب بنجاح');
      bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
      setTimeout(() => location.reload(), 1000);
    } else {
      throw new Error(data.message || 'فشل في إرسال الكتاب');
    }
  } catch (error) {
    console.error('خطأ في إرسال الكتاب:', error);
    showToast('error', error.message || 'حدث خطأ أثناء إرسال الكتاب');
  }
}

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
    
    if (response.ok && data.success) {
      showToast('success', data.message || 'تم حذف الكتاب بنجاح');
      setTimeout(() => location.reload(), 1000);
    } else {
      throw new Error(data.message || 'فشل في حذف الكتاب');
    }
  } catch (error) {
    console.error('خطأ في حذف الكتاب:', error);
    showToast('error', error.message || 'حدث خطأ أثناء حذف الكتاب');
  }
}

// تحسين دالة عرض الرسائل
function showToast(type, message) {
  // إزالة أي رسائل سابقة
  const existingToasts = document.querySelectorAll('.toast');
  existingToasts.forEach(toast => toast.remove());

  const toast = document.createElement('div');
  toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');
  toast.setAttribute('aria-atomic', 'true');
  
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
  const bsToast = new bootstrap.Toast(toast, {
    animation: true,
    autohide: true,
    delay: 3000
  });
  bsToast.show();
}

// دالة تصدير إلى Excel
async function exportToExcel() {
  try {
    // الحصول على معايير الفلتر الحالية
    const filterForm = document.getElementById('filterForm');
    const formData = new FormData(filterForm);
    
    // إضافة معلمة التصدير
    formData.append('export', 'excel');

    const response = await fetch('export_documents.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      // إنشاء رابط تحميل مؤقت
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'documents_' + new Date().toISOString().split('T')[0] + '.xlsx';
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      a.remove();
      
      showToast('success', 'تم تصدير البيانات بنجاح');
    } else {
      throw new Error('فشل في تصدير البيانات');
    }
  } catch (error) {
    showToast('error', error.message);
  }
}

// دالة الطباعة
function printDocuments() {
  // إنشاء نافذة طباعة جديدة
  const printWindow = window.open('', '_blank');
  
  // إعداد محتوى HTML للطباعة
  const table = document.querySelector('.table').cloneNode(true);
  
  // إزالة عمود الإجراءات من الطباعة
  const actionCells = table.querySelectorAll('th:last-child, td:last-child');
  actionCells.forEach(cell => cell.remove());
  
  const printContent = `
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
      <title>طباعة الكتب</title>
      <link href="assets/css/bootstrap.rtl.min.css" rel="stylesheet">
      <style>
        body { font-family: Arial, sans-serif; }
        .table { width: 100%; margin-bottom: 1rem; }
        .table th, .table td { padding: 0.75rem; border-bottom: 1px solid #dee2e6; }
        .badge { padding: 0.35em 0.65em; border-radius: 0.25rem; }
        .bg-success { background-color: #198754 !important; color: white; }
        .bg-warning { background-color: #ffc107 !important; }
        .bg-danger { background-color: #dc3545 !important; color: white; }
        @media print {
          .table { page-break-inside: auto; }
          tr { page-break-inside: avoid; page-break-after: auto; }
        }
      </style>
    </head>
    <body>
      <h2 class="text-center mb-4">قائمة الكتب والمراسلات</h2>
      <div class="table-responsive">
        ${table.outerHTML}
      </div>
    </body>
    </html>
  `;
  
  printWindow.document.write(printContent);
  printWindow.document.close();
  
  // انتظار تحميل الصفحة ثم طباعة
  printWindow.onload = function() {
    printWindow.print();
    // إغلاق النافذة بعد الطباعة
    printWindow.onafterprint = function() {
      printWindow.close();
    };
  };
}

// دالة تطبيق الفلتر
function applyFilter(form) {
  const formData = new FormData(form);
  const filters = {
    status: formData.get('status'),
    start_date: formData.get('start_date'),
    end_date: formData.get('end_date'),
    search: formData.get('search')
  };

  const tableRows = document.querySelectorAll('.table tbody tr');
  
  tableRows.forEach(row => {
    let showRow = true;

    // فلتر الحالة
    if (filters.status) {
      const statusCell = row.querySelector('td:nth-child(5) .badge');
      const status = statusCell.textContent.trim();
      if (status !== getStatusLabel(filters.status)) {
        showRow = false;
      }
    }

    // فلتر التاريخ
    if (filters.start_date || filters.end_date) {
      const dateCell = row.querySelector('td:nth-child(6)');
      const rowDate = new Date(dateCell.textContent.trim());

      if (filters.start_date && new Date(filters.start_date) > rowDate) {
        showRow = false;
      }
      if (filters.end_date && new Date(filters.end_date) < rowDate) {
        showRow = false;
      }
    }

    // فلتر البحث
    if (filters.search) {
      const searchText = filters.search.toLowerCase();
      let rowText = '';
      row.querySelectorAll('td:not(.actions-cell)').forEach(cell => {
        rowText += cell.textContent + ' ';
      });
      if (!rowText.toLowerCase().includes(searchText)) {
        showRow = false;
      }
    }

    // تطبيق النتيجة
    row.style.display = showRow ? '' : 'none';
  });

  // تحديث حالة الزر
  const filterBtn = document.getElementById('filterBtn');
  if (filterBtn) {
    filterBtn.innerHTML = '<i class="fas fa-filter"></i> تم تطبيق الفلتر';
    setTimeout(() => {
      filterBtn.innerHTML = '<i class="fas fa-filter"></i> تطبيق الفلتر';
    }, 2000);
  }
}

// دالة مساعدة للحصول على نص الحالة
function getStatusLabel(status) {
  const statusLabels = {
    'draft': 'مسودة',
    'pending': 'قيد الإرسال',
    'sent': 'تم الإرسال',
    'received': 'تم الاستلام',
    'processed': 'تمت المعالجة',
    'archived': 'مؤرشف'
  };
  return statusLabels[status] || status;
}
