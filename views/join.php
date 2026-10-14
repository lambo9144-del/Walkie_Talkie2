<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#111827"><link rel="manifest" href="public/manifest.webmanifest">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="public/assets/css/app.css"><title>Walkie Talkie — Push to Talk</title>
</head><body class="join-body"><main class="join-shell">
<div class="brand-mark"><i class="fa-solid fa-tower-broadcast"></i></div>
<p class="eyebrow">INSTANT VOICE COMMUNICATION</p><h1>Walkie<span>Talkie</span></h1>
<p class="muted lead-copy">Your team, one channel away.</p>
<section class="panel join-panel"><h2>Join a channel</h2><p class="muted">Enter the same channel name as your teammates.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" action="index.php">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
<label for="nickname">Your nickname</label><input class="form-control" id="nickname" name="nickname" maxlength="24" placeholder="e.g. Alex" required autocomplete="nickname" value="<?= h((string)($_POST['nickname'] ?? '')) ?>">
<label for="channel" class="mt-3">Channel name</label><input class="form-control" id="channel" name="channel" maxlength="64" placeholder="e.g. security-team" required value="<?= h((string)($_POST['channel'] ?? '')) ?>">
<button class="btn btn-primary w-100 join-button mt-4" type="submit"><i class="fa-solid fa-tower-broadcast me-2"></i>JOIN CHANNEL</button>
</form><div class="join-note"><i class="fa-solid fa-shield-halved"></i> Guest access · Voice only · No account needed</div>
</section><p class="footer-note">Best experience: use headphones and allow microphone access when prompted.</p>
</main><script src="public/assets/js/pwa.js"></script></body></html>
