-- ============================================================
--  CSHunter: Background Items Migration
--  Виконай: mysql -h HOST -u USER -pPASS DB < migrate_backgrounds.sql
-- ============================================================

-- items.type вже має 'background' в ENUM з початкової міграції
-- Просто додаємо демо-предмети

INSERT IGNORE INTO items (type, slug, name, rarity, image_lg, image_sm, animated, sort_order) VALUES
('background', 'bg_default',    'Стандартний фон',   'consumer',   '', '', 0, 10),
('background', 'bg_veteran',    'Фон ветерана',       'industrial', '', '', 0, 20),
('background', 'bg_hunter',     'Мисливець',          'milspec',    '', '', 0, 30),
('background', 'bg_elite',      'Еліта',              'restricted', '', '', 0, 40),
('background', 'bg_champion',   'Чемпіон',            'classified', '', '', 0, 50),
('background', 'bg_legendary',  'Легендарний',        'covert',     '', '', 0, 60);

-- Авто-видача стартового фону при реєстрації
INSERT IGNORE INTO item_conditions (item_id, condition_type, condition_value)
SELECT id, 'registration_days', 0 FROM items WHERE slug = 'bg_default';

-- ============================================================
-- Зображення кладіть в: assets/items/backgrounds/{slug}_lg.png
-- Рекомендований розмір: 1200×200px або 1600×240px (широкоформатний)
-- ============================================================
