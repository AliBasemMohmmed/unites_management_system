-- تعطيل التحقق من العلاقات الخارجية مؤقتاً
SET FOREIGN_KEY_CHECKS = 0;

-- حذف جميع العلاقات الخارجية من جدول units
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_university_fk`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_ibfk_3`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_college_fk`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_division_fk`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_user_fk`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_updated_by_fk`;
ALTER TABLE `units` DROP FOREIGN KEY IF EXISTS `units_created_by_fk`;

-- حذف عمود university_id
ALTER TABLE `units` DROP COLUMN IF EXISTS `university_id`;

-- إعادة إنشاء العلاقات الخارجية واحدة تلو الأخرى
ALTER TABLE `units`
    ADD CONSTRAINT `units_college_fk` 
    FOREIGN KEY (`college_id`) 
    REFERENCES `colleges` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

ALTER TABLE `units`
    ADD CONSTRAINT `units_division_fk` 
    FOREIGN KEY (`division_id`) 
    REFERENCES `divisions` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

ALTER TABLE `units`
    ADD CONSTRAINT `units_user_fk` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

ALTER TABLE `units`
    ADD CONSTRAINT `units_updated_by_fk` 
    FOREIGN KEY (`updated_by`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

ALTER TABLE `units`
    ADD CONSTRAINT `units_created_by_fk` 
    FOREIGN KEY (`created_by`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

-- إعادة تفعيل التحقق من العلاقات الخارجية
SET FOREIGN_KEY_CHECKS = 1; 