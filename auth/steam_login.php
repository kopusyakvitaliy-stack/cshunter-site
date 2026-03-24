<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$url = getSteamLoginUrl();
header('Location: ' . $url);
exit;
