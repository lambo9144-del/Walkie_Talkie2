window.WalkiePTT = (() => {
 const button=()=>document.getElementById('ptt-button');
 let holding=false, timer=null;
 async function start(){
   if(holding)return;holding=true;
   const b=button();b?.classList.add('transmitting');b?.setAttribute('aria-pressed','true');
   try{
     await window.WalkieRTC.getMicrophone();
     const grant=await window.WalkieSignal.request('ptt_request');
     window.WalkieRTC.setEnabled(true);
     document.getElementById('ptt-label').textContent='TRANSMITTING';
     document.getElementById('mic-state').textContent='MICROPHONE ON';
     document.getElementById('radio-help').textContent='You are transmitting. Release to finish.';
     document.getElementById('speaker-status').classList.add('speaking');
     document.getElementById('speaker-status').lastElementChild.textContent='You are transmitting';
     timer=setTimeout(stop,window.WALKIE_CONFIG.floorTimeout);
   }catch(e){
     holding=false;b?.classList.remove('transmitting');b?.setAttribute('aria-pressed','false');
     document.getElementById('radio-help').textContent=e.message;
     document.getElementById('toast-area').textContent=e.message;
   }
 }
 async function stop(){
   clearTimeout(timer);timer=null;
   const wasHolding=holding;holding=false;
   window.WalkieRTC.setEnabled(false);
   const b=button();b?.classList.remove('transmitting');b?.setAttribute('aria-pressed','false');
   document.getElementById('ptt-label').textContent='HOLD TO TALK';
   document.getElementById('mic-state').textContent='MICROPHONE OFF';
   document.getElementById('radio-help').textContent='Hold the button or hold Space to transmit.';
   if(wasHolding){try{await window.WalkieSignal.request('ptt_release')}catch{}}
 }
 function init(){
   const b=button();
   b.addEventListener('pointerdown',e=>{e.preventDefault();b.setPointerCapture?.(e.pointerId);start()});
   ['pointerup','pointercancel','lostpointercapture'].forEach(evt=>b.addEventListener(evt,stop));
   window.addEventListener('keydown',e=>{if(e.code==='Space'&&!e.repeat&&!['INPUT','TEXTAREA','BUTTON'].includes(document.activeElement?.tagName)){e.preventDefault();start()}});
   window.addEventListener('keyup',e=>{if(e.code==='Space')stop()});
   window.addEventListener('blur',stop);
   document.addEventListener('visibilitychange',()=>{if(document.hidden)stop()});
   document.getElementById('leave-button').addEventListener('click',()=>{stop();window.WalkieRTC.cleanup();});
 }
 return {init,start,stop};
})();
