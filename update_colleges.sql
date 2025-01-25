-- إزالة عمود university_id من جدول colleges
ALTER TABLE colleges DROP COLUMN IF EXISTS university_id; 