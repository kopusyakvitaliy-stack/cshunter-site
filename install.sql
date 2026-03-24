-- CSHunter Database Schema
-- Run this in MySQL/MariaDB to create tables

CREATE DATABASE IF NOT EXISTS cshunter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cshunter;

-- Users (Steam auth)
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    steam_id      VARCHAR(20) UNIQUE NOT NULL,
    steam_name    VARCHAR(100) NOT NULL,
    avatar_url    VARCHAR(255),
    profile_url   VARCHAR(255),
    role          ENUM('player','admin') DEFAULT 'player',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_steam_id (steam_id)
) ENGINE=InnoDB;

-- Game modes
CREATE TABLE IF NOT EXISTS modes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(50) UNIQUE NOT NULL,
    name         VARCHAR(100) NOT NULL,
    tag          VARCHAR(30),
    description  TEXT,
    rules        JSON,
    servers_count INT DEFAULT 0,
    total_online INT DEFAULT 0,
    sort_order   INT DEFAULT 0,
    active       TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Servers
CREATE TABLE IF NOT EXISTS servers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    mode_id        INT NOT NULL,
    name           VARCHAR(150) NOT NULL,
    ip             VARCHAR(45) NOT NULL,
    port           SMALLINT UNSIGNED NOT NULL,
    map            VARCHAR(100) DEFAULT 'de_dust2',
    players_online SMALLINT DEFAULT 0,
    players_max    SMALLINT DEFAULT 32,
    status         TINYINT(1) DEFAULT 1,
    tags           VARCHAR(200),
    active         TINYINT(1) DEFAULT 1,
    last_update    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mode_id) REFERENCES modes(id) ON DELETE CASCADE,
    INDEX idx_mode (mode_id)
) ENGINE=InnoDB;

-- Player stats per mode
CREATE TABLE IF NOT EXISTS stats (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    mode_id      INT NOT NULL,
    score        BIGINT DEFAULT 0,
    playtime_hours INT DEFAULT 0,
    kills        INT DEFAULT 0,
    deaths       INT DEFAULT 0,
    sessions     INT DEFAULT 0,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_mode (user_id, mode_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mode_id) REFERENCES modes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Skinchanger selections
CREATE TABLE IF NOT EXISTS skin_selections (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    weapon     VARCHAR(50) NOT NULL,
    skin_name  VARCHAR(100) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_weapon (user_id, weapon),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert demo modes
INSERT IGNORE INTO modes (slug, name, tag, description, rules, servers_count, total_online, sort_order) VALUES
('surf',       'Surf',       'Popular', 'Зліт по хвилях швидкості — відчуй адреналін серфінгу.', '["Жодного гріфінгу","Чити заборонені","Поважай інших","Не стрибай з початку","Таймер зупиняється на забороненій зоні"]', 3, 47, 1),
('deathmatch', 'Deathmatch', 'TOP',     'Нескінченний бій. Тренуй аім та рефлекси.', '["Без образ","Заборонено гучний мік","Не блокуй гравців","Камперство заборонено","Не скаржся на аім :)"]', 4, 89, 2),
('1v1',        '1v1 Arena',  'Skill',   'Доведи що ти найкращий у прямій дуелі.', '["Повага до противника","Один раунд — одне зброя","Pause тільки на тех. проблеми","Результати в рейтинг"]', 2, 26, 3),
('kz',         'KZ Climb',   'Hardcore','Екстремальний паркур по вертикальних картах.', '["Без телепортів","Bunnyhop за дозволом","Не заважай іншим","Рекорди авто","Читерські скрипти = бан"]', 2, 18, 4),
('bhop',       'Bhop',       'Speed',   'Стрибай без зупинки, набирай максимальну швидкість.', '["Заборонені авто-скрипти","Тільки ручний бхоп","Гучні звуки заборонені","Повага до рекордів"]', 2, 31, 5),
('retake',     'Retake',     'Team',    'Відбивай бомбу або захищай підрив — командний режим.', '["Командна гра","Голосове вітається","Кидай гранати по ворогу","Розтискання сайтів — пріоритет"]', 3, 62, 6);
