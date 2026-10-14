(() => {
 const status=document.getElementById('connection-status'),statusText=status.querySelector('span:last-child');
 const list=document.getElementById('participants'),count=document.getElementById('user-count'),badge=document.getElementById('participant-count');
 const speaker=document.getElementById('speaker-status'),toast=document.getElementById('toast-area');
 let connected=false, peers=[];
 function setStatus(kind,label){status.className='connection-pill '+kind;statusText.textContent=label}
 function renderPeers(items){
   peers=items||[];count.textContent=peers.length;badge.textContent=peers.length;
   list.innerHTML='';
   peers.forEach(p=>{
     const li=document.createElement('li');li.className='participant'+(p.speaking?' speaking':'');
     const avatar=document.createElement('span');avatar.className='avatar';avatar.textContent=(p.nickname||'?').slice(0,1).toUpperCase();
     const name=document.createElement('span');name.className='participant-name';name.textContent=p.nickname||'Guest';
     const state=document.createElement('span');state.className='participant-state';state.textContent=p.id===WALKIE_CONFIG.id?'YOU':(p.speaking?'SPEAKING':'LISTENING');
     li.append(avatar,name,state);list.append(li);
   });
 }
 function renderSpeaker(s){
   speaker.classList.remove('speaking');
   if(s&&s.id){speaker.classList.add('speaking');speaker.lastElementChild.textContent=s.id===WALKIE_CONFIG.id?'You are transmitting':`${s.nickname} is talking`;toast.textContent=s.id===WALKIE_CONFIG.id?'':`${s.nickname} has the floor.`}
   else {speaker.lastElementChild.textContent='Waiting for a speaker';toast.textContent=''}
 }
 window.addEventListener('walkie:peers',e=>renderPeers(e.detail));
 window.addEventListener('walkie:speaker',e=>renderSpeaker(e.detail));
 window.addEventListener('walkie:online',()=>{connected=true;setStatus('connected','CONNECTED')});
 window.addEventListener('walkie:signal-error',()=>{connected=false;setStatus('reconnecting','RECONNECTING')});
 window.addEventListener('walkie:event',e=>{const d=e.detail;if(d.type==='peer_joined'&&d.id!==WALKIE_CONFIG.id)toast.textContent=`${d.nickname} joined the channel.`;if(d.type==='peer_left')toast.textContent='A participant left the channel.'});
 document.getElementById('enable-audio').addEventListener('click',async()=>{
   try{await WalkieRTC.getMicrophone();await WalkieRTC.enablePlayback();document.getElementById('radio-help').textContent='Microphone ready. It stays muted until you hold to talk.';toast.textContent='Microphone permission granted. Audio playback enabled.'}
   catch(e){toast.textContent=e.message}
 });
 async function connect(){
   setStatus('reconnecting','CONNECTING');
   try{await WalkieSignal.hello();connected=true;setStatus('connected','CONNECTED');WalkieSignal.start();WalkiePTT.init()}
   catch(e){setStatus('disconnected','DISCONNECTED');toast.textContent=e.message;setTimeout(connect,2500)}
 }
 window.addEventListener('pagehide',()=>{navigator.sendBeacon?.(WALKIE_CONFIG.endpoint,new Blob([JSON.stringify({type:'leave',csrf:WALKIE_CONFIG.csrf})],{type:'application/json'}));WalkieRTC.cleanup()});
 connect();
})();
