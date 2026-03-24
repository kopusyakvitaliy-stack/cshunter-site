-- ============================================================
--  CSHunter: Items System Migration
--  Виконай один раз: mysql -h HOST -u USER -pPASS DB < migrate_items.sql
-- ============================================================

-- 1. Каталог предметів (frame, background, badge, card_style і т.д.)
CREATE TABLE IF NOT EXISTS items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    type            ENUM('frame','background','badge','card_style') NOT NULL DEFAULT 'frame',
    slug            VARCHAR(100) NOT NULL,
    name            VARCHAR(100) NOT NULL,
    rarity          ENUM('consumer','industrial','milspec','restricted','classified','covert') NOT NULL DEFAULT 'consumer',
    image_lg        VARCHAR(255) NOT NULL DEFAULT '',   -- для профілю (рекомендовано: assets/items/frames/name_lg.png)
    image_sm        VARCHAR(255) NOT NULL DEFAULT '',   -- для карток (рекомендовано: assets/items/frames/name_sm.png)
    animated        TINYINT(1)   NOT NULL DEFAULT 0,    -- 0=PNG, 1=APNG/WebP анімований
    hidden          TINYINT(1)   NOT NULL DEFAULT 0,    -- прихований (тільки адмін може видати)
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug),
    INDEX idx_type   (type),
    INDEX idx_rarity (rarity),
    INDEX idx_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Інвентар гравців (що є в наявності)
CREATE TABLE IF NOT EXISTS user_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    item_id         INT NOT NULL,
    obtained_by     ENUM('auto','admin','case','event') NOT NULL DEFAULT 'auto',
    obtained_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_item (user_id, item_id),
    INDEX idx_user  (user_id),
    INDEX idx_item  (item_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Екіпіровані предмети (по одному на кожен тип-слот)
CREATE TABLE IF NOT EXISTS user_equipped (
    user_id         INT          NOT NULL,
    item_type       VARCHAR(50)  NOT NULL,  -- 'frame', 'background', etc.
    item_id         INT          NOT NULL,
    equipped_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, item_type),
    INDEX idx_user  (user_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (item_id)  REFERENCES items(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Умови авто-видачі предметів
CREATE TABLE IF NOT EXISTS item_conditions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    item_id          INT NOT NULL,
    condition_type   ENUM('registration_days','playtime_hours','kills','faceit_level','manual') NOT NULL,
    condition_value  INT NOT NULL DEFAULT 0,
    INDEX idx_item   (item_id),
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Демо-предмети (placeholder рамки всіх рідкостей)
--    Зображення: assets/items/frames/ — додасть адмін або дизайнер
--    Поки image_lg/image_sm порожні — в UI покажеться CSS-плейсхолдер
INSERT IGNORE INTO items (type, slug, name, rarity, image_lg, image_sm, animated, sort_order) VALUES
('frame', 'frame_starter',      'Стартова рамка',     'consumer',   '', '', 0, 10),
('frame', 'frame_veteran',      'Рамка ветерана',      'industrial', '', '', 0, 20),
('frame', 'frame_hunter',       'Мисливець',           'milspec',    '', '', 0, 30),
('frame', 'frame_elite',        'Еліта',               'restricted', '', '', 0, 40),
('frame', 'frame_champion',     'Чемпіон',             'classified', '', '', 0, 50),
('frame', 'frame_legendary',    'Легендарний',         'covert',     '', '', 1, 60);

-- 6. Умови для демо-предметів
INSERT IGNORE INTO item_conditions (item_id, condition_type, condition_value)
SELECT id, 'registration_days', 0   FROM items WHERE slug = 'frame_starter';

INSERT IGNORE INTO item_conditions (item_id, condition_type, condition_value)
SELECT id, 'registration_days', 30  FROM items WHERE slug = 'frame_veteran';

INSERT IGNORE INTO item_conditions (item_id, condition_type, condition_value)
SELECT id, 'registration_days', 365 FROM items WHERE slug = 'frame_champion';

INSERT IGNORE INTO item_conditions (item_id, condition_type, condition_value)
SELECT id, 'manual', 0 FROM items WHERE slug = 'frame_legendary';

-- ============================================================
-- Структура директорії для зображень:
--   assets/items/frames/{slug}_lg.png  (рекоменд. 220×220)
--   assets/items/frames/{slug}_sm.png  (рекоменд. 92×92)
-- Анімовані: .apng або .webp замість .png
-- ============================================================
