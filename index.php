<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/app/bootstrap.php';

if (isset($_GET['leave'])) {
    unset($_SESSION['walkie_guest']);
    header('Location: index.php');
    exit;
}

$guest = $_SESSION['walkie_guest'] ?? null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $channel = trim((string)($_POST['channel'] ?? ''));
        $nickname = preg_replace('/[^\pL\pN _.-]/u', '', $nickname) ?? '';
        $nickname = function_exists('mb_substr') ? mb_substr(trim($nickname), 0, 24) : substr(trim($nickname), 0, 24);
        $channel = strtolower($channel);
        $channel = preg_replace('/[^a-z0-9 _.-]/', '', $channel) ?? '';
        $channel = preg_replace('/\s+/', '-', trim($channel)) ?? '';
        $channel = trim(substr($channel, 0, 64), '-._');
        if ($nickname === '' || $channel === '') {
            $error = 'Please enter a nickname and a valid channel name.';
        } else {
            session_regenerate_id(true);
            $_SESSION['walkie_guest'] = [
                'id' => bin2hex(random_bytes(8)),
                'nickname' => $nickname,
                'channel' => $channel,
                'csrf' => bin2hex(random_bytes(24)),
                'joined_at' => time()
            ];
            header('Location: index.php?channel=1');
            exit;
        }
    }
}
if (isset($_GET['channel']) && $guest) {
    require __DIR__ . '/views/channel.php';
    exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
require __DIR__ . '/views/join.php';
