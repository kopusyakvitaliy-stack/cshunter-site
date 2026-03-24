-- ============================================================
--  CSHunter Admin Panel — повна міграція
--  Виконай один раз: mysql -h HOST -u USER -pPASS DB < admin/admin_migrate.sql
-- ============================================================

-- 1. Таблиця аудит-логу
CREATE TABLE IF NOT EXISTS admin_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    admin_steam_id VARCHAR(20)  NOT NULL,
    admin_name     VARCHAR(100) NOT NULL,
    action         VARCHAR(100) NOT NULL,
    target         VARCHAR(200) DEFAULT '',
    details        JSON,
    ip             VARCHAR(45)  DEFAULT '',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin   (admin_steam_id),
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- 2. Таблиця банів
CREATE TABLE IF NOT EXISTS user_bans (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    steam_id     VARCHAR(20)  NOT NULL,
    reason       VARCHAR(500) DEFAULT '',
    banned_by    VARCHAR(20)  NOT NULL,
    banned_until TIMESTAMP    NULL,
    is_active    TINYINT(1)   DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_steam  (steam_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- 3. Кеш Steam профілів (переглянуті профілі — НЕ залогінені юзери)
CREATE TABLE IF NOT EXISTS steam_profile_cache (
    steam_id    VARCHAR(20)  NOT NULL PRIMARY KEY,
    steam_name  VARCHAR(100) NOT NULL,
    avatar_url  VARCHAR(255) DEFAULT '',
    profile_url VARCHAR(255) DEFAULT '',
    country     VARCHAR(10)  DEFAULT '',
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB;

-- 4. Нові поля в users
ALTER TABLE users
    MODIFY COLUMN role ENUM('player','moderator','admin') DEFAULT 'player';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_banned     TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ban_reason    VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS has_logged_in TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS sort_order    SMALLINT     DEFAULT 0;

-- 5. Позначити існуючих реальних юзерів
-- (last_login оновлювався тільки при реальному логіні через saveOrUpdateUser)
UPDATE users SET has_logged_in = 1 WHERE last_login IS NOT NULL;

-- 6. Перенести "фантомів" з users в steam_profile_cache
-- (юзери які потрапили туди через перегляд профілю, а не через логін)
-- Після цього їх можна видалити з users якщо хочеш (необов'язково)
INSERT IGNORE INTO steam_profile_cache (steam_id, steam_name, avatar_url, profile_url, country, updated_at)
SELECT steam_id, steam_name, avatar_url, profile_url, country, created_at
FROM users
WHERE has_logged_in = 0;

-- 7. sort_order в servers
ALTER TABLE servers
    ADD COLUMN IF NOT EXISTS sort_order SMALLINT DEFAULT 0;

-- ============================================================
-- КРОК 8 — Призначити першого адміна (заміни steam_id)
-- UPDATE users SET role = 'admin' WHERE steam_id = 'YOUR_STEAM_ID_64';
-- ============================================================
