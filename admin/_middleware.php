<?php
/**
 * Admin Middleware
 * Підключається першим у кожному адмін-файлі.
 * Перевіряє авторизацію та роль.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

function requireAdmin(): array {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/steam_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    $user = getUser();

    // Перевіряємо роль з БД (не тільки з сесії — сесія може бути застарілою)
    global $pdo;
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE steam_id = ?');
        $stmt->execute([$user['steam_id']]);
        $row = $stmt->fetch();
        if (!$row || $row['role'] !== 'admin') {
            http_response_code(403);
            die('Access denied.');
        }
        // Оновлюємо сесію якщо роль там застаріла
        if (($user['role'] ?? '') !== 'admin') {
            $_SESSION['user']['role'] = 'admin';
        }
    } else {
        // Якщо БД недоступна — блокуємо на всяк випадок
        http_response_code(503);
        die('Database unavailable.');
    }

    return $user;
}

// ── Audit Log ────────────────────────────────────────────────────────────────
function adminLog(string $action, string $target = '', array $details = []): void {
    global $pdo;
    $user = getUser();
    if (!$pdo || !$user) return;
    try {
        $pdo->prepare("
            INSERT INTO admin_log (admin_steam_id, admin_name, action, target, details, ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $user['steam_id'],
            $user['steam_name'],
            $action,
            $target,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        // Log silently fails — never break the admin action itself
    }
}

// ── CSRF helper (re-export for admin context) ─────────────────────────────────
function adminVerifyCsrf(): void {
    if (!verifyCsrfToken()) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token mismatch']));
    }
}

function adminCsrfField(): string {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
