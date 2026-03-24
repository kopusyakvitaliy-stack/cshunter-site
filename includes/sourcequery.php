<?php
/**
 * CS2 A2S_INFO + A2S_PLAYER queries — handles mandatory challenge (CS2 requires it)
 */
class SourceQuery
{
    private float $timeout;

    public function __construct(float $timeout = 2.0) {
        $this->timeout = $timeout;
    }

    public function query(string $ip, int $port): ?array
    {
        $sock = @fsockopen("udp://$ip", $port, $errno, $errstr, $this->timeout);
        if (!$sock) return null;

        $usec = (int)(($this->timeout - floor($this->timeout)) * 1_000_000);
        stream_set_timeout($sock, (int)$this->timeout, $usec);
        stream_set_blocking($sock, true);

        try {
            $req = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
            fwrite($sock, $req);
            $resp = fread($sock, 1400);
            if (!$resp || strlen($resp) < 5) { fclose($sock); return null; }

            if (ord($resp[4]) === 0x41) {
                $challenge = substr($resp, 5, 4);
                $req2 = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00" . $challenge;
                fwrite($sock, $req2);
                $resp = fread($sock, 1400);
                if (!$resp || strlen($resp) < 5) { fclose($sock); return null; }
            }

            fclose($sock);

            if (ord($resp[4]) !== 0x49) return null;

            $pos = 5;
            $pos++;
            $name    = $this->readStr($resp, $pos);
            $map     = $this->readStr($resp, $pos);
            $folder  = $this->readStr($resp, $pos);
            $game    = $this->readStr($resp, $pos);

            if (strlen($resp) < $pos + 4) return null;
            $pos += 2;
            $players    = ord($resp[$pos++]);
            $maxPlayers = ord($resp[$pos++]);
            $bots       = ord($resp[$pos++]);

            return [
                'online'      => true,
                'name'        => $name,
                'map'         => $map,
                'players'     => $players,
                'max_players' => $maxPlayers,
                'bots'        => $bots,
            ];
        } catch (\Throwable $e) {
            @fclose($sock);
            return null;
        }
    }

    public function queryPlayers(string $ip, int $port): ?array
    {
        $sock = @fsockopen("udp://$ip", $port, $errno, $errstr, $this->timeout);
        if (!$sock) return null;

        $usec = (int)(($this->timeout - floor($this->timeout)) * 1_000_000);
        stream_set_timeout($sock, (int)$this->timeout, $usec);
        stream_set_blocking($sock, true);

        try {
            fwrite($sock, "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
            $resp = fread($sock, 512);
            if (!$resp || strlen($resp) < 9) { fclose($sock); return null; }

            if (ord($resp[4]) === 0x41) {
                $challenge = substr($resp, 5, 4);
                fwrite($sock, "\xFF\xFF\xFF\xFF\x55" . $challenge);
                $resp = fread($sock, 4096);
                if (!$resp || strlen($resp) < 6) { fclose($sock); return null; }
            }

            fclose($sock);

            if (ord($resp[4]) !== 0x44) return null;

            $count   = ord($resp[5]);
            $pos     = 6;
            $players = [];

            for ($i = 0; $i < $count; $i++) {
                if ($pos >= strlen($resp)) break;
                $pos++;
                $name = $this->readStr($resp, $pos);
                if ($pos + 8 > strlen($resp)) break;
                $score    = unpack('l', substr($resp, $pos, 4))[1]; $pos += 4;
                $duration = unpack('f', substr($resp, $pos, 4))[1]; $pos += 4;
                $players[] = [
                    'name'     => $name,
                    'score'    => $score,
                    'duration' => round($duration),
                ];
            }

            return $players;
        } catch (\Throwable $e) {
            @fclose($sock);
            return null;
        }
    }

    private function readStr(string $buf, int &$pos): string
    {
        $end = strpos($buf, "\x00", $pos);
        if ($end === false) { $pos = strlen($buf); return ''; }
        $s   = substr($buf, $pos, $end - $pos);
        $pos = $end + 1;
        return $s;
    }
}
