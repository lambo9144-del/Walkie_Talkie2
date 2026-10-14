window.WalkieSignal = (() => {
 const cfg = window.WALKIE_CONFIG; let stopped=false, seq=0, pollTimer=null, helloDone=false;
 async function request(type, extra={}) {
   const response = await fetch(cfg.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',credentials:'same-origin',body:JSON.stringify({type,csrf:cfg.csrf,since:seq,...extra})});
   let data; try { data=await response.json(); } catch { throw new Error('The signaling endpoint returned an invalid response. Check PHP errors and api/signal.php.'); }
   if (!response.ok || !data.ok) { const err=new Error(data.error||'Signaling request failed.'); err.status=response.status; throw err; }
   if ((type==='poll' || type==='hello') && data.seq) seq=Math.max(seq,Number(data.seq));
   if (Array.isArray(data.events)) for(const event of data.events) { seq=Math.max(seq,Number(event.seq||0)); window.dispatchEvent(new CustomEvent('walkie:event',{detail:event})); }
   if (data.peers) window.dispatchEvent(new CustomEvent('walkie:peers',{detail:data.peers}));
   if ('speaker' in data) window.dispatchEvent(new CustomEvent('walkie:speaker',{detail:data.speaker}));
   return data;
 }
 async function hello(){const d=await request('hello');helloDone=true;return d}
 async function poll(){if(stopped)return;try{await request('poll');window.dispatchEvent(new Event('walkie:online'));}catch(e){window.dispatchEvent(new CustomEvent('walkie:signal-error',{detail:e}));}finally{if(!stopped)pollTimer=setTimeout(poll,1200)}}
 function start(){stopped=false;pollTimer=setTimeout(poll,600)}
 async function stop(){stopped=true;clearTimeout(pollTimer);try{await request('leave')}catch{}}
 return {request,hello,start,stop};
})();
