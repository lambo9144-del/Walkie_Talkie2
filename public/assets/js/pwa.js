if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
 window.addEventListener('load',()=>navigator.serviceWorker.register('public/sw.js').catch(err=>console.warn('Service worker registration failed',err)));
}
