// إعداد الإشعارات الفورية
const setupNotifications = () => {
  const evtSource = new EventSource('realtime_notifications.php');
  
  evtSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    switch (data.type) {
      case 'notifications':
        handleNewNotifications(data.data);
        break;
      case 'documents':
        updateDocumentStatus(data.data);
        break;
    }
  };
  
  evtSource.onerror = (err) => {
    console.error('EventSource failed:', err);
    setTimeout(() => setupNotifications(), 5000); // إعادة المحاولة بعد 5 ثواني
  };
};

// معالجة الإشعارات الجديدة
const handleNewNotifications = (notifications) => {
  const container = document.getElementById('notifications-container');
  const badge = document.getElementById('notifications-badge');
  
  // تحديث عدد الإشعارات
  badge.textContent = notifications.length;
  badge.style.display = notifications.length > 0 ? 'block' : 'none';
  
  // إضافة الإشعارات الجديدة
  notifications.forEach(notification => {
    const notificationElement = createNotificationElement(notification);
    container.insertBefore(notificationElement, container.firstChild);
    
    // عرض إشعار منبثق
    showToast(notification.title, notification.content);
  });
};

// إنشاء عنصر الإشعار
const createNotificationElement = (notification) => {
  const div = document.createElement('div');
  div.className = 'notification-item';
  div.innerHTML = `
    <div class="notification-header">
      <strong>${notification.title}</strong>
      <small>${formatDate(notification.created_at)}</small>
    </div>
    <div class="notification-content">${notification.content}</div>
    <div class="notification-actions">
      <button onclick="markAsRead(${notification.id})" class="btn btn-sm btn-primary">تم القراءة</button>
    </div>
  `;
  return div;
};

// تحديث حالة الكتب
const updateDocumentStatus = (documents) => {
  documents.forEach(doc => {
    const statusElement = document.querySelector(`#doc-status-${doc.id}`);
    if (statusElement) {
      statusElement.textContent = getStatusText(doc.status);
      statusElement.className = `badge bg-${getStatusColor(doc.status)}`;
    }
  });
};

// عرض إشعار منبثق
const showToast = (title, message) => {
  const toast = new bootstrap.Toast(document.createElement('div'));
  toast._element.className = 'toast';
  toast._element.innerHTML = `
    <div class="toast-header">
      <strong class="me-auto">${title}</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body">${message}</div>
  `;
  document.getElementById('toast-container').appendChild(toast._element);
  toast.show();
};

// تنسيق التاريخ
const formatDate = (date) => {
  return new Date(date).toLocaleString('ar-SA');
};

// الحصول على نص الحالة
const getStatusText = (status) => {
  const statusMap = {
    'pending': 'قيد الانتظار',
    'received': 'تم الاستلام',
    'processed': 'تمت المعالجة'
  };
  return statusMap[status] || status;
};

// الحصول على لون الحالة
const getStatusColor = (status) => {
  const colorMap = {
    'pending': 'warning',
    'received': 'info',
    'processed': 'success'
  };
  return colorMap[status] || 'secondary';
};

// تحديد الإشعار كمقروء
const markAsRead = async (notificationId) => {
  try {
    const response = await fetch('mark_notification_read.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ notification_id: notificationId })
    });
    
    if (response.ok) {
      const element = document.querySelector(`[data-notification-id="${notificationId}"]`);
      if (element) {
        element.classList.add('read');
      }
    }
  } catch (error) {
    console.error('Error marking notification as read:', error);
  }
};

// تحديث الإشعارات تلقائياً
document.addEventListener('DOMContentLoaded', setupNotifications);
