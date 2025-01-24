-- تعطيل فحص المفاتيح الخارجية
SET FOREIGN_KEY_CHECKS = 0;

-- إضافة دور المدير إذا لم يكن موجوداً
INSERT IGNORE INTO roles (name, display_name) VALUES 
('admin', 'مدير النظام'),
('unit_employee', 'موظف وحدة'),
('unit_head', 'رئيس وحدة'),
('division_employee', 'موظف شعبة'),
('division_head', 'رئيس الشعبة');

-- حذف العلاقات الخارجية من جدول users
ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_ibfk_1;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_ibfk_2;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_ibfk_3;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS users_ibfk_4;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_role_id;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_college_id;

-- حذف القيود الفريدة القديمة إن وجدت
ALTER TABLE users DROP INDEX IF EXISTS unique_unit_head_college;

-- حذف الأعمدة غير المستخدمة من جدول users
ALTER TABLE users 
    DROP COLUMN IF EXISTS entity_id,
    DROP COLUMN IF EXISTS user_type_id,
    DROP COLUMN IF EXISTS role;

-- تحديث المستخدمين الحاليين حسب أدوارهم
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin')
WHERE username = 'admin';

UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'unit_employee')
WHERE username IN ('unit1', 'unit2', 'علي');

UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'division_employee')
WHERE username IN ('division1', 'division2');

UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'unit_employee')
WHERE username IN ('ministry1', 'ministry2');

-- إضافة القيود الجديدة
ALTER TABLE users MODIFY COLUMN role_id INT NOT NULL;

-- إضافة المفاتيح الخارجية الجديدة
ALTER TABLE users 
    ADD CONSTRAINT fk_users_role_id FOREIGN KEY (role_id) REFERENCES roles(id),
    ADD CONSTRAINT fk_users_college_id FOREIGN KEY (college_id) REFERENCES colleges(id);

-- حذف Triggers القديمة إن وجدت
DROP TRIGGER IF EXISTS before_user_insert;
DROP TRIGGER IF EXISTS before_user_update;

-- إنشاء Trigger للتحقق قبل الإضافة
DELIMITER //
CREATE TRIGGER before_user_insert 
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    DECLARE unit_head_role_id INT;
    DECLARE existing_head_count INT;
    
    -- الحصول على معرف دور رئيس الوحدة
    SELECT id INTO unit_head_role_id FROM roles WHERE name = 'unit_head';
    
    -- التحقق فقط إذا كان المستخدم الجديد رئيس وحدة
    IF NEW.role_id = unit_head_role_id THEN
        -- التحقق من وجود رئيس وحدة آخر لنفس الكلية
        SELECT COUNT(*) INTO existing_head_count
        FROM users 
        WHERE college_id = NEW.college_id 
        AND role_id = unit_head_role_id;
        
        IF existing_head_count > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن إضافة أكثر من رئيس وحدة لنفس الكلية';
        END IF;
    END IF;
END //
DELIMITER ;

-- إنشاء Trigger للتحقق قبل التحديث
DELIMITER //
CREATE TRIGGER before_user_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE unit_head_role_id INT;
    DECLARE existing_head_count INT;
    
    -- الحصول على معرف دور رئيس الوحدة
    SELECT id INTO unit_head_role_id FROM roles WHERE name = 'unit_head';
    
    -- التحقق فقط إذا تم تغيير الدور إلى رئيس وحدة أو تم تغيير الكلية
    IF NEW.role_id = unit_head_role_id AND 
       (OLD.role_id != NEW.role_id OR OLD.college_id != NEW.college_id) THEN
        -- التحقق من وجود رئيس وحدة آخر لنفس الكلية
        SELECT COUNT(*) INTO existing_head_count
        FROM users 
        WHERE college_id = NEW.college_id 
        AND role_id = unit_head_role_id
        AND id != NEW.id;
        
        IF existing_head_count > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن وجود أكثر من رئيس وحدة لنفس الكلية';
        END IF;
    END IF;
END //
DELIMITER ;

-- إعادة تفعيل فحص المفاتيح الخارجية
SET FOREIGN_KEY_CHECKS = 1; 