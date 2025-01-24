-- إنشاء جدول الأدوار
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إضافة الأدوار الأساسية
INSERT INTO roles (name, display_name) VALUES
('unit_employee', 'موظف وحدة'),
('unit_head', 'رئيس وحدة'),
('division_employee', 'موظف شعبة'),
('division_head', 'رئيس الشعبة');

-- إضافة عمود role_id في جدول users
ALTER TABLE users ADD COLUMN role_id INT;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id);

-- تحديث البيانات الموجودة
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'unit_employee') WHERE role = 'موظف وحدة';
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'unit_head') WHERE role = 'رئيس وحدة';
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'division_employee') WHERE role = 'موظف شعبة';
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'division_head') WHERE role = 'رئيس الشعبة'; 