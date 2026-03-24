-- ============================================================
--  CSHunter: Items System v2 Migration
--  Виконай: mysql -h HOST -u USER -pPASS DB < migrate_items_v2.sql
-- ============================================================

-- 1. Додаємо нові колонки в items (якщо ще немає)
ALTER TABLE items
    ADD COLUMN IF NOT EXISTS description  TEXT         NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS avatar_shape ENUM('rounded','square') NOT NULL DEFAULT 'rounded';

-- 2. Оновлюємо коментар до image_sm (no changes to column, just note)
-- image_sm залишається для сумісності, але в UI використовується image_lg

-- Готово.
