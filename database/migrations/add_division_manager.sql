-- إضافة عمود مدير الشعبة إلى جدول الشعب
ALTER TABLE university_divisions
ADD COLUMN division_manager_id INT NULL,
ADD FOREIGN KEY (division_manager_id) REFERENCES users(id) ON DELETE SET NULL; 