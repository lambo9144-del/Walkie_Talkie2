# Walkie Talkie — Push-to-Talk PWA

A student-friendly PHP and vanilla JavaScript prototype for joining a named channel, seeing guests, and using a hold-to-talk interface. It uses JSON room files and file locks; no MySQL is required.

## Important implementation note

This starter implements the PHP room/participant state, a server-side single-speaker floor lock, the PTT interface, microphone permission handling, audio-level meter, and PWA shell. **It does not yet deliver voice between different browsers.** Real multi-user WebRTC audio requires peer connection management plus offer/answer/ICE exchange and a TURN service for many real networks. The included `webrtc.js` acquires a microphone and visualizes its level, but it does not create peer connections. Do not treat this starter as a completed real-time radio until WebRTC peer signaling and audio routing have been added and tested.

## Requirements

- PHP 8.1+ (PHP 8.3+ recommended)
- Apache with PHP enabled
- Browser with `fetch`, WebRTC microphone APIs and service-worker support
- HTTPS for phone microphone/PWA use; `localhost` is allowed for local development
- File write access to `storage/rooms` and `storage/logs`

## XAMPP on macOS

1. Extract this folder into `/Applications/XAMPP/xamppfiles/htdocs/walkie-talkie`.
2. Start Apache in XAMPP.
3. Copy `.env.example` to `.env`. For this simple starter, configure `SIGNAL_SECRET` in the PHP/Apache environment before production; the app has a development fallback secret and should not use that fallback on a public server.
4. Ensure the `storage` folder and its subfolders are writable by the web-server user.
5. Open `http://localhost/walkie-talkie/`.
6. Open the same URL in another browser or private window; use the same channel name to test participant/floor state.
7. Press **Enable audio** or hold **HOLD TO TALK**, and grant microphone permission.

## cPanel deployment

1. Upload the project folder into your hosting account, preferably outside the public document root for private application files, or configure the document root to the `public` directory with appropriate front-controller adjustments.
2. Set PHP to 8.1+ (8.3+ recommended).
3. Enable HTTPS using the hosting provider's SSL feature.
4. Configure `SIGNAL_SECRET`, `SIGNAL_MAX_PEERS`, and `SIGNAL_FLOOR_TIMEOUT_MS` as hosting environment variables. Use a long random secret; do not commit it.
5. Ensure `storage/rooms` and `storage/logs` are writable by PHP.
6. Test in two separate browsers. Some shared hosts disable or restrict file locking; check the host's PHP configuration if floor control behaves unexpectedly.

## How it works

- `index.php`: validates guest nickname/channel and stores guest details in a PHP session.
- `api/signal.php`: handles join, poll, floor request/release and leave messages.
- `app/bootstrap.php`: configuration, JSON response helpers and file-locked room storage.
- `views/`: join and channel UI.
- `public/assets/js/`: signaling polling, microphone controls, audio meter, PTT and PWA registration.
- `storage/rooms/`: one JSON file per hashed channel.

## Security and limitations

- This is a learning prototype, not a hardened public service. Configure a strong `SIGNAL_SECRET`, HTTPS, server-side rate limiting and operational logging before public deployment.
- PHP session cookies are required for the signaling endpoint.
- Polling-based PHP signaling can work on basic hosting, but it is not as efficient as a persistent WebSocket service.
- The current implementation does not exchange WebRTC SDP offers/answers or ICE candidates, so it does not transmit audio between guests yet.
- STUN alone cannot guarantee connectivity on every network; production WebRTC usually needs a TURN relay.
- PWA offline caching only caches the app shell; channel signaling still needs an online connection.
- The manifest includes an SVG icon. Some browsers may prefer PNG icons for installation compatibility.

## Troubleshooting

- **Invalid JSON response:** check the PHP error log; make sure `api/signal.php` is reachable and no PHP warnings are printed before JSON.
- **Storage permission error:** grant the web-server user write access to `storage/rooms` and `storage/logs`.
- **Microphone unavailable:** use HTTPS or localhost and grant permission in browser settings.
- **Channel full:** the configured maximum has been reached; increase `SIGNAL_MAX_PEERS` within the allowed range.
- **PWA not installing:** serve over HTTPS (or localhost), check the manifest and service worker in browser DevTools.
- **No voice between browsers:** expected until WebRTC peer connections and SDP/ICE signaling are implemented.

## Test checklist

- [ ] Join a channel with a nickname.
- [ ] Join the same channel in a second browser and confirm participant counts.
- [ ] Confirm the second user's PTT request is denied while another speaker owns the floor.
- [ ] Release PTT and confirm the floor is available again.
- [ ] Leave the channel and confirm the participant is removed after the next poll/cleanup.
- [ ] Test microphone permission denied and granted.
- [ ] Test mobile over HTTPS.


## cPanel deployment for `/walkie-talkie/`

1. In cPanel File Manager, open `public_html` (or the document root for `samchin.kolejsynergy.com`).
2. Create a folder named `walkie-talkie`.
3. Upload the contents of this ZIP's `walkie-talkie` folder into that folder. The final path should be `public_html/walkie-talkie/index.php`, not `public_html/walkie-talkie/walkie-talkie/index.php`.
4. Ensure `storage/rooms` and `storage/logs` exist and are writable by PHP. Start with folder permission `755`; if PHP cannot write room files, use the hosting provider's recommended ownership/permission settings rather than leaving everything world-writable.
5. Enable SSL for the subdomain in cPanel and open `https://samchin.kolejsynergy.com/walkie-talkie/`.
6. The QR generator is at `https://samchin.kolejsynergy.com/walkie-talkie/qr.php`. It makes a QR code for the current folder URL.
7. Test with two different devices, both joining the exact same channel. On each device tap **Enable audio** and allow microphone access.

## Audio connection notes

This version now exchanges WebRTC offers, answers, and ICE candidates through the PHP polling endpoint, and uses a public STUN server. STUN is not enough on every carrier/router. If devices join but still cannot hear each other on different networks, configure a TURN server and add its credentials to `rtcConfig` in `public/assets/js/webrtc.js`. The project requires PHP sessions and writable JSON room storage. Check browser console and cPanel PHP error logs if the signaling endpoint reports an error.

The QR page uses a QR-code library hosted by cdnjs, so the device viewing `qr.php` needs internet access to render the code. The actual application also uses Bootstrap and Font Awesome CDNs.
