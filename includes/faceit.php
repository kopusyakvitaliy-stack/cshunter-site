<?php
// FACEIT_API_KEY defined in config.php

function faceitGet(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'header'        => "Authorization: Bearer " . FACEIT_API_KEY . "\r\nAccept: application/json\r\n",
        'timeout'       => 3,
        'ignore_errors' => true,
    ]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    return json_decode($json, true) ?: null;
}

function getFaceitBySteamId(string $steamId64): ?array {
    $data = faceitGet('https://open.faceit.com/data/v4/players?game=cs2&game_player_id=' . $steamId64);
    if (empty($data['player_id'])) return null;

    $cs2      = $data['games']['cs2'] ?? $data['games']['csgo'] ?? null;
    $playerId = $data['player_id'];

    // CS2 lifetime stats
    $stats    = faceitGet("https://open.faceit.com/data/v4/players/{$playerId}/stats/cs2");
    $lifetime = $stats['lifetime'] ?? [];

    $kd      = isset($lifetime['Average K/D Ratio'])   ? round((float)$lifetime['Average K/D Ratio'], 2)   : null;
    $hs      = isset($lifetime['Average Headshots %']) ? round((float)$lifetime['Average Headshots %'])     : null;
    $matches = isset($lifetime['Matches'])             ? (int)$lifetime['Matches']                         : null;
    $wins    = isset($lifetime['Wins'])                ? (int)$lifetime['Wins']                            : null;
    $winrate = ($matches && $wins)                     ? round($wins / $matches * 100)                     : null;

    // Peak ELO + last 5 match results from history
    $currentElo = (int)($cs2['faceit_elo'] ?? 0);
    $peakElo    = null;
    $last5      = [];
    $history    = faceitGet("https://open.faceit.com/data/v4/players/{$playerId}/history?game=cs2&limit=100");
    if (!empty($history['items'])) {
        $maxElo = $currentElo;
        foreach ($history['items'] as $i => $match) {
            $elo = (int)($match['elo'] ?? 0);
            if ($elo > $maxElo) $maxElo = $elo;
            // last 10 results
            if ($i < 10) {
                $teams      = $match['results']['score'] ?? [];
                $winner     = $match['results']['winner'] ?? '';
                $playerTeam = null;
                if (!empty($match['teams'])) {
                    foreach ($match['teams'] as $tKey => $team) {
                        $players = $team['players'] ?? [];
                        foreach ($players as $p) {
                            if (($p['player_id'] ?? '') === $playerId) {
                                $playerTeam = $tKey;
                                break 2;
                            }
                        }
                    }
                }
                $last5[] = ($playerTeam && $playerTeam === $winner) ? 'W' : 'L';
            }
        }
        if ($maxElo > $currentElo) $peakElo = $maxElo;
    }

    return [
        'faceit_id'   => $playerId,
        'nickname'    => $data['nickname'] ?? '',
        'avatar'      => $data['avatar'] ?? '',
        'level'       => (int)($cs2['skill_level'] ?? 0),
        'elo'         => $currentElo,
        'region'      => $cs2['region'] ?? '',
        'profile_url' => str_replace('{lang}', 'en', $data['faceit_url'] ?? ''),
        'kd'          => $kd,
        'hs'          => $hs,
        'matches'     => $matches,
        'wins'        => $wins,
        'winrate'     => $winrate,
        'peak_elo'    => $peakElo,
        'last5'       => $last5,
    ];
}

// ── Save FACEIT data to DB (called when profile is viewed) ──────────────────
function syncFaceitToDb($pdo, string $steamId, array $faceit): void {
    if (!$pdo || empty($faceit['level'])) return;
    try {
        $pdo->prepare("
            UPDATE users
            SET faceit_level      = ?,
                faceit_elo        = ?,
                faceit_nickname   = ?,
                faceit_avatar     = ?,
                faceit_updated_at = NOW()
            WHERE steam_id = ?
        ")->execute([
            (int)$faceit['level'],
            (int)($faceit['elo'] ?? 0),
            $faceit['nickname'] ?? '',
            $faceit['avatar']   ?? '',
            $steamId,
        ]);
    } catch (Throwable $e) {}
}

// ── Get FACEIT from DB (fast, no API call) ────────────────────────────────────
function getFaceitFromDb($pdo, string $steamId): ?array {
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT faceit_level, faceit_elo, faceit_updated_at
            FROM users WHERE steam_id = ?
        ");
        $stmt->execute([$steamId]);
        $row = $stmt->fetch();
        if (!$row || !$row['faceit_level']) return null;
        // Return null if older than 2 hours — needs refresh
        if ($row['faceit_updated_at'] && (time() - strtotime($row['faceit_updated_at'])) > 7200) {
            return null; // stale
        }
        return $row;
    } catch (Throwable $e) { return null; }
}

function getFaceitLevelColor(int $level): string {
    if ($level >= 10) return '#FF0000';
    if ($level >= 9)  return '#FF3D00';
    if ($level >= 7)  return '#FF6D00';
    if ($level >= 5)  return '#FF9100';
    if ($level >= 3)  return '#FFD600';
    return '#8BC34A';
}
