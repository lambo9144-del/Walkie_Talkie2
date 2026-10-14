<?php $guest = $_SESSION['walkie_guest']; ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#111827"><link rel="manifest" href="public/manifest.webmanifest">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/app.css"><title><?= h($guest['channel']) ?> — Walkie Talkie</title>
</head><body class="channel-body" data-channel="<?= h($guest['channel']) ?>">
<header class="topbar"><a class="brand-small" href="index.php"><i class="fa-solid fa-tower-broadcast"></i> WALKIETALKIE</a><div class="connection-pill" id="connection-status"><span class="status-dot"></span><span>CONNECTING</span></div></header>
<main class="channel-shell"><section class="channel-heading"><div><p class="eyebrow">CURRENT CHANNEL</p><h1># <?= h(strtoupper($guest['channel'])) ?></h1><p class="muted">Signed in as <strong><?= h($guest['nickname']) ?></strong></p></div><div class="user-count"><i class="fa-solid fa-users"></i><strong id="user-count">1</strong><span>USERS</span></div></section>
<div class="room-grid"><section class="panel radio-panel">
<div class="live-status" id="speaker-status"><span class="status-dot"></span><span>Waiting for a speaker</span></div>
<div class="radio-ring"><button id="ptt-button" class="ptt-button" type="button" aria-pressed="false" aria-label="Hold to talk"><i class="fa-solid fa-microphone"></i><span id="ptt-label">HOLD TO TALK</span><small>PRESS AND HOLD</small></button></div>
<p class="radio-help" id="radio-help">Hold the button or hold Space to transmit.</p>
<div class="audio-box"><div class="audio-caption"><span><i class="fa-solid fa-wave-square me-2"></i>AUDIO LEVEL</span><span id="mic-state">MICROPHONE OFF</span></div><canvas id="audio-meter" width="500" height="44" aria-label="Audio level visualization"></canvas></div>
<div class="channel-actions"><button class="btn btn-outline-light" id="enable-audio" type="button"><i class="fa-solid fa-headphones me-2"></i>Enable audio</button><a class="btn btn-outline-danger" href="index.php?leave=1" id="leave-button"><i class="fa-solid fa-right-from-bracket me-2"></i>Leave channel</a></div>
<div id="toast-area" class="toast-message" role="status" aria-live="polite"></div>
</section><aside class="panel participants-panel"><div class="section-title"><div><p class="eyebrow">YOUR TEAM</p><h2>On this channel</h2></div><span class="count-badge" id="participant-count">1</span></div><ul id="participants" class="participant-list"><li class="participant"><span class="avatar"><?= h(strtoupper(substr($guest['nickname'],0,1))) ?></span><span class="participant-name"><?= h($guest['nickname']) ?></span><span class="participant-state">YOU</span></li></ul>
<div class="floor-info"><i class="fa-solid fa-circle-info"></i><span>Only one person can talk at a time. Release the button when you finish.</span></div>
</aside></div><p class="footer-note">Voice only · Maximum <?= (int)walkie_config()['max_peers'] ?> users · Speaking time limit <?= (int)(walkie_config()['floor_timeout_ms']/1000) ?> seconds</p>
</main><script>window.WALKIE_CONFIG = <?= json_encode(['endpoint'=>'api/signal.php','csrf'=>$guest['csrf'],'id'=>$guest['id'],'nickname'=>$guest['nickname'],'channel'=>$guest['channel'],'floorTimeout'=>walkie_config()['floor_timeout_ms']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;</script>
<script src="public/assets/js/signaling.js"></script><script src="public/assets/js/webrtc.js"></script><script src="public/assets/js/ptt.js"></script><script src="public/assets/js/app.js"></script><script src="public/assets/js/pwa.js"></script>
</body></html>
