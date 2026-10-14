window.WalkieRTC = (() => {
  let localStream = null;
  let audioContext = null, analyser = null, animation = null;
  const pcs = new Map();
  const pendingCandidates = new Map();
  const remoteAudio = new Map();
  let currentPeers = [];

  const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

  async function getMicrophone() {
    if (localStream) return localStream;
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('Microphone access requires HTTPS and a supported browser.');
    }
    localStream = await navigator.mediaDevices.getUserMedia({
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
      video: false
    });
    localStream.getAudioTracks().forEach(track => { track.enabled = false; });
    setupMeter();
    for (const pc of pcs.values()) {
      const sender = pc.getSenders().find(s => s.track?.kind === 'audio' || s.track === null);
      const audioTrack = localStream.getAudioTracks()[0];
      if (sender && audioTrack) {
        try { await sender.replaceTrack(audioTrack); } catch (e) { console.warn('Could not attach microphone track', e); }
      }
    }
    return localStream;
  }

  function setupMeter() {
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
      analyser = audioContext.createAnalyser(); analyser.fftSize = 64;
      audioContext.createMediaStreamSource(localStream).connect(analyser);
      const canvas = document.getElementById('audio-meter');
      if (!canvas) return;
      const ctx = canvas.getContext('2d'), data = new Uint8Array(analyser.frequencyBinCount);
      const draw = () => {
        if (!canvas.isConnected || !analyser) return;
        animation = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(data);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const gap = 4, bw = (canvas.width - gap * (data.length - 1)) / data.length;
        data.forEach((v, i) => {
          const h = Math.max(3, (v / 255) * (canvas.height - 5));
          ctx.fillStyle = localStream?.getAudioTracks()[0]?.enabled ? '#50dba0' : '#35435e';
          ctx.fillRect(i * (bw + gap), canvas.height - h, bw, h);
        });
      };
      draw();
    } catch (e) { console.warn('Audio meter unavailable', e); }
  }

  function setEnabled(enabled) {
    if (localStream) localStream.getAudioTracks().forEach(track => { track.enabled = enabled; });
  }

  function sendSignal(peerId, data) {
    return window.WalkieSignal.request('rtc_signal', { to: peerId, data }).catch(e => {
      console.warn('Could not send WebRTC signaling data', e);
    });
  }

  function makeAudioElement(peerId, stream) {
    let audio = remoteAudio.get(peerId);
    if (!audio) {
      audio = document.createElement('audio');
      audio.autoplay = true;
      audio.playsInline = true;
      audio.setAttribute('aria-label', 'Incoming walkie-talkie audio');
      audio.style.display = 'none';
      document.body.appendChild(audio);
      remoteAudio.set(peerId, audio);
    }
    audio.srcObject = stream;
    audio.play().catch(() => {
      const help = document.getElementById('radio-help');
      if (help) help.textContent = 'If you cannot hear others, tap Enable audio once to allow playback.';
    });
  }

  function createPeer(peer) {
    if (!peer?.id || peer.id === WALKIE_CONFIG.id || pcs.has(peer.id)) return;
    const pc = new RTCPeerConnection(rtcConfig);
    pcs.set(peer.id, pc);
    pendingCandidates.set(peer.id, []);
    const transceiver = pc.addTransceiver('audio', { direction: 'sendrecv' });
    const audioTrack = localStream?.getAudioTracks()[0];
    if (audioTrack) transceiver.sender.replaceTrack(audioTrack).catch(() => {});

    pc.onicecandidate = event => {
      if (event.candidate) sendSignal(peer.id, { kind: 'candidate', candidate: event.candidate });
    };
    pc.ontrack = event => {
      const stream = event.streams?.[0] || new MediaStream([event.track]);
      makeAudioElement(peer.id, stream);
    };
    pc.onconnectionstatechange = () => {
      if (['failed', 'closed'].includes(pc.connectionState)) closePeer(peer.id);
    };
    if (WALKIE_CONFIG.id.localeCompare(peer.id) < 0) {
      pc.createOffer().then(offer => pc.setLocalDescription(offer))
        .then(() => sendSignal(peer.id, { kind: 'description', description: pc.localDescription }))
        .catch(e => console.warn('Could not create WebRTC offer', e));
    }
  }

  async function handleSignal(event) {
    if (event.type !== 'rtc_signal' || event.to !== WALKIE_CONFIG.id || !event.from || !event.data) return;
    const peerId = event.from;
    let pc = pcs.get(peerId);
    if (!pc) {
      const peer = currentPeers.find(p => p.id === peerId);
      if (!peer) return;
      createPeer(peer); pc = pcs.get(peerId);
      if (!pc) return;
    }
    const data = event.data;
    try {
      if (data.kind === 'description' && data.description) {
        await pc.setRemoteDescription(new RTCSessionDescription(data.description));
        const queue = pendingCandidates.get(peerId) || [];
        for (const candidate of queue) await pc.addIceCandidate(new RTCIceCandidate(candidate));
        pendingCandidates.set(peerId, []);
        if (data.description.type === 'offer') {
          const answer = await pc.createAnswer();
          await pc.setLocalDescription(answer);
          await sendSignal(peerId, { kind: 'description', description: pc.localDescription });
        }
      } else if (data.kind === 'candidate' && data.candidate) {
        if (pc.remoteDescription) await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
        else pendingCandidates.get(peerId)?.push(data.candidate);
      }
    } catch (e) { console.warn('WebRTC negotiation error', e); }
  }

  function reconcilePeers(peers) {
    currentPeers = Array.isArray(peers) ? peers : [];
    const liveIds = new Set(currentPeers.filter(p => p.id !== WALKIE_CONFIG.id).map(p => p.id));
    for (const id of pcs.keys()) if (!liveIds.has(id)) closePeer(id);
    currentPeers.forEach(peer => createPeer(peer));
  }

  function closePeer(peerId) {
    const pc = pcs.get(peerId);
    if (pc) { pc.ontrack = null; pc.onicecandidate = null; try { pc.close(); } catch {} }
    pcs.delete(peerId); pendingCandidates.delete(peerId);
    const audio = remoteAudio.get(peerId);
    if (audio) { audio.srcObject = null; audio.remove(); remoteAudio.delete(peerId); }
  }

  async function enablePlayback() {
    if (audioContext?.state === 'suspended') await audioContext.resume().catch(() => {});
    const results = await Promise.all([...remoteAudio.values()].map(a => a.play().catch(e => e)));
    return results.every(result => !(result instanceof Error));
  }

  function cleanup() {
    setEnabled(false);
    for (const id of [...pcs.keys()]) closePeer(id);
    if (localStream) { localStream.getTracks().forEach(track => track.stop()); localStream = null; }
    if (animation) cancelAnimationFrame(animation);
    if (audioContext) audioContext.close().catch(() => {});
    audioContext = null; analyser = null;
  }

  window.addEventListener('walkie:peers', e => reconcilePeers(e.detail));
  window.addEventListener('walkie:event', e => handleSignal(e.detail));
  return { getMicrophone, setEnabled, cleanup, enablePlayback };
})();
