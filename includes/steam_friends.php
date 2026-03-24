<?php
/**
 * Steam Friends + User summary helpers
 */

function getSteamFriends(string $steamId64): array {
    $url = 'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?key='
         . STEAM_API_KEY . '&steamid=' . $steamId64 . '&relationship=friend';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return [];
    $data = json_decode($json, true);
    return $data['friendslist']['friends'] ?? [];
}

function getSteamUserSummaries(array $steamIds): array {
    if (empty($steamIds)) return [];
    $chunks = array_chunk($steamIds, 100); // Steam API limit
    $all = [];
    foreach ($chunks as $chunk) {
        $ids = implode(',', $chunk);
        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='
             . STEAM_API_KEY . '&steamids=' . $ids;
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) continue;
        $data = json_decode($json, true);
        foreach (($data['response']['players'] ?? []) as $p) {
            $all[$p['steamid']] = $p;
        }
    }
    return $all;
}

function getPersonaStateName(int $state): string {
    return match($state) {
        0 => 'Офлайн', 1 => 'Онлайн', 2 => 'Зайнятий',
        3 => 'Немає', 4 => 'Сплю', 5 => 'Хочу грати',
        6 => 'Хочу торгувати', default => 'Невідомо',
    };
}

function isPersonaOnline(int $state): bool {
    return $state > 0;
}
