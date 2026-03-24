<?php
/**
 * CSHunter — ItemService v2
 * Централізована логіка системи предметів.
 */

class ItemService
{
    private static array $rarities = [
        'consumer'   => ['label' => 'Поширений',      'color' => '#b0b0b0', 'glow' => 'rgba(176,176,176,0.5)'],
        'industrial' => ['label' => 'Звичайний',      'color' => '#5e98d9', 'glow' => 'rgba(94,152,217,0.5)'],
        'milspec'    => ['label' => 'Рідкісний',      'color' => '#4b69ff', 'glow' => 'rgba(75,105,255,0.5)'],
        'restricted' => ['label' => 'Дуже рідкісний', 'color' => '#8847ff', 'glow' => 'rgba(136,71,255,0.5)'],
        'classified' => ['label' => 'Епічний',        'color' => '#d32ce6', 'glow' => 'rgba(211,44,230,0.5)'],
        'covert'     => ['label' => 'Легендарний',    'color' => '#eb4b4b', 'glow' => 'rgba(235,75,75,0.5)'],
        'unique'     => ['label' => 'Унікальний',     'color' => '#f0c040', 'glow' => 'rgba(240,192,64,0.5)'],
    ];

    public static function getRarityColor(string $rarity): string { return self::$rarities[$rarity]['color'] ?? '#b0b0b0'; }
    public static function getRarityGlow(string $rarity): string  { return self::$rarities[$rarity]['glow']  ?? 'rgba(176,176,176,0.5)'; }
    public static function getRarityLabel(string $rarity): string { return self::$rarities[$rarity]['label'] ?? ucfirst($rarity); }
    public static function getAllRarities(): array { return self::$rarities; }

    public static function getHowToObtain(array $item): string
    {
        if (!empty($item['description'])) return $item['description'];
        $type  = $item['condition_type']  ?? 'manual';
        $value = (int)($item['condition_value'] ?? 0);
        return match($type) {
            'registration_days' => $value > 0 ? "Отримується за {$value} " . self::pluralDays($value) . " на сайті" : "Видається при реєстрації на сайті",
            'playtime_hours'    => "Отримується за {$value} " . self::pluralHours($value) . " на серверах",
            'kills'             => "Отримується за " . number_format($value) . " вбивств на серверах",
            'faceit_level'      => "Отримується за FACEIT рівень {$value}+",
            'manual'            => "Унікальний предмет, виданий адміністратором",
            default             => "Спеціальний предмет",
        };
    }

