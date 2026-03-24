<?php
/**
 * Simple CS2 RCON client
 */
class CS2Rcon {
  private $socket = null;
  private $host;
  private $port;
  private $password;

  const SERVERDATA_AUTH        = 3;
  const SERVERDATA_EXECCOMMAND = 2;
  const SERVERDATA_AUTH_RESPONSE = 2;

  public function __construct($host, $port, $password) {
    $this->host     = $host;
    $this->port     = (int)$port;
    $this->password = $password;
  }

  public function connect(): bool {
    $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 3);
    if (!$this->socket) return false;
    stream_set_timeout($this->socket, 3);
    return $this->auth();
  }

  private function auth(): bool {
    $this->sendPacket(self::SERVERDATA_AUTH, 1, $this->password);
    $resp = $this->readPacket();
    return $resp && $resp['id'] !== -1;
  }

  public function command(string $cmd): string {
    $this->sendPacket(self::SERVERDATA_EXECCOMMAND, 2, $cmd);
    $resp = $this->readPacket();
    return $resp ? $resp['body'] : '';
  }

  private function sendPacket(int $type, int $id, string $body): void {
    $body   .= "\x00\x00";
    $size    = strlen($body) + 8;
    $packet  = pack('VVV', $size, $id, $type) . $body;
    fwrite($this->socket, $packet);
  }

  private function readPacket(): ?array {
    $header = fread($this->socket, 4);
    if (!$header || strlen($header) < 4) return null;
    $size   = unpack('V', $header)[1];
    $data   = fread($this->socket, $size);
    if (!$data || strlen($data) < 8) return null;
    $id   = unpack('V', substr($data, 0, 4))[1];
    $type = unpack('V', substr($data, 4, 4))[1];
    $body = substr($data, 8, -2);
    return ['id' => $id, 'type' => $type, 'body' => $body];
  }

  public function disconnect(): void {
    if ($this->socket) { fclose($this->socket); $this->socket = null; }
  }
}
