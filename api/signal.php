<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Cache-Control: no-store');

$guest = $_SESSION['walkie_guest'] ?? null;
if (!$guest || !is_array($guest)) json_response(['ok'=>false,'error'=>'Join a channel first.'], 401);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') json_response(['ok'=>false,'error'=>'POST required.'], 405);
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > walkie_config()['max_body']) json_response(['ok'=>false,'error'=>'Request too large.'], 413);
$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) json_response(['ok'=>false,'error'=>'Invalid JSON.'], 400);
if (!hash_equals($guest['csrf'], (string)($input['csrf'] ?? ''))) json_response(['ok'=>false,'error'=>'Invalid session token.'], 403);
$channel = $guest['channel'];
$id = $guest['id'];
$nickname = $guest['nickname'];
$type = (string)($input['type'] ?? '');
$now = time();
try {
    $result = with_room_lock($channel, function (&$room) use ($type, $input, $id, $nickname, $guest, $now) {
        // Expire inactive peers and stale speaker locks.
        foreach ($room['peers'] as $pid => $peer) {
            if (($peer['seen'] ?? 0) < $now - 18) {
                unset($room['peers'][$pid]);
                $room['events'][] = ['seq'=>++$room['seq'],'type'=>'peer_left','id'=>$pid];
                if (($room['speaker']['id'] ?? null) === $pid) $room['speaker'] = null;
            }
        }
        if (($room['speaker']['until'] ?? 0) < $now) $room['speaker'] = null;
        $peer = ['id'=>$id,'nickname'=>$nickname,'seen'=>$now,'speaking'=>false];
        switch ($type) {
            case 'hello':
                if (!isset($room['peers'][$id]) && count($room['peers']) >= walkie_config()['max_peers']) {
                    return ['ok'=>false,'error'=>'This channel is full.','max'=>walkie_config()['max_peers'],'status'=>409];
                }
                $room['peers'][$id] = $peer;
                $room['events'][] = ['seq'=>++$room['seq'],'type'=>'peer_joined','id'=>$id,'nickname'=>$nickname];
                return ['ok'=>true,'peers'=>array_values($room['peers']),'speaker'=>$room['speaker'],'seq'=>$room['seq'],'token'=>signed_token($guest),'floor_timeout_ms'=>walkie_config()['floor_timeout_ms']];
            case 'poll':
                if (!isset($room['peers'][$id])) $room['peers'][$id] = $peer;
                else $room['peers'][$id]['seen'] = $now;
                $since = max(0, (int)($input['since'] ?? 0));
                $events = array_values(array_filter($room['events'], fn($event) => ($event['seq'] ?? 0) > $since && (!isset($event['to']) || $event['to'] === $id)));
                if (count($room['events']) > 300) $room['events'] = array_slice($room['events'], -200);
                return ['ok'=>true,'peers'=>array_values($room['peers']),'speaker'=>$room['speaker'],'events'=>$events,'seq'=>$room['seq']];
            case 'rtc_signal':
                $to = (string)($input['to'] ?? '');
                $data = $input['data'] ?? null;
                if ($to === '' || $to === $id || !isset($room['peers'][$to]) || !is_array($data)) {
                    return ['ok'=>false,'error'=>'Invalid WebRTC signaling recipient or payload.','status'=>400];
                }
                $encodedData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($encodedData) || strlen($encodedData) > walkie_config()['max_data']) {
                    return ['ok'=>false,'error'=>'WebRTC signaling payload is too large.','status'=>413];
                }
                $room['events'][] = ['seq'=>++$room['seq'],'type'=>'rtc_signal','from'=>$id,'to'=>$to,'data'=>$data];
                return ['ok'=>true,'seq'=>$room['seq']];
            case 'ptt_request':
                if (!isset($room['peers'][$id])) $room['peers'][$id] = $peer;
                if ($room['speaker'] && ($room['speaker']['id'] ?? null) !== $id) return ['ok'=>false,'error'=>'Channel busy — '.($room['speaker']['nickname'] ?? 'Someone').' is speaking.','busy'=>true,'status'=>409];
                $room['speaker'] = ['id'=>$id,'nickname'=>$nickname,'until'=>$now + (int)ceil(walkie_config()['floor_timeout_ms']/1000)];
                $room['peers'][$id]['speaking'] = true;
                $room['events'][] = ['seq'=>++$room['seq'],'type'=>'speaker_started','id'=>$id,'nickname'=>$nickname];
                return ['ok'=>true,'speaker'=>$room['speaker']];
            case 'ptt_release':
                if (($room['speaker']['id'] ?? null) === $id) {
                    $room['speaker'] = null;
                    if (isset($room['peers'][$id])) $room['peers'][$id]['speaking'] = false;
                    $room['events'][] = ['seq'=>++$room['seq'],'type'=>'speaker_stopped','id'=>$id];
                }
                return ['ok'=>true];
            case 'leave':
                unset($room['peers'][$id]);
                if (($room['speaker']['id'] ?? null) === $id) $room['speaker'] = null;
                $room['events'][] = ['seq'=>++$room['seq'],'type'=>'peer_left','id'=>$id];
                return ['ok'=>true];
            default:
                return ['ok'=>false,'error'=>'Unknown signal type.','status'=>400];
        }
    });
    $status = (int)($result['status'] ?? 200);
    unset($result['status']);
    json_response($result, $status);
} catch (Throwable $e) {
    error_log('[walkie signal] '.$e->getMessage());
    json_response(['ok'=>false,'error'=>'Signaling server error. Check storage permissions.'], 500);
}
