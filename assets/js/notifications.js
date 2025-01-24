class NotificationManager {
    constructor() {
        this.lastCheck = new Date();
        this.notificationsList = document.querySelector('.notifications-list');
        this.notificationBadge = document.querySelector('.notification-badge');
        this.dropdownToggle = document.getElementById('notificationsDropdown');
        
        this.initializeEventListeners();
        this.checkNotifications();
        
        // التحقق من الإشعارات الجديدة كل دقيقة
        setInterval(() => this.checkNewNotifications(), 60000);
    }

    initializeEventListeners() {
        // تحديث حالة الإشعارات عند فتح القائمة
        this.dropdownToggle.addEventListener('click', () => {
            this.loadNotifications();
        });

        // تحديث حالة الإشعار عند النقر عليه
        this.notificationsList.addEventListener('click', (e) => {
            const notificationLink = e.target.closest('.notification-item');
            if (notificationLink) {
                const notificationId = notificationLink.dataset.id;
                this.markAsRead(notificationId);
            }
        });
    }

    async checkNewNotifications() {
        try {
            const response = await fetch('check_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lastCheck: this.lastCheck.toISOString()
                })
            });
            
            const data = await response.json();
            if (data.hasNew) {
                this.showBadge(data.count);
            }
            
            this.lastCheck = new Date();
        } catch (error) {
            console.error('خطأ في التحقق من الإشعارات:', error);
        }
    }

    async loadNotifications() {
        try {
            const response = await fetch('get_notifications.php');
            const notifications = await response.json();
            
            this.renderNotifications(notifications);
        } catch (error) {
            console.error('خطأ في تحميل الإشعارات:', error);
        }
    }

    renderNotifications(notifications) {
        if (notifications.length === 0) {
            this.notificationsList.innerHTML = `
                <div class="dropdown-item text-muted text-center">
                    لا توجد إشعارات جديدة
                </div>
            `;
            return;
        }

        this.notificationsList.innerHTML = notifications.map(notification => `
            <a href="${this.getNotificationLink(notification)}" 
               class="dropdown-item notification-item ${notification.is_read ? 'read' : 'unread'}"
               data-id="${notification.id}">
                <div class="d-flex align-items-center">
                    <div class="notification-icon me-2">
                        ${this.getNotificationIcon(notification.related_type)}
                    </div>
                    <div class="flex-grow-1">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-text small">${notification.content}</div>
                        <div class="notification-time text-muted small">
                            ${this.formatDate(notification.created_at)}
                        </div>
                    </div>
                </div>
            </a>
        `).join('');
    }

    getNotificationIcon(type) {
        const icons = {
            document: '<i class="fas fa-file-alt"></i>',
            message: '<i class="fas fa-envelope"></i>',
            task: '<i class="fas fa-tasks"></i>',
            default: '<i class="fas fa-bell"></i>'
        };
        return icons[type] || icons.default;
    }

    getNotificationLink(notification) {
        const links = {
            document: `document_workflow.php?id=${notification.related_id}`,
            message: `messages.php?id=${notification.related_id}`,
            task: `tasks.php?id=${notification.related_id}`,
            default: '#'
        };
        return links[notification.related_type] || links.default;
    }

    async markAsRead(notificationId) {
        try {
            await fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: notificationId })
            });
        } catch (error) {
            console.error('خطأ في تحديث حالة الإشعار:', error);
        }
    }

    showBadge(count) {
        this.notificationBadge.textContent = count;
        this.notificationBadge.style.display = 'inline-block';
    }

    hideBadge() {
        this.notificationBadge.style.display = 'none';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        // تنسيق التاريخ حسب المدة
        if (diff < 60000) { // أقل من دقيقة
            return 'الآن';
        } else if (diff < 3600000) { // أقل من ساعة
            const minutes = Math.floor(diff / 60000);
            return `منذ ${minutes} دقيقة`;
        } else if (diff < 86400000) { // أقل من يوم
            const hours = Math.floor(diff / 3600000);
            return `منذ ${hours} ساعة`;
        } else if (diff < 604800000) { // أقل من أسبوع
            const days = Math.floor(diff / 86400000);
            return `منذ ${days} يوم`;
        } else {
            return date.toLocaleDateString('ar-SA');
        }
    }
}

// تهيئة مدير الإشعارات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
}); 