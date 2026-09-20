const CACHE='fetischshop-v1',STATIC=['/','/assets/app.css','/assets/app.js','/assets/app-icon.svg'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(STATIC)).catch(()=>{})));
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;const u=new URL(e.request.url);if(u.origin!==location.origin)return;if(/\/(media|evidence)\//.test(u.pathname))return;e.respondWith(fetch(e.request).catch(()=>caches.match(e.request)));});
