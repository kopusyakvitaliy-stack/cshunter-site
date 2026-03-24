<?php
// Teams management — PandaScore CS2 teams with local caching
// PANDASCORE_KEY defined in config.php

define('TEAMS_CACHE_DIR', __DIR__ . '/../assets/teams/');
define('TEAMS_SYNC_INTERVAL', 86400); // 24h
define('TEAMS_FORCE_SYNC', false); // set true to force re-sync once

// ── Fetch from PandaScore ────────────────────────────────────────────────────
function pandaGet(string $url): ?array {
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

// ── Download and cache logo locally ─────────────────────────────────────────
function cacheTeamLogo(int $pandaId, string $logoUrl): ?string {
    if (empty($logoUrl)) return null;

    $ext      = pathinfo(parse_url($logoUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
    $filename = 'team_' . $pandaId . '.' . $ext;
    $localPath = TEAMS_CACHE_DIR . $filename;
    $webPath   = 'assets/teams/' . $filename;

    // Already cached
    if (file_exists($localPath)) return $webPath;

    // Download
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
    $img = @file_get_contents($logoUrl, false, $ctx);
    if (!$img) return null;

    if (!is_dir(TEAMS_CACHE_DIR)) mkdir(TEAMS_CACHE_DIR, 0755, true);
    file_put_contents($localPath, $img);

    return $webPath;
}

// ── Sync top CS2 teams from PandaScore into local DB ────────────────────────
function syncTeamsFromPandaScore($pdo): bool {
    if (!$pdo) return false;

    // Check last sync
    try {
        $last = $pdo->query("SELECT MAX(updated_at) as t FROM teams")->fetchColumn();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    } catch (Throwable $e) { return false; }
    // Re-sync if: forced, empty, or expired AND less than 50 teams (incomplete sync)
    $expired = !$last || (time() - strtotime($last)) >= TEAMS_SYNC_INTERVAL;
    $incomplete = $count < 50;
    if (!$expired && !$incomplete && !TEAMS_FORCE_SYNC) return true;

    $page    = 1;
    $perPage = 50;
    $synced  = 0;

    while (true) {
        $url  = "https://api.pandascore.co/csgo/teams"
              . "?sort=-modified_at"
              . "&per_page={$perPage}"
              . "&page={$page}";

        $teams = pandaGet($url);
        if (empty($teams) || !is_array($teams) || count($teams) === 0) break;

        $stmt = $pdo->prepare("
            INSERT INTO teams (pandascore_id, name, slug, logo_url, logo_local, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
              name=VALUES(name),
              slug=VALUES(slug),
              logo_url=VALUES(logo_url),
              logo_local=COALESCE(VALUES(logo_local), logo_local),
              updated_at=NOW()
        ");

        foreach ($teams as $t) {
            $logoUrl = $t['image_url'] ?? '';
            // Don't cache logos during bulk sync — cache on demand when team selected
            $stmt->execute([
                $t['id'],
                $t['name'],
                $t['slug'] ?? '',
                $logoUrl,
                null, // logo_local — cached on selection
            ]);
            $synced++;
        }

        // If less than full page — we're done
        if (count($teams) < $perPage) break;
        $page++;

        // Safety: max 20 pages (1000 teams) per sync
        if ($page > 20) break;
    }

    return $synced > 0;
}

// ── Search teams in local DB ─────────────────────────────────────────────────
function searchTeams($pdo, string $query, int $limit = 30, int $offset = 0): array {
    if (!$pdo) return [];
    $q = '%' . $query . '%';
    $stmt = $pdo->prepare("
        SELECT id, pandascore_id, name, slug, logo_local, logo_url
        FROM teams
        WHERE name LIKE ?
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$q, $limit, $offset]);
    return $stmt->fetchAll();
}

// ── Get popular/top teams (for initial display) ──────────────────────────────
function getTopTeams($pdo, int $limit = 30, int $offset = 0): array {
    if (!$pdo) return [];

    // Top teams — shown first in priority order
    $topSlugs = [
        'vitality-cs-go', 'furia', 'mousesports-cs-go', 'falcons-esports',
        'parivision', 'natus-vincere-cs-go', 'aurora-gaming', 'spirit',
        'themongolz', 'astralis', 'faze', 'fut-esports-cs-go',
        'g2', '3dmax-82114b24-7e32-42ab-a8ac-b46c77bbd3d2', 'legacy-48cb0f61-07e7-4b0d-85a0-5f837e29c807', 'gamerlegion',
        'pain-gaming-cs-go', 'heroic', 'b8-cs-go', 'liquid-cs-go',
        'gentle-mates-cs-go', 'nip', 'monte', 'nrg',
        'hotu', 'betboom-team-cs-go', 'passion-ua', 'mibr',
        '9z', 'bc-game-esports',
    ];

    $placeholders = implode(',', array_fill(0, count($topSlugs), '?'));

    // First: top teams in priority order
    $stmt = $pdo->prepare("
        SELECT id, pandascore_id, name, slug, logo_local, logo_url,
               FIELD(slug, {$placeholders}) as priority
        FROM teams
        WHERE slug IN ({$placeholders})
        ORDER BY priority ASC
    ");
    $stmt->execute(array_merge($topSlugs, $topSlugs));
    $top = $stmt->fetchAll();

    // If offset > 0, skip the pinned top teams and go straight to alphabetical
    if ($offset > 0) {
        $topIds  = array_column($top, 'id') ?: [0];
        $excl    = implode(',', array_map('intval', $topIds));
        $realOff = max(0, $offset - count($topSlugs));
        $stmt2   = $pdo->prepare("
            SELECT id, pandascore_id, name, slug, logo_local, logo_url
            FROM teams
            WHERE id NOT IN ({$excl})
            ORDER BY name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt2->execute([$limit, $realOff]);
        return $stmt2->fetchAll();
    }

    // Fill remaining slots with other teams alphabetically
    $remaining = $limit - count($top);
    if ($remaining > 0) {
        $topIds = array_column($top, 'id') ?: [0];
        $excl   = implode(',', array_map('intval', $topIds));
        $stmt2  = $pdo->prepare("
            SELECT id, pandascore_id, name, slug, logo_local, logo_url
            FROM teams
            WHERE id NOT IN ({$excl})
            ORDER BY name ASC
            LIMIT ?
        ");
        $stmt2->execute([$remaining]);
        $rest = $stmt2->fetchAll();
        $top  = array_merge($top, $rest);
    }

    return $top;
}

// ── Get team by ID ────────────────────────────────────────────────────────────
function getTeamById($pdo, int $id): ?array {
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// ── Set user's favourite team ────────────────────────────────────────────────
function setFavTeam($pdo, string $steamId, int $teamId): bool {
    if (!$pdo) return false;
    // Cache logo locally when team is actually selected
    $t = getTeamById($pdo, $teamId);
    if ($t && empty($t['logo_local']) && !empty($t['logo_url'])) {
        $local = cacheTeamLogo($t['pandascore_id'], $t['logo_url']);
        if ($local) {
            $pdo->prepare("UPDATE teams SET logo_local=? WHERE id=?")->execute([$local, $teamId]);
        }
    }
    $stmt = $pdo->prepare("UPDATE users SET fav_team_id=? WHERE steam_id=?");
    return $stmt->execute([$teamId, $steamId]);
}

// ── Remove user's favourite team ─────────────────────────────────────────────
function removeFavTeam($pdo, string $steamId): bool {
    if (!$pdo) return false;
    $stmt = $pdo->prepare("UPDATE users SET fav_team_id=NULL WHERE steam_id=?");
    return $stmt->execute([$steamId]);
}