    private static function pluralDays(int $n): string
    {
        if ($n % 10 === 1 && $n % 100 !== 11) return 'день';
        if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) return 'дні';
        return 'днів';
    }
    private static function pluralHours(int $n): string
    {
        if ($n % 10 === 1 && $n % 100 !== 11) return 'годину';
        if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) return 'години';
        return 'годин';
    }

    public static function getUserInventory(PDO $pdo, int $userId): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT i.*, ui.obtained_at, ui.obtained_by,
                       (ue.item_id IS NOT NULL) AS is_equipped,
                       ic.condition_type, ic.condition_value
                FROM user_items ui
                JOIN items i ON i.id = ui.item_id
                LEFT JOIN user_equipped ue ON ue.user_id = ui.user_id AND ue.item_id = i.id
                LEFT JOIN item_conditions ic ON ic.item_id = i.id
                WHERE ui.user_id = ?
                ORDER BY FIELD(i.rarity,'consumer','industrial','milspec','restricted','classified','covert','unique'), i.sort_order
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return []; }
    }

    public static function getAllVisibleItems(PDO $pdo, ?string $type = null): array
    {
        try {
            if ($type) {
                $stmt = $pdo->prepare("
                    SELECT i.*, ic.condition_type, ic.condition_value
                    FROM items i LEFT JOIN item_conditions ic ON ic.item_id = i.id
                    WHERE i.hidden = 0 AND i.type = ?
                    ORDER BY FIELD(i.rarity,'consumer','industrial','milspec','restricted','classified','covert','unique'), i.sort_order
                ");
                $stmt->execute([$type]);
            } else {
                $stmt = $pdo->query("
                    SELECT i.*, ic.condition_type, ic.condition_value
                    FROM items i LEFT JOIN item_conditions ic ON ic.item_id = i.id
                    WHERE i.hidden = 0
                    ORDER BY FIELD(i.rarity,'consumer','industrial','milspec','restricted','classified','covert','unique'), i.sort_order
                ");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return []; }
    }

    public static function getUserEquipped(PDO $pdo, int $userId): array
    {
        if (!$userId) return [];
        try {
            $stmt = $pdo->prepare("SELECT i.*, ue.item_type FROM user_equipped ue JOIN items i ON i.id = ue.item_id WHERE ue.user_id = ?");
            $stmt->execute([$userId]);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $result[$row['item_type']] = $row; }
            return $result;
        } catch (Throwable) { return []; }
    }

    public static function getEquippedFrame(PDO $pdo, int $userId): ?array
    {
        if (!$userId) return null;
        $equipped = self::getUserEquipped($pdo, $userId);
        return $equipped['frame'] ?? null;
    }

    public static function getEquippedBackground(PDO $pdo, int $userId): ?array
    {
        if (!$userId) return null;
        $equipped = self::getUserEquipped($pdo, $userId);
        return $equipped['background'] ?? null;
    }

    public static function getEquippedFramesBatch(PDO $pdo, array $userIds): array
    {
        if (empty($userIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $pdo->prepare("SELECT ue.user_id, i.* FROM user_equipped ue JOIN items i ON i.id = ue.item_id WHERE ue.user_id IN ($placeholders) AND ue.item_type = 'frame'");
            $stmt->execute($userIds);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $result[(int)$row['user_id']] = $row; }
            return $result;
        } catch (Throwable) { return []; }
    }

    public static function getAllItems(PDO $pdo, ?string $type = null): array
    {
        try {
            if ($type) {
                $stmt = $pdo->prepare("SELECT i.*, ic.condition_type, ic.condition_value FROM items i LEFT JOIN item_conditions ic ON ic.item_id = i.id WHERE i.type = ? ORDER BY i.type, i.sort_order");
                $stmt->execute([$type]);
            } else {
                $stmt = $pdo->query("SELECT i.*, ic.condition_type, ic.condition_value FROM items i LEFT JOIN item_conditions ic ON ic.item_id = i.id ORDER BY i.type, i.sort_order");
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return []; }
    }

    public static function getUserItemCount(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) { return 0; }
    }

    public static function equipItem(PDO $pdo, int $userId, int $itemId): true|string
    {
        try {
            $check = $pdo->prepare("SELECT ui.id, i.type FROM user_items ui JOIN items i ON i.id = ui.item_id WHERE ui.user_id = ? AND ui.item_id = ?");
            $check->execute([$userId, $itemId]);
            $row = $check->fetch();
            if (!$row) return 'not_owned';
            $pdo->prepare("INSERT INTO user_equipped (user_id, item_type, item_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE item_id = VALUES(item_id), equipped_at = NOW()")
                ->execute([$userId, $row['type'], $itemId]);
            return true;
        } catch (Throwable $e) {
            error_log('[ItemService] equipItem error: ' . $e->getMessage());
            return 'db_error';
        }
    }

    public static function unequipItem(PDO $pdo, int $userId, string $itemType): bool
    {
        try {
            $pdo->prepare("DELETE FROM user_equipped WHERE user_id = ? AND item_type = ?")->execute([$userId, $itemType]);
            return true;
        } catch (Throwable) { return false; }
    }

    public static function grantItem(PDO $pdo, int $userId, int $itemId, string $obtainedBy = 'auto'): bool
    {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_items (user_id, item_id, obtained_by) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $itemId, $obtainedBy]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[ItemService] grantItem error: ' . $e->getMessage());
            return false;
        }
    }

    public static function revokeItem(PDO $pdo, int $userId, int $itemId): bool
    {
        try {
            $pdo->prepare("DELETE ue FROM user_equipped ue JOIN items i ON i.id = ue.item_id WHERE ue.user_id = ? AND ue.item_id = ?")->execute([$userId, $itemId]);
            $pdo->prepare("DELETE FROM user_items WHERE user_id = ? AND item_id = ?")->execute([$userId, $itemId]);
            return true;
        } catch (Throwable) { return false; }
    }

    public static function updateItem(PDO $pdo, int $itemId, array $data): bool
    {
        try {
            $pdo->prepare("
                UPDATE items SET name=?, rarity=?, image_lg=?, description=?, avatar_shape=?, animated=?, hidden=?, sort_order=?
                WHERE id=?
            ")->execute([$data['name'], $data['rarity'], $data['image_lg'], $data['description'], $data['avatar_shape'], $data['animated'], $data['hidden'], $data['sort_order'], $itemId]);

            $condType  = $data['condition_type']  ?? 'manual';
            $condValue = (int)($data['condition_value'] ?? 0);
            $pdo->prepare("DELETE FROM item_conditions WHERE item_id = ?")->execute([$itemId]);
            if ($condType !== 'manual' || $condValue > 0) {
                $pdo->prepare("INSERT INTO item_conditions (item_id, condition_type, condition_value) VALUES (?,?,?)")
                    ->execute([$itemId, $condType, $condValue]);
            }
            return true;
        } catch (Throwable $e) {
            error_log('[ItemService] updateItem error: ' . $e->getMessage());
            return false;
        }
    }

    public static function checkAndGrantAutoItems(PDO $pdo, int $userId, array $userRow): void
    {
        try {
            $sessionKey = 'items_checked_' . $userId;
            if (!empty($_SESSION[$sessionKey]) && time() - $_SESSION[$sessionKey] < 86400) return;
            $_SESSION[$sessionKey] = time();

            $stmt = $pdo->prepare("
                SELECT i.id, i.slug, ic.condition_type, ic.condition_value
                FROM items i JOIN item_conditions ic ON ic.item_id = i.id
                WHERE ic.condition_type != 'manual' AND i.hidden = 0
                  AND i.id NOT IN (SELECT item_id FROM user_items WHERE user_id = ?)
            ");
            $stmt->execute([$userId]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($candidates)) return;

            $regDays = $totalKills = $totalHours = null;

            foreach ($candidates as $candidate) {
                $grant = false;
                switch ($candidate['condition_type']) {
                    case 'registration_days':
                        if ($regDays === null) {
                            $regDays = !empty($userRow['created_at']) ? (int)floor((time() - strtotime($userRow['created_at'])) / 86400) : 0;
                        }
                        $grant = $regDays >= (int)$candidate['condition_value'];
                        break;
                    case 'playtime_hours':
                        if ($totalHours === null) {
                            $s = $pdo->prepare("SELECT COALESCE(SUM(playtime_hours),0) FROM stats WHERE user_id=?");
                            $s->execute([$userId]); $totalHours = (int)$s->fetchColumn();
                        }
                        $grant = $totalHours >= (int)$candidate['condition_value'];
                        break;
                    case 'kills':
                        if ($totalKills === null) {
                            $s = $pdo->prepare("SELECT COALESCE(SUM(kills),0) FROM stats WHERE user_id=?");
                            $s->execute([$userId]); $totalKills = (int)$s->fetchColumn();
                        }
                        $grant = $totalKills >= (int)$candidate['condition_value'];
                        break;
                    // faceit_level: реалізується пізніше
                }
                if ($grant) self::grantItem($pdo, $userId, (int)$candidate['id'], 'auto');
            }
        } catch (Throwable $e) {
            error_log('[ItemService] checkAndGrantAutoItems error: ' . $e->getMessage());
        }
    }

    public static function getPlaceholderBorderStyle(string $rarity): string
    {
        $color = self::getRarityColor($rarity);
        return "border: 2px solid {$color}; box-shadow: 0 0 8px " . self::getRarityGlow($rarity) . ", inset 0 0 8px " . self::getRarityGlow($rarity) . ";";
    }

    /**
     * Генерує _sm версію рамки (crop по центру до 450x450) через GD.
     * Викликати після збереження image_lg для type=frame.
     * @param string $baseDir  абсолютний шлях до кореня сайту
     * @param string $imageLg  відносний шлях, напр. assets/items/frames/foo_lg.png
     * @return string|null     відносний шлях до _sm файлу або null при помилці
     */
    public static function generateFrameSm(string $baseDir, string $imageLg): ?string
    {
        if (empty($imageLg)) return null;

        $srcPath = rtrim($baseDir, '/') . '/' . ltrim($imageLg, '/');
        if (!file_exists($srcPath)) return null;

        // Визначаємо _sm шлях: foo_lg.png → foo_sm.png, або foo.png → foo_sm.png
        $dir      = dirname($srcPath);
        $base     = basename($srcPath);
        $ext      = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        $nameOnly = pathinfo($base, PATHINFO_FILENAME);
        // Замінюємо _lg суфікс або просто додаємо _sm
        $smName   = preg_replace('/_lg$/', '', $nameOnly) . '_sm.' . $ext;
        $dstPath  = $dir . '/' . $smName;
        $dstRel   = 'assets/items/frames/' . $smName;

        // Підтримувані формати
        $supported = ['png','jpg','jpeg','webp'];
        if (!in_array($ext, $supported)) return null;

        // Перевіряємо GD
        if (!function_exists('imagecreatefrompng')) return null;

        try {
            $src = match($ext) {
                'png'  => imagecreatefrompng($srcPath),
                'jpg', 'jpeg' => imagecreatefromjpeg($srcPath),
                'webp' => imagecreatefromwebp($srcPath),
                default => null,
            };
            if (!$src) return null;

            $sw = imagesx($src);
            $sh = imagesy($src);
            $targetSize = 450;

            // Crop центру: беремо центральні min(sw,sh) пікселів, потім scale до 450
            $cropSize = min($sw, $sh);
            $cx = (int)(($sw - $cropSize) / 2);
            $cy = (int)(($sh - $cropSize) / 2);

            $dst = imagecreatetruecolor($targetSize, $targetSize);
            // Зберігаємо прозорість для PNG
            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $targetSize, $targetSize, $transparent);
                imagealphablending($dst, true);
            }

            imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $targetSize, $targetSize, $cropSize, $cropSize);

            $ok = match($ext) {
                'png'  => imagepng($dst, $dstPath, 9),
                'jpg', 'jpeg' => imagejpeg($dst, $dstPath, 90),
                'webp' => imagewebp($dst, $dstPath, 90),
                default => false,
            };

            imagedestroy($src);
            imagedestroy($dst);

            return $ok ? $dstRel : null;
        } catch (Throwable $e) {
            error_log('[ItemService] generateFrameSm error: ' . $e->getMessage());
            return null;
        }
    }

    public static function getAvailableImages(string $baseDir): array
    {
        $result = [];
        $itemsDir = rtrim($baseDir, '/') . '/assets/items';
        if (!is_dir($itemsDir)) return $result;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($itemsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['png','jpg','jpeg','webp','gif','apng'])) continue;
            $rel = 'assets/items/' . ltrim(str_replace($itemsDir, '', $file->getPathname()), '/\\');
            $result[] = str_replace('\\', '/', $rel);
        }
        sort($result);
        return $result;
    }
}