<?php
// CS2 Players management — PandaScore with local caching

define('PLAYERS_CACHE_DIR', __DIR__ . '/../assets/players/');
define('PLAYERS_SYNC_INTERVAL', 86400);
define('PLAYERS_CACHE_VER', 1);

function pandaGetPlayers(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'header'        => "Authorization: Bearer " . PANDASCORE_KEY . "\r\nAccept: application/json\r\n",
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function cachePlayerPhoto(int $pandaId, string $photoUrl): ?string {
    if (empty($photoUrl)) return null;
    $ext      = pathinfo(parse_url($photoUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
    $ext      = in_array(strtolower($ext), ['jpg','jpeg','png','webp']) ? $ext : 'png';
    $filename = 'player_' . $pandaId . '.' . $ext;
    $local    = PLAYERS_CACHE_DIR . $filename;
    $web      = 'assets/players/' . $filename;
    if (file_exists($local)) return $web;
    if (!is_dir(PLAYERS_CACHE_DIR)) mkdir(PLAYERS_CACHE_DIR, 0755, true);
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
    $img = @file_get_contents($photoUrl, false, $ctx);
    if (!$img) return null;
    file_put_contents($local, $img);
    return $web;
}

function syncPlayersFromPandaScore($pdo): bool {
    if (!$pdo) return false;
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM cs_players")->fetchColumn();
        $last  = $pdo->query("SELECT MAX(updated_at) FROM cs_players")->fetchColumn();
    } catch (Throwable $e) { return false; }

    $expired    = !$last || (time() - strtotime($last)) >= PLAYERS_SYNC_INTERVAL;
    $incomplete = $count < 500;
    if (!$expired && !$incomplete) return true; // incomplete = count < 100

    $page    = 1;
    $perPage = 100;
    $synced  = 0;

    $stmt = $pdo->prepare("
        INSERT INTO cs_players (pandascore_id, name, slug, real_name, nationality, team_name, photo_url, photo_local, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
          name=VALUES(name), slug=VALUES(slug), real_name=VALUES(real_name),
          nationality=VALUES(nationality), team_name=VALUES(team_name),
          photo_url=VALUES(photo_url),
          photo_local=COALESCE(VALUES(photo_local), photo_local),
          updated_at=NOW()
    ");

    while (true) {
        $url  = "https://api.pandascore.co/players"
              . "?filter[videogame]=cs-go"
              . "&per_page={$perPage}"
              . "&page={$page}";

        $players = pandaGetPlayers($url);
        if (empty($players) || !is_array($players)) break;

        foreach ($players as $p) {
            if (!is_array($p)) continue;
            $photoUrl = $p['image_url'] ?? '';
            // Don't cache photos during bulk sync — too slow
            $ct       = $p['current_team'] ?? null;
            $teamName = (is_array($ct) ? ($ct['name'] ?? null) : null);

            $stmt->execute([
                $p['id'],
                $p['name'] ?? '',
                $p['slug'] ?? '',
                trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: null,
                $p['nationality'] ?? null,
                $teamName,
                $photoUrl,
                null, // photo_local — cached on demand
            ]);
            $synced++;
        }

        if (count($players) < $perPage) break;
        $page++;
        if ($page > 50) break;
    }

    return $synced > 0;
}

function searchCsPlayers($pdo, string $query, int $limit = 30, int $offset = 0): array {
    if (!$pdo) return [];
    $q    = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT id, pandascore_id, name, slug, real_name, nationality, team_name, photo_local, photo_url
        FROM cs_players
        WHERE name LIKE ? OR real_name LIKE ?
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$q, $q, $limit, $offset]);
    return $stmt->fetchAll();
}

function getTopCsPlayers($pdo, int $limit = 30, int $offset = 0): array {
    if (!$pdo) return [];

    $topSlugs = [
        's1mple','zywoo','niko','device','electronic','broky',
        'ropz','karrigan','blameF','frozen','jks','ax1le',
        'sh1ro','xantares','hobbit','buster','nafany','chopper',
        'perfecto','degster','hunter','nexa','lekr0','twist',
    ];

    if ($offset > 0) {
        $excl    = implode(',', array_fill(0, count($topSlugs), '?'));
        $realOff = max(0, $offset - count($topSlugs));
        $stmt    = $pdo->prepare("
            SELECT id, pandascore_id, name, slug, real_name, nationality, team_name, photo_local, photo_url
            FROM cs_players WHERE slug NOT IN ({$excl})
            ORDER BY name ASC LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($topSlugs, [$limit, $realOff]));
        return $stmt->fetchAll();
    }

    $placeholders = implode(',', array_fill(0, count($topSlugs), '?'));
    $stmt = $pdo->prepare("
        SELECT id, pandascore_id, name, slug, real_name, nationality, team_name, photo_local, photo_url,
               FIELD(slug, {$placeholders}) as priority
        FROM cs_players WHERE slug IN ({$placeholders})
        ORDER BY priority ASC
    ");
    $stmt->execute(array_merge($topSlugs, $topSlugs));
    $top = $stmt->fetchAll();

    $remaining = $limit - count($top);
    if ($remaining > 0) {
        $topIds = array_column($top, 'id') ?: [0];
        $excl   = implode(',', array_map('intval', $topIds));
        $stmt2  = $pdo->prepare("
            SELECT id, pandascore_id, name, slug, real_name, nationality, team_name, photo_local, photo_url
            FROM cs_players WHERE id NOT IN ({$excl})
            ORDER BY name ASC LIMIT ?
        ");
        $stmt2->execute([$remaining]);
        $top = array_merge($top, $stmt2->fetchAll());
    }

    return $top;
}

function getCsPlayerById($pdo, int $id): ?array {
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT * FROM cs_players WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function setFavPlayer($pdo, string $steamId, int $playerId): bool {
    if (!$pdo) return false;
    // Cache photo locally when player is actually selected
    $p = getCsPlayerById($pdo, $playerId);
    if ($p && empty($p['photo_local']) && !empty($p['photo_url'])) {
        $local = cachePlayerPhoto($p['pandascore_id'], $p['photo_url']);
        if ($local) {
            $pdo->prepare("UPDATE cs_players SET photo_local=? WHERE id=?")
                ->execute([$local, $playerId]);
        }
    }
    $stmt = $pdo->prepare("UPDATE users SET fav_player_id=? WHERE steam_id=?");
    return $stmt->execute([$playerId, $steamId]);
}

function removeFavPlayer($pdo, string $steamId): bool {
    if (!$pdo) return false;
    $stmt = $pdo->prepare("UPDATE users SET fav_player_id=NULL WHERE steam_id=?");
    return $stmt->execute([$steamId]);
}

function buildPlayerLogoUrl($p): ?string {
    if (!empty($p['photo_local']) && file_exists(__DIR__ . '/../' . $p['photo_local'])) {
        return SITE_URL . '/' . $p['photo_local'];
    }
    return !empty($p['photo_url']) ? $p['photo_url'] : null;
}
